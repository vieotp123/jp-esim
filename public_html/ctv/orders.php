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

$csrf = CtvAuth::csrfToken();
ctv_layout_header('Đơn eSIM', $user);
?>
<div class="card">
  <h2>Đơn eSIM của bạn</h2>
  <form method="get" class="row" style="margin-bottom:14px">
    <div class="field"><label>Tìm đơn/gói</label><input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Mã đơn hoặc tên gói..."></div>
    <div class="field"><label>Trạng thái</label><select name="status"><option value="">Tất cả</option><option value="success" <?= $status==='success'?'selected':'' ?>>Thành công</option><option value="processing" <?= $status==='processing'?'selected':'' ?>>Đang xử lý</option><option value="failed" <?= $status==='failed'?'selected':'' ?>>Thất bại</option></select></div>
    <div class="field"><label>&nbsp;</label><button class="btn">Lọc</button></div>
  </form>
  <div class="filter-row">
    <a href="?status=<?= $q ? '&q='.htmlspecialchars(urlencode($q)) : '' ?>" class="pill <?= $status===''?'active':'' ?>">Tất cả</a>
    <a href="?status=success<?= $q ? '&q='.htmlspecialchars(urlencode($q)) : '' ?>" class="pill <?= $status==='success'?'active':'' ?>">Thành công</a>
    <a href="?status=processing<?= $q ? '&q='.htmlspecialchars(urlencode($q)) : '' ?>" class="pill <?= $status==='processing'?'active':'' ?>">Đang xử lý</a>
    <a href="?status=failed<?= $q ? '&q='.htmlspecialchars(urlencode($q)) : '' ?>" class="pill <?= $status==='failed'?'active':'' ?>">Thất bại</a>
  </div>
  <?php if (!$rows): ?>
    <div class="empty-state"><div class="icon">📋</div><p>Chưa có đơn nào<?= $status || $q ? ' phù hợp bộ lọc' : '' ?>.</p><p>Đơn sẽ hiển thị ở đây sau khi bạn <a href="/ctv/create-esim.php">tạo eSIM</a>.</p></div>
  <?php else: ?>
  <div class="m-cards">
    <?php foreach ($rows as $r):
      $cls = $r['status']==='success' ? 'ok' : ($r['status']==='failed' ? 'err' : 'warn');
      $statusLabel = match($r['status']) { 'success'=>'Thành công', 'failed'=>'Thất bại', default=>'Đang xử lý' };
    ?>
    <a href="/ctv/orders/view.php?id=<?= htmlspecialchars($r['orderId']) ?>" class="m-card" style="text-decoration:none;color:inherit;display:block">
      <div class="m-head">
        <span class="kbd"><?= htmlspecialchars($r['orderId']) ?></span>
        <span class="tag <?= $cls ?>"><?= $statusLabel ?></span>
      </div>
      <div class="m-row"><span class="m-label">Gói</span><span class="m-val"><?= htmlspecialchars($r['carrier'].' '.$r['planName']) ?></span></div>
      <div class="m-row"><span class="m-label">SL</span><span class="m-val"><?= (int)$r['quantity'] ?><?php if ((int)$r['quantity'] > 1 && isset($r['provisionedCount'])): $pc = (int)$r['provisionedCount']; ?> <span class="tag <?= $pc >= (int)$r['quantity'] ? 'ok' : ($pc > 0 ? 'warn' : '') ?>" style="font-size:10px"><?= $pc ?>/<?= (int)$r['quantity'] ?></span><?php endif; ?></span></div>
      <div class="m-row"><span class="m-label">Phí CTV</span><span class="m-val" style="color:var(--c-gold);font-weight:700"><?= htmlspecialchars(format_vnd((int)$r['totalCharge'])) ?></span></div>
      <div class="m-row"><span class="m-label">Ngày tạo</span><span class="m-val muted"><?= htmlspecialchars((string)$r['createdAt']) ?></span></div>
      <?php if ($r['errorMessage']): ?><div style="font-size:12px;color:var(--c-muted);margin-top:4px">⚠ <?= htmlspecialchars($r['errorMessage']) ?></div><?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Mã đơn</th><th>Gói</th><th>SL</th><th>Phí CTV</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/ctv/orders/view.php?id=<?= htmlspecialchars($r['orderId']) ?>" class="kbd" style="text-decoration:none"><?= htmlspecialchars($r['orderId']) ?></a></td>
        <td><?= htmlspecialchars($r['carrier'].' '.$r['planName']) ?></td>
        <td><?= (int)$r['quantity'] ?><?php if ((int)$r['quantity'] > 1 && isset($r['provisionedCount'])): $pc = (int)$r['provisionedCount']; ?> <span class="tag <?= $pc >= (int)$r['quantity'] ? 'ok' : ($pc > 0 ? 'warn' : '') ?>" style="font-size:11px"><?= $pc ?>/<?= (int)$r['quantity'] ?></span><?php endif; ?></td>
        <td style="white-space:nowrap"><?= htmlspecialchars(format_vnd((int)$r['totalCharge'])) ?></td>
        <td>
          <?php
            $cls = $r['status']==='success' ? 'ok' : ($r['status']==='failed' ? 'err' : 'warn');
            $statusLabel = match($r['status']) { 'success'=>'Thành công', 'failed'=>'Thất bại', default=>'Đang xử lý' };
          ?>
          <span class="tag <?= $cls ?>"><?= $statusLabel ?></span>
          <?php if ($r['needsAdmin']): ?><span class="tag err">Cần xử lý</span><?php endif; ?>
        </td>
        <td style="white-space:nowrap"><span class="muted"><?= htmlspecialchars((string)$r['createdAt']) ?></span></td>
        <td><a class="btn sm secondary" href="/ctv/export.php?kind=esims&order_id=<?= rawurlencode((string)$r['orderId']) ?>&_csrf=<?= urlencode($csrf) ?>">Xuất CSV</a></td>
      </tr>
      <?php if ($r['errorMessage']): ?>
      <tr><td colspan="7"><span class="muted" style="font-size:12px">⚠ <?= htmlspecialchars($r['errorMessage']) ?></span></td></tr>
      <?php endif; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div class="filter-row" style="margin-top:14px;justify-content:center">
    <?php if ($page > 1): ?><a class="pill" href="?page=<?= $page-1 ?>&status=<?= htmlspecialchars($status) ?>&q=<?= htmlspecialchars(urlencode($q)) ?>">← Trước</a><?php endif; ?>
    <span class="muted">Trang <?= $page ?></span>
    <?php if (count($rows) >= $perPage): ?><a class="pill" href="?page=<?= $page+1 ?>&status=<?= htmlspecialchars($status) ?>&q=<?= htmlspecialchars(urlencode($q)) ?>">Sau →</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
