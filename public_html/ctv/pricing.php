<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);
$type = ($_GET['type'] ?? 'esim') === 'topup' ? 'topup' : 'esim';
$telecom = trim((string)($_GET['telecom'] ?? '')) ?: null;
$data = (new CtvPricingService())->listFor($user, $type, $telecom);

ctv_layout_header('Bảng giá CTV', $user);
?>
<div class="card">
  <h2>Bảng giá CTV (<?= $type === 'topup' ? 'Nạp data' : 'eSIM mới' ?>)</h2>
  <p>
    <a href="?type=esim" class="btn <?= $type==='esim'?'':'secondary' ?>">eSIM mới</a>
    <a href="?type=topup" class="btn <?= $type==='topup'?'':'secondary' ?>">Nạp data</a>
  </p>
  <?php if (empty($data['plans'])): ?>
    <p class="muted">Hiện chưa có gói nào.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Nhà mạng</th><th>Tên gói</th><th>Số ngày</th><th>Giá lẻ</th><th>Chiết khấu</th><th>Giá CTV</th></tr></thead>
    <tbody>
      <?php foreach ($data['plans'] as $p): ?>
      <tr>
        <td><?= htmlspecialchars((string)$p['telecom']) ?></td>
        <td><?= htmlspecialchars((string)$p['name']) ?></td>
        <td><?= htmlspecialchars((string)$p['day']) ?></td>
        <td><?= htmlspecialchars((string)$p['retailPriceText']) ?></td>
        <td>-<?= htmlspecialchars(format_vnd((int)$p['discount'])) ?></td>
        <td><strong><?= htmlspecialchars((string)$p['ctvPriceText']) ?></strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
