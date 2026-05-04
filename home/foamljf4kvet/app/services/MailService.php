<?php
declare(strict_types=1);

final class MailService {
    public function sendOrderIfNeeded(string $orderId): bool {
        $pdo = db();
        $orderId = strtoupper(trim($orderId));
        if ($orderId === '') return false;
        if (!$this->acquireLock('mail_', $orderId)) return false;
        try {
            $st = $pdo->prepare('SELECT order_id,email,ratinghash,emailsent FROM `order` WHERE order_id=? LIMIT 1');
            $st->execute([$orderId]);
            $order = $st->fetch();
            if (!$order || (int)($order['emailsent'] ?? 0) === 1) return (bool)$order;
            $to = (string)($order['email'] ?? '');
            if ($to === '' || !valid_email($to)) { app_log('MAIL FAIL '.$orderId.' - invalid email','ERROR'); return false; }
            $q = $pdo->prepare('SELECT iccid, qrCodeUrl, ac FROM esimlist WHERE BINARY order_id = BINARY ?');
            $q->execute([$orderId]);
            $esims = $q->fetchAll();
            if (empty($esims)) { app_log('MAIL FAIL '.$orderId.' - empty esimlist','ERROR'); return false; }
            $ok = $this->sendOrderMail($to, $orderId, $esims, (string)($order['ratinghash'] ?? ''));
            if ($ok) {
                $pdo->prepare('UPDATE `order` SET emailsent=1, updated_at=NOW() WHERE order_id=? AND emailsent=0')->execute([$orderId]);
                app_log('MAIL OK '.$orderId.' -> '.$to,'INFO');
                return true;
            }
            return false;
        } finally { $this->releaseLock('mail_', $orderId); }
    }

    public function sendTopupIfNeeded(string $tid): bool {
        $pdo = db();
        $tid = strtoupper(trim($tid));
        if ($tid === '') return false;
        if (!$this->acquireLock('mail_topup_', $tid)) return false;
        try {
            $st = $pdo->prepare('SELECT t.*, p.plan AS plan_name, p.telecom FROM topup_order t LEFT JOIN plan p ON p.id=t.plan_id WHERE t.tid=? LIMIT 1');
            $st->execute([$tid]);
            $topup = $st->fetch();
            if (!$topup || (int)($topup['emailsent'] ?? 0) === 1) return (bool)$topup;
            if ((int)($topup['topup_status'] ?? 0) < 1 && (int)($topup['status'] ?? 0) < 2) return false;
            $to = (string)($topup['email'] ?? '');
            if ($to === '' || !valid_email($to)) { app_log('TOPUP MAIL FAIL '.$tid.' - invalid email','ERROR'); return false; }
            [$html, $alt] = $this->buildTopupHtml($topup);
            $ok = $this->sendBasicMail($to, 'Nạp data thành công – Đơn '.$tid, $html, $alt);
            if ($ok) {
                $pdo->prepare('UPDATE topup_order SET emailsent=1, updated_at=NOW() WHERE tid=? AND emailsent=0')->execute([$tid]);
                app_log('TOPUP MAIL OK '.$tid.' -> '.$to,'INFO');
                return true;
            }
            return false;
        } finally { $this->releaseLock('mail_topup_', $tid); }
    }

    private function acquireLock(string $prefix, string $id): bool {
        try { $stmt = db()->prepare('SELECT GET_LOCK(CONCAT(?, ?), 0) AS got'); $stmt->execute([$prefix, $id]); return (int)$stmt->fetchColumn() === 1; }
        catch (Throwable $e) { app_log('MAIL lock error '.$e->getMessage(), 'ERROR'); return true; }
    }
    private function releaseLock(string $prefix, string $id): void { try { $stmt = db()->prepare('SELECT RELEASE_LOCK(CONCAT(?, ?))'); $stmt->execute([$prefix, $id]); } catch (Throwable $e) {} }
    private function mailgunEndpoint(): string { $domain=trim((string)app_config('MAILGUN_DOMAIN','')); if($domain==='') throw new RuntimeException('MAILGUN_DOMAIN missing'); $region=strtoupper(trim((string)app_config('MAILGUN_REGION',''))); return (($region==='EU')?'https://api.eu.mailgun.net':'https://api.mailgun.net').'/v3/'.$domain.'/messages'; }
    private function from(): string { $domain=(string)app_config('MAILGUN_DOMAIN',''); $fromEmail=(string)app_config('SMTP_FROM',$domain!==''?'noreply@'.$domain:'noreply@jp-esim.vip'); $fromName=(string)app_config('SMTP_NAME','jp-esim.vip'); return $fromName.' <'.$fromEmail.'>'; }

    private function sendBasicMail(string $to, string $subject, string $html, string $alt, array $inlineFiles=[]): bool {
        if (!function_exists('curl_init')) { app_log('cURL not enabled','ERROR'); return false; }
        $apiKey=(string)app_config('MAILGUN_API_KEY',''); if($apiKey===''){ app_log('MAILGUN_API_KEY missing','ERROR'); return false; }
        $post=['from'=>$this->from(),'to'=>$to,'subject'=>$subject,'text'=>$alt,'html'=>$html];
        foreach($inlineFiles as $i=>$curlFile) $post['inline['.$i.']']=$curlFile;
        $ch=curl_init();
        try {
            curl_setopt_array($ch,[CURLOPT_URL=>$this->mailgunEndpoint(),CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_USERPWD=>'api:'.$apiKey]);
            $resp=(string)curl_exec($ch); $errno=curl_errno($ch); $err=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
            if($errno!==0){ app_log('MAILGUN cURL error ('.$errno.'): '.$err,'ERROR'); return false; }
            if($status<200 || $status>=300){ $short=function_exists('mb_substr')?mb_substr($resp,0,500):substr($resp,0,500); app_log('MAILGUN HTTP '.$status.': '.$short,'ERROR'); return false; }
            return true;
        } catch(Throwable $e){ app_log('MAILGUN exception: '.$e->getMessage(),'ERROR'); return false; }
        finally { if($ch) curl_close($ch); }
    }

    private function sendOrderMail(string $to, string $orderId, array $esims, string $ratinghash): bool {
        $temps=[]; $blocks=[]; $files=[];
        foreach($esims as $idx=>$e){
            $iccid=(string)($e['iccid']??''); $lpa=(string)($e['ac']??''); if($iccid==='') continue;
            $tmp=tempnam(sys_get_temp_dir(),'qr_'); if($tmp===false) continue; $tmpPng=$tmp.'.png'; @unlink($tmp);
            $bytes=null;
            try { if($lpa!=='' && stripos($lpa,'LPA:')===0){ $bytes=QrService::pngBytes($lpa,8,2,'M'); } }
            catch(Throwable $ex){ app_log('QR self-render failed (mail) iccid='.$iccid.' '.$ex->getMessage(),'ERROR'); }
            // Fallback: legacy provider URL download (kept for orders with no LPA stored).
            if($bytes===null){
                $qrUrl=(string)($e['qrCodeUrl']??'');
                if($qrUrl==='' || !$this->downloadFile($qrUrl,$tmpPng)){ if(is_file($tmpPng)) @unlink($tmpPng); continue; }
            } else {
                if(@file_put_contents($tmpPng,$bytes)===false){ continue; }
            }
            $remote='qr_'.($idx+1).'_'.substr(hash('sha256',$iccid),0,10).'.png';
            $temps[]=$tmpPng; $blocks[]=['iccid'=>$iccid,'cid'=>$remote]; $files[]=new CURLFile($tmpPng,'image/png',$remote);
        }
        if(empty($blocks)){ foreach($temps as $f) @unlink($f); return false; }
        [$html,$alt]=$this->buildAutoThemeHtml($orderId,$blocks);
        $ok=$this->sendBasicMail($to,'Thông tin eSIM – Đơn hàng '.$orderId,$html,$alt,$files);
        foreach($temps as $f) @unlink($f);
        return $ok;
    }

    private function downloadFile(string $url, string $dest): bool {
        if(function_exists('curl_init')){ $ch=curl_init($url); $fp=@fopen($dest,'wb'); if(!$fp){ curl_close($ch); return false; } curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true]); $ok=curl_exec($ch)!==false; $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch); fclose($fp); if(!$ok||($status>=400&&$status!==0)){ app_log('QR download failed HTTP '.$status.' '.$err,'ERROR'); @unlink($dest); return false; } return is_file($dest)&&filesize($dest)>0; }
        return @copy($url,$dest)&&is_file($dest)&&filesize($dest)>0;
    }

    // Kept visually identical to the cron_retry.php template supplied by owner.
    private function buildAutoThemeHtml(string $orderId, array $blocks): array {
        $css   = 'margin:0;padding:0;border:0;outline:0;vertical-align:baseline;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;';
        $bg    = '#f5f7fb'; $card  = '#ffffff'; $text  = '#111827'; $muted = '#64748b'; $line  = '#e5e7eb'; $chipB = '#eaf1ff'; $chipT = '#0b57d0'; $soft  = '#f1f5ff'; $btn   = '#0d6efd';
        $html = '<!doctype html><html><head>';
        $html .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">';
        $html .= '<title>Thông tin eSIM – Đơn hàng ' . htmlspecialchars($orderId) . '</title></head>';
        $html .= '<body style="' . $css . ' background:' . $bg . '; color:' . $text . '; line-height:1.55;">';
        $html .= '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="' . $css . ' background:' . $bg . ';"><tr><td align="center" style="' . $css . ' padding:22px;">';
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="' . $css . ' max-width:640px; background:' . $card . '; border-radius:16px; border:1px solid ' . $line . '; padding:22px;"><tr><td style="' . $css . '">';
        $html .= '<h1 style="' . $css . ' font-size:22px; font-weight:800; margin-bottom:8px;">Xin chào,</h1>';
        $html .= '<p style="' . $css . ' font-size:15px; color:' . $muted . '; margin-bottom:18px;">Đơn hàng <strong style="color:' . $text . '; letter-spacing:.3px;">' . htmlspecialchars($orderId) . '</strong> đã được xử lý:</p>';
        foreach ($blocks as $i => $b) {
            $iccid = isset($b['iccid']) ? $b['iccid'] : ''; $cid = isset($b['cid']) ? $b['cid'] : '';
            $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="' . $css . ' background:' . $soft . '; border:1px solid ' . $line . '; padding:16px; border-radius:14px; margin-bottom:16px;"><tr><td style="' . $css . '">';
            $html .= '<span style="' . $css . ' display:inline-block; background:' . $chipB . '; color:' . $chipT . '; font-weight:700; font-size:14px; padding:6px 10px; border-radius:10px; margin-bottom:12px;">eSIM #' . ($i+1) . '</span>';
            $html .= '<div style="' . $css . ' margin:0 0 12px;"><span style="' . $css . ' display:inline-block; color:' . $muted . '; font-size:13px; margin-right:8px;">ICCID:</span><span style="' . $css . ' display:inline-block; background:#fff; border:1px solid ' . $line . '; padding:8px 12px; border-radius:10px; font-weight:700; letter-spacing:.3px;">' . htmlspecialchars($iccid) . '</span></div>';
            $html .= '<div style="' . $css . ' background:#fff; border:1px solid ' . $line . '; padding:12px; border-radius:14px;" align="center"><img src="cid:' . htmlspecialchars($cid) . '" width="320" height="320" alt="QR eSIM" style="' . $css . ' display:block; width:320px; height:320px; border-radius:8px;"></div>';
            $html .= '</td></tr></table>';
        }
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="' . $css . ' background:#eef4ff; border:1px solid ' . $line . '; padding:16px; border-radius:14px; margin:6px 0 18px;"><tr><td style="' . $css . '"><p style="' . $css . ' margin:0;"><strong>Mẹo kích hoạt nhanh:</strong> Trên iPhone chạy iOS 17.4 trở lên, bạn có thể <strong>nhấn giữ</strong> vào mã QR trong email và chọn <strong>\"Thêm eSIM\"</strong> để cài đặt ngay!</p></td></tr></table>';
        $html .= '<p style="' . $css . ' margin-top:18px; color:' . $muted . ';">Trân trọng!<br><strong style="color:' . $text . '">Đội ngũ MUAESIM.VN</strong></p>';
        $html .= '</td></tr></table></td></tr></table></body></html>';
        $alt = "Xin chào,\nĐơn hàng " . $orderId . " đã được xử lý.\n"; foreach ($blocks as $i => $b) { $iccid = isset($b['iccid']) ? $b['iccid'] : ''; $alt .= "eSIM #" . ($i+1) . "\nICCID: " . $iccid . "\n"; } $alt .= "\nMẹo kích hoạt nhanh: iPhone iOS 17.4+, nhấn giữ vào QR và chọn \"Thêm eSIM\".\n";
        return [$html, $alt];
    }

    private function buildTopupHtml(array $topup): array {
        $css='margin:0;padding:0;border:0;outline:0;vertical-align:baseline;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif;';
        $bg='#f5f7fb'; $card='#ffffff'; $text='#111827'; $muted='#64748b'; $line='#e5e7eb'; $chipB='#eaf1ff'; $chipT='#0b57d0'; $soft='#f1f5ff'; $btn='#0d6efd';
        $tid=(string)($topup['tid']??''); $iccid=(string)($topup['iccid']??''); $plan=trim((string)($topup['plan_name']??'')) ?: ((int)($topup['gb']??0).'GB'); $telecom=(string)($topup['telecom']??''); $amount=number_format((float)($topup['price']??0)).'₫'; $paidAt=(string)($topup['paid_at']??''); $expired=(string)($topup['expired_time']??''); $gb=(int)($topup['gb']??0);
        $html='<!doctype html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark"><title>Nạp data thành công – Đơn '.htmlspecialchars($tid).'</title></head>';
        $html.='<body style="'.$css.' background:'.$bg.'; color:'.$text.'; line-height:1.55;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="'.$css.' background:'.$bg.';"><tr><td align="center" style="'.$css.' padding:22px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="'.$css.' max-width:640px; background:'.$card.'; border-radius:16px; border:1px solid '.$line.'; padding:22px;"><tr><td style="'.$css.'">';
        $html.='<h1 style="'.$css.' font-size:22px; font-weight:800; margin-bottom:8px;">Xin chào,</h1><p style="'.$css.' font-size:15px; color:'.$muted.'; margin-bottom:18px;">Đơn nạp data <strong style="color:'.$text.'; letter-spacing:.3px;">'.htmlspecialchars($tid).'</strong> đã được xử lý:</p>';
        $html.='<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="'.$css.' background:'.$soft.'; border:1px solid '.$line.'; padding:16px; border-radius:14px; margin-bottom:16px;"><tr><td style="'.$css.'"><span style="'.$css.' display:inline-block; background:'.$chipB.'; color:'.$chipT.'; font-weight:700; font-size:14px; padding:6px 10px; border-radius:10px; margin-bottom:12px;">Nạp thêm data</span>';
        $pairs=['ICCID'=>$iccid,'Gói nạp'=>trim($telecom.' '.$plan),'Dung lượng'=>$gb>0?($gb.'GB'):'','Số tiền'=>$amount,'Ngày thanh toán'=>$paidAt,'Hạn hiện tại'=>$expired];
        foreach($pairs as $label=>$value){ if($value==='') continue; $html.='<div style="'.$css.' margin:0 0 10px;"><span style="'.$css.' display:inline-block; color:'.$muted.'; font-size:13px; margin-right:8px; min-width:110px;">'.htmlspecialchars($label).':</span><span style="'.$css.' display:inline-block; background:#fff; border:1px solid '.$line.'; padding:8px 12px; border-radius:10px; font-weight:700; letter-spacing:.3px;">'.htmlspecialchars((string)$value).'</span></div>'; }
        $html.='</td></tr></table><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="'.$css.' background:#eef4ff; border:1px solid '.$line.'; padding:16px; border-radius:14px; margin:6px 0 18px;"><tr><td style="'.$css.'"><p style="'.$css.' margin:0;"><strong>Lưu ý:</strong> Data thường được cộng vào eSIM trong vài phút. Bạn có thể bật/tắt chế độ máy bay hoặc khởi động lại máy nếu thiết bị chưa cập nhật dung lượng mới.</p></td></tr></table>';
        $html.='<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="'.$css.' background:#f2f6ff; border:1px solid '.$line.'; padding:18px; border-radius:14px;"><tr><td align="center" style="'.$css.'"><div style="'.$css.' color:'.$muted.'; font-size:15px; margin-bottom:10px;">Cần hỗ trợ thêm?</div><a href="https://m.me/jp-esim" style="'.$css.' display:inline-block; background:'.$btn.'; color:#fff; text-decoration:none; font-weight:800; padding:12px 18px; border-radius:12px; font-size:16px;">Nhắn Messenger</a></td></tr></table>';
        $html.='<p style="'.$css.' margin-top:18px; color:'.$muted.';">Trân trọng!<br><strong style="color:'.$text.'">Đội ngũ MUAESIM.VN</strong></p></td></tr></table></td></tr></table></body></html>';
        $alt="Xin chào,\nĐơn nạp data ".$tid." đã được xử lý.\nICCID: ".$iccid."\nGói nạp: ".trim($telecom.' '.$plan)."\nSố tiền: ".$amount."\nData thường được cộng vào eSIM trong vài phút.\n";
        return [$html,$alt];
    }
}
