<?php
declare(strict_types=1);

/**
 * scripts/provider_error_alert.php
 *
 * Counts provider_error in order_admin_queue over the last 1h.
 * If above threshold AND no alert sent in the last cooldown window,
 * sends an email via Mailgun. Idempotent + cooldown-protected.
 *
 * Config keys (db_config.php / env):
 *   ALERT_EMAIL              recipient (fallback: SMTP_FROM)
 *   ALERT_PROVIDER_THRESHOLD count threshold (default 5)
 *   ALERT_COOLDOWN_MIN       minutes between alerts (default 30)
 *   MAILGUN_DOMAIN, MAILGUN_API_KEY, MAILGUN_REGION (us|eu)
 */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "must be run from CLI\n"); exit(1); }

$lockPath = '/run/lock/jpesim-alert.lock';
if (!is_dir(dirname($lockPath)) || !is_writable(dirname($lockPath))) {
    $lockPath = sys_get_temp_dir() . '/jpesim-alert.lock';
}
$lock = @fopen($lockPath, 'c');
if ($lock === false) { fwrite(STDERR, "lock open fail\n"); exit(1); }
if (!flock($lock, LOCK_EX | LOCK_NB)) exit(2);

try {
    require_once '/home/foamljf4kvet/app/bootstrap.php';
} catch (Throwable $e) {
    fwrite(STDERR, "bootstrap fail: " . $e->getMessage() . "\n"); exit(1);
}

$threshold = (int)app_config('ALERT_PROVIDER_THRESHOLD', 5);
$cooldownMin = (int)app_config('ALERT_COOLDOWN_MIN', 30);
$alertEmail = trim((string)app_config('ALERT_EMAIL', ''));
$tag = '[' . date('Y-m-d H:i:s') . '] provider_error_alert';

if ($alertEmail === '' || !filter_var($alertEmail, FILTER_VALIDATE_EMAIL)) {
    // No-op until user explicitly sets ALERT_EMAIL in db_config.php.
    app_log("$tag SKIP: ALERT_EMAIL not configured (set in db_config.php to enable)", 'DEBUG');
    exit(0);
}

$stateDir = '/var/lib/jpesim';
if (!is_dir($stateDir)) { @mkdir($stateDir, 0755, true); }
if (!is_writable($stateDir)) { $stateDir = sys_get_temp_dir(); }
$stateFile = $stateDir . '/last_provider_alert.ts';

try {
    $pdo = db();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM order_admin_queue WHERE kind='provider_error' AND created_at >= (NOW() - INTERVAL 1 HOUR)")->fetchColumn();
    $emailQ = (int)$pdo->query("SELECT COUNT(*) FROM order_admin_queue WHERE kind='email_error' AND created_at >= (NOW() - INTERVAL 1 HOUR)")->fetchColumn();
    $failedTopups = (int)$pdo->query("SELECT COUNT(*) FROM ctv_topup_orders WHERE status=3 AND needs_admin=1 AND created_at >= (NOW() - INTERVAL 1 HOUR)")->fetchColumn();

    $shouldAlert = $count >= $threshold;
    if (!$shouldAlert) {
        app_log("$tag OK provider_err_1h=$count threshold=$threshold (no alert)", 'DEBUG');
        exit(0);
    }

    $lastAlert = is_file($stateFile) ? (int)trim((string)@file_get_contents($stateFile)) : 0;
    if ($lastAlert > 0 && (time() - $lastAlert) < $cooldownMin * 60) {
        app_log("$tag SKIP cooldown ($cooldownMin min): provider_err_1h=$count last_alert=" . date('c', $lastAlert), 'INFO');
        exit(0);
    }

    $domain = trim((string)app_config('MAILGUN_DOMAIN', ''));
    $apiKey = trim((string)app_config('MAILGUN_API_KEY', ''));
    $region = strtolower((string)app_config('MAILGUN_REGION', 'us'));
    if ($domain === '' || $apiKey === '') {
        app_log("$tag FAIL: mailgun not configured", 'ERROR');
        exit(3);
    }
    $endpoint = $region === 'eu' ? 'https://api.eu.mailgun.net/v3/' : 'https://api.mailgun.net/v3/';
    $url = $endpoint . rawurlencode($domain) . '/messages';

    $from = trim((string)app_config('SMTP_FROM', 'noreply@' . $domain));
    $fromName = trim((string)app_config('SMTP_NAME', 'jp-esim alert'));
    $base = (string)app_config('APP_BASE_URL', 'https://jp-esim.vip');

    $subject = "[jp-esim ALERT] Provider errors in the last hour: $count (threshold $threshold)";
    $html = '<h3>jp-esim — provider error spike</h3>'
        . '<p>Trong 1 giờ vừa qua đã có <b>' . $count . '</b> lỗi provider (ngưỡng: ' . $threshold . ').</p>'
        . '<ul>'
        . '<li>Lỗi provider 1h: <b>' . $count . '</b></li>'
        . '<li>Lỗi email 1h: ' . $emailQ . '</li>'
        . '<li>Topup thất bại 1h (cần admin): ' . $failedTopups . '</li>'
        . '</ul>'
        . '<p>Truy cập <a href="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '/admin/ctv/queue.php">queue admin</a> để xử lý.</p>'
        . '<p style="color:#888;font-size:12px">Alert có cooldown ' . $cooldownMin . ' phút để tránh spam.</p>';
    $text = "jp-esim provider errors in the last hour: $count (threshold $threshold)\n"
        . "email_errors_1h=$emailQ failed_topups_1h=$failedTopups\n"
        . "Open: $base/admin/ctv/queue.php\n";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => 'api:' . $apiKey,
        CURLOPT_POSTFIELDS => http_build_query([
            'from' => $fromName . ' <' . $from . '>',
            'to' => $alertEmail,
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
            'o:tag' => 'jpesim-alert',
        ]),
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($code >= 200 && $code < 300) {
        @file_put_contents($stateFile, (string)time());
        echo "$tag SENT alert to $alertEmail count=$count\n";
        app_log("$tag SENT alert to $alertEmail count=$count", 'INFO');
        exit(0);
    }
    fwrite(STDERR, "$tag MAILGUN_FAIL code=$code err=$err resp=" . substr((string)$resp, 0, 200) . "\n");
    app_log("$tag MAILGUN_FAIL code=$code err=$err", 'ERROR');
    exit(3);
} catch (Throwable $e) {
    fwrite(STDERR, "$tag EXCEPTION: " . $e->getMessage() . "\n");
    app_log("$tag EXCEPTION " . $e->getMessage(), 'ERROR');
    exit(3);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
