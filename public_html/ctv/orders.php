<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$status = (string)($_GET['status'] ?? '');
$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$rows = (new CtvOrderService())->listForCtv((int)$user['id'], $perPage, ($page - 1) * $perPage, $status ?: null);
if ($q !== '') { $rows = array_values(array_filter($rows, fn($r) => stripos((string)$r['orderId'], $q) !== false || stripos((string)$r['carrier'].' '.(string)$r['planName'], $q) !== false)); }

ctv_layout_header('Đơn eSIM', $user);
?>
<div class="card">
  <h2>Đơn eSIM của bạn</h2>
  <form method="get" class="row"><div class="field"><label>Tìm đơn/gói</label><input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="CTV order id hoặc tên gói..."></div><div class="field"><label>Trạng thái</label><select name="status"><option value="">Tất cả</option><option value="success" <?= $status==='success'?'selected':'' ?>>Thành công</option><option value="processing" <?= $status==='processing'?'selected':'' ?>>Đang xử lý</option><option value="failed" <?= $status==='failed'?'selected':'' ?>>Thất bại</option></select></div><div class="field"><label>&nbsp;</label><button class="btn">Lọc</button></div></form><p>
    <a href="?status=" class="tag">Tất cả</a>
    <a href="?status=success" class="tag ok">Thành công</a>
    <a href="?status=processing" class="tag warn">Đang xử lý</a>
    <a href="?status=failed" class="tag err">Thất bại</a>
  </p>
  <?php if (!$rows): ?>
    <p class="muted">Chưa có đơn nào.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Mã</th><th>Gói</th><th>Số lượng</th><th>Phí CTV</th><th>Trạng thái</th><th>Ngày</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/ctv/orders/view.php?id=<?= htmlspecialchars($r['orderId']) ?>" class="kbd" style="text-decoration:none"><?= htmlspecialchars($r['orderId']) ?></a></td>
        <td><?= htmlspecialchars($r['carrier'].' '.$r['planName']) ?></td>
        <td><?= (int)$r['quantity'] ?></td>
        <td><?= htmlspecialchars(format_vnd((int)$r['totalCharge'])) ?></td>
        <td>
          <?php $cls = $r['status']==='success' ? 'ok' : ($r['status']==='failed' ? 'err' : 'warn'); ?>
          <span class="tag <?= $cls ?>"><?= htmlspecialchars($r['status']) ?></span>
          <?php if ($r['needsAdmin']): ?><span class="tag err">cần admin</span><?php endif; ?>
        </td>
        <td><?= htmlspecialchars((string)$r['createdAt']) ?></td>
      </tr>
      <?php if ($r['errorMessage']): ?>
      <tr><td colspan="6" class="muted">Lỗi: <?= htmlspecialchars($r['errorMessage']) ?></td></tr>
      <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted">Trang <?= $page ?> · <a href="?page=<?= $page+1 ?>&status=<?= htmlspecialchars($status) ?>">Trang sau</a> · <?php if ($page>1): ?><a href="?page=<?= $page-1 ?>&status=<?= htmlspecialchars($status) ?>">Trước</a><?php endif; ?></p>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
