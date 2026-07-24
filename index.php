<?php

declare(strict_types=1);

// Để PHP development server tự phục vụ các file tĩnh như CSS.
if (PHP_SAPI === 'cli-server') {
    $staticFile = __DIR__ . (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '');
    if (is_file($staticFile)) {
        return false;
    }
}

require_once __DIR__ . '/functions.php';

$pdo = db();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];
$currentUser = $pdo->query('SELECT * FROM users WHERE id = ' . CURRENT_USER_ID)->fetch();
$rooms = $pdo->query("SELECT * FROM rooms WHERE status = 'Hoạt động' ORDER BY name")->fetchAll();
$users = $pdo->query('SELECT * FROM users WHERE id <> ' . CURRENT_USER_ID . ' ORDER BY name')->fetchAll();
$message = (string) ($_GET['message'] ?? '');

// Xử lý các biểu mẫu trước khi xuất HTML để có thể chuyển hướng an toàn.

// POST /meetings/new: kiểm tra và tạo cuộc họp mới.
if ($method === 'POST' && $path === '/meetings/new') {
    $meeting = formData();
    $errors = validateMeeting($pdo, $meeting);
    if ($errors !== []) {
        view('form', compact('currentUser', 'rooms', 'users', 'meeting', 'errors', 'message'));
        exit;
    }

    // Transaction đảm bảo cuộc họp, người tham gia và thông báo được lưu cùng nhau.
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO meetings(title, meeting_date, start_time, end_time, room_id,
            organizer_id, content, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Đã lên lịch', ?)
    SQL);
    $stmt->execute([
        $meeting['title'], $meeting['meeting_date'], $meeting['start_time'], $meeting['end_time'],
        $meeting['room_id'], CURRENT_USER_ID, $meeting['content'], date('Y-m-d\TH:i:s'),
    ]);
    $id = (int) $pdo->lastInsertId();
    saveParticipants($pdo, $id, $meeting['participant_ids'], 'Bạn được mời tham gia: ' . $meeting['title']);
    $pdo->commit();
    redirectTo("/meetings/{$id}", 'Tạo cuộc họp thành công.');
}

// POST /meetings/{id}/edit: cập nhật cuộc họp do người hiện tại tổ chức.
if ($method === 'POST' && preg_match('#^/meetings/(\d+)/edit$#', $path, $match)) {
    $id = (int) $match[1];
    $original = meetingById($pdo, $id);
    if (!canManage($original)) {
        redirectTo('/meetings', 'Không thể chỉnh sửa cuộc họp này.');
    }
    $meeting = formData() + ['id' => $id];
    $errors = validateMeeting($pdo, $meeting, $id);
    if ($errors !== []) {
        view('form', compact('currentUser', 'rooms', 'users', 'meeting', 'errors', 'message'));
        exit;
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare(<<<SQL
        UPDATE meetings SET title = ?, meeting_date = ?, start_time = ?, end_time = ?, room_id = ?, content = ?
        WHERE id = ?
    SQL);
    $stmt->execute([
        $meeting['title'], $meeting['meeting_date'], $meeting['start_time'], $meeting['end_time'],
        $meeting['room_id'], $meeting['content'], $id,
    ]);
    // Xóa danh sách cũ rồi tạo lại theo lựa chọn mới từ form.
    $pdo->prepare('DELETE FROM meeting_participants WHERE meeting_id = ?')->execute([$id]);
    saveParticipants($pdo, $id, $meeting['participant_ids'], 'Cuộc họp đã được cập nhật: ' . $meeting['title']);
    $pdo->commit();
    redirectTo("/meetings/{$id}", 'Cập nhật cuộc họp thành công.');
}

// POST /meetings/{id}/cancel: hủy mềm cuộc họp và thông báo cho người tham gia.
if ($method === 'POST' && preg_match('#^/meetings/(\d+)/cancel$#', $path, $match)) {
    $id = (int) $match[1];
    $meeting = meetingById($pdo, $id);
    if (!canManage($meeting)) {
        redirectTo('/meetings', 'Không thể hủy cuộc họp này.');
    }
    $pdo->beginTransaction();
    // Chỉ đổi trạng thái thay vì DELETE để vẫn giữ lịch sử.
    $pdo->prepare("UPDATE meetings SET status = 'Đã hủy' WHERE id = ?")->execute([$id]);
    $participantIds = $pdo->prepare('SELECT user_id FROM meeting_participants WHERE meeting_id = ?');
    $participantIds->execute([$id]);
    $notify = $pdo->prepare(
        'INSERT INTO notifications(meeting_id, user_id, message, created_at) VALUES (?, ?, ?, ?)'
    );
    foreach ($participantIds->fetchAll() as $participant) {
        $notify->execute([$id, $participant['user_id'], 'Cuộc họp đã bị hủy: ' . $meeting['title'], date('Y-m-d\TH:i:s')]);
    }
    $pdo->commit();
    redirectTo('/meetings', 'Đã hủy cuộc họp.');
}

// POST /meetings/{id}/response: ghi nhận đồng ý hoặc từ chối lời mời.
if ($method === 'POST' && preg_match('#^/meetings/(\d+)/response$#', $path, $match)) {
    $id = (int) $match[1];
    $response = (string) ($_POST['response'] ?? '');
    $meeting = meetingById($pdo, $id);
    if (!in_array($response, ['Đồng ý', 'Từ chối'], true) || !$meeting || $meeting['status'] === 'Đã hủy') {
        redirectTo("/meetings/{$id}", 'Không thể cập nhật phản hồi.');
    }
    $stmt = $pdo->prepare('UPDATE meeting_participants SET response = ? WHERE meeting_id = ? AND user_id = ?');
    $stmt->execute([$response, $id, CURRENT_USER_ID]);
    redirectTo("/meetings/{$id}", 'Đã cập nhật phản hồi tham gia.');
}

// Các trang GET.
if ($path === '/') {
    redirectTo('/meetings');
}

// GET /meetings: danh sách lịch họp kèm tìm kiếm và bộ lọc.
if ($method === 'GET' && $path === '/meetings') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $roomId = (int) ($_GET['room_id'] ?? 0);
    $status = (string) ($_GET['status'] ?? '');
    $sql = <<<SQL
        SELECT m.*, r.name AS room_name, u.name AS organizer_name,
            (SELECT COUNT(*) FROM meeting_participants mp WHERE mp.meeting_id = m.id) AS participant_count
        FROM meetings m
        JOIN rooms r ON r.id = m.room_id
        JOIN users u ON u.id = m.organizer_id
        WHERE (m.organizer_id = ? OR EXISTS (
            SELECT 1 FROM meeting_participants p WHERE p.meeting_id = m.id AND p.user_id = ?
        ))
    SQL;
    $params = [CURRENT_USER_ID, CURRENT_USER_ID];
    // Chỉ ghép điều kiện khi người dùng thực sự chọn bộ lọc.
    if ($q !== '') {
        $sql .= ' AND (m.title LIKE ? OR m.content LIKE ?)';
        $params[] = "%{$q}%";
        $params[] = "%{$q}%";
    }
    if ($roomId > 0) {
        $sql .= ' AND m.room_id = ?';
        $params[] = $roomId;
    }
    if ($status !== '') {
        $sql .= ' AND m.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY m.meeting_date, m.start_time';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $meetings = $stmt->fetchAll();
    view('meetings', compact('currentUser', 'rooms', 'meetings', 'q', 'roomId', 'status', 'message'));
    exit;
}

// GET /meetings/new: hiển thị form tạo mới với dữ liệu rỗng.
if ($method === 'GET' && $path === '/meetings/new') {
    $meeting = ['participant_ids' => []];
    $errors = [];
    view('form', compact('currentUser', 'rooms', 'users', 'meeting', 'errors', 'message'));
    exit;
}

// GET /meetings/{id}/edit: nạp dữ liệu cũ vào form chỉnh sửa.
if ($method === 'GET' && preg_match('#^/meetings/(\d+)/edit$#', $path, $match)) {
    $id = (int) $match[1];
    $meeting = meetingById($pdo, $id);
    if (!canManage($meeting)) {
        redirectTo('/meetings', 'Không thể chỉnh sửa cuộc họp này.');
    }
    $stmt = $pdo->prepare('SELECT user_id FROM meeting_participants WHERE meeting_id = ?');
    $stmt->execute([$id]);
    $meeting['participant_ids'] = array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    $errors = [];
    view('form', compact('currentUser', 'rooms', 'users', 'meeting', 'errors', 'message'));
    exit;
}

// GET /meetings/{id}: hiển thị chi tiết và phản hồi của người hiện tại.
if ($method === 'GET' && preg_match('#^/meetings/(\d+)$#', $path, $match)) {
    $id = (int) $match[1];
    $meeting = meetingById($pdo, $id);
    if (!$meeting) {
        http_response_code(404);
        exit('Không tìm thấy cuộc họp.');
    }
    $stmt = $pdo->prepare(<<<SQL
        SELECT u.*, mp.response FROM meeting_participants mp
        JOIN users u ON u.id = mp.user_id WHERE mp.meeting_id = ? ORDER BY u.name
    SQL);
    $stmt->execute([$id]);
    $participants = $stmt->fetchAll();
    $currentResponse = null;
    foreach ($participants as $participant) {
        if ((int) $participant['id'] === CURRENT_USER_ID) {
            $currentResponse = $participant['response'];
        }
    }
    view('detail', compact('currentUser', 'meeting', 'participants', 'currentResponse', 'message'));
    exit;
}

// GET /notifications: chỉ lấy 50 thông báo gần nhất.
if ($method === 'GET' && $path === '/notifications') {
    $notifications = $pdo->query(<<<SQL
        SELECT n.*, m.title FROM notifications n
        JOIN meetings m ON m.id = n.meeting_id ORDER BY n.id DESC LIMIT 50
    SQL)->fetchAll();
    view('notifications', compact('currentUser', 'notifications', 'message'));
    exit;
}

// Mọi URL không khớp với các route phía trên đều trả về 404.
http_response_code(404);
echo 'Trang không tồn tại.';
