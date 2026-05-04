<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
security_headers(true);
$assetVer = '20260504b';

$orderId = trim((string)($_GET['id'] ?? ''));
$order = null;
$error = null;

if ($orderId !== '') {
    try {
        $orderId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $orderId));
        if ($orderId === '') throw new InvalidArgumentException('Mã đơn không hợp lệ');
        $order = (new PaymentService())->status($orderId, strlen($orderId) > 10 ? 'order' : ($_GET['type'] ?? 'order'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#050607">
<meta name="robots" content="noindex,nofollow,noarchive">
<link rel="canonical" href="https://jp-esim.vip/tra-cuu.php">
<title>Tra cứu đơn hàng · jp-esim.vip</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/app.css?v=<?= $assetVer ?>">
<style>
.track-wrap{max-width:520px;margin:0 auto;padding:18px 16px 60px;min-height:100dvh}
.track-header{margin:6px 0 20px}
.track-header h1{font-size:28px;letter-spacing:-.8px;margin:2px 0 0}
.track-header .eyebrow{font-size:13px;color:var(--muted);font-weight:800;letter-spacing:.4px;text-transform:uppercase}
.search-form{display:flex;gap:8px;margin:16px 0}
.search-form input{flex:1;min-width:0}
.search-form button{border:0;border-radius:17px;background:var(--green);color:#001b0a;font-weight:950;padding:0 20px;font-size:15px;white-space:nowrap}
.progress-bar{display:flex;gap:4px;margin:18px 0}
.progress-step{flex:1;text-align:center}
.progress-step .dot{width:32px;height:32px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-weight:900;font-size:13px;border:2px solid var(--line);color:var(--muted);background:var(--card);margin-bottom:6px;transition:.2s}
.progress-step.done .dot{background:var(--green);border-color:var(--green);color:#001b0a}
.progress-step.current .dot{border-color:var(--green);color:var(--green);box-shadow:0 0 0 4px rgba(48,209,88,.16)}
.progress-step .label{display:block;font-size:11.5px;font-weight:700;color:var(--muted)}
.progress-step.done .label,.progress-step.current .label{color:var(--text)}
.detail-grid{display:grid;gap:10px;margin:14px 0}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--line)}
.detail-row:last-child{border-bottom:0}
.detail-label{color:var(--muted);font-size:13px;font-weight:700}
.detail-value{font-weight:800;font-size:14px;text-align:right}
.err-card{background:rgba(255,69,58,.10);border:1px solid rgba(255,69,58,.25);color:#fca5a5;border-radius:22px;padding:18px;margin:16px 0;font-weight:700}
.back-link{display:inline-flex;align-items:center;gap:6px;color:var(--muted);font-weight:700;font-size:14px;text-decoration:none;margin-top:16px}
.back-link:hover{color:var(--green)}
@media(max-width:400px){
  .track-header h1{font-size:24px}
  .search-form{flex-direction:column}
  .search-form button{padding:14px;border-radius:17px;font-size:16px}
  .progress-step .dot{width:28px;height:28px;font-size:12px}
  .progress-step .label{font-size:10.5px}
  .detail-label{font-size:12px}
  .detail-value{font-size:13px}
}
</style>
</head>
<body>
<div class="track-wrap">
  <div class="track-header">
    <div class="eyebrow">JP eSIM</div>
    <h1>Tra cứu đơn hàng</h1>
  </div>

  <div class="form-card">
    <form method="get" class="search-form">
      <input type="text" name="id" value="<?= htmlspecialchars($orderId) ?>" placeholder="Nhập mã đơn hàng..." required autocomplete="off" autofocus>
      <button type="submit">Tra cứu</button>
    </form>
  </div>

<?php if ($error): ?>
  <div class="err-card"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($order): ?>
  <?php
    $type = (string)($order['type'] ?? 'order');
    $paid = !empty($order['paid']);
    $expired = !empty($order['expired']);

    if ($type === 'order') {
        $ful = (string)($order['fulfillmentStatus'] ?? 'pending');
        $steps = [
            ['label' => 'Đặt hàng', 'done' => true],
            ['label' => 'Thanh toán', 'done' => $paid, 'current' => !$paid && !$expired],
            ['label' => 'Tạo eSIM', 'done' => in_array($ful, ['ordered', 'esim_ready']), 'current' => $paid && $ful === 'ordering'],
            ['label' => 'Hoàn tất', 'done' => $ful === 'esim_ready'],
        ];
    } else {
        $topupDone = ($order['topupStatus'] ?? '') === 'done';
        $steps = [
            ['label' => 'Đặt hàng', 'done' => true],
            ['label' => 'Thanh toán', 'done' => $paid, 'current' => !$paid],
            ['label' => 'Nạp data', 'done' => $topupDone, 'current' => $paid && !$topupDone],
            ['label' => 'Hoàn tất', 'done' => $topupDone],
        ];
    }
    if ($expired) {
        $steps = array_map(fn($s) => array_merge($s, ['current' => false]), $steps);
    }
  ?>

  <div class="form-card">
    <h2 style="margin:0 0 4px;font-size:20px;letter-spacing:-.5px"><?= htmlspecialchars((string)($order['detailTitle'] ?? 'Đơn hàng')) ?></h2>
    <p style="margin:0 0 6px;color:var(--muted);font-size:13px">Mã: <span style="font-weight:900;color:var(--text)"><?= htmlspecialchars((string)($order['id'] ?? '')) ?></span></p>

    <?php if ($expired): ?>
      <span class="status-pill" style="background:rgba(255,69,58,.12);color:var(--danger);border-color:rgba(255,69,58,.25)">Đã hết hạn</span>
    <?php elseif ($paid && ($type !== 'order' || $ful === 'esim_ready')): ?>
      <span class="status-pill ok">Hoàn tất</span>
    <?php elseif ($paid): ?>
      <span class="status-pill" style="background:rgba(10,132,255,.12);color:var(--blue);border-color:rgba(10,132,255,.25)">Đang xử lý</span>
    <?php else: ?>
      <span class="status-pill">Chờ thanh toán</span>
    <?php endif; ?>

    <div class="progress-bar">
      <?php foreach ($steps as $i => $s): ?>
        <div class="progress-step <?= !empty($s['done']) ? 'done' : (!empty($s['current']) ? 'current' : '') ?>">
          <span class="dot"><?= $i + 1 ?></span>
          <span class="label"><?= htmlspecialchars($s['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="detail-grid">
      <?php if (!empty($order['planName'])): ?>
      <div class="detail-row">
        <span class="detail-label">Gói</span>
        <span class="detail-value"><?= htmlspecialchars(trim(($order['carrier'] ?? '') . ' ' . $order['planName'])) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($order['createdAt'])): ?>
      <div class="detail-row">
        <span class="detail-label">Ngày đặt</span>
        <span class="detail-value"><?= htmlspecialchars((string)$order['createdAt']) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($paid && !empty($order['paidAt'])): ?>
      <div class="detail-row">
        <span class="detail-label">Thanh toán lúc</span>
        <span class="detail-value"><?= htmlspecialchars((string)$order['paidAt']) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($order['iccid'])): ?>
      <div class="detail-row">
        <span class="detail-label">ICCID</span>
        <span class="detail-value" style="font-family:monospace;font-size:12px"><?= htmlspecialchars((string)$order['iccid']) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!$paid && !$expired && !empty($order['qrDataUrl'])): ?>
      <div style="margin:16px 0;text-align:center">
        <p style="font-weight:800;margin:0 0 8px">Quét QR để thanh toán</p>
        <img class="pay-qr" src="<?= htmlspecialchars((string)$order['qrDataUrl']) ?>" alt="VietQR" style="max-width:220px">
        <?php if (!empty($order['bankNote'])): ?>
        <p style="font-size:13px;color:var(--muted);margin:8px 0">Nội dung CK: <b><?= htmlspecialchars((string)$order['bankNote']) ?></b></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
<?php elseif ($orderId === ''): ?>
  <div class="form-card" style="text-align:center;padding:30px 18px">
    <p style="font-size:42px;margin:0 0 10px">🔍</p>
    <p style="margin:0;color:var(--muted);line-height:1.5">Nhập mã đơn hàng để tra cứu trạng thái.<br>Mã đơn có dạng <b>Nxxxxxxx</b> và được gửi qua email khi bạn đặt hàng.</p>
    <p style="margin:12px 0 0;font-size:13px;color:var(--muted2)">Không tìm thấy mã đơn? Kiểm tra hộp thư email hoặc <a href="/" style="color:var(--green)">liên hệ hỗ trợ</a>.</p>
  </div>
<?php endif; ?>

  <a class="back-link" href="/">← Quay về trang chủ</a>
</div>
</body>
</html>
