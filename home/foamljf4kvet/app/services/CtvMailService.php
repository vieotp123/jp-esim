<?php
declare(strict_types=1);

/**
 * CtvMailService — send eSIM delivery emails for CTV orders.
 *
 * Notes:
 * - QR is rendered self-host from ctv_esims.ac (LPA) via QrService;
 *   no provider URL or branding is leaked in the email.
 * - The email body never prints the raw LPA string — only ICCID + QR image.
 * - Idempotent: an eSIM row is only emailed once (email_sent_at NOT NULL).
 *   Failures bump email_attempts and set email_last_error but do NOT
 *   change order status.
 * - "Safe mode" (CTV_MAIL_SAFE_MODE=1 in app config) hard-redirects all
 *   recipients to CTV_MAIL_SAFE_TO so we can rehearse without spamming.
 */
final class CtvMailService {

    /** Send all unsent eSIM emails for one CTV order. Returns counts. */
    public function sendForOrderIfNeeded(string $ctvOrderId): array {
        $pdo = db();
        $ctvOrderId = strtoupper(trim($ctvOrderId));
        if ($ctvOrderId === '') return ['sent' => 0, 'skipped' => 0, 'errors' => 0];

        $st = $pdo->prepare('SELECT * FROM ctv_orders WHERE ctv_order_id=? LIMIT 1');
        $st->execute([$ctvOrderId]);
        $order = $st->fetch();
        if (!$order) return ['sent' => 0, 'skipped' => 0, 'errors' => 0, 'reason' => 'no_order'];

        $to = trim((string)($order['email'] ?? ''));
        if ($to === '' || !$this->validEmail($to)) {
            return ['sent' => 0, 'skipped' => 0, 'errors' => 0, 'reason' => 'no_email'];
        }

        $st2 = $pdo->prepare('SELECT * FROM ctv_esims WHERE ctv_order_id=? AND iccid IS NOT NULL AND iccid<>"" AND ac IS NOT NULL AND ac<>"" AND email_sent_at IS NULL');
        $st2->execute([$ctvOrderId]);
        $esims = $st2->fetchAll();
        if (empty($esims)) return ['sent' => 0, 'skipped' => 0, 'errors' => 0, 'reason' => 'nothing_pending'];

        $sent = 0; $errors = 0;
        foreach ($esims as $row) {
            try {
                if ($this->sendOneEsim($order, $row, $to)) {
                    $pdo->prepare('UPDATE ctv_esims SET email_sent_at=NOW(), email_attempts=email_attempts+1, email_last_error=NULL WHERE id=?')
                        ->execute([(int)$row['id']]);
                    $sent++;
                } else {
                    $pdo->prepare('UPDATE ctv_esims SET email_attempts=email_attempts+1, email_last_error=? WHERE id=?')
                        ->execute(['mailgun_post_failed', (int)$row['id']]);
                    $errors++;
                }
            } catch (Throwable $e) {
                $err = substr($e->getMessage(), 0, 240);
                $pdo->prepare('UPDATE ctv_esims SET email_attempts=email_attempts+1, email_last_error=? WHERE id=?')
                    ->execute([$err, (int)$row['id']]);
                if (function_exists('app_log')) app_log('CtvMail FAIL ord='.$ctvOrderId.' iccid='.$row['iccid'].' err='.$err, 'ERROR');
                $errors++;
            }
        }
        return ['sent' => $sent, 'skipped' => 0, 'errors' => $errors];
    }

    private function sendOneEsim(array $order, array $row, string $rawTo): bool {
        $iccid = (string)$row['iccid'];
        $lpa   = (string)$row['ac'];
        $orderId = (string)$order['ctv_order_id'];

        // Render PNG locally — no provider URL, no inline branding.
        $tmp = tempnam(sys_get_temp_dir(), 'ctvqr_');
        if ($tmp === false) throw new RuntimeException('tempnam failed');
        $pngPath = $tmp . '.png';
        @unlink($tmp);
        QrService::pngToFile($lpa, $pngPath, 8, 2, 'M');

        try {
            // Safe mode redirect (rehearsal without spamming real customers).
            $to = $rawTo;
            if ((string)app_config('CTV_MAIL_SAFE_MODE','0') === '1') {
                $safeTo = trim((string)app_config('CTV_MAIL_SAFE_TO',''));
                if ($safeTo !== '' && $this->validEmail($safeTo)) {
                    $to = $safeTo;
                }
            }

            [$html, $alt] = $this->buildHtml($orderId, $iccid, $row, $order, basename($pngPath));

            $subject = 'eSIM của bạn đã sẵn sàng – Đơn ' . $orderId;
            $cidName = 'qr_' . substr(hash('sha256', $iccid), 0, 12) . '.png';
            $attach  = [new CURLFile($pngPath, 'image/png', $cidName)];

            return $this->postMailgun($to, $subject, $html, $alt, $attach, $cidName);
        } finally {
            if (is_file($pngPath)) @unlink($pngPath);
        }
    }

    private function postMailgun(string $to, string $subject, string $html, string $alt, array $inline, string $cidFromName): bool {
        if (!function_exists('curl_init')) { app_log('cURL not enabled', 'ERROR'); return false; }
        $apiKey = (string)app_config('MAILGUN_API_KEY','');
        $domain = (string)app_config('MAILGUN_DOMAIN','');
        if ($apiKey === '' || $domain === '') { app_log('Mailgun not configured', 'ERROR'); return false; }
        $region = strtoupper((string)app_config('MAILGUN_REGION',''));
        $endpoint = (($region === 'EU') ? 'https://api.eu.mailgun.net' : 'https://api.mailgun.net') . '/v3/' . $domain . '/messages';
        $fromName  = (string)app_config('SMTP_NAME','jp-esim.vip');
        $fromEmail = (string)app_config('SMTP_FROM','noreply@'.$domain);
        // Replace cid:<placeholder> in html with the CURLFile remote name.
        $html = str_replace('cid:__QR__', 'cid:' . $cidFromName, $html);

        $post = [
            'from'    => $fromName.' <'.$fromEmail.'>',
            'to'      => $to,
            'subject' => $subject,
            'text'    => $alt,
            'html'    => $html,
            'h:X-Auto-Source' => 'jp-esim/ctv',
        ];
        foreach ($inline as $i => $f) $post['inline['.$i.']'] = $f;

        // Dry-run: short-circuit before hitting Mailgun (for safe rehearsal).
        if ((string)app_config('CTV_MAIL_DRY_RUN', '0') === '1') {
            app_log('CtvMail DRY_RUN to='.$to.' subject='.$subject.' size='.strlen($html), 'INFO');
            return true;
        }
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
            if ($errno !== 0) { app_log('CtvMail cURL error('.$errno.'): '.$err, 'ERROR'); return false; }
            if ($status < 200 || $status >= 300) {
                app_log('CtvMail HTTP '.$status.': '.substr($resp, 0, 400), 'ERROR');
                return false;
            }
            return true;
        } finally {
            if ($ch) curl_close($ch);
        }
    }

    private function validEmail(string $e): bool {
        return (bool)filter_var($e, FILTER_VALIDATE_EMAIL);
    }

    private function buildHtml(string $orderId, string $iccid, array $row, array $order, string $cidName): array {
        // We embed QR via cid:__QR__ placeholder — postMailgun rewrites it.
        $bg='#f5f7fb'; $card='#ffffff'; $text='#111827'; $muted='#64748b'; $line='#e5e7eb';
        $brand='jp-esim.vip';
        $plan = htmlspecialchars((string)($row['package_name'] ?? $order['plan_name'] ?? ''));
        $expired = htmlspecialchars((string)($row['expired_time'] ?? '—'));
        $iccidH = htmlspecialchars($iccid);
        $orderIdH = htmlspecialchars($orderId);

        $css = 'margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;';
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>eSIM '.$orderIdH.'</title></head>';
        $html .= '<body style="'.$css.'background:'.$bg.';color:'.$text.';line-height:1.55;">';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:'.$bg.';">';
        $html .= '<tr><td align="center" style="padding:22px;">';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:'.$card.';border:1px solid '.$line.';border-radius:16px;padding:24px;">';
        $html .= '<tr><td>';
        $html .= '<h1 style="font-size:22px;font-weight:800;margin:0 0 6px;">Xin chào,</h1>';
        $html .= '<p style="font-size:15px;color:'.$muted.';margin:0 0 16px;">Đơn hàng <strong style="color:'.$text.'">'.$orderIdH.'</strong> của bạn đã sẵn sàng.</p>';

        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5ff;border:1px solid '.$line.';border-radius:14px;padding:18px;margin-bottom:16px;">';
        $html .= '<tr><td>';
        $html .= '<div style="margin-bottom:12px;"><span style="display:inline-block;background:#eaf1ff;color:#0b57d0;font-weight:700;font-size:14px;padding:6px 10px;border-radius:10px;">eSIM của bạn</span></div>';
        $html .= '<div style="margin-bottom:12px;"><span style="color:'.$muted.';font-size:13px;margin-right:8px;">ICCID:</span><span style="background:#fff;border:1px solid '.$line.';padding:8px 12px;border-radius:10px;font-weight:700;letter-spacing:.3px;">'.$iccidH.'</span></div>';
        if ($plan !== '')   $html .= '<div style="margin-bottom:6px;"><span style="color:'.$muted.';font-size:13px;margin-right:8px;">Gói:</span> '.$plan.'</div>';
        if ($expired !== '—' && $expired !== '') $html .= '<div style="margin-bottom:12px;"><span style="color:'.$muted.';font-size:13px;margin-right:8px;">Hết hạn:</span> '.$expired.'</div>';
        $html .= '<div align="center" style="background:#fff;border:1px solid '.$line.';border-radius:14px;padding:14px;">';
        $html .= '<img src="cid:__QR__" alt="QR eSIM" width="320" height="320" style="display:block;width:320px;height:320px;border-radius:8px;">';
        $html .= '</div>';
        $html .= '</td></tr></table>';

        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4ff;border:1px solid '.$line.';border-radius:14px;padding:14px;margin-bottom:14px;">';
        $html .= '<tr><td><p style="margin:0;font-size:14px;"><strong>Cách kích hoạt:</strong> mở Cài đặt → Di động/SIM di động → Thêm eSIM → Sử dụng mã QR và quét mã trong email này. Trên iPhone iOS 17.4+, bạn có thể nhấn giữ trực tiếp vào ảnh QR và chọn <strong>“Thêm eSIM”</strong>.</p></td></tr></table>';

        $html .= '<p style="margin-top:16px;color:'.$muted.';font-size:13px;">Nếu cần hỗ trợ, vui lòng phản hồi email này.<br>Trân trọng,<br><strong style="color:'.$text.'">'.$brand.'</strong></p>';
        $html .= '</td></tr></table>';
        $html .= '</td></tr></table></body></html>';

        $alt = "Xin chao,\n";
        $alt .= "Don hang ".$orderId." cua ban da san sang.\n";
        $alt .= "ICCID: ".$iccid."\n";
        if ($plan !== '') $alt .= "Goi: ".strip_tags($plan)."\n";
        $alt .= "QR cai dat duoc dinh kem trong email.\n";
        return [$html, $alt];
    }
}
