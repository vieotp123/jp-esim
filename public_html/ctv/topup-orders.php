<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$status = (string)($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$ctvId = (int)$user['id'];

$where = 'WHERE ctv_id=?';
$params = [$ctvId];
$statusMap = [0 => 'pending', 1 => 'processing', 2 => 'success', 3 => 'failed'];
$statusVi = ['pending' => 'Chờ', 'processing' => 'Đang xử lý', 'success' => 'Thành công', 'failed' => 'Thất bại'];
$statusCls = ['pending' => '', 'processing' => 'warn', 'success' => 'ok', 'failed' => 'err'];

if ($status !== '' && in_array($status, ['success', 'processing', 'failed'], true)) {
    $statusNum = array_search($status, $statusMap, true);
    if ($statusNum !== false) {
        $where .= ' AND status=?';
        $params[] = $statusNum;
    }
}

$st = db()->prepare("SELECT * FROM ctv_topup_orders $where ORDER BY id DESC LIMIT " . (int)$perPage . ' OFFSET ' . (int)(($page - 1) * $perPage));
$st->execute($params);
$rows = $st->fetchAll();

function ctv_topup_plan_label(array $r): string {
    $parts = [];
    $c = trim((string)($r['carrier'] ?? ''));
    if ($c !== '') $parts[] = $c;
    $n = trim((string)($r['plan_name'] ?? ''));
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(GB|MB)\b/i', $n, $m)) {
        $parts[] = str_replace(',', '.', $m[1]) . ' ' . strtoupper($m[2]);
    } elseif ($n !== '') {
        $parts[] = $n;
    }
    return implode(' · ', $parts) ?: 'Data';
}

ctv_layout_header('Lịch sử nạp data', $user);
?>
<div class="card">
  <h2>Lịch sử nạp data</h2>
  <div class="filter-row">
    <a href="?<?= $status!==''?'':'status=' ?>" class="pill <?= $status===''?'active':'' ?>">Tất cả</a>
    <a href="?status=success" class="pill <?= $status==='success'?'active':'' ?>">Thành công</a>
    <a href="?status=processing" class="pill <?= $status==='processing'?'active':'' ?>">Đang xử lý</a>
    <a href="?status=failed" class="pill <?= $status==='failed'?'active':'' ?>">Thất bại</a>
  </div>
  <?php if (!$rows): ?>
    <div class="empty-state"><div class="icon">📶</div><p>Chưa có đơn nạp data nào<?= $status ? ' phù hợp bộ lọc' : '' ?>.</p><p>Bạn có thể <a href="/ctv/topup-esim.php">nạp data cho eSIM</a> tại đây.</p></div>
  <?php else: ?>
  <div class="m-cards">
    <?php foreach ($rows as $r):
      $sKey = $statusMap[(int)$r['status']] ?? 'pending';
      $cls = $statusCls[$sKey] ?? '';
      $label = $statusVi[$sKey] ?? '?';
    ?>
    <div class="m-card">
      <div class="m-head">
        <span class="kbd"><?= htmlspecialchars((string)$r['ctv_topup_id']) ?></span>
        <span class="tag <?= $cls ?>"><?= $label ?></span>
      </div>
      <div class="m-row"><span class="m-label">ICCID</span><span class="m-val kbd" style="font-size:11px" data-copy="<?= htmlspecialchars((string)$r['iccid']) ?>"><?= htmlspecialchars((string)$r['iccid']) ?></span></div>
      <div class="m-row"><span class="m-label">Gói</span><span class="m-val"><?= htmlspecialchars(ctv_topup_plan_label($r)) ?></span></div>
      <div class="m-row"><span class="m-label">Phí đối tác</span><span class="m-val" style="color:var(--c-gold);font-weight:700"><?= htmlspecialchars(format_vnd((int)$r['total_charge'])) ?></span></div>
      <div class="m-row"><span class="m-label">Ngày tạo</span><span class="m-val muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></div>
      <?php if (!empty($r['error_message'])): ?><div style="font-size:12px;color:var(--c-muted);margin-top:4px"><?= htmlspecialchars((string)$r['error_message']) ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Mã nạp</th><th>ICCID</th><th>Gói</th><th>Phí đối tác</th><th>Trạng thái</th><th>Ngày tạo</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $sKey = $statusMap[(int)$r['status']] ?? 'pending';
      $cls = $statusCls[$sKey] ?? '';
      $label = $statusVi[$sKey] ?? '?';
    ?>
      <tr>
        <td><span class="kbd"><?= htmlspecialchars((string)$r['ctv_topup_id']) ?></span></td>
        <td style="font-size:12px"><span class="kbd" data-copy="<?= htmlspecialchars((string)$r['iccid']) ?>"><?= htmlspecialchars((string)$r['iccid']) ?></span></td>
        <td><?= htmlspecialchars(ctv_topup_plan_label($r)) ?></td>
        <td style="color:var(--c-gold);font-weight:700"><?= htmlspecialchars(format_vnd((int)$r['total_charge'])) ?></td>
        <td><span class="tag <?= $cls ?>"><?= $label ?></span><?php if (!empty($r['error_message'])): ?><br><span class="muted" style="font-size:11px"><?= htmlspecialchars(mb_strimwidth((string)$r['error_message'], 0, 80, '…')) ?></span><?php endif; ?></td>
        <td class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if (count($rows) >= $perPage): ?>
    <div style="text-align:center;margin-top:14px"><a class="btn secondary" href="?page=<?= $page + 1 ?><?= $status ? '&status=' . htmlspecialchars(urlencode($status)) : '' ?>">Trang tiếp →</a></div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
