<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

// On-demand sync: try to populate QR/ICCID for any recent successful CTV order that
// is still missing them. Bounded + best-effort; failures don't block the page.
try {
    (new CtvFulfillmentService())->syncPendingForCtv((int)$user['id'], 20);
} catch (Throwable $e) {
    app_log('ctv esims page sync failed: ' . $e->getMessage(), 'WARN');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$q = trim((string)($_GET['q'] ?? ''));
$perPage = 50;
$where = 'WHERE ctv_id=?'; $params=[(int)$user['id']];
if ($q !== '') { $where .= ' AND (iccid LIKE ? OR ctv_order_id LIKE ? OR package_name LIKE ?)'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; }
$st = db()->prepare('SELECT * FROM ctv_esims '.$where.' ORDER BY id DESC LIMIT '.(int)$perPage.' OFFSET '.(int)(($page-1)*$perPage));
$st->execute($params);
$rows = $st->fetchAll();

// Also surface any successful CTV orders still waiting for QR (provider preparing).
$pStmt = db()->prepare('SELECT ctv_order_id, plan_name, carrier, updated_at FROM ctv_orders WHERE ctv_id=? AND status=2 AND (iccid IS NULL OR iccid=\'\') ORDER BY id DESC LIMIT 20');
$pStmt->execute([(int)$user['id']]);
$pending = $pStmt->fetchAll();

ctv_layout_header('eSIM của CTV', $user);
?>
<div class="card">
  <h2>Danh sách eSIM</h2>
  <form method="get" class="row">
    <div class="field"><label>Tìm ICCID / đơn / gói</label><input name="q" value="<?= htmlspecialchars($q) ?>"></div>
    <div class="field"><label>&nbsp;</label>
      <button class="btn">Lọc</button>
      <a class="btn secondary" href="/ctv/esims.php">Làm mới QR</a>
      <a class="btn secondary" href="/ctv/export.php?kind=esims">Xuất eSIM</a>
    </div>
  </form>

  <?php if ($pending): ?>
  <div class="muted" style="margin:8px 0">Đang chờ phát hành QR (<?= count($pending) ?> đơn). Refresh để cập nhật.</div>
  <table>
    <thead><tr><th>Đơn CTV</th><th>Gói</th><th>Cập nhật</th></tr></thead>
    <tbody>
      <?php foreach ($pending as $p): ?>
      <tr>
        <td><a href="/ctv/orders/view.php?id=<?= htmlspecialchars((string)$p['ctv_order_id']) ?>"><?= htmlspecialchars((string)$p['ctv_order_id']) ?></a></td>
        <td><?= htmlspecialchars((string)$p['carrier'].' '.(string)$p['plan_name']) ?></td>
        <td><?= htmlspecialchars((string)$p['updated_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <div class="empty-state"><div class="icon">📱</div><p>Chưa có eSIM nào.</p><p>eSIM sẽ hiển thị sau khi đơn thành công và hệ thống đồng bộ.</p></div>
  <?php else: ?>
  <table>
    <thead><tr><th>QR</th><th>ICCID</th><th>Đơn CTV</th><th>Gói</th><th>Hết hạn</th><th>Trạng thái</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td>
          <?php $qrIccid = (string)($r['iccid'] ?? ''); $qr = $qrIccid !== '' ? ('/ctv/qr.php?id=' . urlencode($qrIccid)) : ''; if ($qr !== ''): ?>
            <a href="<?= htmlspecialchars($qr) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars($qr) ?>" alt="QR" style="height:48px;width:48px;border-radius:6px;border:1px solid #2a2a2a"></a>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td><span class="kbd copy" data-copy="<?= htmlspecialchars((string)$r['iccid']) ?>"><?= htmlspecialchars((string)$r['iccid']) ?></span></td>
        <td><a href="/ctv/orders/view.php?id=<?= htmlspecialchars((string)$r['ctv_order_id']) ?>"><?= htmlspecialchars((string)$r['ctv_order_id']) ?></a></td>
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
