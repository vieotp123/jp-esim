<?php
declare(strict_types=1);

final class CtvApiKeyService {
    /**
     * Generate a new API key for the given CTV. Returns the full plaintext token (only shown once)
     * along with the persisted record (id, prefix, hash). The token format is "ctvK_<prefix>_<secret>".
     */
    public function generate(int $ctvId, string $name): array {
        $name = trim($name);
        if ($name === '') $name = 'API Key';
        if (strlen($name) > 64) $name = substr($name, 0, 64);
        $prefix = strtolower(substr(bin2hex(random_bytes(4)), 0, 8));
        $secret = bin2hex(random_bytes(24));
        $token = 'ctvK_' . $prefix . '_' . $secret;
        $hash = self::hashKey($token);
        db()->prepare('INSERT INTO ctv_api_keys(ctv_id,name,key_prefix,key_hash,status) VALUES(?,?,?,?,1)')
            ->execute([$ctvId, $name, $prefix, $hash]);
        return [
            'id' => (int)db()->lastInsertId(),
            'name' => $name,
            'prefix' => $prefix,
            'token' => $token, // plaintext - shown only once
        ];
    }

    public function listForCtv(int $ctvId): array {
        $st = db()->prepare('SELECT id,name,key_prefix,last_used_at,last_used_ip,status,created_at,revoked_at FROM ctv_api_keys WHERE ctv_id=? ORDER BY id DESC');
        $st->execute([$ctvId]);
        return $st->fetchAll();
    }

    public function revoke(int $ctvId, int $keyId): void {
        db()->prepare('UPDATE ctv_api_keys SET status=0, revoked_at=NOW() WHERE id=? AND ctv_id=?')->execute([$keyId, $ctvId]);
    }

    /**
     * Look up an API key by its full plaintext token. Returns the joined CTV row or null.
     * The token must be active and the CTV must be active+verified.
     */
    public function authenticate(string $token): ?array {
        if ($token === '' || strlen($token) > 200) return null;
        $hash = self::hashKey($token);
        try {
            $st = db()->prepare('SELECT k.id AS key_id, k.ctv_id, u.* FROM ctv_api_keys k JOIN ctv_users u ON u.id=k.ctv_id WHERE k.key_hash=? AND k.status=1 AND u.status=1 AND u.email_verified=1 LIMIT 1');
            $st->execute([$hash]);
            $row = $st->fetch();
        } catch (Throwable $e) { return null; }
        if (!$row) return null;
        try {
            db()->prepare('UPDATE ctv_api_keys SET last_used_at=NOW(), last_used_ip=? WHERE id=?')
                ->execute([$_SERVER['REMOTE_ADDR'] ?? null, (int)$row['key_id']]);
        } catch (Throwable $e) { /* ignore */ }
        return $row;
    }

    public static function hashKey(string $token): string {
        // SHA-256 is sufficient for high-entropy random tokens; allows constant-time DB lookup.
        return hash('sha256', $token);
    }
}
