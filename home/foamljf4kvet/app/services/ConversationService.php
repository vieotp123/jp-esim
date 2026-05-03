<?php
declare(strict_types=1);
final class ConversationService {
    private static ?bool $tableOk = null;
    public function getSession(string $channel, string $userKey): array {
        if (!$this->ensureTable()) return ['channel'=>$channel,'user_key'=>$userKey,'state'=>null,'context_json'=>'{}','context'=>[],'bot_paused'=>0,'needs_human'=>0];
        try {
            $st=db()->prepare('SELECT * FROM support_sessions WHERE channel=? AND user_key=? LIMIT 1');
            $st->execute([$channel,$userKey]);
            $r=$st->fetch();
            if($r) { $r['context']=json_decode($r['context_json'] ?: '{}', true) ?: []; return $r; }
            db()->prepare('INSERT INTO support_sessions(channel,user_key,context_json) VALUES(?,?,?)')->execute([$channel,$userKey,'{}']);
        } catch(Throwable $e) { app_log('support session read failed '.$e->getMessage(),'WARN'); }
        return ['channel'=>$channel,'user_key'=>$userKey,'state'=>null,'context_json'=>'{}','context'=>[],'bot_paused'=>0,'needs_human'=>0];
    }
    public function save(string $channel, string $userKey, ?string $state, array $context, bool $paused=false, bool $human=false): void {
        if (!$this->ensureTable()) return;
        try {
            db()->prepare('INSERT INTO support_sessions(channel,user_key,state,context_json,bot_paused,needs_human) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE state=VALUES(state), context_json=VALUES(context_json), bot_paused=VALUES(bot_paused), needs_human=VALUES(needs_human), updated_at=NOW()')
                ->execute([$channel,$userKey,$state,json_encode($context, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$paused?1:0,$human?1:0]);
        } catch(Throwable $e) { app_log('support session save failed '.$e->getMessage(),'WARN'); }
    }
    public function log(string $channel, string $userKey, string $role, string $message, array $payload=[]): void {
        try {
            $cid=hash('sha256',$channel.':'.$userKey.':'.microtime(true).':'.$role);
            db()->prepare('INSERT INTO chat_conversations(context_id,context_data,short_memory,summary,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())')
                ->execute([$cid,json_encode(['channel'=>$channel,'user'=>$userKey,'role'=>$role,'message'=>$message,'payload'=>$payload], JSON_UNESCAPED_UNICODE),'','']);
        } catch(Throwable $e) { app_log('conversation log failed '.$e->getMessage(),'WARN'); }
    }
    private function ensureTable(): bool {
        if (self::$tableOk !== null) return self::$tableOk;
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS support_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, channel VARCHAR(32) NOT NULL, user_key VARCHAR(128) NOT NULL, state VARCHAR(64) DEFAULT NULL, context_json LONGTEXT DEFAULT NULL, bot_paused TINYINT(1) NOT NULL DEFAULT 0, needs_human TINYINT(1) NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_channel_user (channel, user_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            self::$tableOk=true;
        } catch(Throwable $e) { self::$tableOk=false; app_log('ensure support_sessions failed '.$e->getMessage(),'WARN'); }
        return self::$tableOk;
    }
}
