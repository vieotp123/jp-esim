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
  <h2>Bảng giá CTV</h2>
  <div class="filter-row">
    <a href="?type=esim" class="pill <?= $type==='esim'?'active':'' ?>">eSIM mới</a>
    <a href="?type=topup" class="pill <?= $type==='topup'?'active':'' ?>">Nạp data</a>
  </div>
  <?php if (empty($data['plans'])): ?>
    <div class="empty-state"><div class="icon">📦</div><p>Hiện chưa có gói nào trong danh mục này.</p></div>
  <?php else: ?>
  <div class="m-cards">
    <?php foreach ($data['plans'] as $p): ?>
    <div class="m-card">
      <div class="m-head">
        <strong><?= htmlspecialchars((string)$p['name']) ?></strong>
        <span class="tag gold"><?= htmlspecialchars((string)$p['telecom']) ?></span>
      </div>
      <div class="m-row"><span class="m-label">Số ngày</span><span class="m-val"><?= htmlspecialchars((string)$p['day']) ?></span></div>
      <div class="m-row"><span class="m-label">Giá lẻ</span><span class="m-val"><?= htmlspecialchars((string)$p['retailPriceText']) ?></span></div>
      <div class="m-row"><span class="m-label">Chiết khấu</span><span class="m-val" style="color:var(--c-green)">-<?= htmlspecialchars(format_vnd((int)$p['discount'])) ?></span></div>
      <div class="m-row"><span class="m-label">Giá CTV</span><span class="m-val" style="color:var(--c-gold);font-weight:700;font-size:15px"><?= htmlspecialchars((string)$p['ctvPriceText']) ?></span></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Nhà mạng</th><th>Tên gói</th><th>Số ngày</th><th>Giá lẻ</th><th>Chiết khấu</th><th>Giá CTV</th></tr></thead>
    <tbody>
      <?php foreach ($data['plans'] as $p): ?>
      <tr>
        <td><?= htmlspecialchars((string)$p['telecom']) ?></td>
        <td><strong><?= htmlspecialchars((string)$p['name']) ?></strong></td>
        <td><?= htmlspecialchars((string)$p['day']) ?></td>
        <td style="white-space:nowrap"><?= htmlspecialchars((string)$p['retailPriceText']) ?></td>
        <td style="color:var(--c-green);white-space:nowrap">-<?= htmlspecialchars(format_vnd((int)$p['discount'])) ?></td>
        <td style="white-space:nowrap"><strong style="color:var(--c-gold)"><?= htmlspecialchars((string)$p['ctvPriceText']) ?></strong></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p class="muted" style="margin-top:10px">Chiết khấu hiện tại của bạn: <strong style="color:var(--c-gold)"><?= htmlspecialchars(format_vnd((new CtvPricingService())->effectiveDiscount($user))) ?></strong> / eSIM.</p>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
