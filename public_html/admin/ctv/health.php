<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
$pdo = db();

$flags = [
    'TOPUP_LOCKED' => (string)app_config('TOPUP_LOCKED', '0'),
    'PROVIDER_TEST_MODE' => (string)app_config('PROVIDER_TEST_MODE', '0'),
    'CTV_PROVIDER_TEST_MODE' => (string)app_config('CTV_PROVIDER_TEST_MODE', '0'),
    'ADMIN_REQUIRE_PASSKEY' => (string)app_config('ADMIN_REQUIRE_PASSKEY', '1'),
    'CTV_MAIL_DRY_RUN' => (string)app_config('CTV_MAIL_DRY_RUN', '0'),
    'CTV_MAIL_SAFE_MODE' => (string)app_config('CTV_MAIL_SAFE_MODE', '0'),
    'ALERT_EMAIL' => (string)app_config('ALERT_EMAIL', '') !== '' ? 'set' : 'unset',
];

$adminQueue = (int)$pdo->query("SELECT COUNT(*) FROM order_admin_queue WHERE status='open'")->fetchColumn();
$queueByKind = $pdo->query("SELECT kind, COUNT(*) cnt FROM order_admin_queue WHERE status='open' GROUP BY kind ORDER BY cnt DESC")->fetchAll();
$pendingEmails = (int)$pdo->query("SELECT COUNT(*) FROM ctv_esims e JOIN ctv_orders o ON o.ctv_order_id=e.ctv_order_id WHERE e.email_sent_at IS NULL AND (e.email_last_error IS NULL OR e.email_last_error='') AND o.email IS NOT NULL AND o.email<>''")->fetchColumn();
$failedEmails = (int)$pdo->query("SELECT COUNT(*) FROM ctv_esims WHERE email_sent_at IS NULL AND email_last_error IS NOT NULL AND email_last_error<>''")->fetchColumn();
$failedTopups = (int)$pdo->query("SELECT COUNT(*) FROM ctv_topup_orders WHERE status=3 AND needs_admin=1")->fetchColumn();
$pendingTopupReqs = (int)$pdo->query("SELECT COUNT(*) FROM ctv_topup_requests WHERE status='pending'")->fetchColumn();
$providerErr1h = (int)$pdo->query("SELECT COUNT(*) FROM order_admin_queue WHERE kind='provider_error' AND created_at >= (NOW() - INTERVAL 1 HOUR)")->fetchColumn();
$providerErr24h = (int)$pdo->query("SELECT COUNT(*) FROM order_admin_queue WHERE kind='provider_error' AND created_at >= (NOW() - INTERVAL 24 HOUR)")->fetchColumn();

$diskFree = @disk_free_space('/');
$diskFreeGb = $diskFree !== false ? round($diskFree / 1073741824, 1) : null;
$dbStart = microtime(true);
try { $pdo->query('SELECT 1'); $dbMs = round((microtime(true) - $dbStart) * 1000, 1); } catch (Throwable $e) { $dbMs = null; }

// Probe systemd timers (best-effort; needs no sudo for systemctl --user-unit list)
function probe_timer(string $unit): array {
    $out = [];
    $code = 0;
    @exec('systemctl show ' . escapeshellarg($unit) . ' --property=ActiveState,SubState,Result,ExecMainStatus,ExecMainStartTimestamp 2>&1', $out, $code);
    $kv = [];
    foreach ($out as $line) { if (strpos($line, '=') !== false) { [$k,$v] = explode('=', $line, 2); $kv[$k] = $v; } }
    return $kv;
}
$timers = [
    'jpesim-ctv-fulfillment-poll' => probe_timer('jpesim-ctv-fulfillment-poll.service'),
    'jpesim-email-retry' => probe_timer('jpesim-email-retry.service'),
    'jpesim-provider-alert' => probe_timer('jpesim-provider-alert.service'),
];

$recentErrors = $pdo->query("SELECT id, kind, ref_id, error_summary, created_at FROM order_admin_queue WHERE status='open' ORDER BY id DESC LIMIT 10")->fetchAll();

admin_layout_header('Tình trạng hệ thống', $admin);
?>
<style>
.h-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:18px}
.h-card{background:var(--a-card);border:1px solid var(--a-line);border-radius:14px;padding:16px}
.h-card.green{border-left:3px solid #4ade80}
.h-card.warn{border-left:3px solid #facc15}
.h-card.danger{border-left:3px solid #ef4444}
.h-card b{font-size:12px;color:var(--a-muted);text-transform:uppercase;letter-spacing:.5px}
.h-card h2{font-size:24px;font-weight:800;margin:6px 0 4px;color:var(--a-ink)}
.h-card .sub{font-size:12px;color:var(--a-muted)}
.h-section{margin-top:24px}
.h-section h3{font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--a-muted);margin-bottom:10px}
.flag-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--a-line)}
.flag-row code{font-size:12px;background:var(--a-surface);padding:2px 8px;border-radius:6px}
.tag{display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700}
.tag.ok{background:rgba(74,222,128,.12);color:#4ade80}
.tag.warn{background:rgba(250,204,21,.12);color:#facc15}
.tag.err{background:rgba(239,68,68,.12);color:#ef4444}
</style>

<div class="h-grid">
  <div class="h-card <?= $dbMs !== null ? 'green' : 'danger' ?>">
    <b>Database</b>
    <h2><?= $dbMs !== null ? $dbMs . ' ms' : 'OFFLINE' ?></h2>
    <div class="sub">Ping <?= $dbMs !== null ? 'OK' : 'failed' ?></div>
  </div>
  <div class="h-card <?= $diskFreeGb !== null && $diskFreeGb > 5 ? 'green' : ($diskFreeGb !== null && $diskFreeGb > 1 ? 'warn' : 'danger') ?>">
    <b>Đĩa trống</b>
    <h2><?= $diskFreeGb !== null ? $diskFreeGb . ' GB' : '—' ?></h2>
    <div class="sub">Filesystem /</div>
  </div>
  <div class="h-card <?= $adminQueue == 0 ? 'green' : ($adminQueue < 50 ? 'warn' : 'danger') ?>">
    <b>Queue admin (open)</b>
    <h2><?= $adminQueue ?></h2>
    <div class="sub">Đơn cần xử lý</div>
  </div>
  <div class="h-card <?= $providerErr1h == 0 ? 'green' : ($providerErr1h < 5 ? 'warn' : 'danger') ?>">
    <b>Lỗi provider 1h</b>
    <h2><?= $providerErr1h ?></h2>
    <div class="sub">24h: <?= $providerErr24h ?></div>
  </div>
  <div class="h-card <?= $failedTopups == 0 ? 'green' : 'warn' ?>">
    <b>Topup thất bại</b>
    <h2><?= $failedTopups ?></h2>
    <div class="sub"><a href="/admin/ctv/topup-orders.php?status=3" style="color:inherit">cần admin xử lý</a></div>
  </div>
  <div class="h-card <?= $pendingTopupReqs == 0 ? 'green' : 'warn' ?>">
    <b>Nạp ví chờ duyệt</b>
    <h2><?= $pendingTopupReqs ?></h2>
    <div class="sub"><a href="/admin/ctv/topup-requests.php?status=pending" style="color:inherit">duyệt yêu cầu</a></div>
  </div>
  <div class="h-card <?= $pendingEmails == 0 ? 'green' : 'warn' ?>">
    <b>Email chờ gửi</b>
    <h2><?= $pendingEmails ?></h2>
    <div class="sub">Lỗi: <?= $failedEmails ?></div>
  </div>
</div>

<div class="h-section">
  <h3>Cấu hình production</h3>
  <div class="card">
    <?php foreach ($flags as $k => $v):
      $isFlag = in_array($v, ['0','1','set','unset'], true);
      $cls = match (true) {
        $k === 'TOPUP_LOCKED' && $v === '1' => 'warn',
        $k === 'PROVIDER_TEST_MODE' && $v === '1' => 'warn',
        $k === 'CTV_PROVIDER_TEST_MODE' && $v === '1' => 'warn',
        $k === 'ADMIN_REQUIRE_PASSKEY' && $v !== '0' => 'ok',
        $k === 'CTV_MAIL_DRY_RUN' && $v === '1' => 'warn',
        $k === 'CTV_MAIL_SAFE_MODE' && $v === '1' => 'warn',
        $k === 'ALERT_EMAIL' && $v === 'unset' => 'warn',
        default => 'ok',
      };
    ?>
    <div class="flag-row">
      <code><?= htmlspecialchars($k) ?></code>
      <span class="tag <?= $cls ?>"><?= htmlspecialchars($v) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="h-section">
  <h3>Systemd timers</h3>
  <div class="card">
    <?php foreach ($timers as $name => $kv):
      $active = (string)($kv['ActiveState'] ?? 'unknown');
      $sub = (string)($kv['SubState'] ?? '');
      $result = (string)($kv['Result'] ?? '');
      $last = (string)($kv['ExecMainStartTimestamp'] ?? 'never');
      $cls = match (true) {
        $result === 'success' || $sub === 'dead' => 'ok',
        $result === 'exit-code' || $result === 'failed' => 'err',
        default => 'warn',
      };
    ?>
    <div class="flag-row">
      <span><code><?= htmlspecialchars($name) ?></code> <span class="muted" style="font-size:11px">last: <?= htmlspecialchars($last ?: 'never') ?></span></span>
      <span class="tag <?= $cls ?>"><?= htmlspecialchars($result ?: $active) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($recentErrors): ?>
<div class="h-section">
  <h3>10 lỗi mới nhất (queue admin)</h3>
  <div class="card">
    <div class="table-wrap">
    <table><thead><tr><th>ID</th><th>Loại</th><th>Ref</th><th>Tóm tắt lỗi</th><th>Thời gian</th></tr></thead><tbody>
    <?php foreach ($recentErrors as $r): ?>
    <tr>
      <td>#<?= (int)$r['id'] ?></td>
      <td><span class="tag warn"><?= htmlspecialchars((string)$r['kind']) ?></span></td>
      <td><span class="kbd"><?= htmlspecialchars((string)$r['ref_id']) ?></span></td>
      <td><?= htmlspecialchars(mb_strimwidth((string)$r['error_summary'], 0, 100, '…')) ?></td>
      <td><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="h-section">
  <h3>Endpoints</h3>
  <div class="card">
    <p class="muted" style="margin-bottom:10px">Tích hợp giám sát ngoài (Uptime Robot, Pingdom, Datadog…) qua URL:</p>
    <p><a href="/api/health.php" style="color:var(--a-gold)" target="_blank"><code>/api/health.php</code></a> — JSON, 200/503</p>
  </div>
</div>

<script>
// Auto-refresh every 60s if user keeps tab focused
let timer = setInterval(() => { if (!document.hidden) location.reload(); }, 60000);
document.addEventListener('visibilitychange', () => {
  if (document.hidden) { clearInterval(timer); }
  else if (!timer) { timer = setInterval(() => { if (!document.hidden) location.reload(); }, 60000); }
});
</script>
<?php admin_layout_footer();
