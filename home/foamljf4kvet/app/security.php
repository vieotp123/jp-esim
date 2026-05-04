<?php
declare(strict_types=1);
function security_headers(bool $html = false): void {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') { header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload'); }
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-site');
    if ($html) {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; frame-src https://www.google.com/recaptcha/; connect-src 'self' https://www.google.com/recaptcha/; base-uri 'self'; form-action 'self'; object-src 'none'; frame-ancestors 'none'");
        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if (str_starts_with($script, '/admin/') || str_starts_with($script, '/ctv/')) {
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }
    }
}
function rand_alnum(int $len): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; $out='';
    for ($i=0;$i<$len;$i++) $out .= $chars[random_int(0, strlen($chars)-1)];
    return $out;
}
function verify_recaptcha(string $token, string $action): bool {
    $secret = (string)app_config('RECAPTCHA_SECRET', app_config('RC_SEC', ''));
    if ($secret === '') return (bool)app_config('APP_DEBUG', false);
    if ($token === '') return false;
    $payload = ['secret' => $secret, 'response' => $token];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) { $payload['remoteip'] = $ip; }
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => http_build_query($payload),
    ]);
    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);
    $res = $raw ? json_decode($raw, true) : null;
    if (!is_array($res) || empty($res['success'])) {
        $codes = is_array($res['error-codes'] ?? null) ? implode(',', $res['error-codes']) : 'invalid-response';
        app_log('recaptcha fail action='.$action.' codes='.$codes.($curlErr ? ' curl='.$curlErr : ''), 'WARN');
        return false;
    }
    $host = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
    $rh = (string)($res['hostname'] ?? '');
    if ($rh !== '' && $host !== '' && strcasecmp($rh, $host) !== 0) {
        app_log('recaptcha hostname mismatch action='.$action.' host='.$host.' response_host='.$rh, 'WARN');
        return false;
    }
    return true;
}
function valid_email(string $email): bool { return (bool)filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190; }
function rate_limit(string $key, int $limit, int $seconds): bool {
    try {
        $pdo = db();
        $pdo->prepare('DELETE FROM chat_rate_limit WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)')->execute([$seconds]);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM chat_rate_limit WHERE identifier=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)');
        $stmt->execute([$key, $seconds]);
        if ((int)$stmt->fetchColumn() >= $limit) return false;
        $pdo->prepare('INSERT INTO chat_rate_limit(identifier, created_at) VALUES(?, NOW())')->execute([$key]);
    } catch (Throwable $e) { app_log('rate limit fail '.$e->getMessage(), 'WARN'); }
    return true;
}
