<?php
declare(strict_types=1);

/**
 * scripts/email_queue_retry.php
 *
 * Retry sending QR emails for ctv_esims rows where email_sent_at IS NULL,
 * email_last_error is set, and email_attempts < threshold. Idempotent.
 *
 * Usage: php email_queue_retry.php [--limit=20] [--max-attempts=5] [--max-age-days=7]
 * Exit codes: 0 ok | 1 bootstrap fail | 2 lock contention | 3 exception
 */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "must be run from CLI\n"); exit(1); }

$limit = 20;
$maxAttempts = 5;
$maxAgeDays = 7;
foreach ($argv as $a) {
    if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = max(1, min(200, (int)$m[1]));
    elseif (preg_match('/^--max-attempts=(\d+)$/', $a, $m)) $maxAttempts = max(1, (int)$m[1]);
    elseif (preg_match('/^--max-age-days=(\d+)$/', $a, $m)) $maxAgeDays = max(1, (int)$m[1]);
}

$lockPath = '/var/lock/jpesim-email-retry.lock';
if (!is_dir(dirname($lockPath)) || !is_writable(dirname($lockPath))) {
    $lockPath = sys_get_temp_dir() . '/jpesim-email-retry.lock';
}
$lock = @fopen($lockPath, 'c');
if ($lock === false) { fwrite(STDERR, "lock open fail\n"); exit(1); }
if (!flock($lock, LOCK_EX | LOCK_NB)) { exit(2); }

try {
    require_once '/home/foamljf4kvet/app/bootstrap.php';
} catch (Throwable $e) {
    fwrite(STDERR, "bootstrap fail: " . $e->getMessage() . "\n"); exit(1);
}

$start = microtime(true);
$tag = '[' . date('Y-m-d H:i:s') . '] email_queue_retry';
try {
    $pdo = db();
    $st = $pdo->prepare(
        "SELECT DISTINCT ctv_order_id FROM ctv_esims
         WHERE email_sent_at IS NULL
           AND email_attempts < ?
           AND created_at >= (NOW() - INTERVAL ? DAY)
           AND iccid IS NOT NULL AND iccid <> ''
           AND ac IS NOT NULL AND ac <> ''
         ORDER BY id DESC LIMIT ?"
    );
    $st->bindValue(1, $maxAttempts, PDO::PARAM_INT);
    $st->bindValue(2, $maxAgeDays, PDO::PARAM_INT);
    $st->bindValue(3, $limit, PDO::PARAM_INT);
    $st->execute();
    $orderIds = array_map(fn($r) => (string)$r['ctv_order_id'], $st->fetchAll());

    $svc = new CtvMailService();
    $sent = 0; $errors = 0; $orders = 0; $reasons = [];
    foreach ($orderIds as $oid) {
        $orders++;
        try {
            $r = $svc->sendForOrderIfNeeded($oid);
            $sent += (int)($r['sent'] ?? 0);
            $errors += (int)($r['errors'] ?? 0);
            if (!empty($r['reason'])) {
                $reasons[(string)$r['reason']] = ($reasons[(string)$r['reason']] ?? 0) + 1;
            }
        } catch (Throwable $e) {
            $errors++;
            app_log("email_queue_retry: order=$oid err=" . $e->getMessage(), 'ERROR');
        }
    }
    $dur = (int)round((microtime(true) - $start) * 1000);
    $reasonsStr = $reasons ? ' reasons=' . json_encode($reasons) : '';
    $line = sprintf("%s orders=%d sent=%d errors=%d limit=%d max_attempts=%d dur_ms=%d%s", $tag, $orders, $sent, $errors, $limit, $maxAttempts, $dur, $reasonsStr);
    if ($orders > 0) echo $line . "\n";
    app_log($line, $orders > 0 ? 'INFO' : 'DEBUG');
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $tag . " EXCEPTION: " . $e->getMessage() . "\n");
    app_log("email_queue_retry EXCEPTION " . $e->getMessage(), 'ERROR');
    exit(3);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
