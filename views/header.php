<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý lịch họp</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<!-- Thanh điều hướng dùng chung cho tất cả các trang. -->
<header class="topbar">
    <a class="brand" href="/meetings">QUẢN LÝ LỊCH HỌP</a>
    <nav>
        <a href="/meetings">Lịch họp</a>
        <a href="/meetings/new">Tạo lịch họp</a>
        <a href="/notifications">Thông báo</a>
    </nav>
    <span class="user"><?= h($currentUser['name']) ?></span>
</header>
<main class="page">
    <!-- Thông báo kết quả sau thao tác tạo, sửa, hủy hoặc phản hồi. -->
    <?php if (($message ?? '') !== ''): ?>
        <div class="alert success"><?= h($message) ?></div>
    <?php endif; ?>
