<?php

declare(strict_types=1);

const CURRENT_USER_ID = 1;
const DB_FILE = __DIR__ . '/data/meeting_manager.db';

// Tạo và cấu hình kết nối PDO tới SQLite.
function db(): PDO
{
    // Thư mục data có thể chưa tồn tại trong lần chạy đầu.
    if (!is_dir(dirname(DB_FILE))) {
        mkdir(dirname(DB_FILE), 0777, true);
    }

    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    initializeDatabase($pdo);
    return $pdo;
}

// Tạo cấu trúc database và dữ liệu mẫu nếu database đang trống.
function initializeDatabase(PDO $pdo): void
{
    // IF NOT EXISTS giúp khởi động ứng dụng nhiều lần mà không tạo trùng bảng.
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE
        );
        CREATE TABLE IF NOT EXISTS rooms (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL UNIQUE,
            capacity INTEGER NOT NULL,
            equipment TEXT,
            status TEXT NOT NULL DEFAULT 'Hoạt động'
        );
        CREATE TABLE IF NOT EXISTS meetings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            meeting_date TEXT NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL,
            room_id INTEGER NOT NULL,
            organizer_id INTEGER NOT NULL,
            content TEXT,
            status TEXT NOT NULL DEFAULT 'Đã lên lịch',
            created_at TEXT NOT NULL,
            FOREIGN KEY (room_id) REFERENCES rooms(id),
            FOREIGN KEY (organizer_id) REFERENCES users(id)
        );
        CREATE TABLE IF NOT EXISTS meeting_participants (
            meeting_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            response TEXT NOT NULL DEFAULT 'Chờ phản hồi',
            PRIMARY KEY (meeting_id, user_id),
            FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'Đã ghi nhận',
            created_at TEXT NOT NULL,
            FOREIGN KEY (meeting_id) REFERENCES meetings(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    SQL);

    // Tài khoản mẫu phục vụ cho luồng đăng nhập giả lập.
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $users = [
            [1, 'Nguyễn Quốc Trung', 'trung.nguyen@xyz.com'],
            [2, 'Trần Hải Cường', 'cuong.tran@xyz.com'],
            [3, 'Nguyễn Văn An', 'an.nguyen@xyz.com'],
            [4, 'Lê Minh Anh', 'anh.le@xyz.com'],
            [5, 'Phạm Thu Hà', 'ha.pham@xyz.com'],
        ];
        $stmt = $pdo->prepare('INSERT INTO users(id, name, email) VALUES (?, ?, ?)');
        foreach ($users as $user) {
            $stmt->execute($user);
        }
    }

    // Danh sách phòng họp mẫu dùng trong form tạo và bộ lọc.
    if ((int) $pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn() === 0) {
        $rooms = [
            [1, 'Phòng A', 20, 'Máy chiếu, Camera, Micro'],
            [2, 'Phòng B', 12, 'TV, Bảng trắng'],
            [3, 'Phòng C', 8, 'TV, Micro'],
        ];
        $stmt = $pdo->prepare("INSERT INTO rooms(id, name, capacity, equipment, status) VALUES (?, ?, ?, ?, 'Hoạt động')");
        foreach ($rooms as $room) {
            $stmt->execute($room);
        }
    }

    // Thêm hai cuộc họp mẫu để giao diện có dữ liệu ngay trong lần chạy đầu.
    if ((int) $pdo->query('SELECT COUNT(*) FROM meetings')->fetchColumn() === 0) {
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO meetings(title, meeting_date, start_time, end_time, room_id,
                organizer_id, content, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Đã lên lịch', ?)
        SQL);
        $stmt->execute([
            'Họp Sprint Planning', date('Y-m-d', strtotime('+1 day')), '09:00', '10:00', 1, 2,
            'Phân công công việc cho Sprint tiếp theo.', date('Y-m-d\TH:i:s'),
        ]);
        $firstMeeting = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO meeting_participants(meeting_id, user_id) VALUES (?, ?)')
            ->execute([$firstMeeting, CURRENT_USER_ID]);

        $stmt->execute([
            'Họp rà soát giao diện', date('Y-m-d', strtotime('+2 days')), '14:00', '15:00', 2,
            CURRENT_USER_ID, 'Rà soát giao diện tạo và quản lý cuộc họp.', date('Y-m-d\TH:i:s'),
        ]);
        $secondMeeting = (int) $pdo->lastInsertId();
        $participant = $pdo->prepare('INSERT INTO meeting_participants(meeting_id, user_id) VALUES (?, ?)');
        $participant->execute([$secondMeeting, 2]);
        $participant->execute([$secondMeeting, 3]);
    }
}
