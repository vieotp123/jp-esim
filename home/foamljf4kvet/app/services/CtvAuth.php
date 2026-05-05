<?php
declare(strict_types=1);

final class CtvAuth {
    private const COOKIE = 'ctv_session';
    private const TTL_SECONDS = 7 * 24 * 3600;

    public static function register(string $email, string $password, ?string $displayName, ?string $phone): array {
        $email = strtolower(trim($email));
        if (!valid_email($email)) throw new InvalidArgumentException('Email không hợp lệ');
        if (strlen($password) < 8) throw new InvalidArgumentException('Mật khẩu tối thiểu 8 ký tự');
        $pdo = db();
        $st = $pdo->prepare('SELECT id, email_verified FROM ctv_users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $existing = $st->fetch();
        if ($existing && (int)$existing['email_verified'] === 1) {
            throw new InvalidArgumentException('Email đã được đăng ký');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $token = bin2hex(random_bytes(24));
        if ($existing) {
            $pdo->prepare('UPDATE ctv_users SET password_hash=?, display_name=?, phone=?, email_verify_token=?, email_verify_sent_at=NOW(), status=1 WHERE id=?')
                ->execute([$hash, $displayName, $phone, $token, (int)$existing['id']]);
            $id = (int)$existing['id'];
        } else {
            $pdo->prepare('INSERT INTO ctv_users(email,password_hash,display_name,phone,status,email_verified,email_verify_token,email_verify_sent_at) VALUES(?,?,?,?,1,0,?,NOW())')
                ->execute([$email, $hash, $displayName, $phone, $token]);
            $id = (int)$pdo->lastInsertId();
        }
        try { (new CtvMailer())->sendVerifyEmail($email, $token); }
        catch (Throwable $e) { app_log('CTV verify email send failed '.$email.' '.$e->getMessage(), 'ERROR'); }
        return ['id' => $id, 'email' => $email, 'verifySent' => true];
    }

    public static function verifyEmail(string $token): bool {
        $token = trim($token);
        if ($token === '') return false;
        $pdo = db();
        $st = $pdo->prepare('SELECT id FROM ctv_users WHERE email_verify_token=? LIMIT 1');
        $st->execute([$token]);
        $id = (int)$st->fetchColumn();
        if ($id <= 0) return false;
        $pdo->prepare('UPDATE ctv_users SET email_verified=1, email_verified_at=NOW(), email_verify_token=NULL WHERE id=?')->execute([$id]);
        return true;
    }

    public static function login(string $email, string $password): array {
        $email = strtolower(trim($email));
        if (!valid_email($email) || $password === '') throw new InvalidArgumentException('Sai email hoặc mật khẩu');
        $pdo = db();
        $st = $pdo->prepare('SELECT id,email,password_hash,status,email_verified FROM ctv_users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u || !password_verify($password, (string)$u['password_hash'])) throw new InvalidArgumentException('Sai email hoặc mật khẩu');
        if ((int)$u['status'] !== 1) throw new InvalidArgumentException('Tài khoản đã bị tạm khóa');
        if ((int)$u['email_verified'] !== 1) throw new InvalidArgumentException('Vui lòng xác thực email trước khi đăng nhập');
        $sid = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);
        $pdo->prepare('INSERT INTO ctv_sessions(id,ctv_id,ip,user_agent,expires_at) VALUES(?,?,?,?,?)')
            ->execute([$sid, (int)$u['id'], $_SERVER['REMOTE_ADDR'] ?? null, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250), $expires]);
        $pdo->prepare('UPDATE ctv_users SET last_login_at=NOW(), last_login_ip=? WHERE id=?')
            ->execute([$_SERVER['REMOTE_ADDR'] ?? null, (int)$u['id']]);
        self::setSessionCookie($sid);
        self::gcSessions($pdo);
        return ['id' => (int)$u['id'], 'email' => $u['email'], 'session' => $sid];
    }

    public static function loginWithPasskey(int $userId): array {
        $pdo = db();
        $st = $pdo->prepare('SELECT id,email,status,email_verified FROM ctv_users WHERE id=? LIMIT 1');
        $st->execute([$userId]);
        $u = $st->fetch();
        if (!$u) throw new RuntimeException('Tài khoản không tồn tại');
        if ((int)$u['status'] !== 1) throw new RuntimeException('Tài khoản đã bị tạm khóa');
        if ((int)$u['email_verified'] !== 1) throw new RuntimeException('Vui lòng xác thực email trước khi đăng nhập');
        $sid = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);
        $pdo->prepare('INSERT INTO ctv_sessions(id,ctv_id,ip,user_agent,expires_at) VALUES(?,?,?,?,?)')
            ->execute([$sid, (int)$u['id'], $_SERVER['REMOTE_ADDR'] ?? null, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250), $expires]);
        $pdo->prepare('UPDATE ctv_users SET last_login_at=NOW(), last_login_ip=? WHERE id=?')
            ->execute([$_SERVER['REMOTE_ADDR'] ?? null, (int)$u['id']]);
        self::setSessionCookie($sid);
        self::gcSessions($pdo);
        return ['id' => (int)$u['id'], 'email' => $u['email'], 'session' => $sid];
    }

    public static function logout(): void {
        $sid = $_COOKIE[self::COOKIE] ?? '';
        if ($sid !== '') {
            try { db()->prepare('DELETE FROM ctv_sessions WHERE id=?')->execute([$sid]); } catch (Throwable $e) {}
        }
        self::clearSessionCookie();
    }

    public static function currentUser(): ?array {
        static $cached = null;
        if ($cached !== null) return $cached ?: null;
        $sid = $_COOKIE[self::COOKIE] ?? '';
        if ($sid === '' || strlen($sid) > 64) { $cached = false; return null; }
        try {
            $st = db()->prepare('SELECT u.* FROM ctv_sessions s JOIN ctv_users u ON u.id=s.ctv_id WHERE s.id=? AND s.expires_at > NOW() AND u.status=1 LIMIT 1');
            $st->execute([$sid]);
            $u = $st->fetch();
        } catch (Throwable $e) { $cached = false; return null; }
        $cached = $u ?: false;
        return $u ?: null;
    }

    public static function requireUser(): array {
        $u = self::currentUser();
        if (!$u) {
            header('Location: /auth?role=partner');
            exit;
        }
        return $u;
    }

    private static function gcSessions(\PDO $pdo): void {
        if (random_int(1, 20) === 1) {
            try { $pdo->exec('DELETE FROM ctv_sessions WHERE expires_at < NOW()'); } catch (\Throwable $e) {}
        }
    }

    private static function setSessionCookie(string $sid): void {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(self::COOKIE, $sid, [
            'expires' => time() + self::TTL_SECONDS,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearSessionCookie(): void {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function csrfToken(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['ctv_csrf'])) {
            $_SESSION['ctv_csrf'] = bin2hex(random_bytes(16));
        }
        return (string)$_SESSION['ctv_csrf'];
    }

    public static function checkCsrf(?string $token): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $expected = (string)($_SESSION['ctv_csrf'] ?? '');
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }
}
