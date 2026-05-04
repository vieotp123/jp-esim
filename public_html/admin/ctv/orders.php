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
                $flash = [$r['status']==='ready'?'ok':'err', 'Đồng bộ '.$orderId.': '.$r['status'].' - '.($r['message'] ?? '')];
            } elseif ($action === 'mark_resolved') {
                db()->prepare('UPDATE ctv_orders SET needs_admin=0 WHERE ctv_order_id=?')->execute([$orderId]);
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
                    $resp = (new CtvProviderClient())->createOrder($ctvId, $orderId, (string)$row['pack_code'], $orderId);
                    if (!empty($resp['success'])) {
                        db()->prepare('UPDATE ctv_orders SET status=2, provider_order_no=?, provider_transaction_id=?, updated_at=NOW() WHERE ctv_order_id=?')
                            ->execute([(string)($resp['obj']['orderNo'] ?? ''), (string)($resp['obj']['transactionId'] ?? $orderId), $orderId]);
                        $flash = ['ok', 'Thử lại thành công ' . $orderId];
                    } else {
                        $err = (string)($resp['errorMsg'] ?? 'Xử lý thất bại');
                        db()->prepare('UPDATE ctv_orders SET status=3, needs_admin=1, error_message=? WHERE ctv_order_id=?')
                            ->execute([mb_substr($err, 0, 500), $orderId]);
                        (new CtvWalletService())->credit($ctvId, $totalCharge, 'order_refund', 'ctv_order', $orderId, 'Hoàn tiền thử lại bởi ' . $admin['user'], $admin['user']);
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
$where = $onlyFailed ? 'WHERE o.needs_admin=1 OR o.status=3' : 'WHERE 1';
$rows = db()->query("SELECT o.*, u.email AS ctv_email FROM ctv_orders o LEFT JOIN ctv_users u ON u.id=o.ctv_id $where ORDER BY o.id DESC LIMIT 200")->fetchAll();
$counts = db()->query('SELECT SUM(needs_admin=1) needs, SUM(status=3) failed, COUNT(*) total FROM ctv_orders')->fetch();

admin_layout_header('Đơn CTV', $admin);
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>
<div class="summary">
  <div class="card"><b>Tổng đơn</b><h2><?= (int)($counts['total'] ?? 0) ?></h2></div>
  <div class="card"><b>Thất bại</b><h2><?= (int)($counts['failed'] ?? 0) ?></h2></div>
  <div class="card"><b>Cần xử lý</b><h2><?= (int)($counts['needs'] ?? 0) ?></h2></div>
</div>
<div class="card">
  <h2>Đơn CTV (<?= count($rows) ?>)
    <a href="?failed=1" class="btn <?= $onlyFailed?'danger':'secondary' ?>" style="float:right">Cần xử lý</a>
    <a href="?" class="btn <?= !$onlyFailed?'':'secondary' ?>" style="float:right;margin-right:6px">Tất cả</a>
  </h2>
  <?php if (!$rows): ?>
    <div class="empty"><div class="icon">📋</div><p>Không có đơn nào.</p></div>
  <?php else: ?>
  <table>
    <thead><tr><th>Mã</th><th>CTV</th><th>Gói</th><th>Phí</th><th>Trạng thái</th><th>Lỗi</th><th>Tạo lúc</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $statusMap=[0=>'Chờ',1=>'Đang xử lý',2=>'Thành công',3=>'Thất bại'];
      $statusCls=[0=>'',1=>'warn',2=>'ok',3=>'err'];
      $st=(int)$r['status'];
      $oid=(string)$r['ctv_order_id'];
    ?>
      <tr>
        <td><span class="kbd"><?= htmlspecialchars($oid) ?></span></td>
        <td><?= htmlspecialchars((string)($r['ctv_email'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)$r['carrier'].' '.(string)$r['plan_name']) ?> ×<?= (int)$r['quantity'] ?></td>
        <td><?= htmlspecialchars(format_vnd((int)$r['total_charge'])) ?></td>
        <td><span class="tag <?= $statusCls[$st] ?? '' ?>"><?= $statusMap[$st] ?? '?' ?></span><?php if ((int)$r['needs_admin']): ?> <span class="tag err">Cần xử lý</span><?php endif; ?></td>
        <td style="max-width:240px;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['error_message'] ?? ''), 0, 220, '…')) ?></td>
        <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
        <td>
          <?php if ($st===3): ?>
          <form method="post" class="inline" onsubmit="return confirm('Xác nhận thử lại đơn <?= htmlspecialchars($oid, ENT_QUOTES) ?>? Số dư CTV sẽ bị trừ lại trước khi xử lý.');">
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
  <?php endif; ?>
</div>
<?php admin_layout_footer();
