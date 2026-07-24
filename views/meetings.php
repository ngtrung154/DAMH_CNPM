<!-- Tiêu đề trang và nút tạo lịch họp. -->
<section class="hero-row">
    <div>
        <p class="eyebrow">MODULE NHÂN VIÊN</p>
        <h1>Lịch họp của tôi</h1>
        <p>Theo dõi, tìm kiếm và quản lý các cuộc họp liên quan.</p>
    </div>
    <a class="btn primary" href="/meetings/new">+ Tạo lịch họp</a>
</section>

<!-- Các giá trị lọc được gửi qua query string bằng phương thức GET. -->
<form class="filters" method="get" action="/meetings">
    <input name="q" value="<?= h($q) ?>" placeholder="Tìm theo tiêu đề hoặc nội dung">
    <select name="room_id">
        <option value="">Tất cả phòng</option>
        <?php foreach ($rooms as $room): ?>
            <option value="<?= $room['id'] ?>" <?= $roomId === (int) $room['id'] ? 'selected' : '' ?>>
                <?= h($room['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">Tất cả trạng thái</option>
        <option <?= $status === 'Đã lên lịch' ? 'selected' : '' ?>>Đã lên lịch</option>
        <option <?= $status === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
    </select>
    <button class="btn secondary">Lọc lịch</button>
</form>

<!-- Danh sách card cuộc họp liên quan tới người dùng hiện tại. -->
<div class="meeting-grid">
    <?php if ($meetings === []): ?>
        <div class="empty">Không có cuộc họp phù hợp.</div>
    <?php endif; ?>
    <?php foreach ($meetings as $item): ?>
        <article class="meeting-card <?= $item['status'] === 'Đã hủy' ? 'cancelled' : '' ?>">
            <div class="date-box">
                <strong><?= h(substr($item['meeting_date'], 8, 2)) ?></strong>
                <span><?= h(substr($item['meeting_date'], 5, 2) . '/' . substr($item['meeting_date'], 0, 4)) ?></span>
            </div>
            <div class="meeting-info">
                <div class="card-top">
                    <span class="badge"><?= h($item['status']) ?></span>
                    <span><?= h($item['start_time'] . ' - ' . $item['end_time']) ?></span>
                </div>
                <h3><a href="/meetings/<?= $item['id'] ?>"><?= h($item['title']) ?></a></h3>
                <p>📍 <?= h($item['room_name']) ?> · 👤 <?= h($item['organizer_name']) ?> · 👥 <?= $item['participant_count'] ?> người</p>
                <?php if ((int) $item['organizer_id'] === CURRENT_USER_ID && $item['status'] !== 'Đã hủy'): ?>
                    <div class="actions">
                        <a href="/meetings/<?= $item['id'] ?>/edit">Chỉnh sửa</a>
                        <form method="post" action="/meetings/<?= $item['id'] ?>/cancel" onsubmit="return confirm('Bạn có chắc muốn hủy?')">
                            <button class="link-danger">Hủy họp</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>
