<?php
declare(strict_types=1);
final class SupportService {
    public function handleMessage(string $userKey, string $message, string $channel='web', array $context=[]): array {
        try {
            return $this->handleMessageSafe($userKey, $message, $channel, $context);
        } catch (Throwable $e) {
            app_log('SupportService failed: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine(), 'ERROR');
            return ['reply'=>'Dạ hệ thống chat đang bận một chút. Anh/chị nhắn Messenger: https://m.me/muaesim hoặc Facebook: https://fb.com/muaesim để được hỗ trợ ngay ạ.','actions'=>[]];
        }
    }
    private function handleMessageSafe(string $userKey, string $message, string $channel='web', array $context=[]): array {
        if (trim($message)==='' || preg_match('/^(hi|hello|alo|xin chào|chào|hey)$/iu', trim($message))) {
            return ['reply'=>'Dạ em có thể tư vấn gói eSIM Nhật, tạo đơn, kiểm tra thanh toán, gửi lại QR eSIM hoặc hỗ trợ nạp data ạ. Anh/chị cần em hỗ trợ gì ạ?','actions'=>[]];
        }
        $conv=new ConversationService();
        if(!rate_limit('support_'.$channel.'_'.$userKey, 10, 60)) return ['reply'=>'Dạ anh/chị nhắn hơi nhanh, vui lòng thử lại sau ít phút nhé.','actions'=>[]];
        $sess=$conv->getSession($channel,$userKey);
        if((int)($sess['bot_paused'] ?? 0)===1) return ['reply'=>'Yêu cầu của anh/chị đang được nhân viên hỗ trợ xử lý ạ.','actions'=>[]];
        $conv->log($channel,$userKey,'user',$message);
        $ctx=array_merge($sess['context'] ?? [], $context);
        $msg=trim($message);
        if(($sess['state'] ?? '') === 'waiting_email_order' && valid_email($msg)) { $ctx['email']=$msg; $reply=$this->confirmOrder($ctx); $conv->save($channel,$userKey,'confirm_order',$ctx); return $reply; }
        if(($sess['state'] ?? '') === 'confirm_order' && preg_match('/^(ok|oke|đúng|dung|tạo|tao|yes|xác nhận|xac nhan)/iu',$msg)) { return $this->createOrder($channel,$userKey,$ctx); }
        if(($sess['state'] ?? '') === 'waiting_email_topup' && valid_email($msg)) { $ctx['email']=$msg; $reply=$this->confirmTopup($ctx); $conv->save($channel,$userKey,'confirm_topup',$ctx); return $reply; }
        if(($sess['state'] ?? '') === 'confirm_topup' && preg_match('/^(ok|oke|đúng|dung|nạp|nap|yes|xác nhận|xac nhan)/iu',$msg)) { return $this->createTopup($channel,$userKey,$ctx); }
        $intent=$this->detect($msg);
        if($intent['intent']==='human_support') { $conv->save($channel,$userKey,null,$ctx,true,true); return ['reply'=>'Dạ em đã chuyển yêu cầu cho nhân viên hỗ trợ. Anh/chị vui lòng chờ trong giây lát nhé.','actions'=>[]]; }
        if($intent['intent']==='list_plans') return $this->listPlans();
        if($intent['intent']==='recommend_plan') return $this->recommend($msg);
        if($intent['intent']==='payment_status') return $this->paymentStatus($intent);
        if($intent['intent']==='get_esim') return $this->getEsim($intent);
        if(in_array($intent['intent'], ['check_usage','check_expiry'], true)) return $this->usage($intent);
        if($intent['intent']==='topup_info') return $this->topupInfo($intent, $channel, $userKey, $ctx);
        if($intent['intent']==='create_topup') { $ctx=array_merge($ctx,$intent['entities']); if(empty($ctx['email'])) { $conv->save($channel,$userKey,'waiting_email_topup',$ctx); return ['reply'=>'Dạ anh/chị gửi giúp em email để nhận thông tin đơn nạp data nhé.','actions'=>[]]; } $reply=$this->confirmTopup($ctx); $conv->save($channel,$userKey,'confirm_topup',$ctx); return $reply; }
        if($intent['intent']==='create_order') { $ctx=array_merge($ctx,$intent['entities']); $plan=$this->choosePlan($ctx); if(!$plan) return ['reply'=>'Dạ anh/chị muốn mua gói bao nhiêu GB ạ? Ví dụ: 5GB, 10GB hoặc 20GB.','actions'=>$this->planActions()]; $ctx['planId']=$plan['id']; $ctx['planName']=$plan['name']; $ctx['priceText']=$plan['priceText']; if(empty($ctx['email'])) { $conv->save($channel,$userKey,'waiting_email_order',$ctx); return ['reply'=>'Dạ anh/chị gửi giúp em email nhận QR eSIM nhé.','actions'=>[]]; } $reply=$this->confirmOrder($ctx); $conv->save($channel,$userKey,'confirm_order',$ctx); return $reply; }
        return ['reply'=>'Dạ em có thể tư vấn gói eSIM Nhật, tạo đơn, kiểm tra thanh toán, gửi lại QR eSIM hoặc hỗ trợ nạp data. Anh/chị cần em hỗ trợ gì ạ?','actions'=>[['type'=>'quick_reply','title'=>'Bảng giá','payload'=>'LIST_PLANS'],['type'=>'quick_reply','title'=>'Nạp data','payload'=>'TOPUP_INFO'],['type'=>'quick_reply','title'=>'Gặp nhân viên','payload'=>'HUMAN']]];
    }
    private function detect(string $m): array {
        $ai=(new OpenAIHelper())->classify($m); if($ai && isset($ai['intent'])) return ['intent'=>$ai['intent'],'entities'=>$ai];
        $e=[]; if(preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',$m,$x)) $e['email']=$x[0];
        if(preg_match('/\bN[A-Z0-9]{7}\b/i',$m,$x)) $e['orderId']=strtoupper($x[0]);
        if(preg_match('/\bT[A-Z0-9]{7}\b/i',$m,$x)) $e['tid']=strtoupper($x[0]);
        if(preg_match('/\b(89\d{13,30})\b/',$m,$x)) $e['iccid']=$x[1];
        if(preg_match('/(\d+)\s*gb/iu',$m,$x)) $e['gb']=(int)$x[1];
        if(preg_match('/docomo/iu',$m)) $e['carrier']='Docomo'; if(preg_match('/\bau\b/iu',$m)) $e['carrier']='au';
        $l=function_exists("mb_strtolower") ? mb_strtolower($m, "UTF-8") : strtolower($m);
        if(preg_match('/(nhân viên|nguoi that|người thật|admin|gặp người|gap nguoi)/iu',$m)) $i='human_support';
        elseif(preg_match('/(có gói|co goi|bảng giá|bang gia|giá esim|gia esim|gói docomo|gói au)/iu',$m)) $i='list_plans';
        elseif(preg_match('/(nên mua|tu van|tư vấn|đi nhật|di nhat|google map|facebook|zalo|tiktok|youtube)/iu',$m)) $i='recommend_plan';
        elseif(preg_match('/(thanh toán|thanh toan|kiểm tra đơn|kiem tra don|paid|chuyển khoản)/iu',$m) && (!empty($e['orderId']) || !empty($e['tid']))) $i='payment_status';
        elseif(preg_match('/(gửi lại qr|gui lai qr|qr esim|lpa)/iu',$m)) $i='get_esim';
        elseif(preg_match('/(còn bao nhiêu|con bao nhieu|dung lượng|dung luong|hết hạn|het han|ngày hết|ngay het)/iu',$m)) $i=(strpos($l,"hạn")!==false || strpos($l,"han")!==false)?"check_expiry":"check_usage";
        elseif(preg_match('/(nạp|nap|topup|mua thêm|mua them)/iu',$m) && !empty($e['iccid']) && !empty($e['gb'])) $i='create_topup';
        elseif(preg_match('/(nạp|nap|topup|mua thêm|mua them)/iu',$m)) $i='topup_info';
        elseif(preg_match('/(mua|tạo đơn|tao don|lấy gói|lay goi)/iu',$m)) $i='create_order';
        else $i='unknown';
        return ['intent'=>$i,'entities'=>$e];
    }
    private function listPlans(): array { $data=(new PlanService())->list('esim'); $lines=['Dạ hiện bên em có các gói eSIM Nhật:']; foreach($data['telecoms'] as $tel){ $lines[]="\n{$tel}:"; foreach($data['plans'] as $p) if($p['telecom']===$tel) $lines[]='- '.$p['name'].'/'.$p['day'].' ngày: '.$p['priceText']; } return ['reply'=>implode("\n",$lines).'\n\nAnh/chị muốn dùng mấy ngày và khoảng bao nhiêu GB ạ?','actions'=>$this->planActions()]; }
    private function recommend(string $m): array { $gb=preg_match('/(tiktok|youtube|xem phim|nhiều|nhieu)/iu',$m)?20:(preg_match('/(10|20|30)\s*ngày|ngay/iu',$m)?10:5); return ['reply'=>'Dạ theo nhu cầu của anh/chị, em gợi ý gói Docomo '.$gb.'GB vì phủ sóng ổn định. Nếu chỉ dùng Google Maps/Zalo/Facebook nhẹ thì 5GB là hợp lý; xem TikTok/YouTube nhiều nên chọn 10GB hoặc 20GB.','actions'=>$this->planActions()]; }
    private function choosePlan(array $ctx): ?array { $plans=(new PlanService())->list('esim', $ctx['carrier'] ?? null)['plans']; $gb=(int)($ctx['gb'] ?? 0); foreach($plans as $p) if($gb && preg_match('/\b'.$gb.'\s*GB\b/i',$p['name'])) return $p; return $plans[0] ?? null; }
    private function planActions(): array { $acts=[]; foreach((new PlanService())->list('esim')['plans'] as $p) if(count($acts)<6) $acts[]=['type'=>'quick_reply','title'=>$p['telecom'].' '.$p['name'],'payload'=>'CREATE_ORDER:'.$p['id']]; return $acts; }
    private function confirmOrder(array $ctx): array { return ['reply'=>'Dạ xác nhận tạo đơn gói '.($ctx['planName'] ?? 'đã chọn').' giá '.($ctx['priceText'] ?? '').' cho email '.($ctx['email'] ?? '').' nhé?','actions'=>[['type'=>'quick_reply','title'=>'Xác nhận','payload'=>'CONFIRM_ORDER'],['type'=>'quick_reply','title'=>'Đổi gói','payload'=>'LIST_PLANS']]]; }
    private function createOrder(string $channel,string $userKey,array $ctx): array { $planId=(int)($ctx['planId'] ?? 0); if(!$planId || empty($ctx['email'])) return ['reply'=>'Em còn thiếu gói hoặc email ạ.','actions'=>[]]; $o=(new OrderService())->create($ctx['email'],$planId,$ctx['voucher'] ?? null,true); (new ConversationService())->save($channel,$userKey,null,[]); return ['reply'=>'Dạ em đã tạo đơn '.$o['orderId']."\nSố tiền: ".$o['amountText']."\nNội dung CK: ".$o['orderId']."\nNgân hàng: ".$o['bank']['code']."\nSTK: ".$o['bank']['account']."\nChủ TK: ".$o['bank']['name']."\nAnh/chị quét QR để thanh toán. Sau khi thanh toán hệ thống sẽ tự gửi QR eSIM.",'actions'=>[['type'=>'image','url'=>$o['qrUrl']]]]; }
    private function paymentStatus(array $intent): array { $e=$intent['entities']; $id=$e['orderId'] ?? $e['tid'] ?? ''; $type=str_starts_with($id,'T')?'topup':'order'; $s=(new PaymentService())->status($id,$type); return ['reply'=>'Trạng thái '.$id.': '.($s['paid']?'đã thanh toán':'đang chờ thanh toán').'.','actions'=>[]]; }
    private function getEsim(array $intent): array { $id=$intent['entities']['orderId'] ?? ''; if(!$id) return ['reply'=>'Anh/chị gửi giúp em mã đơn dạng Nxxxxxxx nhé.','actions'=>[]]; $r=(new EsimService())->getByOrder($id); if(($r['status']??'')!=='ready') return ['reply'=>$r['message'] ?? 'eSIM đang xử lý.','actions'=>[]]; $e=$r['esims'][0] ?? []; return ['reply'=>'Đây là thông tin eSIM đơn '.$id."\nICCID: ".($e['iccid'] ?? '')."\nLPA: ".($e['lpa'] ?? ''),'actions'=>!empty($e['qrCodeUrl'])?[['type'=>'image','url'=>$e['qrCodeUrl']]]:[]]; }
    private function usage(array $intent): array { $iccid=$intent['entities']['iccid'] ?? ''; if(!$iccid) return ['reply'=>'Anh/chị gửi giúp em ICCID để kiểm tra nhé.','actions'=>[]]; $u=(new EsimService())->queryUsage($iccid); if(!$u) return ['reply'=>'Em chưa tìm thấy ICCID này trong hệ thống ạ.','actions'=>[]]; return ['reply'=>'ICCID '.$u['iccid'].' hiện còn khoảng '.($u['remainingGB'] ?? '—').'GB, hạn dùng: '.($u['expiredAt'] ?? '—').'.','actions'=>[]]; }
    private function topupInfo(array $intent,string $channel,string $userKey,array $ctx): array { $iccid=$intent['entities']['iccid'] ?? ''; if(!$iccid) return ['reply'=>'Anh/chị gửi giúp em ICCID cần nạp data nhé.','actions'=>[]]; $r=(new TopupService())->lookup($iccid); $ctx['iccid']=$r['iccid']; (new ConversationService())->save($channel,$userKey,null,$ctx); $lines=['Dạ ICCID '.$r['iccid'].' có thể nạp các gói:']; foreach($r['plans'] as $p) $lines[]='- '.$p['name'].'/'.$p['day'].' ngày: '.$p['priceText']; return ['reply'=>implode("\n",$lines).'\nAnh/chị muốn nạp gói nào ạ?','actions'=>array_map(fn($p)=>['type'=>'quick_reply','title'=>$p['name'],'payload'=>'CREATE_TOPUP:'.$p['id']], array_slice($r['plans'],0,6))]; }
    private function confirmTopup(array $ctx): array { return ['reply'=>'Dạ xác nhận tạo đơn nạp data cho ICCID '.($ctx['iccid'] ?? '').' nhé?','actions'=>[['type'=>'quick_reply','title'=>'Xác nhận','payload'=>'CONFIRM_TOPUP']]]; }
    private function createTopup(string $channel,string $userKey,array $ctx): array { if(empty($ctx['iccid'])||empty($ctx['planId'])||empty($ctx['email'])) return ['reply'=>'Em còn thiếu ICCID, gói hoặc email ạ.','actions'=>[]]; $o=(new TopupService())->create($ctx['iccid'],(int)$ctx['planId'],$ctx['email']); (new ConversationService())->save($channel,$userKey,null,[]); return ['reply'=>'Dạ em đã tạo đơn nạp data '.$o['tid']."\nSố tiền: ".$o['amountText']."\nNội dung CK: ".$o['tid']."\nSTK: ".$o['bank']['account']."\nAnh/chị quét QR bên dưới để thanh toán.",'actions'=>[['type'=>'image','url'=>$o['qrUrl']]]]; }
}
