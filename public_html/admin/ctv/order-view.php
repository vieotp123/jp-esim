<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

$orderId = strtoupper(trim((string)($_GET['id'] ?? '')));
if ($orderId === '' || !preg_match('/^[A-Z0-9\-]{2,20}$/', $orderId)) {
    http_response_code(400);
    admin_layout_header('Đơn không hợp lệ', $admin);
    echo '<div class="card"><h2>Mã đơn không hợp lệ</h2><p><a class="btn secondary" href="/admin/ctv/orders.php">← Danh sách đơn</a></p></div>';
    admin_layout_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'sync_esim') {
            $r = (new CtvFulfillmentService())->syncOrderEsims($orderId);
            AuditLog::log($admin['user'], 'order_sync_esim', 'ctv_order', $orderId, ['result' => $r['status']]);
            admin_flash_set($r['status'] === 'ready' ? 'ok' : 'warn', 'Đồng bộ: ' . ($r['message'] ?? $r['status']));
        } elseif ($action === 'mark_resolved') {
            db()->prepare('UPDATE ctv_orders SET needs_admin=0 WHERE ctv_order_id=?')->execute([$orderId]);
            AuditLog::log($admin['user'], 'order_mark_resolved', 'ctv_order', $orderId);
            admin_flash_set('ok', 'Đã đánh dấu đã xử lý');
        } elseif ($action === 'refund') {
            $st = db()->prepare('SELECT * FROM ctv_orders WHERE ctv_order_id=? LIMIT 1');
            $st->execute([$orderId]);
            $row = $st->fetch();
            if (!$row || (int)$row['status'] !== 3) throw new RuntimeException('Chỉ hoàn tiền đơn thất bại');
            $ctvId = (int)$row['ctv_id'];
            $totalCharge = (int)$row['total_charge'];
            $note = trim((string)($_POST['note'] ?? '')) ?: 'Admin hoàn tiền bởi ' . $admin['user'];
            (new CtvWalletService())->credit($ctvId, $totalCharge, 'order_refund', 'ctv_order', $orderId, $note, $admin['user']);
            db()->prepare('UPDATE ctv_orders SET needs_admin=0 WHERE ctv_order_id=?')->execute([$orderId]);
            AuditLog::log($admin['user'], 'order_refund', 'ctv_order', $orderId, ['ctv_id' => $ctvId, 'amount' => $totalCharge, 'note' => $note]);
            (new CtvNotificationService())->create($ctvId, 'Đơn đã được hoàn tiền', 'Đơn ' . $orderId . ' được hoàn ' . format_vnd($totalCharge) . ' vào ví.', 'order');
            admin_flash_set('ok', 'Đã hoàn ' . format_vnd($totalCharge) . ' vào ví đối tác');
        } elseif ($action === 'retry_email') {
            $result = (new CtvMailService())->sendForOrderIfNeeded($orderId);
            AuditLog::log($admin['user'], 'email_retry', 'ctv_order', $orderId, $result);
            admin_flash_set($result['sent'] > 0 ? 'ok' : 'warn', $result['sent'] > 0 ? 'Đã gửi ' . $result['sent'] . ' email' : 'Không cần gửi lại');
        }
    } catch (Throwable $e) {
        admin_flash_set('err', 'Lỗi: ' . $e->getMessage());
    }
    admin_redirect_self();
}

$st = db()->prepare('SELECT o.*, u.email AS ctv_email, u.display_name AS company_name, u.display_name FROM ctv_orders o LEFT JOIN ctv_users u ON u.id=o.ctv_id WHERE o.ctv_order_id=? LIMIT 1');
$st->execute([$orderId]);
$order = $st->fetch();
if (!$order) {
    http_response_code(404);
    admin_layout_header('Không tìm thấy đơn', $admin);
    echo '<div class="card"><h2>Không tìm thấy đơn ' . htmlspecialchars($orderId) . '</h2><p><a class="btn secondary" href="/admin/ctv/orders.php">← Danh sách đơn</a></p></div>';
    admin_layout_footer();
    exit;
}

$esims = db()->prepare('SELECT * FROM ctv_esims WHERE ctv_order_id=? ORDER BY id ASC');
$esims->execute([$orderId]);
$esims = $esims->fetchAll();

$statusMap = [0 => ['Chờ xử lý', 'warn'], 1 => ['Đang xử lý', 'warn'], 2 => ['Thành công', 'ok'], 3 => ['Thất bại', 'err']];
[$statusLabel, $statusCls] = $statusMap[(int)$order['status']] ?? ['Không rõ', ''];

$planStmt = db()->prepare('SELECT day FROM plan WHERE id=? LIMIT 1');
$planStmt->execute([(int)$order['plan_id']]);
$planDays = (int)($planStmt->fetchColumn() ?: 0);

function admin_ov_plan_data(string $plan): string {
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(GB|MB)\b/i', $plan, $m)) {
        return str_replace(',', '.', $m[1]) . ' ' . strtoupper($m[2]);
    }
    return 'Data';
}
$orderPlanLabel = trim((string)$order['carrier'] . ' · ' . admin_ov_plan_data((string)$order['plan_name']) . ($planDays > 0 ? (' · ' . $planDays . ' ngày') : ''));

admin_layout_header('Đơn ' . $orderId, $admin);
?>
<?php admin_flash_render(); ?>
<style>
.ov-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px}
.ov-kv{display:grid;grid-template-columns:130px 1fr;gap:6px 12px;font-size:14px}
.ov-kv b{color:var(--a-muted);font-weight:500}
.esim-row{border:1px solid var(--a-line);border-radius:8px;padding:14px;margin-bottom:10px;background:var(--a-surface)}
.esim-row .kv-inline{display:grid;grid-template-columns:110px 1fr;gap:4px 10px;font-size:13px}
.esim-row .kv-inline b{color:var(--a-muted);font-weight:500}
.admin-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
@media(max-width:760px){.ov-grid{grid-template-columns:1fr} .ov-kv{grid-template-columns:110px 1fr;font-size:13px}}
</style>

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <h2 style="margin:0">Đơn <span class="kbd"><?= htmlspecialchars($orderId) ?></span> <span class="tag <?= $statusCls ?>"><?= htmlspecialchars($statusLabel) ?></span></h2>
      <?php if ((int)$order['needs_admin']): ?><span class="tag err" style="margin-top:4px">Cần xử lý</span><?php endif; ?>
    </div>
    <a class="btn secondary" href="/admin/ctv/orders.php">← Danh sách đơn</a>
  </div>

  <div class="ov-grid">
    <div>
      <h3>Thông tin đơn</h3>
      <div class="ov-kv">
        <b>Gói</b><div><?= htmlspecialchars($orderPlanLabel) ?></div>
        <b>Mã gói</b><div><span class="kbd"><?= htmlspecialchars((string)($order['pack_code'] ?? '')) ?></span></div>
        <b>Số lượng</b><div><?= (int)$order['quantity'] ?><?php $qty=(int)$order['quantity']; $pc=count($esims); if($qty>1): ?> <span class="tag <?= $pc>=$qty?'ok':($pc>0?'warn':'') ?>"><?= $pc ?>/<?= $qty ?> eSIM</span><?php endif; ?></div>
        <b>Tạo lúc</b><div><?= htmlspecialchars((string)$order['created_at']) ?></div>
        <b>Cập nhật</b><div><?= htmlspecialchars((string)$order['updated_at']) ?></div>
        <b>Email khách</b><div><?= htmlspecialchars((string)($order['email'] ?? '—')) ?></div>
        <?php if (!empty($order['client_ref'])): ?><b>Client ref</b><div><span class="kbd"><?= htmlspecialchars((string)$order['client_ref']) ?></span></div><?php endif; ?>
        <?php if (!empty($order['notes'])): ?><b>Ghi chú</b><div><?= htmlspecialchars((string)$order['notes']) ?></div><?php endif; ?>
        <?php if (!empty($order['error_message'])): ?><b>Lỗi</b><div style="color:var(--a-red)"><?= htmlspecialchars((string)$order['error_message']) ?></div><?php endif; ?>
        <?php if (!empty($order['provider_order_no'])): ?><b>Provider #</b><div class="muted"><?= htmlspecialchars((string)$order['provider_order_no']) ?></div><?php endif; ?>
        <?php if (!empty($order['provider_transaction_id'])): ?><b>Provider TX</b><div class="muted"><?= htmlspecialchars((string)$order['provider_transaction_id']) ?></div><?php endif; ?>
      </div>
    </div>
    <div>
      <h3>Đối tác & Chi phí</h3>
      <div class="ov-kv">
        <b>Đối tác</b><div><a href="/admin/ctv/view.php?id=<?= (int)$order['ctv_id'] ?>">#<?= (int)$order['ctv_id'] ?> <?= htmlspecialchars((string)($order['ctv_email'] ?? '')) ?></a></div>
        <?php if (!empty($order['company_name'])): ?><b>Công ty</b><div><?= htmlspecialchars((string)$order['company_name']) ?></div><?php endif; ?>
        <b>Giá lẻ</b><div><?= htmlspecialchars(format_vnd((int)$order['retail_price'])) ?> / eSIM</div>
        <b>Chiết khấu</b><div><?= htmlspecialchars(format_vnd((int)$order['discount'])) ?></div>
        <b>Giá đối tác</b><div><strong><?= htmlspecialchars(format_vnd((int)$order['ctv_price'])) ?></strong> / eSIM</div>
        <b>Tổng phí</b><div><strong style="color:var(--a-green)"><?= htmlspecialchars(format_vnd((int)$order['total_charge'])) ?></strong></div>
      </div>
    </div>
  </div>

  <div class="admin-actions">
    <?php if ((int)$order['status'] === 3): ?>
    <form method="post" onsubmit="return confirm('Hoàn <?= htmlspecialchars(format_vnd((int)$order['total_charge']), ENT_QUOTES) ?> vào ví đối tác?')">
      <?php admin_csrf_field(); ?>
      <input type="hidden" name="action" value="refund">
      <input type="text" name="note" placeholder="Ghi chú (tuỳ chọn)" style="width:200px">
      <button class="btn gold" type="submit">Hoàn tiền</button>
    </form>
    <?php endif; ?>
    <?php if ((int)$order['status'] === 2 && count($esims) < (int)$order['quantity']): ?>
    <form method="post" title="Buộc poll provider ngay, không chờ lịch 2 phút">
      <?php admin_csrf_field(); ?>
      <input type="hidden" name="action" value="sync_esim">
      <button class="btn primary" type="submit">Đồng bộ ngay</button>
    </form>
    <?php endif; ?>
    <?php if ((int)$order['needs_admin']): ?>
    <form method="post">
      <?php admin_csrf_field(); ?>
      <input type="hidden" name="action" value="mark_resolved">
      <button class="btn secondary" type="submit">Đánh dấu đã xử lý</button>
    </form>
    <?php endif; ?>
    <?php if ($esims): ?>
    <form method="post">
      <?php admin_csrf_field(); ?>
      <input type="hidden" name="action" value="retry_email">
      <button class="btn secondary" type="submit">Gửi lại email QR</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>eSIM (<?= count($esims) ?><?php if((int)$order['quantity']>1): ?>/<?= (int)$order['quantity'] ?><?php endif; ?>)</h2>
  <?php if (!$esims): ?>
    <?php if ((int)$order['status'] === 2): ?>
      <p class="muted">Đơn thành công, chờ provider phát hành QR.</p>
    <?php elseif ((int)$order['status'] === 3): ?>
      <p class="muted">Đơn thất bại — không có eSIM.</p>
    <?php else: ?>
      <p class="muted">Đơn đang xử lý.</p>
    <?php endif; ?>
  <?php else: foreach ($esims as $e): ?>
  <div class="esim-row">
    <div class="kv-inline">
      <b>ICCID</b><div><span class="kbd"><?= htmlspecialchars((string)$e['iccid']) ?></span></div>
      <b>Nhà mạng</b><div><?= htmlspecialchars((string)($e['carrier'] ?? $order['carrier'] ?? '')) ?></div>
      <b>Dung lượng</b><div><?= (int)$e['total_volume'] > 0 ? number_format((int)$e['total_volume'] / 1073741824, 2) . ' GB' : '—' ?></div>
      <b>Thời hạn</b><div><?= (int)($e['total_duration'] ?? 0) ?> <?= htmlspecialchars((string)($e['duration_unit'] ?? 'DAY')) ?></div>
      <b>Hết hạn</b><div><?= htmlspecialchars((string)($e['expired_time'] ?? '—')) ?></div>
      <b>SMDP</b><div><?= htmlspecialchars((string)($e['smdp_status'] ?? '—')) ?></div>
      <b>eSIM status</b><div><?= htmlspecialchars((string)($e['esim_status'] ?? '—')) ?></div>
      <b>APN</b><div><?= htmlspecialchars((string)($e['apn'] ?? '—')) ?></div>
      <b>Email</b><div><?php if (!empty($e['email_sent_at'])): ?>đã gửi <?= htmlspecialchars((string)$e['email_sent_at']) ?><?php elseif (!empty($e['email_last_error'])): ?><span style="color:var(--a-red)">lỗi: <?= htmlspecialchars(mb_strimwidth((string)$e['email_last_error'], 0, 80, '…')) ?> (<?= (int)($e['email_attempts'] ?? 0) ?> lần)</span><?php else: ?>chưa gửi<?php endif; ?></div>
      <?php if (!empty($e['ac'])): ?><b>LPA</b><div class="muted" style="font-family:ui-monospace,monospace;font-size:11px;word-break:break-all"><?= htmlspecialchars((string)$e['ac']) ?></div><?php endif; ?>
    </div>
  </div>
  <?php endforeach; endif; ?>
</div>

<?php
$plog = db()->prepare('SELECT created_at, endpoint, http_status, success, error_message, duration_ms FROM ctv_provider_logs WHERE ref_id=? ORDER BY id DESC LIMIT 10');
$plog->execute([$orderId]);
$plog = $plog->fetchAll();
?>
<div class="card">
  <h2>Hoạt động provider <span class="muted" style="font-weight:400;font-size:13px">(10 lần gần nhất)</span></h2>
  <?php if (!$plog): ?>
    <p class="muted">Chưa có log provider cho đơn này.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Thời gian</th><th>Endpoint</th><th>HTTP</th><th>Kết quả</th><th>Thời gian (ms)</th><th>Lỗi</th></tr></thead>
    <tbody>
    <?php foreach ($plog as $r): ?>
      <tr>
        <td><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
        <td><span class="kbd"><?= htmlspecialchars((string)$r['endpoint']) ?></span></td>
        <td><?= (int)$r['http_status'] ?: '—' ?></td>
        <td><?= (int)$r['success'] === 1 ? '<span class="tag ok">OK</span>' : '<span class="tag err">FAIL</span>' ?></td>
        <td><?= (int)$r['duration_ms'] ?></td>
        <td><?php if (!empty($r['error_message'])): ?><span style="color:var(--a-red)"><?= htmlspecialchars(mb_strimwidth((string)$r['error_message'], 0, 100, '…')) ?></span><?php else: ?>—<?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
