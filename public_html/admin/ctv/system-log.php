<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();

$logs = [
    'app.log' => '/var/log/jpesim/app.log',
    'ctv_fulfillment_poll.log' => '/var/log/jpesim/ctv_fulfillment_poll.log',
    'email_retry.log' => '/var/log/jpesim/email_retry.log',
    'provider_alert.log' => '/var/log/jpesim/provider_alert.log',
    'db_backup.log' => '/var/log/jpesim/db_backup.log',
];
$which = (string)($_GET['log'] ?? 'app.log');
if (!isset($logs[$which])) $which = 'app.log';
$linesParam = max(20, min(500, (int)($_GET['n'] ?? 100)));
$path = $logs[$which];

$content = '';
$err = null;
$mtime = 0;
$size = 0;
if (is_file($path)) {
    $mtime = (int)(@filemtime($path));
    $size = (int)(@filesize($path));
    if ($size > 0 && is_readable($path)) {
        // Tail using shell as the file may be large
        $cmd = 'tail -n ' . (int)$linesParam . ' ' . escapeshellarg($path) . ' 2>&1';
        $content = (string)shell_exec($cmd);
    } else {
        $err = 'Empty or unreadable.';
    }
} else {
    $err = 'File not found.';
}

admin_layout_header('Nhật ký hệ thống', $admin);
?>
<div class="card">
  <div class="filter-row" style="margin-bottom:12px">
    <span class="muted">Log file:</span>
    <?php foreach ($logs as $key => $_): ?>
      <a class="pill <?= $which === $key ? 'active' : '' ?>" href="?log=<?= htmlspecialchars($key) ?>&n=<?= $linesParam ?>"><?= htmlspecialchars($key) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="filter-row" style="margin-bottom:12px">
    <span class="muted">Số dòng:</span>
    <?php foreach ([50, 100, 200, 500] as $n): ?>
      <a class="pill <?= $linesParam === $n ? 'active' : '' ?>" href="?log=<?= htmlspecialchars($which) ?>&n=<?= $n ?>"><?= $n ?></a>
    <?php endforeach; ?>
  </div>
  <p class="muted" style="font-size:12px;margin-bottom:8px">
    <code><?= htmlspecialchars($path) ?></code>
    <?php if ($mtime > 0): ?> · <?= number_format($size) ?> bytes · cập nhật <?= htmlspecialchars(date('Y-m-d H:i:s', $mtime)) ?><?php endif; ?>
  </p>
  <?php if ($err): ?>
    <div class="flash err"><?= htmlspecialchars($err) ?></div>
  <?php else: ?>
    <pre style="white-space:pre-wrap;font-family:ui-monospace,monospace;font-size:12px;background:#0a1020;color:var(--a-ink-2);padding:12px;border-radius:8px;border:1px solid var(--a-line);max-height:60vh;overflow:auto;line-height:1.45"><?= htmlspecialchars($content) ?></pre>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
