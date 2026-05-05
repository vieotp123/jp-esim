<?php
declare(strict_types=1);

final class CtvMailer {
    public function sendVerifyEmail(string $email, string $token): bool {
        $base = (string)app_config('CTV_BASE_URL', app_config('APP_BASE_URL', 'https://jp-esim.vip'));
        $url = rtrim($base, '/') . '/ctv/verify-email.php?token=' . rawurlencode($token);
        $subject = 'Xác thực email CTV - jp-esim.vip';
        $html = '<p>Xin chào,</p>'
              . '<p>Cảm ơn bạn đã đăng ký tài khoản CTV (đại lý) của jp-esim.vip.</p>'
              . '<p>Vui lòng nhấn vào liên kết dưới đây để xác thực email và kích hoạt tài khoản:</p>'
              . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a></p>'
              . '<p>Nếu bạn không thực hiện đăng ký này, vui lòng bỏ qua email.</p>'
              . '<p>Trân trọng,<br>jp-esim.vip</p>';
        $alt = "Xác thực email CTV jp-esim.vip\n" . $url . "\n";
        return $this->sendBasic($email, $subject, $html, $alt);
    }

    public function sendPasswordResetEmail(string $email, string $token): bool {
        $base = (string)app_config('CTV_BASE_URL', app_config('APP_BASE_URL', 'https://jp-esim.vip'));
        $url = rtrim($base, '/') . '/ctv/reset-password.php?token=' . rawurlencode($token);
        $subject = 'Đặt lại mật khẩu CTV - jp-esim.vip';
        $html = '<p>Xin chào,</p>'
              . '<p>Bạn (hoặc ai đó) đã yêu cầu đặt lại mật khẩu cho tài khoản CTV.</p>'
              . '<p>Nhấn vào liên kết dưới đây để đặt mật khẩu mới (liên kết có hiệu lực 30 phút):</p>'
              . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a></p>'
              . '<p>Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.</p>'
              . '<p>Trân trọng,<br>jp-esim.vip</p>';
        $alt = "Đặt lại mật khẩu CTV jp-esim.vip\n" . $url . "\nLiên kết có hiệu lực 30 phút.\n";
        return $this->sendBasic($email, $subject, $html, $alt);
    }

    private function sendBasic(string $to, string $subject, string $html, string $alt): bool {
        if (!function_exists('curl_init')) { app_log('cURL missing, cannot send CTV email', 'ERROR'); return false; }
        $apiKey = (string)app_config('MAILGUN_API_KEY', '');
        $domain = (string)app_config('MAILGUN_DOMAIN', '');
        if ($apiKey === '' || $domain === '') {
            app_log('Mailgun not configured, CTV verify email skipped (token logged for manual delivery)', 'WARN');
            return false;
        }
        $region = strtoupper(trim((string)app_config('MAILGUN_REGION', '')));
        $endpoint = (($region === 'EU') ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net') . '/v3/' . $domain . '/messages';
        $fromEmail = (string)app_config('SMTP_FROM', 'noreply@' . $domain);
        $fromName = (string)app_config('SMTP_NAME', 'jp-esim.vip CTV');
        $from = $fromName . ' <' . $fromEmail . '>';
        $post = ['from' => $from, 'to' => $to, 'subject' => $subject, 'html' => $html, 'text' => $alt];
        $ch = curl_init();
        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERPWD => 'api:' . $apiKey,
            ]);
            $resp = (string)curl_exec($ch);
            $errno = curl_errno($ch);
            $err = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($errno !== 0) { app_log('CTV mail cURL ('.$errno.'): '.$err, 'ERROR'); return false; }
            if ($status < 200 || $status >= 300) {
                $short = function_exists('mb_substr') ? mb_substr($resp, 0, 500) : substr($resp, 0, 500);
                app_log('CTV mail HTTP '.$status.': '.$short, 'ERROR');
                return false;
            }
            return true;
        } catch (Throwable $e) {
            app_log('CTV mail exception '.$e->getMessage(), 'ERROR');
            return false;
        } finally { if ($ch) curl_close($ch); }
    }
}
