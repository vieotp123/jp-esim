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
if ($q !== '') { $where .= ' AND (iccid LIKE ? OR ctv_order_id LIKE ? OR carrier LIKE ?)'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; $params[]='%'.$q.'%'; }
$st = db()->prepare('SELECT * FROM ctv_esims '.$where.' ORDER BY id DESC LIMIT '.(int)$perPage.' OFFSET '.(int)(($page-1)*$perPage));
$st->execute($params);
$rows = $st->fetchAll();

// Also surface any successful CTV orders still waiting for QR (provider preparing).
$pStmt = db()->prepare('SELECT ctv_order_id, plan_name, carrier, updated_at FROM ctv_orders WHERE ctv_id=? AND status=2 AND (iccid IS NULL OR iccid=\'\') ORDER BY id DESC LIMIT 20');
$pStmt->execute([(int)$user['id']]);
$pending = $pStmt->fetchAll();

function ctv_esims_data_label($bytes): string {
    $bytes = (float)$bytes;
    if ($bytes <= 0) return 'Data';
    return rtrim(rtrim(number_format($bytes / 1073741824, 2, '.', ''), '0'), '.') . ' GB';
}

function ctv_esims_days_label($days, $unit): string {
    $days = (int)$days;
    if ($days <= 0) return '';
    $unit = strtoupper(trim((string)$unit));
    return $days . ' ' . ($unit === 'DAY' || $unit === '' ? 'ngày' : strtolower($unit));
}

function ctv_esims_profile_label(array $row): string {
    $parts = [];
    $carrier = trim((string)($row['carrier'] ?? ''));
    if ($carrier !== '') $parts[] = $carrier;
    $parts[] = ctv_esims_data_label($row['total_volume'] ?? 0);
    $days = ctv_esims_days_label($row['total_duration'] ?? 0, $row['duration_unit'] ?? '');
    if ($days !== '') $parts[] = $days;
    return implode(' · ', $parts);
}

$csrf = CtvAuth::csrfToken();
ctv_layout_header('eSIM của CTV', $user);
?>
<style>
  .qr-thumb{height:44px;width:44px;border-radius:8px;border:1px solid var(--c-line-2);transition:transform .15s}
  .qr-thumb:hover{transform:scale(1.6);z-index:5;position:relative}
  .select-label{display:flex;align-items:center;gap:8px;margin:0;min-height:34px}
  .select-label .kbd{min-width:0;word-break:break-all}
  .select-cell{text-align:center}
</style>
<div class="card">
  <h2>Danh sách eSIM</h2>
  <form method="get" class="row" style="margin-bottom:14px">
    <div class="field"><label>Tìm ICCID / đơn / nhà mạng</label><input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nhập ICCID, mã đơn hoặc nhà mạng..."></div>
    <div class="field"><label>&nbsp;</label>
      <div class="actions" style="margin-top:0">
        <button class="btn">Lọc</button>
        <a class="btn secondary" href="/ctv/esims.php">Làm mới</a>
        <a class="btn secondary" href="/ctv/export.php?kind=esims&_csrf=<?= urlencode($csrf) ?>">Xuất CSV</a>
      </div>
    </div>
  </form>

  <?php if ($pending): ?>
  <div class="flash warn" style="margin-bottom:14px">
    Đang chờ phát hành QR (<?= count($pending) ?> đơn). Làm mới trang để cập nhật.
  </div>
  <div class="table-wrap" style="margin-bottom:16px">
  <table>
    <thead><tr><th>Đơn CTV</th><th>Nhà mạng</th><th>Cập nhật</th></tr></thead>
    <tbody>
      <?php foreach ($pending as $p): ?>
      <tr>
        <td><a href="/ctv/orders/view.php?id=<?= htmlspecialchars((string)$p['ctv_order_id']) ?>" class="kbd" style="text-decoration:none"><?= htmlspecialchars((string)$p['ctv_order_id']) ?></a></td>
        <td><?= htmlspecialchars((string)$p['carrier']) ?></td>
        <td><span class="muted"><?= htmlspecialchars((string)$p['updated_at']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

  <?php if (!$rows): ?>
    <div class="empty-state"><div class="icon">📱</div><p>Chưa có eSIM nào<?= $q ? ' phù hợp bộ lọc' : '' ?>.</p><p>eSIM sẽ hiển thị sau khi đơn thành công và hệ thống đồng bộ.</p></div>
  <?php else: ?>
  <form method="post" action="/ctv/export.php" id="esimExportForm">
  <input type="hidden" name="kind" value="esims">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
  <div class="actions" style="margin-bottom:12px">
    <button class="btn secondary" type="submit">Xuất eSIM đã chọn CSV</button>
  </div>
  <div class="m-cards">
    <?php foreach ($rows as $r):
      $qrIccid = (string)($r['iccid'] ?? ''); $qr = $qrIccid !== '' ? ('/ctv/qr.php?id=' . urlencode($qrIccid)) : '';
    ?>
    <div class="m-card">
      <div class="m-head">
        <label class="select-label">
          <input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" class="esim-select">
          <span class="kbd copy" data-copy="<?= htmlspecialchars((string)$r['iccid']) ?>" style="font-size:11px"><?= htmlspecialchars((string)$r['iccid']) ?></span>
        </label>
        <span class="muted" style="font-size:11px"><?= htmlspecialchars((string)($r['esim_status'] ?? $r['smdp_status'] ?? '')) ?></span>
      </div>
      <div class="m-row"><span class="m-label">Gói</span><span class="m-val"><?= htmlspecialchars(ctv_esims_profile_label($r)) ?></span></div>
      <div class="m-row"><span class="m-label">Đơn</span><span class="m-val"><a href="/ctv/orders/view.php?id=<?= htmlspecialchars((string)$r['ctv_order_id']) ?>"><?= htmlspecialchars((string)$r['ctv_order_id']) ?></a></span></div>
      <div class="m-row"><span class="m-label">Hết hạn</span><span class="m-val muted"><?= htmlspecialchars((string)($r['expired_time'] ?? '—')) ?></span></div>
      <?php if ($qr !== ''): ?>
      <div class="m-actions">
        <a class="btn sm" href="<?= htmlspecialchars($qr) ?>" target="_blank" rel="noopener">Xem QR</a>
        <a class="btn sm secondary" href="/ctv/orders/view.php?id=<?= htmlspecialchars((string)$r['ctv_order_id']) ?>">Chi tiết</a>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th class="select-cell"><input type="checkbox" id="selectAllEsims" aria-label="Chọn tất cả eSIM"></th><th>QR</th><th>ICCID</th><th>Đơn CTV</th><th>Gói</th><th>Hết hạn</th><th>Trạng thái</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td class="select-cell"><input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" class="esim-select" aria-label="Chọn eSIM <?= htmlspecialchars((string)$r['iccid']) ?>"></td>
        <td>
          <?php $qrIccid = (string)($r['iccid'] ?? ''); $qr = $qrIccid !== '' ? ('/ctv/qr.php?id=' . urlencode($qrIccid)) : ''; if ($qr !== ''): ?>
            <a href="<?= htmlspecialchars($qr) ?>" target="_blank" rel="noopener"><img src="<?= htmlspecialchars($qr) ?>" alt="QR" class="qr-thumb"></a>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td><span class="kbd copy" data-copy="<?= htmlspecialchars((string)$r['iccid']) ?>"><?= htmlspecialchars((string)$r['iccid']) ?></span></td>
        <td><a href="/ctv/orders/view.php?id=<?= htmlspecialchars((string)$r['ctv_order_id']) ?>"><?= htmlspecialchars((string)$r['ctv_order_id']) ?></a></td>
        <td><?= htmlspecialchars(ctv_esims_profile_label($r)) ?></td>
        <td style="white-space:nowrap"><span class="muted"><?= htmlspecialchars((string)($r['expired_time'] ?? '')) ?></span></td>
        <td><?= htmlspecialchars((string)($r['esim_status'] ?? $r['smdp_status'] ?? '')) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  </form>
  <script>
  (function(){
    var form = document.getElementById('esimExportForm');
    var selectAll = document.getElementById('selectAllEsims');
    var boxes = Array.prototype.slice.call(document.querySelectorAll('.esim-select'));
    if (selectAll) selectAll.addEventListener('change', function(){
      boxes.forEach(function(box){ box.checked = selectAll.checked; });
    });
    if (form) form.addEventListener('submit', function(e){
      var checked = boxes.some(function(box){ return box.checked; });
      if (!checked) {
        e.preventDefault();
        alert('Vui lòng chọn ít nhất một eSIM để xuất CSV.');
      }
    });
  })();
  </script>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
