<?php
declare(strict_types=1);

/**
 * scripts/ctv_fulfillment_poll.php
 *
 * Cron-driven background poll: sync QR/ICCID for any successful CTV order
 * still missing them. Idempotent — safe to run every 1–2 minutes.
 *
 * Calls EsimAccessClient::queryOrder only. Does NOT place new orders or topups.
 *
 * Usage:
 *   php /home/levanrin2404/esimtravel/scripts/ctv_fulfillment_poll.php [--limit=50] [--max-age=1440]
 *
 * Exit codes:
 *   0  ok
 *   1  bootstrap failed
 *   2  another instance already running (lock contention; not an error)
 *   3  uncaught exception during sync
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "must be run from CLI\n");
    exit(1);
}

// ---- arg parsing -----------------------------------------------------------
$limit = 50;
$maxAge = 1440; // minutes (24h default)
foreach ($argv as $a) {
    if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = max(1, min(500, (int)$m[1]));
    elseif (preg_match('/^--max-age=(\d+)$/', $a, $m)) $maxAge = max(1, (int)$m[1]);
}

// ---- single-instance lock --------------------------------------------------
$lockPath = '/var/lock/jpesim-ctv-fulfillment-poll.lock';
$lockDir  = dirname($lockPath);
if (!is_dir($lockDir) || !is_writable($lockDir)) {
    // fall back to /tmp if /var/lock is not writable for our user
    $lockPath = sys_get_temp_dir() . '/jpesim-ctv-fulfillment-poll.lock';
}
$lockFh = @fopen($lockPath, 'c');
if ($lockFh === false) {
    fwrite(STDERR, "could not open lock $lockPath\n");
    exit(1);
}
if (!flock($lockFh, LOCK_EX | LOCK_NB)) {
    // Another instance is running — silent exit (cron will retry).
    exit(2);
}

// ---- bootstrap app ---------------------------------------------------------
try {
    require_once '/home/foamljf4kvet/app/bootstrap.php';
} catch (Throwable $e) {
    fwrite(STDERR, "bootstrap fail: " . $e->getMessage() . "\n");
    exit(1);
}

$start = microtime(true);
$logTag = '[' . date('Y-m-d H:i:s') . '] ctv_fulfillment_poll';

try {
    $svc = new CtvFulfillmentService();
    $r = $svc->syncPendingGlobal($limit, $maxAge);
    $dur = (int)round((microtime(true) - $start) * 1000);
    $testMode = CtvProviderClient::isTestMode() ? 'TEST' : 'LIVE';
    $line = sprintf(
        "%s mode=%s ready=%d processing=%d skipped=%d failed=%d limit=%d max_age=%dm dur_ms=%d",
        $logTag, $testMode,
        (int)($r['ready'] ?? 0), (int)($r['processing'] ?? 0),
        (int)($r['skipped'] ?? 0), (int)($r['failed'] ?? 0),
        $limit, $maxAge, $dur
    );
    // Only print to stdout when there is something to report to keep cron logs clean.
    $hadWork = ($r['ready'] ?? 0) + ($r['processing'] ?? 0) + ($r['failed'] ?? 0) > 0;
    if ($hadWork) {
        echo $line . "\n";
    }
    // Always log to app_log (rotates via app's logger).
    if (function_exists('app_log')) {
        app_log($line, $hadWork ? 'INFO' : 'DEBUG');
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $logTag . " EXCEPTION: " . $e->getMessage() . "\n");
    if (function_exists('app_log')) {
        app_log('ctv_fulfillment_poll EXCEPTION ' . $e->getMessage(), 'ERROR');
    }
    exit(3);
} finally {
    flock($lockFh, LOCK_UN);
    fclose($lockFh);
}
