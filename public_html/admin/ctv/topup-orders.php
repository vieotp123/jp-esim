<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $tid = (string)($_POST['tid'] ?? '');
    if ($tid === '') { $flash = ['err', 'Thiếu mã nạp']; }
    elseif ($action === 'refund') {
        $st = db()->prepare('SELECT * FROM ctv_topup_orders WHERE ctv_topup_id=? LIMIT 1');
        $st->execute([$tid]);
        $row = $st->fetch();
        if (!$row) { $flash = ['err', 'Không tìm thấy đơn nạp']; }
        elseif ((int)$row['status'] === 3) { $flash = ['warn', 'Đơn đã ở trạng thái thất bại/đã hoàn']; }
        else {
            $charge = (int)$row['total_charge'];
            $ctvId = (int)$row['ctv_id'];
            db()->prepare('UPDATE ctv_topup_orders SET status=3, needs_admin=0, error_message=CONCAT(IFNULL(error_message,""), ?), updated_at=NOW() WHERE ctv_topup_id=?')
                ->execute([' [Admin hoàn: ' . $admin['user'] . ']', $tid]);
            try { (new CtvWalletService())->credit($ctvId, $charge, 'topup_refund', 'ctv_topup', $tid, 'Admin refund by ' . $admin['user']); }
            catch (Throwable $e) { $flash = ['err', 'Hoàn ví lỗi: ' . $e->getMessage()]; }
            if (!$flash) {
                AuditLog::log($admin['user'], 'topup_refund', 'ctv_topup', $tid, ['ctv_id' => $ctvId, 'amount' => $charge]);
                $flash = ['ok', 'Đã hoàn ' . format_vnd($charge) . ' cho CTV #' . $ctvId . ' (đơn ' . $tid . ')'];
            }
        }
    }
}

$status = (string)($_GET['status'] ?? '');
$search = trim((string)($_GET['q'] ?? ''));
$where = [];
$params = [];
$statusMap = [0 => 'pending', 1 => 'processing', 2 => 'success', 3 => 'failed'];
$statusVi = [0 => 'Chờ', 1 => 'Đang xử lý', 2 => 'Thành công', 3 => 'Thất bại'];
$statusCls = [0 => '', 1 => 'warn', 2 => 'ok', 3 => 'err'];

if ($status !== '' && in_array((int)$status, [0,1,2,3], true)) {
    $where[] = 't.status=?';
    $params[] = (int)$status;
}
if ($search !== '') {
    $where[] = '(t.ctv_topup_id LIKE ? OR t.iccid LIKE ? OR u.email LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = db()->prepare("SELECT t.*, u.email AS ctv_email FROM ctv_topup_orders t LEFT JOIN ctv_users u ON u.id=t.ctv_id $whereSql ORDER BY t.id DESC LIMIT 200");
$st->execute($params);
$rows = $st->fetchAll();

$counts = db()->query("SELECT
    COUNT(*) AS total,
    SUM(status=0) AS pending_n,
    SUM(status=1) AS processing_n,
    SUM(status=2) AS success_n,
    SUM(status=3) AS failed_n,
    SUM(needs_admin=1 AND status!=2) AS needs_admin_n
FROM ctv_topup_orders")->fetch();

admin_layout_header('Đơn nạp data', $admin);
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="summary">
  <div class="card"><b>Tổng</b><h2><?= (int)($counts['total'] ?? 0) ?></h2></div>
  <div class="card"><b>Chờ</b><h2><?= (int)($counts['pending_n'] ?? 0) ?></h2></div>
  <div class="card warn"><b>Đang xử lý</b><h2><?= (int)($counts['processing_n'] ?? 0) ?></h2></div>
  <div class="card green"><b>Thành công</b><h2><?= (int)($counts['success_n'] ?? 0) ?></h2></div>
  <div class="card danger"><b>Thất bại</b><h2><?= (int)($counts['failed_n'] ?? 0) ?></h2></div>
  <div class="card gold"><b>Cần admin</b><h2><?= (int)($counts['needs_admin_n'] ?? 0) ?></h2></div>
</div>

<div class="card">
  <form method="get" class="filter-row" style="margin-bottom:14px">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm mã nạp, ICCID, email..." style="flex:1;min-width:200px">
    <select name="status">
      <option value="">Tất cả</option>
      <option value="0" <?= $status==='0'?'selected':'' ?>>Chờ</option>
      <option value="1" <?= $status==='1'?'selected':'' ?>>Đang xử lý</option>
      <option value="2" <?= $status==='2'?'selected':'' ?>>Thành công</option>
      <option value="3" <?= $status==='3'?'selected':'' ?>>Thất bại</option>
    </select>
    <button class="btn gold" type="submit">Lọc</button>
  </form>

  <?php if (!$rows): ?>
    <div class="empty"><div class="icon">📶</div><p>Không có đơn nạp data nào khớp bộ lọc.</p></div>
  <?php else: ?>
  <div class="m-cards">
  <?php foreach ($rows as $r): $s = (int)$r['status']; ?>
    <div class="m-card">
      <div class="m-head"><span class="kbd"><?= htmlspecialchars((string)$r['ctv_topup_id']) ?></span><span class="tag <?= $statusCls[$s] ?? '' ?>"><?= $statusVi[$s] ?? '?' ?></span></div>
      <div class="m-row"><span class="m-label">CTV</span><span class="m-val">#<?= (int)$r['ctv_id'] ?> <?= htmlspecialchars((string)($r['ctv_email'] ?? '')) ?></span></div>
      <div class="m-row"><span class="m-label">ICCID</span><span class="m-val kbd" style="font-size:11px"><?= htmlspecialchars((string)$r['iccid']) ?></span></div>
      <div class="m-row"><span class="m-label">Gói</span><span class="m-val"><?= htmlspecialchars((string)$r['carrier'] . ' · ' . (string)$r['plan_name']) ?></span></div>
      <div class="m-row"><span class="m-label">Phí</span><span class="m-val"><?= htmlspecialchars(format_vnd((int)$r['total_charge'])) ?></span></div>
      <div class="m-row"><span class="m-label">Nguồn</span><span class="m-val"><?= htmlspecialchars((string)($r['source'] ?? 'panel')) ?></span></div>
      <div class="m-row"><span class="m-label">Thời gian</span><span class="m-val muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></div>
      <?php if (!empty($r['error_message'])): ?><div style="font-size:11px;color:var(--a-muted);margin-top:4px"><?= htmlspecialchars(mb_strimwidth((string)$r['error_message'], 0, 120, '…')) ?></div><?php endif; ?>
      <?php if ($s !== 2 && (int)$r['needs_admin'] === 1): ?>
      <div class="m-actions">
        <form method="post" onsubmit="return confirm('Hoàn tiền đơn này?');" style="flex:1"><?php admin_csrf_field(); ?><input type="hidden" name="tid" value="<?= htmlspecialchars((string)$r['ctv_topup_id']) ?>"><input type="hidden" name="action" value="refund"><button class="btn sm gold" style="width:100%">Hoàn tiền</button></form>
      </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Mã nạp</th><th>CTV</th><th>ICCID</th><th>Gói</th><th>Phí</th><th>Trạng thái</th><th>Nguồn</th><th>Thời gian</th><th>Hành động</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $s = (int)$r['status']; ?>
      <tr>
        <td><span class="kbd"><?= htmlspecialchars((string)$r['ctv_topup_id']) ?></span></td>
        <td><a href="/admin/ctv/view.php?id=<?= (int)$r['ctv_id'] ?>">#<?= (int)$r['ctv_id'] ?></a><br><span class="muted" style="font-size:11px"><?= htmlspecialchars((string)($r['ctv_email'] ?? '')) ?></span></td>
        <td style="font-size:12px"><?= htmlspecialchars((string)$r['iccid']) ?></td>
        <td><?= htmlspecialchars((string)$r['carrier'] . ' · ' . (string)$r['plan_name']) ?></td>
        <td><?= htmlspecialchars(format_vnd((int)$r['total_charge'])) ?></td>
        <td><span class="tag <?= $statusCls[$s] ?? '' ?>"><?= $statusVi[$s] ?? '?' ?></span><?php if (!empty($r['error_message'])): ?><br><span class="muted" style="font-size:11px"><?= htmlspecialchars(mb_strimwidth((string)$r['error_message'], 0, 100, '…')) ?></span><?php endif; ?></td>
        <td><?= htmlspecialchars((string)($r['source'] ?? 'panel')) ?></td>
        <td class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></td>
        <td>
          <?php if ($s !== 2 && (int)$r['needs_admin'] === 1): ?>
          <form method="post" class="inline" onsubmit="return confirm('Hoàn tiền đơn <?= htmlspecialchars((string)$r['ctv_topup_id']) ?>?');">
            <?php admin_csrf_field(); ?>
            <input type="hidden" name="tid" value="<?= htmlspecialchars((string)$r['ctv_topup_id']) ?>">
            <input type="hidden" name="action" value="refund">
            <button class="btn sm gold">Hoàn</button>
          </form>
          <?php else: ?>
          <span class="muted">—</span>
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
