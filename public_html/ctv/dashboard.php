<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$wallet = new CtvWalletService();
$bal = $wallet->balance((int)$user['id']);
$user['balance'] = $bal;

$pdo = db();
$st = $pdo->prepare('SELECT COUNT(*) FROM ctv_orders WHERE ctv_id=?'); $st->execute([(int)$user['id']]); $totalOrders = (int)$st->fetchColumn();
$st = $pdo->prepare("SELECT COUNT(*) FROM ctv_orders WHERE ctv_id=? AND status=2"); $st->execute([(int)$user['id']]); $okOrders = (int)$st->fetchColumn();
$st = $pdo->prepare("SELECT COUNT(*) FROM ctv_orders WHERE ctv_id=? AND status=3"); $st->execute([(int)$user['id']]); $failOrders = (int)$st->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(*) FROM ctv_topup_orders WHERE ctv_id=?'); $st->execute([(int)$user['id']]); $totalTopups = (int)$st->fetchColumn();
$tx = $wallet->transactions((int)$user['id'], 10);

ctv_layout_header('Tổng quan', $user);
ctv_flash_render();
?>
<div class="row">
  <div class="card">
    <h2>Số dư ví</h2>
    <div style="font-size:28px;font-weight:700;"><?= htmlspecialchars(format_vnd($bal)) ?></div>
    <p class="muted">Liên hệ admin để nạp số dư hoặc điều chỉnh chiết khấu.</p>
  </div>
  <div class="card">
    <h2>Đơn eSIM</h2>
    <p>Tổng: <strong><?= $totalOrders ?></strong> · Thành công: <strong><?= $okOrders ?></strong> · Thất bại: <strong style="color:#dc2626"><?= $failOrders ?></strong></p>
    <p>Đơn nạp data: <strong><?= $totalTopups ?></strong></p>
    <a class="btn" href="/ctv/create-esim.php">Tạo eSIM mới</a>
    <a class="btn secondary" href="/ctv/topup-esim.php">Nạp data</a>
  </div>
  <div class="card">
    <h2>Tài khoản</h2>
    <p>Email: <strong><?= htmlspecialchars((string)$user['email']) ?></strong></p>
    <p>Tên: <?= htmlspecialchars((string)($user['display_name'] ?? '')) ?></p>
    <p>Chiết khấu hiệu lực: <strong><?= htmlspecialchars(format_vnd((new CtvPricingService())->effectiveDiscount($user))) ?></strong> / eSIM</p>
  </div>
</div>

<div class="card">
  <h2>Giao dịch ví gần đây</h2>
  <?php if (!$tx): ?>
    <p class="muted">Chưa có giao dịch nào.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Thời gian</th><th>Loại</th><th>Số tiền</th><th>Số dư sau</th><th>Tham chiếu</th><th>Ghi chú</th></tr></thead>
    <tbody>
      <?php foreach ($tx as $r): ?>
      <tr>
        <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
        <td><?= htmlspecialchars((string)$r['reason']) ?></td>
        <td style="color:<?= ((int)$r['amount']) >= 0 ? '#166534' : '#991b1b' ?>"><?= htmlspecialchars(format_vnd((int)$r['amount'])) ?></td>
        <td><?= htmlspecialchars(format_vnd((int)$r['balance_after'])) ?></td>
        <td><?= htmlspecialchars((string)($r['ref_type'] ?? '')) ?> <?= htmlspecialchars((string)($r['ref_id'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)($r['note'] ?? '')) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
