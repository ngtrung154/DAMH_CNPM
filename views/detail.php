<!-- Thông tin tổng quan và các thao tác của người tổ chức. -->
<section class="detail-shell">
    <a class="back" href="/meetings">← Quay lại lịch họp</a>
    <div class="detail-header">
        <div>
            <span class="badge"><?= h($meeting['status']) ?></span>
            <h1><?= h($meeting['title']) ?></h1>
            <p>Người tổ chức: <?= h($meeting['organizer_name']) ?></p>
        </div>
        <?php if (canManage($meeting)): ?>
            <div class="actions">
                <a class="btn secondary" href="/meetings/<?= $meeting['id'] ?>/edit">Chỉnh sửa</a>
                <form method="post" action="/meetings/<?= $meeting['id'] ?>/cancel">
                    <button class="btn danger">Hủy cuộc họp</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <div class="detail-grid">
        <div><span>Thời gian</span><strong><?= h($meeting['meeting_date'] . ' · ' . $meeting['start_time'] . ' - ' . $meeting['end_time']) ?></strong></div>
        <div><span>Phòng họp</span><strong><?= h($meeting['room_name']) ?> (<?= $meeting['capacity'] ?> chỗ)</strong></div>
    </div>
    <div class="content-box"><h3>Nội dung</h3><p><?= h($meeting['content'] ?: 'Không có nội dung.') ?></p></div>
    <div class="content-box">
        <h3>Người tham gia</h3>
        <table>
            <thead><tr><th>Họ tên</th><th>Email</th><th>Phản hồi</th></tr></thead>
            <tbody><?php foreach ($participants as $participant): ?>
                <tr><td><?= h($participant['name']) ?></td><td><?= h($participant['email']) ?></td><td><?= h($participant['response']) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table>
    </div>
    <!-- Nút phản hồi chỉ xuất hiện khi người hiện tại được mời và cuộc họp còn hiệu lực. -->
    <?php if ($currentResponse !== null && $meeting['status'] !== 'Đã hủy'): ?>
        <form class="response-box" method="post" action="/meetings/<?= $meeting['id'] ?>/response">
            <strong>Phản hồi của tôi:</strong>
            <button class="btn primary" name="response" value="Đồng ý">Đồng ý</button>
            <button class="btn ghost" name="response" value="Từ chối">Từ chối</button>
        </form>
    <?php endif; ?>
</section>
