<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

$flash = null;
$svc = new CtvTopupRequestService();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        $reqId = (int)($_POST['request_id'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));
        if ($reqId <= 0) throw new RuntimeException('ID không hợp lệ');

        if ($action === 'approve') {
            $svc->approve($reqId, $admin['user'], $note ?: null);
            AuditLog::log($admin['user'], 'topup_request_approve', 'topup_request', (string)$reqId, ['note' => $note]);
            $flash = ['ok', 'Đã duyệt yêu cầu #' . $reqId . ' và nạp ví'];
        } elseif ($action === 'reject') {
            $svc->reject($reqId, $admin['user'], $note ?: null);
            AuditLog::log($admin['user'], 'topup_request_reject', 'topup_request', (string)$reqId, ['note' => $note]);
            $flash = ['ok', 'Đã từ chối yêu cầu #' . $reqId];
        }
    }
} catch (Throwable $e) {
    $flash = ['err', 'Lỗi: ' . $e->getMessage()];
}

$filterStatus = (string)($_GET['status'] ?? '');
$rows = $svc->listAll(200, 0, $filterStatus ?: null);
$pendingCount = count(array_filter($rows, fn($r) => $r['status'] === 'pending'));

admin_layout_header('Yêu cầu nạp ví', $admin);
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="summary">
  <div class="card <?= $pendingCount > 0 ? 'danger' : 'green' ?>"><b>Chờ duyệt</b><h2><?= $pendingCount ?></h2></div>
</div>

<div class="card">
  <div class="filter-row" style="margin-bottom:12px">
    <a class="pill <?= $filterStatus === '' ? 'active' : '' ?>" href="?">Tất cả</a>
    <a class="pill <?= $filterStatus === 'pending' ? 'active' : '' ?>" href="?status=pending">Chờ duyệt</a>
    <a class="pill <?= $filterStatus === 'approved' ? 'active' : '' ?>" href="?status=approved">Đã duyệt</a>
    <a class="pill <?= $filterStatus === 'rejected' ? 'active' : '' ?>" href="?status=rejected">Từ chối</a>
  </div>

  <?php if (!$rows): ?><div class="empty"><div class="icon">📋</div><p>Chưa có yêu cầu nạp ví nào<?= $filterStatus ? ' ở trạng thái này' : '' ?>.</p></div><?php else: ?>
  <div class="table-wrap">
  <table><thead><tr><th>#</th><th>CTV</th><th>Số tiền</th><th>Bằng chứng</th><th>Trạng thái</th><th>Ngày gửi</th><th>Thao tác</th></tr></thead><tbody>
  <?php foreach ($rows as $r):
    $sCls = match ($r['status']) { 'approved' => 'ok', 'rejected' => 'err', default => 'warn' };
    $sLabel = match ($r['status']) { 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', default => 'Chờ duyệt' };
  ?>
  <tr>
    <td><?= (int)$r['id'] ?></td>
    <td><a href="/admin/ctv/view.php?id=<?= (int)$r['ctv_id'] ?>">#<?= (int)$r['ctv_id'] ?></a> <?= htmlspecialchars((string)($r['email'] ?? '')) ?><?php if (!empty($r['company_name'])): ?><br><span class="muted"><?= htmlspecialchars((string)$r['company_name']) ?></span><?php endif; ?></td>
    <td><b><?= htmlspecialchars(format_vnd((int)$r['amount'])) ?></b></td>
    <td><?php if (!empty($r['proof_path'])): ?><a href="<?= htmlspecialchars((string)$r['proof_path']) ?>" target="_blank" class="btn sm secondary">Xem</a><?php else: ?><span class="muted">-</span><?php endif; ?></td>
    <td><span class="tag <?= $sCls ?>"><?= $sLabel ?></span><?php if ($r['admin_note']): ?><br><span class="muted"><?= htmlspecialchars(mb_strimwidth((string)$r['admin_note'], 0, 60, '…')) ?></span><?php endif; ?></td>
    <td><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span><?php if ($r['resolved_at']): ?><br><span class="muted">Xử lý: <?= htmlspecialchars((string)$r['resolved_at']) ?></span><?php endif; ?></td>
    <td>
    <?php if ($r['status'] === 'pending'): ?>
      <details><summary class="btn sm">Xử lý</summary>
      <div class="action-group" style="margin-top:8px">
        <form method="post">
          <?php admin_csrf_field(); ?>
          <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
          <label>Ghi chú</label>
          <input name="note" placeholder="Tuỳ chọn">
          <button name="action" value="approve" class="btn sm gold">Duyệt & nạp ví</button>
          <button name="action" value="reject" class="btn sm danger" onclick="return confirm('Từ chối yêu cầu #<?= (int)$r['id'] ?>?')">Từ chối</button>
        </form>
      </div>
      </details>
    <?php else: ?>
      <span class="muted"><?= htmlspecialchars((string)($r['resolved_by'] ?? '')) ?></span>
    <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
