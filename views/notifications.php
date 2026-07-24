<!-- Trang mô phỏng việc gửi email bằng cách đọc nhật ký trong database. -->
<section class="hero-row">
    <div>
        <p class="eyebrow">MÔ PHỎNG EMAIL/THÔNG BÁO</p>
        <h1>Nhật ký thông báo</h1>
        <p>Hệ thống ghi thông báo vào database thay vì gửi email thật.</p>
    </div>
</section>
<div class="notification-list">
    <?php if ($notifications === []): ?><div class="empty">Chưa có thông báo.</div><?php endif; ?>
    <?php foreach ($notifications as $notification): ?>
        <div><strong><?= h($notification['message']) ?></strong><span><?= h($notification['created_at'] . ' · ' . $notification['status']) ?></span></div>
    <?php endforeach; ?>
</div>
