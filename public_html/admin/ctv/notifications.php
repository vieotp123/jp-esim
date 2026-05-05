<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

$svc = new CtvNotificationService();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'send') {
            $ctvId = (int)($_POST['ctv_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $message = trim((string)($_POST['message'] ?? ''));
            $type = (string)($_POST['type'] ?? 'system');
            if ($title === '') throw new RuntimeException('Tiêu đề là bắt buộc');
            if ($ctvId > 0) {
                $svc->create($ctvId, $title, $message ?: null, $type);
                AuditLog::log($admin['user'], 'notification_send', 'ctv', (string)$ctvId, ['title' => $title]);
                admin_flash_set('ok', 'Đã gửi thông báo cho đối tác #' . $ctvId);
            } else {
                $count = $svc->broadcast($title, $message ?: null, $type);
                AuditLog::log($admin['user'], 'notification_broadcast', null, null, ['title' => $title, 'count' => $count]);
                admin_flash_set('ok', 'Đã gửi thông báo tới ' . $count . ' đối tác');
            }
        }
    } catch (Throwable $e) {
        admin_flash_set('err', 'Lỗi: ' . $e->getMessage());
    }
    admin_redirect_self();
}

$recent = db()->query('SELECT n.id, n.ctv_id, n.type, n.title, n.is_read, n.created_at, u.email FROM ctv_notifications n LEFT JOIN ctv_users u ON u.id = n.ctv_id ORDER BY n.id DESC LIMIT 50')->fetchAll();

admin_layout_header('Thông báo đối tác', $admin);
?>
<?php admin_flash_render(); ?>

<div class="dash-grid">
<div class="card">
  <h2>Gửi cho đối tác cụ thể</h2>
  <form method="post">
    <?php admin_csrf_field(); ?>
    <input type="hidden" name="action" value="send">
    <div class="field"><label>ID đối tác</label><input type="number" name="ctv_id" min="1" required placeholder="ID đối tác"></div>
    <div class="field"><label>Loại</label><select name="type"><option value="system">Hệ thống</option><option value="order">Đơn hàng</option><option value="wallet">Ví</option><option value="promo">Khuyến mãi</option></select></div>
    <div class="field"><label>Tiêu đề</label><input type="text" name="title" required maxlength="255" placeholder="Tiêu đề thông báo"></div>
    <div class="field"><label>Nội dung</label><textarea name="message" rows="3" placeholder="Nội dung chi tiết (tùy chọn)"></textarea></div>
    <button class="btn">Gửi</button>
  </form>
</div>

<div class="card">
  <h2>Gửi hàng loạt</h2>
  <p class="muted" style="margin-bottom:12px">Gửi cho tất cả đối tác đang hoạt động.</p>
  <form method="post">
    <?php admin_csrf_field(); ?>
    <input type="hidden" name="action" value="send">
    <input type="hidden" name="ctv_id" value="0">
    <div class="field"><label>Loại</label><select name="type"><option value="system">Hệ thống</option><option value="promo">Khuyến mãi</option></select></div>
    <div class="field"><label>Tiêu đề</label><input type="text" name="title" required maxlength="255" placeholder="Tiêu đề thông báo"></div>
    <div class="field"><label>Nội dung</label><textarea name="message" rows="3" placeholder="Nội dung chi tiết (tùy chọn)"></textarea></div>
    <button class="btn gold" onclick="return confirm('Gửi thông báo cho tất cả đối tác hoạt động?')">Gửi hàng loạt</button>
  </form>
</div>
</div>

<div class="card">
  <h2>50 thông báo gần nhất</h2>
  <?php if (!$recent): ?><div class="empty"><div class="icon">🔔</div><p>Chưa có thông báo nào.</p></div><?php else: ?>
  <?php $typeVi = ['system'=>'Hệ thống','order'=>'Đơn hàng','wallet'=>'Ví','promo'=>'Khuyến mãi']; ?>
  <div class="m-cards">
    <?php foreach ($recent as $r): ?>
    <div class="m-card">
      <div class="m-head"><span><?= htmlspecialchars((string)$r['title']) ?></span><?= (int)$r['is_read'] ? '<span class="tag ok">Đã đọc</span>' : '<span class="tag warn">Chưa đọc</span>' ?></div>
      <div class="m-row"><span class="m-label">Đối tác</span><span class="m-val"><a href="/admin/ctv/view.php?id=<?= (int)$r['ctv_id'] ?>">#<?= (int)$r['ctv_id'] ?></a> <?= htmlspecialchars((string)($r['email'] ?? '')) ?></span></div>
      <div class="m-row"><span class="m-label">Loại</span><span class="m-val"><span class="tag"><?= htmlspecialchars($typeVi[(string)$r['type']] ?? (string)$r['type']) ?></span></span></div>
      <div class="m-row"><span class="m-label">Thời gian</span><span class="m-val muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
  <table><thead><tr><th>ID</th><th>Đối tác</th><th>Loại</th><th>Tiêu đề</th><th>Đã đọc</th><th>Thời gian</th></tr></thead><tbody>
  <?php foreach ($recent as $r): ?>
  <tr>
    <td><?= (int)$r['id'] ?></td>
    <td><a href="/admin/ctv/view.php?id=<?= (int)$r['ctv_id'] ?>">#<?= (int)$r['ctv_id'] ?></a> <span class="muted"><?= htmlspecialchars((string)($r['email'] ?? '')) ?></span></td>
    <td><span class="tag"><?= htmlspecialchars($typeVi[(string)$r['type']] ?? (string)$r['type']) ?></span></td>
    <td><?= htmlspecialchars((string)$r['title']) ?></td>
    <td><?= (int)$r['is_read'] ? '<span class="tag ok">Đã đọc</span>' : '<span class="tag warn">Chưa đọc</span>' ?></td>
    <td style="white-space:nowrap"><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
