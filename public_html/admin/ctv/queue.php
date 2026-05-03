<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once '/home/foamljf4kvet/app/services/LegacyProviderClient.php';
require_once '/home/foamljf4kvet/app/services/RetailFulfillmentService.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

$flash = null;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        $id = max(0, (int)($_POST['id'] ?? 0));
        $note = trim((string)($_POST['note'] ?? ''));
        if (mb_strlen($note) > 1000) $note = mb_substr($note, 0, 1000);
        if ($id <= 0) throw new RuntimeException('ID không hợp lệ');
        if (!in_array($action, ['resolve','ignore','reopen','retry'], true)) throw new RuntimeException('Hành động không hỗ trợ');
        $st = db()->prepare('SELECT id, status FROM order_admin_queue WHERE id=? LIMIT 1');
        $st->execute([$id]); $row = $st->fetch();
        if (!$row) throw new RuntimeException('Không tìm thấy mục #' . $id);
        if ($action === 'resolve') {
            $resolverNote = $note !== '' ? $note : ('Resolved by ' . $admin['user']);
            db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=?')
                ->execute(['resolved', $resolverNote, $id]);
            $flash = ['ok', 'Đã đánh dấu giải quyết #' . $id];
        } elseif ($action === 'ignore') {
            $resolverNote = $note !== '' ? $note : ('Ignored by ' . $admin['user']);
            db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=?')
                ->execute(['ignored', $resolverNote, $id]);
            $flash = ['warn', 'Đã bỏ qua #' . $id];
        } elseif ($action === 'reopen') {
            db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NULL, resolver_note=? WHERE id=?')
                ->execute(['open', $note !== '' ? $note : null, $id]);
            $flash = ['ok', 'Đã mở lại #' . $id];
        } elseif ($action === 'retry') {
            // Retry provider. Tôn trọng test-mode + TEST-DEMO ref để tránh gọi API thật ngoài ý muốn.
            $refId = (string)$row['ref_id'];
            $kind  = (string)$row['kind'];
            $isTestDemo = str_starts_with($refId, 'TEST-DEMO-');
            $providerTest = LegacyProviderClient::isTestMode();
            if (!$isTestDemo && !$providerTest) {
                throw new RuntimeException('Provider retry bị chặn: cần PROVIDER_TEST_MODE=1 hoặc ref TEST-DEMO-* (an toàn). Bật env và refresh.');
            }
            if ($kind === 'amount_mismatch') {
                throw new RuntimeException('amount_mismatch không retry tự động — cần Resolve/Ignore thủ công.');
            }
            $svc = new RetailFulfillmentService();
            $stripped = $isTestDemo ? substr($refId, strlen('TEST-DEMO-')) : $refId;
            // Map ref to method: N* -> order, T* -> topup. Demo refs giữ prefix N/T sau khi strip.
            $first = strtoupper(substr($stripped, 0, 1));
            $result = null;
            if ($isTestDemo) {
                // Demo flow: không gọi service thật, chỉ mark resolved và ghi note để admin thấy UI hoạt động.
                db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=?')
                    ->execute(['resolved', '[demo retry] ' . ($note !== '' ? $note : 'Demo retry pass — admin: ' . $admin['user']), $id]);
                $flash = ['ok', 'Demo retry #' . $id . ' (ref ' . htmlspecialchars($refId) . ') — đã đánh dấu resolved (không gọi API thật).'];
            } elseif ($first === 'N') {
                $result = $svc->fulfillPaidOrder($stripped);
                if (!empty($result['success'])) {
                    db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=?')
                        ->execute(['resolved', '[retry pass] ' . ($note !== '' ? $note : '') . ' by ' . $admin['user'], $id]);
                    $flash = ['ok', 'Retry order ' . $stripped . ' thành công — đã đánh dấu resolved.'];
                } else {
                    $flash = ['err', 'Retry order ' . $stripped . ' vẫn fail: ' . htmlspecialchars((string)($result['reason'] ?? 'unknown'))];
                }
            } elseif ($first === 'T') {
                $result = $svc->fulfillPaidTopup($stripped);
                if (!empty($result['success'])) {
                    db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=?')
                        ->execute(['resolved', '[retry pass] ' . ($note !== '' ? $note : '') . ' by ' . $admin['user'], $id]);
                    $flash = ['ok', 'Retry topup ' . $stripped . ' thành công — đã đánh dấu resolved.'];
                } else {
                    $flash = ['err', 'Retry topup ' . $stripped . ' vẫn fail: ' . htmlspecialchars((string)($result['reason'] ?? 'unknown'))];
                }
            } else {
                throw new RuntimeException('Không nhận diện được loại ref (cần N* hoặc T*): ' . htmlspecialchars($refId));
            }
        }
    }
} catch (Throwable $e) {
    $flash = ['err', 'Lỗi: ' . $e->getMessage()];
}

$status = (string)($_GET['status'] ?? 'open');
if (!in_array($status, ['open','resolved','ignored','all'], true)) $status = 'open';
$kind = trim((string)($_GET['kind'] ?? ''));
$kindAllowed = ['amount_mismatch','provider_error','email_error',''];
if (!in_array($kind, $kindAllowed, true)) $kind = '';

$where = []; $params = [];
if ($status !== 'all') { $where[] = 'status=?'; $params[] = $status; }
if ($kind !== '')      { $where[] = 'kind=?';   $params[] = $kind; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$st = db()->prepare("SELECT * FROM order_admin_queue $whereSql ORDER BY id DESC LIMIT 200");
$st->execute($params); $rows = $st->fetchAll();

$counts = db()->query("SELECT
    SUM(status='open') AS open_n,
    SUM(status='resolved') AS resolved_n,
    SUM(status='ignored') AS ignored_n,
    SUM(status='open' AND kind='amount_mismatch') AS amt_n,
    SUM(status='open' AND kind='provider_error') AS prv_n,
    SUM(status='open' AND kind='email_error') AS eml_n,
    COUNT(*) AS total_n
FROM order_admin_queue")->fetch();

$kindLabel = [
    'amount_mismatch' => ['Amount mismatch', 'warn'],
    'provider_error'  => ['Provider error',  'err'],
    'email_error'     => ['Email error',     'info'],
];
$qsBuild = function(array $extra) use ($status, $kind): string {
    $params = array_filter(array_merge(['status'=>$status, 'kind'=>$kind ?: null], $extra), fn($v)=> $v !== null && $v !== '');
    return '?' . http_build_query($params);
};

admin_layout_header('Failed Order Queue', $admin);
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="summary">
  <div class="card gold"><b>Đang chờ xử lý</b><h2><?= (int)($counts['open_n'] ?? 0) ?></h2><div class="sub">tất cả loại</div></div>
  <div class="card"><b>Amount mismatch</b><h2><?= (int)($counts['amt_n'] ?? 0) ?></h2><div class="sub">webhook không khớp số tiền</div></div>
  <div class="card danger"><b>Provider error</b><h2><?= (int)($counts['prv_n'] ?? 0) ?></h2><div class="sub">EsimAccess fail</div></div>
  <div class="card"><b>Email error</b><h2><?= (int)($counts['eml_n'] ?? 0) ?></h2><div class="sub">QR không gửi được</div></div>
  <div class="card green"><b>Đã giải quyết</b><h2><?= (int)($counts['resolved_n'] ?? 0) ?></h2><div class="sub">tổng cộng</div></div>
</div>

<div class="card">
  <div class="filter-row">
    <span class="muted">Trạng thái:</span>
    <a class="pill <?= $status==='open'?'active':'' ?>"     href="<?= htmlspecialchars($qsBuild(['status'=>'open'])) ?>">Open <span class="count"><?= (int)$counts['open_n'] ?></span></a>
    <a class="pill <?= $status==='resolved'?'active':'' ?>" href="<?= htmlspecialchars($qsBuild(['status'=>'resolved'])) ?>">Resolved <span class="count"><?= (int)$counts['resolved_n'] ?></span></a>
    <a class="pill <?= $status==='ignored'?'active':'' ?>"  href="<?= htmlspecialchars($qsBuild(['status'=>'ignored'])) ?>">Ignored <span class="count"><?= (int)$counts['ignored_n'] ?></span></a>
    <a class="pill <?= $status==='all'?'active':'' ?>"      href="<?= htmlspecialchars($qsBuild(['status'=>'all'])) ?>">All <span class="count"><?= (int)$counts['total_n'] ?></span></a>
  </div>
  <div class="filter-row">
    <span class="muted">Loại:</span>
    <a class="pill <?= $kind===''?'active':'' ?>"                 href="<?= htmlspecialchars($qsBuild(['kind'=>null])) ?>">Tất cả</a>
    <a class="pill <?= $kind==='amount_mismatch'?'active':'' ?>"  href="<?= htmlspecialchars($qsBuild(['kind'=>'amount_mismatch'])) ?>">Amount mismatch</a>
    <a class="pill <?= $kind==='provider_error'?'active':'' ?>"   href="<?= htmlspecialchars($qsBuild(['kind'=>'provider_error'])) ?>">Provider error</a>
    <a class="pill <?= $kind==='email_error'?'active':'' ?>"      href="<?= htmlspecialchars($qsBuild(['kind'=>'email_error'])) ?>">Email error</a>
  </div>

  <?php if (!$rows): ?>
    <div class="empty">
      <div class="icon">✓</div>
      <p>Không có mục nào khớp filter.</p>
    </div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:60px">#</th>
        <th>Loại</th>
        <th>Order ref</th>
        <th>Tóm tắt lỗi</th>
        <th>Trạng thái</th>
        <th>Tạo lúc</th>
        <th>Resolver</th>
        <th style="width:280px">Hành động</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
      $st = (string)$r['status'];
      $stCls = $st==='open' ? 'warn' : ($st==='resolved' ? 'ok' : 'info');
      $k = (string)$r['kind'];
      [$kLabel, $kCls] = $kindLabel[$k] ?? [$k, 'info'];
      $err = (string)($r['error_summary'] ?? '');
      $errShort = mb_strimwidth($err, 0, 220, '…');
    ?>
      <tr>
        <td><span class="kbd">#<?= (int)$r['id'] ?></span></td>
        <td><span class="tag <?= $kCls ?>"><?= htmlspecialchars($kLabel) ?></span></td>
        <td><span class="kbd"><?= htmlspecialchars((string)$r['ref_id']) ?></span></td>
        <td style="max-width:380px">
          <div><?= htmlspecialchars($errShort) ?></div>
          <?php if (!empty($r['payload_redacted'])): ?>
            <details style="margin-top:6px">
              <summary>payload (redacted)</summary>
              <pre style="white-space:pre-wrap;font-size:12px;color:var(--a-ink-2);background:#0a1020;padding:8px;border-radius:6px;border:1px solid var(--a-line);margin-top:6px;max-height:240px;overflow:auto"><?= htmlspecialchars(mb_strimwidth((string)$r['payload_redacted'], 0, 4000, '…')) ?></pre>
            </details>
          <?php endif; ?>
          <?php if (!empty($r['resolver_note'])): ?>
            <div class="muted" style="margin-top:6px"><b>Note:</b> <?= htmlspecialchars((string)$r['resolver_note']) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="tag <?= $stCls ?>"><?= htmlspecialchars($st) ?></span></td>
        <td><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span><?php if ($r['resolved_at']): ?><br><span class="muted">→ <?= htmlspecialchars((string)$r['resolved_at']) ?></span><?php endif; ?></td>
        <td><span class="muted"><?= htmlspecialchars((string)($r['resolver_note'] ? '' : '')) ?></span></td>
        <td>
          <?php if ($st === 'open'): ?>
            <?php if ($k === 'provider_error' || $k === 'email_error'): ?>
              <form method="post" class="inline" style="display:inline-block;margin-bottom:6px" onsubmit="return confirm('Retry provider cho mục này?\nLưu ý: sẽ chỉ thực thi khi PROVIDER_TEST_MODE=1 hoặc ref TEST-DEMO-*');">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="retry">
                <button class="btn gold sm" title="Gọi RetailFulfillmentService::fulfillPaidOrder hoặc fulfillPaidTopup">↻ Retry provider</button>
              </form>
            <?php endif; ?>
            <details>
              <summary>Resolve</summary>
              <form method="post" style="margin-top:8px">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="resolve">
                <input type="text" name="note" placeholder="Ghi chú resolve (tuỳ chọn)" maxlength="500" style="width:100%;margin-bottom:6px">
                <button class="btn gold sm">Mark resolved</button>
              </form>
              <form method="post" style="margin-top:6px" onsubmit="return confirm('Bỏ qua mục này?');">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="ignore">
                <input type="text" name="note" placeholder="Lý do ignore (tuỳ chọn)" maxlength="500" style="width:100%;margin-bottom:6px">
                <button class="btn secondary sm">Ignore</button>
              </form>
            </details>
          <?php else: ?>
            <form method="post" class="inline" onsubmit="return confirm('Mở lại mục này?');">
              <?php admin_csrf_field(); ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="action" value="reopen">
              <button class="btn secondary sm">Reopen</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3>Hướng dẫn</h3>
  <p class="muted">Hàng đợi này được điền tự động bởi <span class="kbd">BankWebhookService</span> và <span class="kbd">RetailFulfillmentService</span> khi gặp:</p>
  <ul class="muted" style="margin:0;padding-left:20px;line-height:1.8">
    <li><b>amount_mismatch</b> — webhook NH nhận tiền nhưng số tiền không khớp đơn → cần xác nhận thủ công và refund/bù.</li>
    <li><b>provider_error</b> — gọi EsimAccess thất bại sau khi đã xác nhận thanh toán → cần retry hoặc refund.</li>
    <li><b>email_error</b> — eSIM đã tạo thành công nhưng gửi email QR thất bại → cần resend hoặc liên hệ khách.</li>
  </ul>
</div>
<?php admin_layout_footer();
