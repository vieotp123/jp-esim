<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

$flash = null;
$svc = new CtvNotificationService();
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $flash = ['ok', 'Đã gửi thông báo cho CTV #' . $ctvId];
            } else {
                $count = $svc->broadcast($title, $message ?: null, $type);
                AuditLog::log($admin['user'], 'notification_broadcast', null, null, ['title' => $title, 'count' => $count]);
                $flash = ['ok', 'Đã gửi thông báo tới ' . $count . ' CTV'];
            }
        }
    }
} catch (Throwable $e) {
    $flash = ['err', 'Lỗi: ' . $e->getMessage()];
}

$recent = db()->query('SELECT n.id, n.ctv_id, n.type, n.title, n.is_read, n.created_at, u.email FROM ctv_notifications n LEFT JOIN ctv_users u ON u.id = n.ctv_id ORDER BY n.id DESC LIMIT 50')->fetchAll();

admin_layout_header('Thông báo CTV', $admin);
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="dash-grid">
<div class="card">
  <h2>Gửi cho CTV cụ thể</h2>
  <form method="post">
    <?php admin_csrf_field(); ?>
    <input type="hidden" name="action" value="send">
    <div style="margin-bottom:8px"><label>CTV ID</label><input type="number" name="ctv_id" min="1" required placeholder="ID CTV"></div>
    <div style="margin-bottom:8px"><label>Loại</label><select name="type"><option value="system">Hệ thống</option><option value="order">Đơn hàng</option><option value="wallet">Ví</option><option value="promo">Khuyến mãi</option></select></div>
    <div style="margin-bottom:8px"><label>Tiêu đề</label><input type="text" name="title" required maxlength="255"></div>
    <div style="margin-bottom:8px"><label>Nội dung</label><textarea name="message" rows="3"></textarea></div>
    <button class="btn">Gửi</button>
  </form>
</div>

<div class="card">
  <h2>Gửi hàng loạt cho tất cả CTV hoạt động</h2>
  <form method="post">
    <?php admin_csrf_field(); ?>
    <input type="hidden" name="action" value="send">
    <input type="hidden" name="ctv_id" value="0">
    <div style="margin-bottom:8px"><label>Loại</label><select name="type"><option value="system">Hệ thống</option><option value="promo">Khuyến mãi</option></select></div>
    <div style="margin-bottom:8px"><label>Tiêu đề</label><input type="text" name="title" required maxlength="255"></div>
    <div style="margin-bottom:8px"><label>Nội dung</label><textarea name="message" rows="3"></textarea></div>
    <button class="btn gold" onclick="return confirm('Gửi thông báo cho tất cả CTV hoạt động?')">Gửi hàng loạt</button>
  </form>
</div>
</div>

<div class="card">
  <h2>50 thông báo gần nhất</h2>
  <?php if (!$recent): ?><div class="empty"><div class="icon">🔔</div><p>Chưa có thông báo nào.</p></div><?php else: ?>
  <?php $typeVi = ['system'=>'Hệ thống','order'=>'Đơn hàng','wallet'=>'Ví','promo'=>'Khuyến mãi']; ?>
  <table><thead><tr><th>ID</th><th>CTV</th><th>Loại</th><th>Tiêu đề</th><th>Đã đọc</th><th>Thời gian</th></tr></thead><tbody>
  <?php foreach ($recent as $r): ?>
  <tr>
    <td><?= (int)$r['id'] ?></td>
    <td><a href="/admin/ctv/view.php?id=<?= (int)$r['ctv_id'] ?>">#<?= (int)$r['ctv_id'] ?></a> <span class="muted"><?= htmlspecialchars((string)($r['email'] ?? '')) ?></span></td>
    <td><span class="tag"><?= htmlspecialchars($typeVi[(string)$r['type']] ?? (string)$r['type']) ?></span></td>
    <td><?= htmlspecialchars((string)$r['title']) ?></td>
    <td><?= (int)$r['is_read'] ? '<span class="tag ok">Đã đọc</span>' : '<span class="tag warn">Chưa đọc</span>' ?></td>
    <td><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
