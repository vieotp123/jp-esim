<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once '/home/foamljf4kvet/app/facebook.php';
$fb=new FacebookClient();
if($_SERVER['REQUEST_METHOD']==='GET'){
    $mode=$_GET['hub_mode'] ?? $_GET['hub.mode'] ?? ''; $token=$_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? ''; $challenge=$_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';
    if($mode==='subscribe' && hash_equals((string)app_config('FB_VERIFY_TOKEN',''), (string)$token)){ echo $challenge; exit; }
    http_response_code(403); echo 'Forbidden'; exit;
}
$raw=file_get_contents('php://input') ?: '';
if(!$fb->verifySignature($raw)){ http_response_code(403); echo 'Bad signature'; exit; }
$payload=json_decode($raw,true) ?: [];
foreach(($payload['entry'] ?? []) as $entry){
    foreach(($entry['messaging'] ?? []) as $ev){
        $psid=(string)($ev['sender']['id'] ?? ''); if($psid==='') continue;
        $text=(string)($ev['message']['text'] ?? $ev['postback']['payload'] ?? ''); if($text==='') continue;
        try { $res=(new SupportService())->handleMessage($psid,$text,'facebook'); $fb->sendText($psid,$res['reply'] ?? '', array_filter($res['actions'] ?? [], fn($a)=>($a['type'] ?? '')==='quick_reply')); foreach(($res['actions'] ?? []) as $a) if(($a['type'] ?? '')==='image' && !empty($a['url'])) $fb->sendImage($psid,$a['url']); } catch(Throwable $e){ app_log('FB handle '.$e->getMessage(),'ERROR'); $fb->sendText($psid,'Dạ hệ thống đang bận, anh/chị thử lại sau ít phút nhé.'); }
    }
}
echo 'EVENT_RECEIVED';
