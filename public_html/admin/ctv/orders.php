<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $orderId = trim((string)($_POST['order_id'] ?? ''));
    if ($orderId === '' || strlen($orderId) > 64) {
        $flash = ['err', 'Mã đơn không hợp lệ'];
    } else {
        try {
            if ($action === 'sync_esim') {
                $r = (new CtvFulfillmentService())->syncOrderEsims($orderId);
                AuditLog::log($admin['user'], 'order_sync_esim', 'ctv_order', $orderId, ['result' => $r['status']]);
                $flash = [$r['status']==='ready'?'ok':'err', 'Đồng bộ '.$orderId.': '.$r['status'].' - '.($r['message'] ?? '')];
            } elseif ($action === 'mark_resolved') {
                db()->prepare('UPDATE ctv_orders SET needs_admin=0 WHERE ctv_order_id=?')->execute([$orderId]);
                AuditLog::log($admin['user'], 'order_mark_resolved', 'ctv_order', $orderId);
                $flash = ['ok', 'Đã đánh dấu đã xử lý ' . $orderId];
            } elseif ($action === 'retry') {
                $st = db()->prepare('SELECT * FROM ctv_orders WHERE ctv_order_id=? LIMIT 1');
                $st->execute([$orderId]);
                $row = $st->fetch();
                if (!$row || (int)$row['status'] !== 3) throw new RuntimeException('Đơn không ở trạng thái Thất bại');
                $ctvId = (int)$row['ctv_id'];
                $totalCharge = (int)$row['total_charge'];
                $adminNote = 'Admin thử lại bởi ' . $admin['user'];
                (new CtvWalletService())->debit($ctvId, $totalCharge, 'order_retry', 'ctv_order', $orderId, $adminNote, $admin['user']);
                db()->prepare('UPDATE ctv_orders SET status=1, needs_admin=0, error_message=NULL, updated_at=NOW() WHERE ctv_order_id=?')->execute([$orderId]);
                try {
                    $resp = (new CtvProviderClient())->createOrder($ctvId, $orderId, (string)$row['pack_code'], $orderId, (int)$row['quantity']);
                    if (!empty($resp['success'])) {
                        db()->prepare('UPDATE ctv_orders SET status=2, provider_order_no=?, provider_transaction_id=?, updated_at=NOW() WHERE ctv_order_id=?')
                            ->execute([(string)($resp['obj']['orderNo'] ?? ''), (string)($resp['obj']['transactionId'] ?? $orderId), $orderId]);
                        AuditLog::log($admin['user'], 'order_retry_success', 'ctv_order', $orderId, ['ctv_id' => $ctvId, 'charge' => $totalCharge]);
                        $flash = ['ok', 'Thử lại thành công ' . $orderId];
                    } else {
                        $err = (string)($resp['errorMsg'] ?? 'Xử lý thất bại');
                        db()->prepare('UPDATE ctv_orders SET status=3, needs_admin=1, error_message=? WHERE ctv_order_id=?')
                            ->execute([mb_substr($err, 0, 500), $orderId]);
                        (new CtvWalletService())->credit($ctvId, $totalCharge, 'order_refund', 'ctv_order', $orderId, 'Hoàn tiền thử lại bởi ' . $admin['user'], $admin['user']);
                        AuditLog::log($admin['user'], 'order_retry_failed', 'ctv_order', $orderId, ['ctv_id' => $ctvId, 'error' => mb_substr($err, 0, 200)]);
                        $flash = ['err', 'Thử lại vẫn thất bại: ' . $err];
                    }
                } catch (Throwable $e) {
                    db()->prepare('UPDATE ctv_orders SET status=3, needs_admin=1, error_message=? WHERE ctv_order_id=?')
                        ->execute([mb_substr($e->getMessage(), 0, 500), $orderId]);
                    (new CtvWalletService())->credit($ctvId, $totalCharge, 'order_refund', 'ctv_order', $orderId, 'Hoàn tiền thử lại bởi ' . $admin['user'], $admin['user']);
                    throw $e;
                }
            }
        } catch (Throwable $e) { $flash = ['err', 'Lỗi: ' . $e->getMessage()]; }
    }
}

$onlyFailed = !empty($_GET['failed']);
$q = trim((string)($_GET['q'] ?? ''));
$where = $onlyFailed ? 'WHERE (o.needs_admin=1 OR o.status=3)' : 'WHERE 1';
$params = [];
if ($q !== '') {
    $where .= ' AND (o.ctv_order_id LIKE ? OR u.email LIKE ? OR o.client_ref LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
}
$st = db()->prepare("SELECT o.*, u.email AS ctv_email, (SELECT COUNT(*) FROM ctv_esims e WHERE e.ctv_order_id=o.ctv_order_id) AS esim_count FROM ctv_orders o LEFT JOIN ctv_users u ON u.id=o.ctv_id $where ORDER BY o.id DESC LIMIT 200");
$st->execute($params);
$rows = $st->fetchAll();
$counts = db()->query('SELECT SUM(needs_admin=1) needs, SUM(status=3) failed, COUNT(*) total FROM ctv_orders')->fetch();

function admin_orders_plan_data(string $plan): string {
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(GB|MB)\b/i', $plan, $m)) {
        return str_replace(',', '.', $m[1]) . ' ' . strtoupper($m[2]);
    }
    return 'Data';
}

function admin_orders_plan_label(array $r): string {
    $parts = [];
    $carrier = trim((string)($r['carrier'] ?? ''));
    if ($carrier !== '') $parts[] = $carrier;
    $parts[] = admin_orders_plan_data((string)($r['plan_name'] ?? ''));
    return implode(' · ', $parts);
}

admin_layout_header('Đơn đối tác', $admin);
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>
<div class="summary">
  <div class="card"><b>Tổng đơn</b><h2><?= (int)($counts['total'] ?? 0) ?></h2></div>
  <div class="card"><b>Thất bại</b><h2><?= (int)($counts['failed'] ?? 0) ?></h2></div>
  <div class="card"><b>Cần xử lý</b><h2><?= (int)($counts['needs'] ?? 0) ?></h2></div>
</div>
<div class="card">
  <h2>Đơn đối tác (<?= count($rows) ?>)</h2>
  <form method="get" class="toolbar" style="margin-bottom:12px">
    <?php if ($onlyFailed): ?><input type="hidden" name="failed" value="1"><?php endif; ?>
    <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Tìm mã đơn, email đối tác, client ref..." style="flex:1;min-width:180px">
    <button class="btn">Tìm</button>
    <?php if ($q !== ''): ?><a class="btn secondary" href="?<?= $onlyFailed ? 'failed=1' : '' ?>">Xoá tìm</a><?php endif; ?>
  </form>
  <div class="filter-row">
    <a href="?<?= $q ? 'q='.htmlspecialchars(urlencode($q)) : '' ?>" class="pill <?= !$onlyFailed?'active':'' ?>">Tất cả</a>
    <a href="?failed=1<?= $q ? '&q='.htmlspecialchars(urlencode($q)) : '' ?>" class="pill <?= $onlyFailed?'active':'' ?>">Cần xử lý</a>
  </div>
  <?php if (!$rows): ?>
    <div class="empty"><div class="icon">📋</div><p>Không có đơn nào<?= $onlyFailed ? ' cần xử lý' : '' ?>.</p></div>
  <?php else: ?>
  <div class="m-cards">
    <?php foreach ($rows as $r):
      $statusMap=[0=>'Chờ',1=>'Đang xử lý',2=>'Thành công',3=>'Thất bại'];
      $statusCls=[0=>'',1=>'warn',2=>'ok',3=>'err'];
      $st=(int)$r['status'];
      $oid=(string)$r['ctv_order_id'];
      $qty=(int)$r['quantity'];
      $pc=(int)$r['esim_count'];
    ?>
    <div class="m-card">
      <div class="m-head">
        <a class="kbd" href="/admin/ctv/order-view.php?id=<?= htmlspecialchars(urlencode($oid)) ?>" style="text-decoration:none"><?= htmlspecialchars($oid) ?></a>
        <span class="tag <?= $statusCls[$st] ?? '' ?>"><?= $statusMap[$st] ?? '?' ?></span>
      </div>
      <div class="m-row"><span class="m-label">Đối tác</span><span class="m-val"><?= htmlspecialchars((string)($r['ctv_email'] ?? '')) ?></span></div>
      <div class="m-row"><span class="m-label">Gói</span><span class="m-val"><?= htmlspecialchars(admin_orders_plan_label($r)) ?> ×<?= $qty ?><?php if($qty>1): ?> <span class="tag <?= $pc>=$qty?'ok':($pc>0?'warn':'') ?>" style="font-size:10px"><?= $pc ?>/<?= $qty ?></span><?php endif; ?></span></div>
      <div class="m-row"><span class="m-label">Phí</span><span class="m-val" style="font-weight:700"><?= htmlspecialchars(format_vnd((int)$r['total_charge'])) ?></span></div>
      <div class="m-row"><span class="m-label">Tạo lúc</span><span class="m-val muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></div>
      <?php if ((int)$r['needs_admin']): ?><div style="margin-top:4px"><span class="tag err">Cần xử lý</span></div><?php endif; ?>
      <?php if (!empty($r['error_message'])): ?><div style="font-size:12px;color:var(--a-muted);margin-top:4px">⚠ <?= htmlspecialchars(mb_strimwidth((string)$r['error_message'], 0, 120, '…')) ?></div><?php endif; ?>
      <div class="m-actions">
        <?php if ($st===3): ?>
        <form method="post" style="flex:1" onsubmit="return confirm('Xác nhận thử lại đơn <?= htmlspecialchars($oid, ENT_QUOTES) ?>?');">
          <?php admin_csrf_field(); ?>
          <input type="hidden" name="action" value="retry">
          <input type="hidden" name="order_id" value="<?= htmlspecialchars($oid, ENT_QUOTES) ?>">
          <button class="btn sm" type="submit" style="width:100%">Thử lại</button>
        </form>
        <?php endif; ?>
        <?php if ($st===2 && empty($r['iccid'])): ?>
        <form method="post" style="flex:1">
          <?php admin_csrf_field(); ?>
          <input type="hidden" name="action" value="sync_esim">
          <input type="hidden" name="order_id" value="<?= htmlspecialchars($oid, ENT_QUOTES) ?>">
          <button class="btn sm secondary" type="submit" style="width:100%">Đồng bộ eSIM</button>
        </form>
        <?php endif; ?>
        <?php if ((int)$r['needs_admin']===1): ?>
        <form method="post" style="flex:1">
          <?php admin_csrf_field(); ?>
          <input type="hidden" name="action" value="mark_resolved">
          <input type="hidden" name="order_id" value="<?= htmlspecialchars($oid, ENT_QUOTES) ?>">
          <button class="btn sm secondary" type="submit" style="width:100%">Đã xử lý</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Mã</th><th>Đối tác</th><th>Gói</th><th>Phí</th><th>Trạng thái</th><th>Lỗi</th><th>Tạo lúc</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $statusMap=[0=>'Chờ',1=>'Đang xử lý',2=>'Thành công',3=>'Thất bại'];
      $statusCls=[0=>'',1=>'warn',2=>'ok',3=>'err'];
      $st=(int)$r['status'];
      $oid=(string)$r['ctv_order_id'];
    ?>
      <tr>
        <td><a class="kbd" href="/admin/ctv/order-view.php?id=<?= htmlspecialchars(urlencode($oid)) ?>" style="text-decoration:none"><?= htmlspecialchars($oid) ?></a></td>
        <td><?= htmlspecialchars((string)($r['ctv_email'] ?? '')) ?></td>
        <td><?= htmlspecialchars(admin_orders_plan_label($r)) ?> ×<?= (int)$r['quantity'] ?><?php
          $qty=(int)$r['quantity']; $pc=(int)$r['esim_count'];
          if($qty>1): ?> <span class="tag <?= $pc>=$qty?'ok':($pc>0?'warn':'') ?>" style="font-size:11px"><?= $pc ?>/<?= $qty ?></span><?php endif;
        ?></td>
        <td><?= htmlspecialchars(format_vnd((int)$r['total_charge'])) ?></td>
        <td><span class="tag <?= $statusCls[$st] ?? '' ?>"><?= $statusMap[$st] ?? '?' ?></span><?php if ((int)$r['needs_admin']): ?> <span class="tag err">Cần xử lý</span><?php endif; ?></td>
        <td style="max-width:240px;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['error_message'] ?? ''), 0, 220, '…')) ?></td>
        <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
        <td>
          <?php if ($st===3): ?>
          <form method="post" class="inline" onsubmit="return confirm('Xác nhận thử lại đơn <?= htmlspecialchars($oid, ENT_QUOTES) ?>? Số dư đối tác sẽ bị trừ lại trước khi xử lý.');">
            <?php admin_csrf_field(); ?>
            <input type="hidden" name="action" value="retry">
            <input type="hidden" name="order_id" value="<?= htmlspecialchars($oid, ENT_QUOTES) ?>">
            <button class="btn" type="submit">Thử lại</button>
          </form>
          <?php endif; ?>
          <?php if ($st===2 && empty($r['iccid'])): ?>
          <form method="post" class="inline">
            <?php admin_csrf_field(); ?>
            <input type="hidden" name="action" value="sync_esim">
            <input type="hidden" name="order_id" value="<?= htmlspecialchars($oid, ENT_QUOTES) ?>">
            <button class="btn secondary" type="submit" title="Lấy QR/ICCID từ nhà cung cấp">Đồng bộ eSIM</button>
          </form>
          <?php endif; ?>
          <?php if ((int)$r['needs_admin']===1): ?>
          <form method="post" class="inline">
            <?php admin_csrf_field(); ?>
            <input type="hidden" name="action" value="mark_resolved">
            <input type="hidden" name="order_id" value="<?= htmlspecialchars($oid, ENT_QUOTES) ?>">
            <button class="btn secondary" type="submit">Đã xử lý</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
