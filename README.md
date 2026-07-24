# HK253 - Đồ án Hệ thống đặt lịch họp - Nhóm 10

Module được thực hiện: **Nhân viên**, gồm tạo lịch họp và quản lý cuộc họp.

## Chức năng

- Xem, tìm kiếm và lọc lịch họp.
- Tạo và chỉnh sửa cuộc họp.
- Kiểm tra trùng phòng và trùng lịch người tham gia.
- Hủy cuộc họp và giữ lịch sử.
- Phản hồi đồng ý hoặc từ chối lời mời.
- Ghi nhật ký thông báo trong SQLite.

## Chạy nhanh

Mở terminal trong folder và chạy:

```
php -S 127.0.0.1:8000 index.php
```

Sau đó truy cập `http://127.0.0.1:8000`. Database được tạo tự động trong thư mục `data`.

## Cấu trúc

- `index.php`: định tuyến và xử lý request.
- `config.php`: kết nối, tạo bảng và dữ liệu mẫu.
- `functions.php`: hàm dùng chung và kiểm tra nghiệp vụ.
- `views/`: các trang giao diện PHP/HTML.
- `style.css`: giao diện responsive.
