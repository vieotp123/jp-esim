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

    private function buildAutoThemeHtml(string $orderId, array $blocks): array {
        $esimBlocksHtml = '';
        foreach ($blocks as $i => $b) {
            $iccid = $b['iccid'] ?? '';
            $cid = $b['cid'] ?? '';
            $esimBlocksHtml .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5ff;border:1px solid #e5e7eb;padding:16px;border-radius:14px;margin-bottom:16px;"><tr><td>';
            $esimBlocksHtml .= '<span style="display:inline-block;background:#eaf1ff;color:#0b57d0;font-weight:700;font-size:14px;padding:6px 10px;border-radius:10px;margin-bottom:12px;">eSIM #' . ($i + 1) . '</span>';
            $esimBlocksHtml .= '<div style="margin:0 0 12px;"><span style="color:#64748b;font-size:13px;margin-right:8px;">ICCID:</span><span style="background:#fff;border:1px solid #e5e7eb;padding:8px 12px;border-radius:10px;font-weight:700;letter-spacing:.3px;">' . htmlspecialchars($iccid) . '</span></div>';
            $esimBlocksHtml .= '<div style="background:#fff;border:1px solid #e5e7eb;padding:12px;border-radius:14px;" align="center"><img src="cid:' . htmlspecialchars($cid) . '" width="320" height="320" alt="QR eSIM" style="display:block;width:320px;height:320px;border-radius:8px;"></div>';
            $esimBlocksHtml .= '</td></tr></table>';
        }
        $html = EmailTemplate::renderRaw('esim_ready', [
            'orderId' => htmlspecialchars($orderId),
            'esimBlocks' => $esimBlocksHtml,
        ]);
        $alt = "Xin chào,\nĐơn hàng " . $orderId . " đã được xử lý.\n";
        foreach ($blocks as $i => $b) {
            $alt .= "eSIM #" . ($i + 1) . "\nICCID: " . ($b['iccid'] ?? '') . "\n";
        }
        $alt .= "\nMẹo kích hoạt nhanh: iPhone iOS 17.4+, nhấn giữ vào QR và chọn \"Thêm eSIM\".\n";
        return [$html, $alt];
    }

    private function buildTopupHtml(array $topup): array {
        $tid = (string)($topup['tid'] ?? '');
        $iccid = (string)($topup['iccid'] ?? '');
        $plan = trim((string)($topup['plan_name'] ?? '')) ?: ((int)($topup['gb'] ?? 0) . 'GB');
        $telecom = (string)($topup['telecom'] ?? '');
        $amount = number_format((float)($topup['price'] ?? 0)) . '₫';
        $paidAt = (string)($topup['paid_at'] ?? '');
        $html = EmailTemplate::render('topup_confirmed', [
            'tid' => $tid,
            'iccid' => $iccid,
            'planName' => trim($telecom . ' ' . $plan),
            'amount' => $amount,
            'paidAt' => $paidAt,
        ]);
        $alt = "Xin chào,\nĐơn nạp data " . $tid . " đã được xử lý.\nICCID: " . $iccid . "\nGói nạp: " . trim($telecom . ' ' . $plan) . "\nSố tiền: " . $amount . "\nData thường được cộng vào eSIM trong vài phút.\n";
        return [$html, $alt];
    }
}
