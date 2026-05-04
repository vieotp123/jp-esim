<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/../_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$orderId = strtoupper(trim((string)($_GET['id'] ?? '')));
if ($orderId === '' || !preg_match('/^[A-Z0-9]{2,16}$/', $orderId)) {
    http_response_code(400);
    ctv_layout_header('Đơn không hợp lệ', $user);
    echo '<div class="card"><h2>Mã đơn không hợp lệ</h2><p class="muted">Vui lòng quay lại danh sách đơn.</p><p><a class="btn secondary" href="/ctv/orders.php">Về danh sách đơn</a></p></div>';
    ctv_layout_footer();
    exit;
}

// Ownership-guarded fetch — fails closed if order belongs to another CTV.
$st = db()->prepare('SELECT * FROM ctv_orders WHERE ctv_order_id=? AND ctv_id=? LIMIT 1');
$st->execute([$orderId, (int)$user['id']]);
$order = $st->fetch();
if (!$order) {
    http_response_code(404);
    ctv_layout_header('Không tìm thấy đơn', $user);
    echo '<div class="card"><h2>Không tìm thấy đơn</h2><p class="muted">Đơn không tồn tại hoặc không thuộc tài khoản CTV của bạn.</p><p><a class="btn secondary" href="/ctv/orders.php">Về danh sách đơn</a></p></div>';
    ctv_layout_footer();
    exit;
}

// On-demand sync if order is success but missing iccid (best-effort, swallows errors).
if ((int)$order['status'] === 2 && empty($order['iccid'])) {
    try { (new CtvFulfillmentService())->syncOrderEsims($orderId); }
    catch (Throwable $e) { app_log('ctv view on-demand sync ' . $orderId . ' ' . $e->getMessage(), 'WARN'); }
    // Reload after sync attempt.
    $st->execute([$orderId, (int)$user['id']]);
    $order = $st->fetch();
}

// Load esim rows (may be 0 if provider still preparing).
$esStmt = db()->prepare('SELECT * FROM ctv_esims WHERE ctv_order_id=? AND ctv_id=? ORDER BY id ASC');
$esStmt->execute([$orderId, (int)$user['id']]);
$esims = $esStmt->fetchAll();

// Map status code → label/class
$statusMap = [0=>['pending','warn'], 1=>['processing','warn'], 2=>['success','ok'], 3=>['failed','err']];
[$statusLabel, $statusCls] = $statusMap[(int)$order['status']] ?? ['unknown', ''];

// Topup eligibility — code-locked while TOPUP_LOCKED=1
$topupLocked = ((string)app_config('TOPUP_LOCKED', '0') === '1');

ctv_layout_header('Đơn ' . $orderId, $user);

// Auto-refresh hint: while order is pending or success-without-esims, refetch every 30s.
$needsAutoRefresh = ((int)$order['status'] < 2) || ((int)$order['status'] === 2 && empty($esims));
if ($needsAutoRefresh) {
    echo '<meta http-equiv="refresh" content="30">';
}

function _bytes_to_gb($b): string {
    $b = (int)$b;
    if ($b <= 0) return '0';
    return number_format($b / 1073741824, 2) . ' GB';
}
?>
<style>
  .order-grid { display:grid; grid-template-columns: 2fr 1fr; gap:16px; }
  @media (max-width: 760px){ .order-grid{ grid-template-columns:1fr; } }
  .kv { display:grid; grid-template-columns:140px 1fr; gap:6px 12px; font-size:14px; }
  .kv b { color:#a8a8a8; font-weight:500; }
  .esim-card { border:1px solid #232323; border-radius:10px; padding:14px; margin-bottom:10px; background:#141414; }
  .esim-card .row { display:flex; gap:14px; align-items:flex-start; }
  .esim-card img.qr { width:160px;height:160px;border-radius:8px;background:#fff;border:1px solid #2a2a2a; }
  .install-row { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
  .lpa { font-family: ui-monospace, monospace; word-break: break-all; background:#1a1a1a; padding:6px 8px; border-radius:6px; font-size:12px; }
</style>

<div class="card">
  <h2>
    Đơn <span class="kbd"><?= htmlspecialchars($orderId) ?></span>
    <span class="tag <?= $statusCls ?>" style="margin-left:8px"><?= htmlspecialchars($statusLabel) ?></span>
    <a class="btn secondary" href="/ctv/orders.php" style="float:right">← Danh sách đơn</a>
  </h2>

  <div class="order-grid">
    <div>
      <h3>Thông tin đơn</h3>
      <div class="kv">
        <b>Gói</b><div><?= htmlspecialchars((string)$order['carrier'].' '.(string)$order['plan_name']) ?></div>
        <b>Mã gói</b><div><span class="kbd"><?= htmlspecialchars((string)$order['pack_code']) ?></span></div>
        <b>Số lượng</b><div><?= (int)$order['quantity'] ?></div>
        <b>Tạo lúc</b><div><?= htmlspecialchars((string)$order['created_at']) ?></div>
        <b>Cập nhật</b><div><?= htmlspecialchars((string)$order['updated_at']) ?></div>
        <b>Email khách</b><div><?= htmlspecialchars((string)($order['email'] ?? '—')) ?></div>
        <?php if (!empty($order['notes'])): ?><b>Ghi chú</b><div><?= htmlspecialchars((string)$order['notes']) ?></div><?php endif; ?>
        <?php if (!empty($order['client_ref'])): ?><b>Client ref</b><div><span class="kbd"><?= htmlspecialchars((string)$order['client_ref']) ?></span></div><?php endif; ?>
        <?php if (!empty($order['error_message'])): ?><b>Lỗi</b><div class="muted"><?= htmlspecialchars((string)$order['error_message']) ?></div><?php endif; ?>
      </div>
    </div>
    <div>
      <h3>Chi phí</h3>
      <div class="kv">
        <b>Giá lẻ</b><div><?= htmlspecialchars(format_vnd((int)$order['retail_price'])) ?> / eSIM</div>
        <b>Chiết khấu</b><div><?= htmlspecialchars(format_vnd((int)$order['discount'])) ?></div>
        <b>Giá CTV</b><div><b><?= htmlspecialchars(format_vnd((int)$order['ctv_price'])) ?></b> / eSIM</div>
        <b>Tổng phí</b><div><b style="color:#7ad27a"><?= htmlspecialchars(format_vnd((int)$order['total_charge'])) ?></b></div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <h2>eSIM (<?= count($esims) ?>)
    <a class="btn secondary" href="/ctv/orders/view.php?id=<?= htmlspecialchars($orderId) ?>" style="float:right">Refresh</a>
  </h2>
  <?php if (!$esims): ?>
    <?php if ((int)$order['status'] === 2): ?>
      <p class="muted">Đơn đã thành công, đang chờ nhà cung cấp phát hành QR. Hệ thống tự sync mỗi 2 phút — refresh sau ít phút.</p>
    <?php elseif ((int)$order['status'] === 3): ?>
      <p class="muted">Đơn thất bại. Vui lòng liên hệ admin nếu cần xử lý.</p>
    <?php else: ?>
      <p class="muted">Đơn đang xử lý.</p>
    <?php endif; ?>
  <?php else: foreach ($esims as $e):
    $iccidStr = (string)($e['iccid'] ?? '');
    $qr = $iccidStr !== '' ? ('/ctv/qr.php?id=' . urlencode($iccidStr)) : '';
    $lpa = (string)($e['ac'] ?? '');
    $iosUrl = $iccidStr !== '' ? ('/ctv/install.php?id=' . urlencode($iccidStr)) : '';
  ?>
  <div class="esim-card">
    <div class="row">
      <?php if ($qr !== ''): ?>
        <a href="<?= htmlspecialchars($qr) ?>" target="_blank" rel="noopener">
          <img class="qr" src="<?= htmlspecialchars($qr) ?>" alt="QR <?= htmlspecialchars((string)$e['iccid']) ?>">
        </a>
      <?php else: ?>
        <div class="qr" style="display:flex;align-items:center;justify-content:center;color:#666;background:#1a1a1a">QR chưa có</div>
      <?php endif; ?>
      <div style="flex:1">
        <div class="kv">
          <b>ICCID</b><div><span class="kbd copy" data-copy="<?= htmlspecialchars((string)$e['iccid']) ?>"><?= htmlspecialchars((string)$e['iccid']) ?></span></div>
          <b>Gói</b><div><?= htmlspecialchars((string)($e['package_name'] ?? $order['plan_name'])) ?></div>
          <b>Dung lượng</b><div><?= htmlspecialchars(_bytes_to_gb($e['total_volume'])) ?></div>
          <b>Thời hạn</b><div><?= (int)($e['total_duration'] ?? 0) ?> <?= htmlspecialchars((string)($e['duration_unit'] ?? 'DAY')) ?></div>
          <b>Hết hạn</b><div><?= htmlspecialchars((string)($e['expired_time'] ?? '—')) ?></div>
          <b>SMDP</b><div><?= htmlspecialchars((string)($e['smdp_status'] ?? '—')) ?></div>
          <b>eSIM status</b><div><?= htmlspecialchars((string)($e['esim_status'] ?? '—')) ?></div>
          <b>APN</b><div><span class="kbd"><?= htmlspecialchars((string)($e['apn'] ?? '')) ?></span></div>
          <?php if (!empty($e['email_sent_at'])): ?>
          <b>Email QR</b><div class="muted">đã gửi <?= htmlspecialchars((string)$e['email_sent_at']) ?></div>
          <?php elseif (!empty($e['email_last_error'])): ?>
          <b>Email QR</b><div class="muted">lỗi: <?= htmlspecialchars((string)$e['email_last_error']) ?> (đã thử <?= (int)($e['email_attempts'] ?? 0) ?> lần)</div>
          <?php else: ?>
          <b>Email QR</b><div class="muted">chưa gửi</div>
          <?php endif; ?>

        </div>
        <div class="install-row">
          <?php if ($iosUrl !== ''): ?>
            <a class="btn" href="<?= htmlspecialchars($iosUrl) ?>" target="_blank" rel="noopener">Cài trên iPhone (iOS 17.4+)</a>
          <?php endif; ?>
          <?php if ($qr !== ''): ?>
            <a class="btn secondary" href="<?= htmlspecialchars($qr) ?>" target="_blank" rel="noopener">Mở QR full-size</a>
          <?php endif; ?>
          <?php if ($topupLocked): ?>
            <button class="btn secondary" disabled title="TOPUP_LOCKED đang bật">Gia hạn (đang khoá)</button>
          <?php else: ?>
            <a class="btn secondary" href="/ctv/topup.php?iccid=<?= rawurlencode((string)$e['iccid']) ?>">Gia hạn</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>
<?php ctv_layout_footer();
