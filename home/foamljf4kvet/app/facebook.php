<?php
declare(strict_types=1);
final class FacebookClient {
    public function verifySignature(string $raw): bool {
        $secret=(string)app_config('FB_APP_SECRET',''); if($secret==='') return false;
        $sig=$_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if(!str_starts_with($sig,'sha256=')) return false;
        $expected='sha256='.hash_hmac('sha256',$raw,$secret);
        return hash_equals($expected,$sig);
    }
    public function sendText(string $psid,string $text,array $quickReplies=[]): void {
        $msg=['text'=>$text];
        if($quickReplies){$msg['quick_replies']=array_map(fn($a)=>['content_type'=>'text','title'=>mb_substr($a['title'] ?? 'OK',0,20),'payload'=>$a['payload'] ?? $a['title'] ?? 'OK'],$quickReplies);}        
        $this->send(['recipient'=>['id'=>$psid],'message'=>$msg]);
    }
    public function sendImage(string $psid,string $url): void { $this->send(['recipient'=>['id'=>$psid],'message'=>['attachment'=>['type'=>'image','payload'=>['url'=>$url,'is_reusable'=>true]]]]); }
    private function send(array $payload): void {
        $token=(string)app_config('FB_PAGE_ACCESS_TOKEN',''); if($token==='') { app_log('FB token missing','WARN'); return; }
        $ch=curl_init('https://graph.facebook.com/v19.0/me/messages?access_token='.rawurlencode($token));
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        $raw=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
        if($code>=400) app_log('FB send failed '.$raw,'WARN');
    }
}
