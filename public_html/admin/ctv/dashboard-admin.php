<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
$pdo = db();

$revenueRetail = function (string $interval) use ($pdo): int {
    $st = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM `order` WHERE status >= 2 AND paid_at >= (NOW() - INTERVAL $interval)");
    $st->execute();
    return (int)$st->fetchColumn();
};
$revenueCtv = function (string $interval) use ($pdo): int {
    $st = $pdo->prepare("SELECT COALESCE(SUM(total_charge),0) FROM ctv_orders WHERE status=2 AND created_at >= (NOW() - INTERVAL $interval)");
    $st->execute();
    return (int)$st->fetchColumn();
};

$revToday = $revenueRetail('1 DAY') + $revenueCtv('1 DAY');
$rev7d = $revenueRetail('7 DAY') + $revenueCtv('7 DAY');
$rev30d = $revenueRetail('30 DAY') + $revenueCtv('30 DAY');

$ctvActive = (int)$pdo->query("SELECT COUNT(*) FROM ctv_users WHERE status=1")->fetchColumn();
$ctvPending = (int)$pdo->query("SELECT COUNT(*) FROM ctv_users WHERE status=1 AND email_verified=0")->fetchColumn();
$ctvDisabled = (int)$pdo->query("SELECT COUNT(*) FROM ctv_users WHERE status=0")->fetchColumn();

$top5 = $pdo->query("SELECT u.id, u.email, u.company_name, SUM(o.total_charge) rev, COUNT(*) cnt FROM ctv_orders o JOIN ctv_users u ON u.id=o.ctv_id WHERE o.status=2 AND o.created_at >= (CURDATE() - INTERVAL 30 DAY) GROUP BY u.id ORDER BY rev DESC LIMIT 5")->fetchAll();

$orderStats = $pdo->query("SELECT 'retail' src, status, COUNT(*) cnt FROM `order` GROUP BY status UNION ALL SELECT 'ctv' src, status, COUNT(*) cnt FROM ctv_orders GROUP BY status")->fetchAll();
$orderBreakdown = [];
foreach ($orderStats as $r) {
    $src = (string)$r['src'];
    $s = (int)$r['status'];
    $label = match ($s) { 0 => 'Chờ TT', 1 => 'Hết hạn', 2 => 'Thành công', 3 => 'Thất bại', default => 'Khác' };
    $orderBreakdown[$src][$label] = ($orderBreakdown[$src][$label] ?? 0) + (int)$r['cnt'];
}

$queueCounts = $pdo->query("SELECT kind, COUNT(*) cnt FROM order_admin_queue WHERE status='open' GROUP BY kind")->fetchAll();
$queueMap = [];
foreach ($queueCounts as $r) $queueMap[(string)$r['kind']] = (int)$r['cnt'];
$queueTotal = array_sum($queueMap);

$recent = $pdo->query("(SELECT 'retail' AS src, order_id AS ref, status, total AS amount, created_at FROM `order` ORDER BY id DESC LIMIT 10) UNION ALL (SELECT 'ctv', ctv_order_id, status, total_charge, created_at FROM ctv_orders ORDER BY id DESC LIMIT 10) ORDER BY created_at DESC LIMIT 10")->fetchAll();

admin_layout_header('Tổng quan Admin', $admin);
?>
<div class="summary">
  <div class="card gold"><b>Doanh thu hôm nay</b><h2><?= htmlspecialchars(format_vnd($revToday)) ?></h2><div class="sub">Lẻ + CTV</div></div>
  <div class="card"><b>Doanh thu 7 ngày</b><h2><?= htmlspecialchars(format_vnd($rev7d)) ?></h2></div>
  <div class="card"><b>Doanh thu 30 ngày</b><h2><?= htmlspecialchars(format_vnd($rev30d)) ?></h2></div>
  <div class="card"><b>CTV hoạt động</b><h2><?= $ctvActive ?></h2><div class="sub">Chờ xác minh: <?= $ctvPending ?> · Vô hiệu: <?= $ctvDisabled ?></div></div>
  <div class="card <?= $queueTotal > 0 ? 'danger' : 'green' ?>"><b>Đơn cần xử lý</b><h2><?= $queueTotal ?></h2><?php if ($queueMap): ?><div class="sub"><?php $kindVi=['amount_mismatch'=>'Sai số tiền','fulfillment_error'=>'Lỗi xử lý','email_error'=>'Lỗi email','payment_orphan'=>'Thiếu đơn']; foreach ($queueMap as $k => $v) echo '<span class="tag err" style="margin:2px">'.htmlspecialchars($kindVi[$k] ?? $k).': '.$v.'</span> '; ?></div><?php endif; ?></div>
</div>

<div class="dash-grid">
<div class="card">
  <h2>Top 5 CTV (30 ngày)</h2>
  <?php if ($top5): ?>
  <div class="table-wrap">
  <table><thead><tr><th>CTV</th><th>Đơn</th><th>Doanh thu</th></tr></thead><tbody>
  <?php foreach ($top5 as $t): ?>
  <tr>
    <td><a class="rowlink" href="/admin/ctv/view.php?id=<?= (int)$t['id'] ?>">#<?= (int)$t['id'] ?></a> <?= htmlspecialchars((string)($t['company_name'] ?: $t['email'])) ?></td>
    <td><?= (int)$t['cnt'] ?></td>
    <td style="white-space:nowrap"><?= htmlspecialchars(format_vnd((int)$t['rev'])) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div>
  <?php else: ?><div class="empty"><div class="icon">📊</div><p>Chưa có dữ liệu 30 ngày.</p></div><?php endif; ?>
</div>

<div class="card">
  <h2>Đơn hàng theo trạng thái</h2>
  <?php foreach (['retail', 'ctv'] as $src): ?>
  <h3><?= $src === 'retail' ? 'KHÁCH LẺ' : 'CTV' ?></h3>
  <div class="filter-row">
    <?php foreach ($orderBreakdown[$src] ?? [] as $label => $cnt): ?>
      <span class="tag <?= $label === 'Thành công' ? 'ok' : ($label === 'Thất bại' ? 'err' : ($label === 'Chờ TT' ? 'warn' : 'info')) ?>"><?= htmlspecialchars($label) ?>: <?= $cnt ?></span>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>
</div>

<div class="card">
  <h2>10 đơn gần nhất (Lẻ + CTV)</h2>
  <div class="table-wrap">
  <table><thead><tr><th>Nguồn</th><th>Mã đơn</th><th>Trạng thái</th><th>Số tiền</th><th>Thời gian</th></tr></thead><tbody>
  <?php foreach ($recent as $r):
    $s = (int)$r['status'];
    $sLabel = match ($s) { 0 => 'Chờ TT', 1 => 'Hết hạn', 2 => 'Thành công', 3 => 'Thất bại', default => (string)$s };
    $sCls = match ($s) { 2 => 'ok', 3 => 'err', 0 => 'warn', default => 'info' };
  ?>
  <tr>
    <td><span class="tag <?= $r['src'] === 'ctv' ? 'gold' : 'info' ?>"><?= $r['src'] === 'ctv' ? 'CTV' : 'Lẻ' ?></span></td>
    <td><span class="kbd"><?= htmlspecialchars((string)$r['ref']) ?></span></td>
    <td><span class="tag <?= $sCls ?>"><?= $sLabel ?></span></td>
    <td style="white-space:nowrap"><?= htmlspecialchars(format_vnd((int)$r['amount'])) ?></td>
    <td style="white-space:nowrap"><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div>
</div>
<?php admin_layout_footer();
