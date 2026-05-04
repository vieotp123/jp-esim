<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = 0;

$pdo = db();
$st = $pdo->prepare('SELECT COUNT(*) FROM ctv_orders WHERE ctv_id=?'); $st->execute([(int)$user['id']]); $totalOrders = (int)$st->fetchColumn();
$st = $pdo->prepare("SELECT COUNT(*) FROM ctv_orders WHERE ctv_id=? AND status=2"); $st->execute([(int)$user['id']]); $okOrders = (int)$st->fetchColumn();
$st = $pdo->prepare("SELECT COUNT(*) FROM ctv_orders WHERE ctv_id=? AND status=3"); $st->execute([(int)$user['id']]); $failOrders = (int)$st->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) FROM ctv_topup_orders WHERE ctv_id=?'); $st->execute([(int)$user['id']]); $totalTopups = (int)$st->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) FROM ctv_esims WHERE ctv_id=?'); $st->execute([(int)$user['id']]); $totalEsims = (int)$st->fetchColumn();
$successRate = $totalOrders > 0 ? round(($okOrders / $totalOrders) * 100, 1) : 0;
$st = $pdo->prepare("SELECT DATE(created_at) d, COUNT(*) orders, SUM(CASE WHEN status=2 THEN total_charge ELSE 0 END) revenue FROM ctv_orders WHERE ctv_id=? AND created_at >= (CURDATE() - INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY d ASC");
$st->execute([(int)$user['id']]);
$chartRows = $st->fetchAll();
$chartMap = [];
foreach ($chartRows as $r) $chartMap[(string)$r['d']] = $r;
$chart = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime('-'.$i.' days'));
    $r = $chartMap[$d] ?? ['d'=>$d,'orders'=>0,'revenue'=>0];
    $chart[] = ['d'=>$d, 'orders'=>(int)$r['orders'], 'revenue'=>(int)$r['revenue']];
}
$maxRevenue = max(1, ...array_map(fn($r)=>(int)$r['revenue'], $chart));
$st = $pdo->prepare('SELECT pack_code, plan_name, carrier, COUNT(*) cnt, SUM(total_charge) revenue FROM ctv_orders WHERE ctv_id=? AND status=2 GROUP BY pack_code, plan_name, carrier ORDER BY cnt DESC, revenue DESC LIMIT 5');
$st->execute([(int)$user['id']]);
$topProducts = $st->fetchAll();

ctv_layout_header('Tổng quan', $user);
ctv_flash_render();
?>
<style>
  .dash-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:14px}
  .dash-metric{background:var(--c-card);border:1px solid var(--c-line);border-radius:12px;padding:14px}
  .dash-metric b{display:block;color:var(--c-muted);font-size:12px;text-transform:uppercase;letter-spacing:.5px}.dash-metric .num{font-size:28px;font-weight:800;margin-top:6px}
  .bars{display:flex;align-items:flex-end;gap:4px;height:160px;padding-top:10px}.bar{flex:1;background:linear-gradient(180deg,var(--c-gold-2),var(--c-gold-deep));border-radius:6px 6px 2px 2px;min-height:3px;position:relative}.bar:hover::after{content:attr(data-tip);position:absolute;left:50%;bottom:105%;transform:translateX(-50%);white-space:nowrap;background:#fff;color:#111;padding:4px 7px;border-radius:6px;font-size:11px;z-index:3}.top-list{display:grid;gap:8px}.top-item{display:flex;justify-content:space-between;gap:12px;border:1px solid var(--c-line);background:var(--c-card);border-radius:10px;padding:10px 12px}.top-item span{color:var(--c-muted)}
</style>
<div class="dash-metrics">
  <div class="dash-metric"><b>Tổng đơn</b><div class="num"><?= $totalOrders ?></div></div>
  <div class="dash-metric"><b>Tỉ lệ thành công</b><div class="num"><?= htmlspecialchars((string)$successRate) ?>%</div></div>
  <div class="dash-metric"><b>eSIM đã lưu</b><div class="num"><?= $totalEsims ?></div></div>
  <div class="dash-metric"><b>Đơn nạp data</b><div class="num"><?= $totalTopups ?></div></div>
</div>

<div class="grid">
  <div class="card">
    <h2>Đơn eSIM</h2>
    <p>Tổng: <strong><?= $totalOrders ?></strong> · Thành công: <strong><?= $okOrders ?></strong> · Thất bại: <strong style="color:#dc2626"><?= $failOrders ?></strong></p>
    <p>Đơn nạp data: <strong><?= $totalTopups ?></strong></p><p>eSIM đã lưu: <strong><?= $totalEsims ?></strong></p>
    <div class="actions"><a class="btn" href="/ctv/create-esim.php">Tạo eSIM mới</a><a class="btn secondary" href="/ctv/topup-esim.php">Nạp data</a><a class="btn secondary" href="/ctv/export.php">Xuất CSV</a></div>
  </div>
  <div class="card">
    <h2>Tài khoản</h2>
    <p>Email: <strong><?= htmlspecialchars((string)$user['email']) ?></strong></p>
    <p>Tên: <?= htmlspecialchars((string)($user['display_name'] ?? '')) ?></p>
    <p>Chiết khấu hiệu lực: <strong><?= htmlspecialchars(format_vnd((new CtvPricingService())->effectiveDiscount($user))) ?></strong> / eSIM</p>
  </div>
</div>


<div class="grid">
  <div class="card" style="grid-column:span 2">
    <h2>Doanh thu 30 ngày</h2>
    <div class="bars">
      <?php foreach ($chart as $r): $h=max(3,(int)round(((int)$r['revenue']/$maxRevenue)*150)); ?>
        <div class="bar" style="height:<?= $h ?>px" data-tip="<?= htmlspecialchars(date('d/m', strtotime($r['d'])).' · '.format_vnd((int)$r['revenue']).' · '.$r['orders'].' đơn') ?>"></div>
      <?php endforeach; ?>
    </div>
    <p class="muted">Di chuột/chạm vào cột để xem ngày, doanh thu và số đơn.</p>
  </div>
  <div class="card">
    <h2>Top sản phẩm</h2>
    <?php if (!$topProducts): ?><p class="muted">Chưa có dữ liệu.</p><?php else: ?><div class="top-list">
      <?php foreach ($topProducts as $p): ?><div class="top-item"><div><strong><?= htmlspecialchars((string)$p['carrier'].' '.(string)$p['plan_name']) ?></strong><br><span><?= htmlspecialchars((string)$p['pack_code']) ?></span></div><div><strong><?= (int)$p['cnt'] ?></strong><br><span><?= htmlspecialchars(format_vnd((int)$p['revenue'])) ?></span></div></div><?php endforeach; ?>
    </div><?php endif; ?>
  </div>
</div>

<?php ctv_layout_footer();
