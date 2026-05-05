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
        $note = trim((string)($_POST['note'] ?? ''));
        if ($action === 'bulk_approve' || $action === 'bulk_reject') {
            $ids = $_POST['ids'] ?? [];
            if (!is_array($ids) || !$ids) throw new RuntimeException('Chưa chọn yêu cầu nào');
            $ids = array_values(array_unique(array_map('intval', $ids)));
            $ids = array_filter($ids, fn($i) => $i > 0);
            if (count($ids) > 50) throw new RuntimeException('Tối đa 50 yêu cầu mỗi lần');
            $ok = 0; $errs = [];
            foreach ($ids as $id) {
                try {
                    if ($action === 'bulk_approve') {
                        $svc->approve($id, $admin['user'], $note ?: 'Bulk approve');
                        AuditLog::log($admin['user'], 'topup_request_approve', 'topup_request', (string)$id, ['note' => $note, 'bulk' => 1]);
                    } else {
                        $svc->reject($id, $admin['user'], $note ?: 'Bulk reject');
                        AuditLog::log($admin['user'], 'topup_request_reject', 'topup_request', (string)$id, ['note' => $note, 'bulk' => 1]);
                    }
                    $ok++;
                } catch (Throwable $e) {
                    $errs[] = "#$id: " . $e->getMessage();
                }
            }
            $verb = $action === 'bulk_approve' ? 'duyệt' : 'từ chối';
            $msg = 'Đã ' . $verb . ' ' . $ok . '/' . count($ids) . ' yêu cầu';
            if ($errs) $msg .= ' — Lỗi: ' . implode('; ', array_slice($errs, 0, 3));
            $flash = [$errs ? 'warn' : 'ok', $msg];
        } else {
            $reqId = (int)($_POST['request_id'] ?? 0);
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
  <?php if ($pendingCount > 0): ?>
  <form method="post" id="bulkForm" onsubmit="return confirmBulk(this)" style="margin-bottom:12px;padding:10px;border:1px dashed var(--a-line-2);border-radius:8px;background:rgba(230,192,104,.04)">
    <?php admin_csrf_field(); ?>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
        <input type="checkbox" id="selectAllPending" onchange="toggleAll(this)"> Chọn tất cả pending
      </label>
      <input name="note" placeholder="Ghi chú bulk (tuỳ chọn)" style="flex:1;min-width:200px;padding:6px 10px;border-radius:6px;border:1px solid var(--a-line-2);background:var(--a-surface);color:var(--a-ink)">
      <button name="action" value="bulk_approve" class="btn sm gold">Duyệt đã chọn</button>
      <button name="action" value="bulk_reject" class="btn sm danger">Từ chối đã chọn</button>
      <span class="muted" style="font-size:12px"><span id="bulkSelectedCount">0</span> chọn / tối đa 50</span>
    </div>
  </form>
  <script>
  function toggleAll(box) { document.querySelectorAll('.bulk-row-cb').forEach(cb => { cb.checked = box.checked; }); updateBulkCount(); }
  function updateBulkCount() {
    const n = document.querySelectorAll('.bulk-row-cb:checked').length;
    document.getElementById('bulkSelectedCount').textContent = n;
  }
  function confirmBulk(form) {
    const n = document.querySelectorAll('.bulk-row-cb:checked').length;
    if (n === 0) { alert('Chưa chọn yêu cầu nào'); return false; }
    const boxes = document.querySelectorAll('.bulk-row-cb:checked');
    boxes.forEach(b => {
      const inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = b.value;
      form.appendChild(inp);
    });
    return confirm('Xác nhận xử lý ' + n + ' yêu cầu?');
  }
  document.addEventListener('change', e => { if (e.target.classList.contains('bulk-row-cb')) updateBulkCount(); });
  </script>
  <?php endif; ?>
  <div class="m-cards">
  <?php foreach ($rows as $r):
    $sCls = match ($r['status']) { 'approved' => 'ok', 'rejected' => 'err', default => 'warn' };
    $sLabel = match ($r['status']) { 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', default => 'Chờ duyệt' };
  ?>
  <div class="m-card">
    <div class="m-head">
      <span>#<?= (int)$r['id'] ?> — <a href="/admin/ctv/view.php?id=<?= (int)$r['ctv_id'] ?>"><?= htmlspecialchars((string)($r['email'] ?? '')) ?></a></span>
      <span class="tag <?= $sCls ?>"><?= $sLabel ?></span>
    </div>
    <div class="m-row"><span class="m-label">Số tiền</span><span class="m-val" style="color:var(--a-gold);font-weight:700"><?= htmlspecialchars(format_vnd((int)$r['amount'])) ?></span></div>
    <div class="m-row"><span class="m-label">Ngày gửi</span><span class="m-val muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></div>
    <?php if (!empty($r['admin_note'])): ?><div style="font-size:12px;color:var(--a-muted);margin-top:4px"><?= htmlspecialchars(mb_strimwidth((string)$r['admin_note'],0,80,'…')) ?></div><?php endif; ?>
    <?php if ($r['status'] === 'pending'): ?>
    <div class="m-actions">
      <?php if (!empty($r['proof_path'])): ?><a href="<?= htmlspecialchars((string)$r['proof_path']) ?>" target="_blank" class="btn sm secondary">Xem bằng chứng</a><?php endif; ?>
      <form method="post" style="flex:1"><?php admin_csrf_field(); ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="note" value=""><button name="action" value="approve" class="btn sm gold" style="width:100%">Duyệt</button></form>
      <form method="post" style="flex:1" onsubmit="return confirm('Từ chối #<?= (int)$r['id'] ?>?')"><?php admin_csrf_field(); ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="note" value=""><button name="action" value="reject" class="btn sm danger" style="width:100%">Từ chối</button></form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  </div>
  <div class="table-wrap">
  <table><thead><tr><th style="width:24px"></th><th>#</th><th>Đối tác</th><th>Số tiền</th><th>Bằng chứng</th><th>Trạng thái</th><th>Ngày gửi</th><th>Thao tác</th></tr></thead><tbody>
  <?php foreach ($rows as $r):
    $sCls = match ($r['status']) { 'approved' => 'ok', 'rejected' => 'err', default => 'warn' };
    $sLabel = match ($r['status']) { 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', default => 'Chờ duyệt' };
  ?>
  <tr>
    <td><?php if ($r['status'] === 'pending'): ?><input type="checkbox" class="bulk-row-cb" value="<?= (int)$r['id'] ?>" form="bulkForm"><?php endif; ?></td>
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
