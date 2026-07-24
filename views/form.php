<?php $editing = isset($meeting['id']); ?>
<!-- Một form dùng chung cho cả thao tác tạo mới và chỉnh sửa. -->
<section class="form-shell">
    <div class="form-title">
        <p class="eyebrow">MODULE NHÂN VIÊN</p>
        <h1><?= $editing ? 'Chỉnh sửa cuộc họp' : 'Tạo lịch họp mới' ?></h1>
        <p>Hệ thống sẽ kiểm tra trùng phòng và trùng lịch người tham gia.</p>
    </div>

    <!-- Giữ lại dữ liệu đã nhập và hiển thị tất cả lỗi kiểm tra. -->
    <?php if ($errors !== []): ?>
        <div class="alert error">
            <strong>Chưa thể lưu cuộc họp:</strong>
            <ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form class="meeting-form" method="post" action="<?= $editing ? '/meetings/' . $meeting['id'] . '/edit' : '/meetings/new' ?>">
        <label>Tiêu đề cuộc họp *
            <input name="title" maxlength="200" required value="<?= h($meeting['title'] ?? '') ?>">
        </label>
        <div class="two-cols">
            <label>Ngày họp *
                <input type="date" name="meeting_date" min="<?= date('Y-m-d') ?>" required value="<?= h($meeting['meeting_date'] ?? '') ?>">
            </label>
            <label>Phòng họp *
                <select name="room_id" required>
                    <option value="">-- Chọn phòng --</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= $room['id'] ?>" <?= (int) ($meeting['room_id'] ?? 0) === (int) $room['id'] ? 'selected' : '' ?>>
                            <?= h($room['name']) ?> - <?= $room['capacity'] ?> chỗ
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="two-cols">
            <label>Thời gian bắt đầu *
                <input type="time" name="start_time" required value="<?= h($meeting['start_time'] ?? '') ?>">
            </label>
            <label>Thời gian kết thúc *
                <input type="time" name="end_time" required value="<?= h($meeting['end_time'] ?? '') ?>">
            </label>
        </div>
        <label>Người tham gia *
            <select name="participant_ids[]" multiple size="4" required>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= in_array((int) $user['id'], $meeting['participant_ids'] ?? [], true) ? 'selected' : '' ?>>
                        <?= h($user['name'] . ' - ' . $user['email']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Giữ Ctrl để chọn nhiều người.</small>
        </label>
        <label>Nội dung
            <textarea name="content" rows="5"><?= h($meeting['content'] ?? '') ?></textarea>
        </label>
        <div class="form-actions">
            <button class="btn primary"><?= $editing ? 'Lưu thay đổi' : 'Xác nhận tạo' ?></button>
            <a class="btn ghost" href="/meetings">Hủy bỏ</a>
        </div>
    </form>
</section>
