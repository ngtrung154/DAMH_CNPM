<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Mã hóa dữ liệu trước khi in ra HTML để tránh chèn mã XSS.
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Chuyển hướng sau khi xử lý form và gửi kèm thông báo trên URL.
function redirectTo(string $path, string $message = ''): never
{
    $url = $path . ($message !== '' ? '?message=' . urlencode($message) : '');
    header('Location: ' . $url, true, 303);
    exit;
}

// Gom và chuẩn hóa dữ liệu POST của form cuộc họp.
function formData(): array
{
    return [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'meeting_date' => (string) ($_POST['meeting_date'] ?? ''),
        'start_time' => (string) ($_POST['start_time'] ?? ''),
        'end_time' => (string) ($_POST['end_time'] ?? ''),
        'room_id' => (int) ($_POST['room_id'] ?? 0),
        'content' => trim((string) ($_POST['content'] ?? '')),
        // Chuyển ID người tham gia sang số nguyên và loại các ID không hợp lệ.
        'participant_ids' => array_values(array_filter(
            array_map('intval', (array) ($_POST['participant_ids'] ?? [])),
            fn (int $id): bool => $id > 0
        )),
    ];
}

// Lấy một cuộc họp kèm thông tin phòng và người tổ chức.
function meetingById(PDO $pdo, int $id): array|false
{
    $stmt = $pdo->prepare(<<<SQL
        SELECT m.*, r.name AS room_name, r.capacity, u.name AS organizer_name
        FROM meetings m
        JOIN rooms r ON r.id = m.room_id
        JOIN users u ON u.id = m.organizer_id
        WHERE m.id = ?
    SQL);
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Chỉ người tổ chức được phép sửa hoặc hủy cuộc họp chưa bị hủy.
function canManage(array|false $meeting): bool
{
    return $meeting !== false
        && (int) $meeting['organizer_id'] === CURRENT_USER_ID
        && $meeting['status'] !== 'Đã hủy';
}

// Kiểm tra dữ liệu, trùng phòng và trùng lịch người tham gia.
function validateMeeting(PDO $pdo, array $data, ?int $meetingId = null): array
{
    // Bước 1: kiểm tra các trường bắt buộc trước khi truy vấn database.
    $errors = [];
    if ($data['title'] === '') {
        $errors[] = 'Tiêu đề cuộc họp không được để trống.';
    } elseif (mb_strlen($data['title']) > 200) {
        $errors[] = 'Tiêu đề không được vượt quá 200 ký tự.';
    }
    if ($data['meeting_date'] === '' || $data['meeting_date'] < date('Y-m-d')) {
        $errors[] = 'Ngày họp không hợp lệ hoặc đã nằm trong quá khứ.';
    }
    if ($data['start_time'] === '' || $data['end_time'] === '' || $data['start_time'] >= $data['end_time']) {
        $errors[] = 'Thời gian bắt đầu phải nhỏ hơn thời gian kết thúc.';
    }
    if ($data['room_id'] <= 0) {
        $errors[] = 'Vui lòng chọn phòng họp.';
    }
    if ($data['participant_ids'] === []) {
        $errors[] = 'Vui lòng chọn ít nhất một người tham gia.';
    }
    if ($errors !== []) {
        return $errors;
    }

    // Bước 2: tìm cuộc họp khác dùng cùng phòng trong khoảng thời gian giao nhau.
    // Khi đang sửa, loại chính cuộc họp hiện tại khỏi kết quả kiểm tra.
    $exclude = $meetingId ? ' AND id <> :id' : '';
    $stmt = $pdo->prepare("SELECT id, title FROM meetings
        WHERE meeting_date = :meeting_date AND room_id = :room_id AND status <> 'Đã hủy'
        AND start_time < :end_time AND end_time > :start_time{$exclude} LIMIT 1");
    $params = [
        ':meeting_date' => $data['meeting_date'], ':room_id' => $data['room_id'],
        ':end_time' => $data['end_time'], ':start_time' => $data['start_time'],
    ];
    if ($meetingId) {
        $params[':id'] = $meetingId;
    }
    $stmt->execute($params);
    if ($conflict = $stmt->fetch()) {
        $errors[] = "Phòng họp đã được sử dụng (#{$conflict['id']} - {$conflict['title']}).";
    }

    // Bước 3: tạo placeholder cho mệnh đề IN và tìm người đang bận cùng giờ.
    $marks = implode(',', array_fill(0, count($data['participant_ids']), '?'));
    $sql = "SELECT DISTINCT u.name FROM meetings m
        JOIN meeting_participants mp ON mp.meeting_id = m.id
        JOIN users u ON u.id = mp.user_id
        WHERE m.meeting_date = ? AND m.status <> 'Đã hủy'
        AND m.start_time < ? AND m.end_time > ? AND mp.user_id IN ({$marks})";
    $params = [$data['meeting_date'], $data['end_time'], $data['start_time'], ...$data['participant_ids']];
    if ($meetingId) {
        $sql .= ' AND m.id <> ?';
        $params[] = $meetingId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $busyUsers = array_column($stmt->fetchAll(), 'name');
    if ($busyUsers !== []) {
        $errors[] = 'Người tham gia bị trùng lịch: ' . implode(', ', $busyUsers) . '.';
    }
    return $errors;
}

// Lưu người tham gia và ghi một thông báo cho từng người.
function saveParticipants(PDO $pdo, int $meetingId, array $userIds, string $message): void
{
    $participant = $pdo->prepare('INSERT INTO meeting_participants(meeting_id, user_id) VALUES (?, ?)');
    $notification = $pdo->prepare(
        'INSERT INTO notifications(meeting_id, user_id, message, created_at) VALUES (?, ?, ?, ?)'
    );
    $now = date('Y-m-d\TH:i:s');
    foreach ($userIds as $userId) {
        $participant->execute([$meetingId, $userId]);
        $notification->execute([$meetingId, $userId, $message, $now]);
    }
}

// Ghép header, nội dung trang và footer thành một trang HTML hoàn chỉnh.
function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require __DIR__ . '/views/header.php';
    require __DIR__ . '/views/' . $name . '.php';
    require __DIR__ . '/views/footer.php';
}
