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
        if (!in_array($action, ['resolve','ignore','reopen','retry','cancel_order','mark_refunded'], true)) throw new RuntimeException('Hành động không hỗ trợ');
        $st = db()->prepare('SELECT id, kind, ref_id, status FROM order_admin_queue WHERE id=? LIMIT 1');
        $st->execute([$id]); $row = $st->fetch();
        if (!$row) throw new RuntimeException('Không tìm thấy mục #' . $id);
        if ($action === 'resolve') {
            $resolverNote = $note !== '' ? $note : ('Resolved by ' . $admin['user']);
            db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=?')
                ->execute(['resolved', $resolverNote, $id]);
            AuditLog::log($admin['user'], 'queue_resolve', 'queue', (string)$id, ['ref' => $row['ref_id'], 'kind' => $row['kind'], 'note' => $resolverNote]);
            $flash = ['ok', 'Đã đánh dấu giải quyết #' . $id];
        } elseif ($action === 'ignore') {
            $resolverNote = $note !== '' ? $note : ('Ignored by ' . $admin['user']);
            db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=?')
                ->execute(['ignored', $resolverNote, $id]);
            AuditLog::log($admin['user'], 'queue_ignore', 'queue', (string)$id, ['ref' => $row['ref_id'], 'kind' => $row['kind'], 'note' => $resolverNote]);
            $flash = ['warn', 'Đã bỏ qua #' . $id];
        } elseif ($action === 'reopen') {
            db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NULL, resolver_note=? WHERE id=?')
                ->execute(['open', $note !== '' ? $note : null, $id]);
            AuditLog::log($admin['user'], 'queue_reopen', 'queue', (string)$id, ['ref' => $row['ref_id'], 'kind' => $row['kind']]);
            $flash = ['ok', 'Đã mở lại #' . $id];
        } elseif ($action === 'cancel_order') {
            $refId = (string)($row['ref_id'] ?? '');
            $stripped = str_starts_with($refId, 'TEST-DEMO-') ? substr($refId, strlen('TEST-DEMO-')) : $refId;
            $stripped = str_starts_with($stripped, 'OVERPAY-') ? substr($stripped, strlen('OVERPAY-')) : $stripped;
            $first = strtoupper(substr($stripped, 0, 1));
            if ($first === 'N') {
                db()->prepare('UPDATE `order` SET status=3, updated_at=NOW() WHERE order_id=? AND status IN (0,2)')
                    ->execute([$stripped]);
            } elseif ($first === 'T') {
                db()->prepare('UPDATE topup_order SET status=3, updated_at=NOW() WHERE tid=? AND status IN (0,2)')
                    ->execute([$stripped]);
            }
            $cancelNote = '[cancelled] ' . ($note !== '' ? $note : 'Admin cancel') . ' by ' . $admin['user'];
            db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=?')
                ->execute(['resolved', $cancelNote, $id]);
            AuditLog::log($admin['user'], 'queue_cancel', 'queue', (string)$id, ['ref' => $row['ref_id'], 'order' => $stripped, 'note' => $cancelNote]);
            $flash = ['warn', 'Đã huỷ đơn ' . htmlspecialchars($stripped) . ' và đóng mục #' . $id . ' — không gọi hệ thống ngoài.'];
        } elseif ($action === 'mark_refunded') {
            $refId = (string)($row['ref_id'] ?? '');
            $stripped = str_starts_with($refId, 'TEST-DEMO-') ? substr($refId, strlen('TEST-DEMO-')) : $refId;
            $stripped = str_starts_with($stripped, 'OVERPAY-') ? substr($stripped, strlen('OVERPAY-')) : $stripped;
            $first = strtoupper(substr($stripped, 0, 1));
            if ($first === 'N') {
                db()->prepare('UPDATE `order` SET status=3, updated_at=NOW() WHERE order_id=? AND status IN (0,2)')
                    ->execute([$stripped]);
            } elseif ($first === 'T') {
                db()->prepare('UPDATE topup_order SET status=3, updated_at=NOW() WHERE tid=? AND status IN (0,2)')
                    ->execute([$stripped]);
            }
            $refundNote = '[refunded] ' . ($note !== '' ? $note : 'Manual bank refund confirmed') . ' by ' . $admin['user'];
            db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=?')
                ->execute(['resolved', $refundNote, $id]);
            AuditLog::log($admin['user'], 'queue_refund', 'queue', (string)$id, ['ref' => $row['ref_id'], 'order' => $stripped, 'note' => $refundNote]);
            $flash = ['ok', 'Đã đánh dấu hoàn tiền cho ' . htmlspecialchars($stripped) . ' — không gọi hệ thống ngoài.'];
        } elseif ($action === 'retry') {
            $refId = (string)($row['ref_id'] ?? '');
            $kind  = (string)($row['kind'] ?? '');
            if ($kind === 'amount_mismatch') {
                throw new RuntimeException('amount_mismatch không retry tự động — dùng Cancel/Refund hoặc Resolve thủ công.');
            }

            $isTestDemo = str_starts_with($refId, 'TEST-DEMO-');
            $stripped = $isTestDemo ? substr($refId, strlen('TEST-DEMO-')) : $refId;
            $first = strtoupper(substr($stripped, 0, 1));

            if ($kind === 'email_error') {
                if ($first === 'N') {
                    $ok = $isTestDemo ? true : (new MailService())->sendOrderIfNeeded($stripped);
                } elseif ($first === 'T') {
                    $ok = $isTestDemo ? true : (new MailService())->sendTopupIfNeeded($stripped);
                } else {
                    throw new RuntimeException('Không nhận diện được loại ref để resend email (cần N* hoặc T*): ' . htmlspecialchars($refId));
                }
                if ($ok) {
                    db()->prepare('UPDATE order_admin_queue SET status=?, resolved_at=NOW(), resolver_note=? WHERE id=?')
                        ->execute(['resolved', '[email retry pass] ' . ($note !== '' ? $note : '') . ' by ' . $admin['user'], $id]);
                    $flash = ['ok', 'Đã gửi lại email cho ' . htmlspecialchars($stripped) . ' — không gọi hệ thống ngoài.'];
                } else {
                    $flash = ['err', 'Resend email cho ' . htmlspecialchars($stripped) . ' chưa thành công. Kiểm tra Mailgun/logs.'];
                }
            } else {
                $providerTest = LegacyProviderClient::isTestMode();
                if (!$isTestDemo && !$providerTest) {
                    throw new RuntimeException('Thử lại bị chặn: cần PROVIDER_TEST_MODE=1 hoặc ref TEST-DEMO-* (an toàn). Bật env và tải lại.');
                }
                $svc = new RetailFulfillmentService();
                $result = null;
                if ($isTestDemo) {
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
    'amount_mismatch' => ['Sai số tiền', 'warn'],
    'provider_error'  => ['Lỗi xử lý',  'err'],
    'email_error'     => ['Lỗi email',   'info'],
];
$qsBuild = function(array $extra) use ($status, $kind): string {
    $params = array_filter(array_merge(['status'=>$status, 'kind'=>$kind ?: null], $extra), fn($v)=> $v !== null && $v !== '');
    return '?' . http_build_query($params);
};

admin_layout_header('Hàng đợi đơn lỗi', $admin);
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="summary">
  <div class="card gold"><b>Đang chờ xử lý</b><h2><?= (int)($counts['open_n'] ?? 0) ?></h2><div class="sub">tất cả loại</div></div>
  <div class="card"><b>Sai số tiền</b><h2><?= (int)($counts['amt_n'] ?? 0) ?></h2><div class="sub">webhook không khớp số tiền</div></div>
  <div class="card danger"><b>Lỗi xử lý</b><h2><?= (int)($counts['prv_n'] ?? 0) ?></h2><div class="sub">Lỗi nhà cung cấp eSIM</div></div>
  <div class="card"><b>Lỗi email</b><h2><?= (int)($counts['eml_n'] ?? 0) ?></h2><div class="sub">QR không gửi được</div></div>
  <div class="card green"><b>Đã giải quyết</b><h2><?= (int)($counts['resolved_n'] ?? 0) ?></h2><div class="sub">tổng cộng</div></div>
</div>

<div class="card">
  <div class="filter-row">
    <span class="muted">Trạng thái:</span>
    <a class="pill <?= $status==='open'?'active':'' ?>"     href="<?= htmlspecialchars($qsBuild(['status'=>'open'])) ?>">Đang mở <span class="count"><?= (int)$counts['open_n'] ?></span></a>
    <a class="pill <?= $status==='resolved'?'active':'' ?>" href="<?= htmlspecialchars($qsBuild(['status'=>'resolved'])) ?>">Đã xử lý <span class="count"><?= (int)$counts['resolved_n'] ?></span></a>
    <a class="pill <?= $status==='ignored'?'active':'' ?>"  href="<?= htmlspecialchars($qsBuild(['status'=>'ignored'])) ?>">Bỏ qua <span class="count"><?= (int)$counts['ignored_n'] ?></span></a>
    <a class="pill <?= $status==='all'?'active':'' ?>"      href="<?= htmlspecialchars($qsBuild(['status'=>'all'])) ?>">Tất cả <span class="count"><?= (int)$counts['total_n'] ?></span></a>
  </div>
  <div class="filter-row">
    <span class="muted">Loại:</span>
    <a class="pill <?= $kind===''?'active':'' ?>"                 href="<?= htmlspecialchars($qsBuild(['kind'=>null])) ?>">Tất cả</a>
    <a class="pill <?= $kind==='amount_mismatch'?'active':'' ?>"  href="<?= htmlspecialchars($qsBuild(['kind'=>'amount_mismatch'])) ?>">Sai số tiền</a>
    <a class="pill <?= $kind==='provider_error'?'active':'' ?>"   href="<?= htmlspecialchars($qsBuild(['kind'=>'provider_error'])) ?>">Lỗi xử lý</a>
    <a class="pill <?= $kind==='email_error'?'active':'' ?>"      href="<?= htmlspecialchars($qsBuild(['kind'=>'email_error'])) ?>">Lỗi email</a>
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
        <th>Mã đơn</th>
        <th>Tóm tắt lỗi</th>
        <th>Trạng thái</th>
        <th>Tạo lúc</th>
        <th>Người xử lý</th>
        <th style="width:280px">Hành động</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
      $st = (string)$r['status'];
      $stCls = $st==='open' ? 'warn' : ($st==='resolved' ? 'ok' : 'info');
      $k = (string)($r['kind'] ?? '');
      [$kLabel, $kCls] = $kindLabel[$k] ?? [$k, 'info'];
      $err = (string)($r['error_summary'] ?? '');
      $errShort = mb_strimwidth($err, 0, 220, '…');
    ?>
      <tr>
        <td><span class="kbd">#<?= (int)$r['id'] ?></span></td>
        <td><span class="tag <?= $kCls ?>"><?= htmlspecialchars($kLabel) ?></span></td>
        <td><span class="kbd"><?= htmlspecialchars((string)($r['ref_id'] ?? '')) ?></span></td>
        <td style="max-width:380px">
          <div><?= htmlspecialchars($errShort) ?></div>
          <?php if (!empty($r['payload_redacted'])): ?>
            <details style="margin-top:6px">
              <summary>Dữ liệu (đã ẩn bớt)</summary>
              <pre style="white-space:pre-wrap;font-size:12px;color:var(--a-ink-2);background:#0a1020;padding:8px;border-radius:6px;border:1px solid var(--a-line);margin-top:6px;max-height:240px;overflow:auto"><?= htmlspecialchars(mb_strimwidth((string)$r['payload_redacted'], 0, 4000, '…')) ?></pre>
            </details>
          <?php endif; ?>
          <?php if (!empty($r['resolver_note'])): ?>
            <div class="muted" style="margin-top:6px"><b>Ghi chú:</b> <?= htmlspecialchars((string)$r['resolver_note']) ?></div>
          <?php endif; ?>
        </td>
        <td><span class="tag <?= $stCls ?>"><?= match($st) { 'open'=>'Đang mở', 'resolved'=>'Đã xử lý', 'ignored'=>'Bỏ qua', default=>htmlspecialchars($st) } ?></span></td>
        <td><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span><?php if ($r['resolved_at']): ?><br><span class="muted">→ <?= htmlspecialchars((string)$r['resolved_at']) ?></span><?php endif; ?></td>
        <td><span class="muted"><?= htmlspecialchars((string)($r['resolver_note'] ? '' : '')) ?></span></td>
        <td>
          <?php if ($st === 'open'): ?>
            <?php if ($k === 'provider_error' || $k === 'email_error'): ?>
              <?php if ($k === 'email_error'): ?>
              <form method="post" class="inline" style="display:inline-block;margin-bottom:6px" onsubmit="return confirm('Gửi lại email cho mục này?\nKhông gọi hệ thống ngoài, chỉ gửi lại mail khách.');">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="retry">
                <button class="btn gold sm" title="Gửi lại email qua Mailgun, không gọi nhà cung cấp">✉ Gửi lại email</button>
              </form>
              <?php else: ?>
              <form method="post" class="inline" style="display:inline-block;margin-bottom:6px" onsubmit="return confirm('Thử lại xử lý cho mục này?\nLưu ý: sẽ chỉ thực thi khi PROVIDER_TEST_MODE=1 hoặc ref TEST-DEMO-*');">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="retry">
                <button class="btn gold sm" title="Thử lại xử lý đơn">↻ Thử lại</button>
              </form>
              <?php endif; ?>
            <?php endif; ?>
            <details>
              <summary>Huỷ / Hoàn tiền / Xử lý</summary>
              <form method="post" style="margin-top:8px" onsubmit="return confirm('HUỶ ĐƠN: Đặt status=3 (cancelled) cho đơn gốc.\nKhông gọi hệ thống ngoài.\nKhông hoàn tiền tự động — cần chuyển khoản thủ công nếu cần.');">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="cancel_order">
                <input type="text" name="note" placeholder="Lý do huỷ đơn (tuỳ chọn)" maxlength="500" style="width:100%;margin-bottom:6px">
                <button class="btn danger sm">✕ Huỷ đơn</button>
              </form>
              <form method="post" style="margin-top:6px" onsubmit="return confirm('ĐÁNH DẤU HOÀN TIỀN: Đặt status=3 cho đơn gốc.\nKhông gọi bank/provider API — chỉ ghi nhận đã hoàn tiền thủ công.\nXác nhận đã chuyển khoản hoàn lại cho khách?');">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="mark_refunded">
                <input type="text" name="note" placeholder="Ghi chú hoàn tiền (số tiền, ref chuyển khoản...)" maxlength="500" style="width:100%;margin-bottom:6px">
                <button class="btn gold sm">↩ Đánh dấu đã hoàn tiền</button>
              </form>
              <hr style="border-color:var(--a-line);margin:10px 0">
              <form method="post" style="margin-top:6px">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="resolve">
                <input type="text" name="note" placeholder="Ghi chú xử lý (tuỳ chọn)" maxlength="500" style="width:100%;margin-bottom:6px">
                <button class="btn gold sm">Đánh dấu đã xử lý</button>
              </form>
              <form method="post" style="margin-top:6px" onsubmit="return confirm('Bỏ qua mục này?');">
                <?php admin_csrf_field(); ?>
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="action" value="ignore">
                <input type="text" name="note" placeholder="Lý do bỏ qua (tuỳ chọn)" maxlength="500" style="width:100%;margin-bottom:6px">
                <button class="btn secondary sm">Bỏ qua</button>
              </form>
            </details>
          <?php else: ?>
            <form method="post" class="inline" onsubmit="return confirm('Mở lại mục này?');">
              <?php admin_csrf_field(); ?>
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="action" value="reopen">
              <button class="btn secondary sm">Mở lại</button>
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
    <li><b>amount_mismatch</b> — webhook NH nhận tiền nhưng số tiền không khớp đơn → dùng Huỷ đơn hoặc Đánh dấu hoàn tiền.</li>
    <li><b>provider_error</b> — gọi nhà cung cấp eSIM thất bại sau khi đã xác nhận thanh toán → cần retry hoặc refund.</li>
    <li><b>email_error</b> — eSIM đã tạo thành công nhưng gửi email QR thất bại → cần resend hoặc liên hệ khách.</li>
  </ul>
  <h4 style="margin-top:12px">Hành động:</h4>
  <ul class="muted" style="margin:0;padding-left:20px;line-height:1.8">
    <li><b>Huỷ đơn</b> — đặt order/topup status=3 (cancelled). Không gọi provider. Cần hoàn tiền thủ công.</li>
    <li><b>Đánh dấu hoàn tiền</b> — đặt order/topup status=3 + ghi nhận đã hoàn tiền. Không gọi bank/provider API.</li>
    <li><b>Retry</b> — chỉ hoạt động khi PROVIDER_TEST_MODE=1 hoặc email_error. Không dùng cho amount_mismatch.</li>
    <li><b>Resolve/Ignore</b> — đóng mục mà không thay đổi trạng thái đơn gốc.</li>
  </ul>
</div>
<?php admin_layout_footer();
