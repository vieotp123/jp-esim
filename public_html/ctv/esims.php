<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$page = max(1, (int)($_GET['page'] ?? 1));
$q = trim((string)($_GET['q'] ?? ''));
$perPage = 50;
$where = 'WHERE ctv_id=?'; $params=[(int)$user['id']];
if ($q !== '') { $where .= ' AND (iccid LIKE ? OR ctv_order_id LIKE ? OR package_name LIKE ?)'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; }
$st = db()->prepare('SELECT * FROM ctv_esims '.$where.' ORDER BY id DESC LIMIT '.(int)$perPage.' OFFSET '.(int)(($page-1)*$perPage));
$st->execute($params);
$rows = $st->fetchAll();

ctv_layout_header('eSIM của CTV', $user);
?>
<div class="card">
  <h2>Danh sách eSIM</h2><form method="get" class="row"><div class="field"><label>Tìm ICCID / đơn / gói</label><input name="q" value="<?= htmlspecialchars($q) ?>"></div><div class="field"><label>&nbsp;</label><button class="btn">Lọc</button> <a class="btn secondary" href="/ctv/export.php?kind=esims">Export eSIM</a></div></form>
  <?php if (!$rows): ?>
    <p class="muted">Chưa có eSIM nào. eSIM sẽ được lưu sau khi đơn thành công và đồng bộ từ nhà cung cấp.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>ICCID</th><th>Đơn CTV</th><th>Gói</th><th>Hết hạn</th><th>Trạng thái</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><span class="kbd copy" data-copy="<?= htmlspecialchars((string)$r['iccid']) ?>"><?= htmlspecialchars((string)$r['iccid']) ?></span></td>
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
