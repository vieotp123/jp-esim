<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

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
$st = $pdo->prepare('SELECT o.plan_name, o.carrier, p.day, COUNT(*) cnt, SUM(o.total_charge) revenue FROM ctv_orders o LEFT JOIN plan p ON p.id=o.plan_id WHERE o.ctv_id=? AND o.status=2 GROUP BY o.plan_name, o.carrier, p.day ORDER BY cnt DESC, revenue DESC LIMIT 5');
$st->execute([(int)$user['id']]);
$topProducts = $st->fetchAll();

function ctv_dash_plan_data(string $plan): string {
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(GB|MB)\b/i', $plan, $m)) {
        return str_replace(',', '.', $m[1]) . ' ' . strtoupper($m[2]);
    }
    return 'Data';
}

function ctv_dash_plan_label(array $row): string {
    $parts = [];
    $carrier = trim((string)($row['carrier'] ?? ''));
    if ($carrier !== '') $parts[] = $carrier;
    $parts[] = ctv_dash_plan_data((string)($row['plan_name'] ?? ''));
    $days = (int)($row['day'] ?? 0);
    if ($days > 0) $parts[] = $days . ' ngày';
    return implode(' · ', $parts);
}

ctv_layout_header('Tổng quan', $user);
ctv_flash_render();
?>
<style>
  .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
  .stat{
    background:linear-gradient(180deg,var(--c-card-2),var(--c-card));
    border:1px solid var(--c-line-2);border-radius:var(--c-radius);
    padding:16px 18px;position:relative;overflow:hidden;
  }
  .stat::after{
    content:'';position:absolute;right:-20px;top:-20px;width:80px;height:80px;
    border-radius:50%;background:radial-gradient(circle,rgba(230,192,104,.08),transparent 70%);
    pointer-events:none;
  }
  .stat-label{font-size:11px;font-weight:700;color:var(--c-muted);text-transform:uppercase;letter-spacing:.6px}
  .stat-val{font-size:26px;font-weight:900;margin-top:4px;letter-spacing:-.5px}
  .stat-val.gold{color:var(--c-gold)}
  .stat-val.green{color:var(--c-green)}
  .stat-val.red{color:var(--c-red)}
  .bars{display:flex;align-items:flex-end;gap:3px;height:150px;padding-top:10px}
  .bar{
    flex:1;min-width:0;min-height:3px;
    background:linear-gradient(180deg,var(--c-gold-2),var(--c-gold-deep));
    border-radius:4px 4px 1px 1px;position:relative;
    transition:filter .15s;
  }
  .bar:hover{filter:brightness(1.2)}
  .bar:hover::after,.bar:active::after{
    content:attr(data-tip);position:absolute;left:50%;bottom:calc(100% + 6px);
    transform:translateX(-50%);white-space:nowrap;
    background:var(--c-ink);color:var(--c-bg);padding:5px 10px;border-radius:8px;
    font-size:11px;font-weight:600;z-index:3;pointer-events:none;
    box-shadow:0 4px 12px rgba(0,0,0,.3);
  }
  .top-list{display:grid;gap:8px}
  .top-item{
    display:flex;justify-content:space-between;align-items:center;gap:12px;
    border:1px solid var(--c-line-2);background:var(--c-surface);
    border-radius:var(--c-radius-sm);padding:12px 14px;transition:border-color .15s;
  }
  .top-item:hover{border-color:rgba(230,192,104,.20)}
  .top-item .tp-name{font-weight:600;font-size:13px}
  .top-item .tp-code{color:var(--c-muted);font-size:12px;margin-top:2px}
  .top-item .tp-right{text-align:right}
  .top-item .tp-cnt{font-weight:800;font-size:15px}
  .top-item .tp-rev{color:var(--c-muted);font-size:12px;margin-top:2px}
  .dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
  .dash-grid .full{grid-column:1/-1}
  @media(max-width:768px){
    .stats{grid-template-columns:repeat(2,1fr)}
    .stat-val{font-size:22px}
    .bars{height:110px;gap:2px}
    .bar:hover::after,.bar:active::after{font-size:10px;padding:3px 6px}
    .dash-grid{grid-template-columns:1fr}
  }
  @media(max-width:480px){
    .stat{padding:12px 14px}
    .stat-val{font-size:20px}
  }
</style>

<div class="stat" style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap">
  <div>
    <div class="stat-label">Số dư ví</div>
    <div class="stat-val gold" style="font-size:30px"><?= htmlspecialchars(format_vnd((int)$user['balance'])) ?></div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn gold" href="/ctv/create-esim.php" style="white-space:nowrap">Tạo eSIM mới</a>
    <a class="btn secondary" href="/ctv/topup-request.php" style="white-space:nowrap">Nạp ví</a>
  </div>
</div>

<?php if ($totalOrders === 0): ?>
<div class="card" style="margin-bottom:14px;background:linear-gradient(135deg,rgba(230,192,104,.06),rgba(230,192,104,.02));border:1px dashed rgba(230,192,104,.3)">
  <h2 style="margin-bottom:6px">👋 Chào mừng đến với jp-esim Partner</h2>
  <p class="muted" style="margin-bottom:14px">Tài khoản của bạn đã sẵn sàng. Hãy hoàn tất 3 bước đầu tiên:</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
    <div style="padding:14px;border:1px solid var(--c-line-2);border-radius:10px;background:var(--c-card)">
      <div style="font-size:12px;color:var(--c-gold);font-weight:700;margin-bottom:4px">BƯỚC 1</div>
      <div style="font-weight:700;margin-bottom:8px">Nạp ví đối tác</div>
      <p class="muted" style="font-size:12px;margin-bottom:10px">Chuyển khoản và upload bằng chứng để admin duyệt nạp ví.</p>
      <a class="btn sm gold" href="/ctv/topup-request.php">Nạp ví ngay →</a>
    </div>
    <div style="padding:14px;border:1px solid var(--c-line-2);border-radius:10px;background:var(--c-card)">
      <div style="font-size:12px;color:var(--c-muted);font-weight:700;margin-bottom:4px">BƯỚC 2</div>
      <div style="font-weight:700;margin-bottom:8px">Xem bảng giá</div>
      <p class="muted" style="font-size:12px;margin-bottom:10px">Kiểm tra giá đối tác và hạng chiết khấu của bạn.</p>
      <a class="btn sm secondary" href="/ctv/pricing.php">Xem bảng giá →</a>
    </div>
    <div style="padding:14px;border:1px solid var(--c-line-2);border-radius:10px;background:var(--c-card)">
      <div style="font-size:12px;color:var(--c-muted);font-weight:700;margin-bottom:4px">BƯỚC 3</div>
      <div style="font-weight:700;margin-bottom:8px">Tạo eSIM đầu tiên</div>
      <p class="muted" style="font-size:12px;margin-bottom:10px">Đặt eSIM cho khách hàng — QR sẽ gửi tự động vào email.</p>
      <a class="btn sm secondary" href="/ctv/create-esim.php">Tạo eSIM →</a>
    </div>
  </div>
  <p class="muted" style="margin-top:14px;font-size:12px">💡 Bạn cũng có thể thiết lập <a href="/ctv/security.php" style="color:var(--c-gold)">Passkey</a> để đăng nhập an toàn không cần mật khẩu, hoặc xem <a href="/ctv/api-keys.php" style="color:var(--c-gold)">API key</a> để tích hợp lập trình.</p>
</div>
<?php endif; ?>
<div class="stats">
  <div class="stat"><div class="stat-label">Tổng đơn</div><div class="stat-val"><?= $totalOrders ?></div></div>
  <div class="stat"><div class="stat-label">Thành công</div><div class="stat-val green"><?= htmlspecialchars((string)$successRate) ?>%</div></div>
  <div class="stat"><div class="stat-label">eSIM đã lưu</div><div class="stat-val"><?= $totalEsims ?></div></div>
  <div class="stat"><div class="stat-label">Đơn nạp data</div><div class="stat-val"><?= $totalTopups ?></div></div>
</div>

<div class="dash-grid">
  <div class="card">
    <h2>Đơn eSIM</h2>
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px">
      <div><span class="muted">Tổng</span><br><strong><?= $totalOrders ?></strong></div>
      <div><span class="muted">Thành công</span><br><strong style="color:var(--c-green)"><?= $okOrders ?></strong></div>
      <div><span class="muted">Thất bại</span><br><strong style="color:var(--c-red)"><?= $failOrders ?></strong></div>
      <div><span class="muted">Nạp data</span><br><strong><?= $totalTopups ?></strong></div>
    </div>
    <div class="actions">
      <a class="btn" href="/ctv/create-esim.php">Tạo eSIM mới</a>
      <a class="btn secondary" href="/ctv/topup-esim.php">Nạp data</a>
      <a class="btn secondary" href="/ctv/export.php">Xuất CSV</a>
    </div>
  </div>
  <div class="card">
    <h2>Tài khoản</h2>
    <div style="display:grid;gap:8px;font-size:14px">
      <div><span class="muted">Email</span><br><strong><?= htmlspecialchars((string)$user['email']) ?></strong></div>
      <?php if (!empty($user['display_name'])): ?>
        <div><span class="muted">Tên hiển thị</span><br><?= htmlspecialchars((string)$user['display_name']) ?></div>
      <?php endif; ?>
      <div><span class="muted">Chiết khấu hiệu lực</span><br><strong style="color:var(--c-gold)"><?= htmlspecialchars(format_vnd((new CtvPricingService())->effectiveDiscount($user))) ?></strong> / eSIM</div>
    </div>
  </div>
</div>

<div class="dash-grid">
  <div class="card full">
    <h2>Doanh thu 30 ngày</h2>
    <?php $hasRevenue = array_sum(array_map(fn($r)=>(int)$r['revenue'], $chart)) > 0; ?>
    <?php if ($hasRevenue): ?>
    <div class="bars">
      <?php foreach ($chart as $r): $h=max(3,(int)round(((int)$r['revenue']/$maxRevenue)*150)); ?>
        <div class="bar" style="height:<?= $h ?>px" data-tip="<?= htmlspecialchars(date('d/m', strtotime($r['d'])).' · '.format_vnd((int)$r['revenue']).' · '.$r['orders'].' đơn') ?>"></div>
      <?php endforeach; ?>
    </div>
    <p class="muted" style="margin-top:8px">Di chuột hoặc chạm vào cột để xem chi tiết ngày.</p>
    <?php else: ?>
    <div class="empty-state" style="padding:30px 20px;text-align:center">
      <div class="icon" style="font-size:32px;margin-bottom:8px">📈</div>
      <p class="muted">Chưa có doanh thu trong 30 ngày qua. Đơn thành công sẽ hiển thị ở đây.</p>
    </div>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Top sản phẩm</h2>
    <?php if (!$topProducts): ?>
      <div class="empty-state"><div class="icon">📊</div><p>Chưa có dữ liệu sản phẩm.</p></div>
    <?php else: ?>
      <div class="top-list">
        <?php foreach ($topProducts as $p): ?>
        <div class="top-item">
          <div><div class="tp-name"><?= htmlspecialchars(ctv_dash_plan_label($p)) ?></div></div>
          <div class="tp-right"><div class="tp-cnt"><?= (int)$p['cnt'] ?></div><div class="tp-rev"><?= htmlspecialchars(format_vnd((int)$p['revenue'])) ?></div></div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php ctv_layout_footer();
