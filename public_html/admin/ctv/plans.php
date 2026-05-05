<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? '');
        $id = max(0, (int)($_POST['plan_id'] ?? 0));
        if ($id <= 0) throw new RuntimeException('ID không hợp lệ');
        if ($action === 'toggle_status') {
            $newStatus = (int)($_POST['status'] ?? 0) ? 1 : 0;
            db()->prepare('UPDATE plan SET status=?, updated_at=NOW() WHERE id=?')->execute([$newStatus, $id]);
            AuditLog::log($admin['user'], 'plan_toggle_status', 'plan', (string)$id, ['status' => $newStatus]);
            admin_flash_set('ok', 'Đã ' . ($newStatus ? 'kích hoạt' : 'tạm tắt') . ' gói #' . $id);
        } elseif ($action === 'update_price') {
            $price = max(0, (int)($_POST['price'] ?? 0));
            $cost = max(0, (int)($_POST['cost'] ?? 0));
            if ($price > 100000000) throw new RuntimeException('Giá không hợp lệ');
            db()->prepare('UPDATE plan SET price=?, cost=?, updated_at=NOW() WHERE id=?')->execute([$price, $cost, $id]);
            AuditLog::log($admin['user'], 'plan_update_price', 'plan', (string)$id, ['price' => $price, 'cost' => $cost]);
            admin_flash_set('ok', 'Đã cập nhật giá gói #' . $id . ' (giá lẻ ' . format_vnd($price) . ', vốn ' . format_vnd($cost) . ')');
        }
    } catch (Throwable $e) {
        admin_flash_set('err', 'Lỗi: ' . $e->getMessage());
    }
    admin_redirect_self();
}

$pdo = db();
$plans = $pdo->query("SELECT * FROM plan ORDER BY status DESC, telecom ASC, day ASC, price ASC")->fetchAll();
$active = array_filter($plans, fn($p) => (int)($p['status'] ?? 0) === 1);
$inactive = array_filter($plans, fn($p) => (int)($p['status'] ?? 0) !== 1);

admin_layout_header('Quản lý gói cước', $admin);
?>
<?php admin_flash_render(); ?>

<div class="summary">
  <div class="card"><b>Tổng số gói</b><h2><?= count($plans) ?></h2></div>
  <div class="card"><b>Đang hoạt động</b><h2><?= count($active) ?></h2></div>
  <div class="card"><b>Đã tắt</b><h2><?= count($inactive) ?></h2></div>
</div>

<div class="card">
  <h2>Danh sách gói cước (<?= count($plans) ?>)</h2>
  <p class="muted" style="margin-bottom:12px">Mỗi đối tác có chiết khấu riêng (xem chi tiết tại trang Đối tác). Chỉ thay đổi giá ở đây nếu được CEO duyệt — log audit ghi lại mọi thay đổi.</p>
  <?php if (!$plans): ?>
    <div class="empty"><div class="icon">📋</div><p>Chưa có gói nào trong DB.</p></div>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead><tr><th>ID</th><th>Tên</th><th>Telecom</th><th>Ngày</th><th>Giá lẻ</th><th>Vốn</th><th>Lợi nhuận</th><th>Trạng thái</th><th style="width:340px">Thao tác</th></tr></thead>
    <tbody>
    <?php foreach ($plans as $p):
      $price = (int)($p['price'] ?? 0);
      $cost = (int)($p['cost'] ?? 0);
      $margin = $price - $cost;
      $marginPct = $price > 0 ? round(($margin / $price) * 100) : 0;
      $isActive = (int)($p['status'] ?? 0) === 1;
    ?>
    <tr>
      <td><span class="kbd">#<?= (int)$p['id'] ?></span></td>
      <td><strong><?= htmlspecialchars((string)($p['name'] ?? '')) ?></strong><br><span class="muted" style="font-size:11px">code: <?= htmlspecialchars((string)($p['code'] ?? '')) ?> · pack: <?= htmlspecialchars((string)($p['pack_code'] ?? '')) ?></span></td>
      <td><?= htmlspecialchars((string)($p['telecom'] ?? '')) ?></td>
      <td><?= (int)($p['day'] ?? 0) ?> ngày</td>
      <td><?= htmlspecialchars(format_vnd($price)) ?></td>
      <td><span class="muted"><?= htmlspecialchars(format_vnd($cost)) ?></span></td>
      <td>
        <span style="color:<?= $margin > 0 ? 'var(--a-green)' : 'var(--a-red)' ?>"><?= htmlspecialchars(format_vnd($margin)) ?></span>
        <?php if ($price > 0): ?><br><span class="muted" style="font-size:11px"><?= $marginPct ?>%</span><?php endif; ?>
      </td>
      <td><span class="tag <?= $isActive ? 'ok' : 'warn' ?>"><?= $isActive ? 'Hoạt động' : 'Tắt' ?></span></td>
      <td>
        <details>
          <summary class="btn sm">Chỉnh sửa</summary>
          <div class="action-group" style="margin-top:8px;display:flex;flex-direction:column;gap:8px">
            <form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap" onsubmit="return confirm('Cập nhật giá gói #<?= (int)$p['id'] ?>?')">
              <?php admin_csrf_field(); ?>
              <input type="hidden" name="action" value="update_price">
              <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
              <label style="font-size:11px">Giá<input type="number" name="price" value="<?= $price ?>" min="0" max="100000000" step="1000" style="width:90px" required></label>
              <label style="font-size:11px">Vốn<input type="number" name="cost" value="<?= $cost ?>" min="0" max="100000000" step="1000" style="width:90px"></label>
              <button class="btn sm gold">Lưu giá</button>
            </form>
            <form method="post" onsubmit="return confirm('<?= $isActive ? 'Tạm tắt' : 'Kích hoạt' ?> gói #<?= (int)$p['id'] ?>?')">
              <?php admin_csrf_field(); ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="plan_id" value="<?= (int)$p['id'] ?>">
              <input type="hidden" name="status" value="<?= $isActive ? 0 : 1 ?>">
              <button class="btn sm <?= $isActive ? 'danger' : '' ?>"><?= $isActive ? 'Tạm tắt gói' : 'Kích hoạt gói' ?></button>
            </form>
          </div>
        </details>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
