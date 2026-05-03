<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$st = db()->prepare('SELECT * FROM ctv_esims WHERE ctv_id=? ORDER BY id DESC LIMIT '.(int)$perPage.' OFFSET '.(int)(($page-1)*$perPage));
$st->execute([(int)$user['id']]);
$rows = $st->fetchAll();

ctv_layout_header('eSIM của CTV', $user);
?>
<div class="card">
  <h2>Danh sách eSIM</h2>
  <?php if (!$rows): ?>
    <p class="muted">Chưa có eSIM nào. eSIM sẽ được lưu sau khi đơn thành công và đồng bộ từ nhà cung cấp.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>ICCID</th><th>Đơn CTV</th><th>Gói</th><th>Hết hạn</th><th>Trạng thái</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><span class="kbd"><?= htmlspecialchars((string)$r['iccid']) ?></span></td>
        <td><?= htmlspecialchars((string)$r['ctv_order_id']) ?></td>
        <td><?= htmlspecialchars((string)$r['carrier'].' '.(string)$r['package_name']) ?></td>
        <td><?= htmlspecialchars((string)($r['expired_time'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)($r['esim_status'] ?? $r['smdp_status'] ?? '')) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
