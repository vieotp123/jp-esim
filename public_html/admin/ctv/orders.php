<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $orderId = (string)($_POST['order_id'] ?? '');
    try {
        if ($action === 'mark_resolved') {
            db()->prepare('UPDATE ctv_orders SET needs_admin=0 WHERE ctv_order_id=?')->execute([$orderId]);
            $flash = ['ok', 'Đã đánh dấu đã xử lý ' . $orderId];
        } elseif ($action === 'retry') {
            // Retry logic: only valid for failed orders. Re-call provider with fresh transactionId.
            $st = db()->prepare('SELECT * FROM ctv_orders WHERE ctv_order_id=? LIMIT 1');
            $st->execute([$orderId]);
            $row = $st->fetch();
            if (!$row || (int)$row['status'] !== 3) throw new RuntimeException('Đơn không ở trạng thái failed');
            $ctvId = (int)$row['ctv_id'];
            $totalCharge = (int)$row['total_charge'];
            // Reserve again (debit)
            (new CtvWalletService())->debit($ctvId, $totalCharge, 'order_retry', 'ctv_order', $orderId, 'Admin retry');
            db()->prepare('UPDATE ctv_orders SET status=1, needs_admin=0, error_message=NULL, updated_at=NOW() WHERE ctv_order_id=?')->execute([$orderId]);
            try {
                $resp = (new CtvProviderClient())->createOrder($ctvId, $orderId, (string)$row['pack_code'], $orderId);
                if (!empty($resp['success'])) {
                    db()->prepare('UPDATE ctv_orders SET status=2, provider_order_no=?, provider_transaction_id=?, updated_at=NOW() WHERE ctv_order_id=?')
                        ->execute([(string)($resp['obj']['orderNo'] ?? ''), (string)($resp['obj']['transactionId'] ?? $orderId), $orderId]);
                    $flash = ['ok', 'Retry thành công ' . $orderId];
                } else {
                    $err = (string)($resp['errorMsg'] ?? 'Provider failed');
                    db()->prepare('UPDATE ctv_orders SET status=3, needs_admin=1, error_message=? WHERE ctv_order_id=?')
                        ->execute([substr($err, 0, 500), $orderId]);
                    (new CtvWalletService())->credit($ctvId, $totalCharge, 'order_refund', 'ctv_order', $orderId, 'Retry refund');
                    $flash = ['err', 'Retry vẫn thất bại: ' . $err];
                }
            } catch (Throwable $e) {
                db()->prepare('UPDATE ctv_orders SET status=3, needs_admin=1, error_message=? WHERE ctv_order_id=?')
                    ->execute([substr($e->getMessage(), 0, 500), $orderId]);
                (new CtvWalletService())->credit($ctvId, $totalCharge, 'order_refund', 'ctv_order', $orderId, 'Retry refund');
                throw $e;
            }
        }
    } catch (Throwable $e) { $flash = ['err', 'Lỗi: ' . $e->getMessage()]; }
}

$onlyFailed = !empty($_GET['failed']);
$where = $onlyFailed ? 'WHERE o.needs_admin=1 OR o.status=3' : 'WHERE 1';
$rows = db()->query("SELECT o.*, u.email AS ctv_email FROM ctv_orders o LEFT JOIN ctv_users u ON u.id=o.ctv_id $where ORDER BY o.id DESC LIMIT 200")->fetchAll();

admin_layout_header('Đơn CTV', $admin);
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>
<div class="card">
  <h2>Đơn CTV (<?= count($rows) ?>)
    <a href="?failed=1" class="btn danger" style="float:right">Chỉ xem cần xử lý</a>
    <a href="?" class="btn secondary" style="float:right;margin-right:6px">Tất cả</a>
  </h2>
  <table>
    <thead><tr><th>Mã</th><th>CTV</th><th>Gói</th><th>Phí</th><th>Trạng thái</th><th>Lỗi</th><th>Tạo lúc</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $statusMap=[0=>'pending',1=>'processing',2=>'success',3=>'failed']; ?>
      <tr>
        <td><span class="kbd"><?= htmlspecialchars((string)$r['ctv_order_id']) ?></span></td>
        <td><?= htmlspecialchars((string)$r['ctv_email']) ?></td>
        <td><?= htmlspecialchars($r['carrier'].' '.$r['plan_name']) ?> ×<?= (int)$r['quantity'] ?></td>
        <td><?= htmlspecialchars(format_vnd((int)$r['total_charge'])) ?></td>
        <td><?= $statusMap[(int)$r['status']] ?? '?' ?> <?php if ((int)$r['needs_admin']): ?>(cần admin)<?php endif; ?></td>
        <td><?= htmlspecialchars((string)($r['error_message'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
        <td>
          <?php if ((int)$r['status']===3): ?>
          <form method="post" class="inline">
            <input type="hidden" name="action" value="retry"><input type="hidden" name="order_id" value="<?= htmlspecialchars((string)$r['ctv_order_id']) ?>">
            <button class="btn" type="submit">Retry</button>
          </form>
          <?php endif; ?>
          <?php if ((int)$r['needs_admin']===1): ?>
          <form method="post" class="inline">
            <input type="hidden" name="action" value="mark_resolved"><input type="hidden" name="order_id" value="<?= htmlspecialchars((string)$r['ctv_order_id']) ?>">
            <button class="btn secondary" type="submit">Đã xử lý</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_layout_footer();
