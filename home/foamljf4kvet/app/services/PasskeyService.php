<?php
declare(strict_types=1);

$autoload = null;
$dir = __DIR__;
for ($i = 0; $i < 8; $i++) {
    $candidate = $dir . '/vendor/autoload.php';
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
    $parent = dirname($dir);
    if ($parent === $dir) break;
    $dir = $parent;
}
if ($autoload === null) {
    throw new RuntimeException('Composer autoload not found for PasskeyService');
}
require_once $autoload;

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Binary\ByteBuffer;

final class PasskeyService
{
    private const RP_NAME = 'JP eSIM';
    private const RP_ID = 'jp-esim.vip';
    private const ORIGIN = 'https://jp-esim.vip';
    private const CHALLENGE_TTL = 300;
    private const MAX_PASSKEYS = 5;
    private const TIMEOUT_SECONDS = 60;

    private WebAuthn $webauthn;
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
        $this->webauthn = new WebAuthn(self::RP_NAME, self::RP_ID, ['none'], true);
    }

    public function registerBegin(string $userType, int $userId, string $userName, string $displayName): array
    {
        $this->validateUserType($userType);

        $count = $this->credentialCount($userType, $userId);
        if ($count >= self::MAX_PASSKEYS) {
            throw new RuntimeException('Tối đa ' . self::MAX_PASSKEYS . ' passkey cho mỗi tài khoản');
        }

        $existingIds = $this->getCredentialIds($userType, $userId);

        $userIdBytes = $userType . ':' . $userId;
        $createArgs = $this->webauthn->getCreateArgs(
            $userIdBytes,
            $userName,
            $displayName,
            self::TIMEOUT_SECONDS,
            true,
            'preferred',
            null,
            $existingIds
        );

        $challenge = $this->webauthn->getChallenge();
        $this->storeChallenge($challenge->getBinaryString(), $userType, $userId, 'register');

        return json_decode(json_encode($createArgs), true);
    }

    public function registerFinish(string $userType, int $userId, string $clientDataJSON, string $attestationObject, ?string $deviceName = null): array
    {
        $this->validateUserType($userType);

        $challengeRow = $this->consumeChallenge($userType, $userId, 'register');
        $challengeBinary = $challengeRow['challenge_binary'];

        $data = $this->webauthn->processCreate(
            $clientDataJSON,
            $attestationObject,
            new ByteBuffer($challengeBinary),
            false,
            true,
            false
        );

        $credentialId = $data->credentialId instanceof ByteBuffer
            ? $data->credentialId->getBinaryString()
            : (string)$data->credentialId;
        $credentialIdB64 = $this->base64url_encode($credentialId);
        $publicKeyPem = $data->credentialPublicKey;
        $signCount = $data->signatureCounter ?? 0;
        $aaguidRaw = $data->AAGUID ?? null;
        $aaguid = ($aaguidRaw !== null && strlen($aaguidRaw) === 16)
            ? vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($aaguidRaw), 4))
            : $aaguidRaw;

        $st = $this->pdo->prepare('SELECT id FROM user_passkeys WHERE credential_id = ?');
        $st->execute([$credentialIdB64]);
        if ($st->fetchColumn()) {
            throw new RuntimeException('Passkey này đã được đăng ký');
        }

        $this->pdo->prepare(
            'INSERT INTO user_passkeys (user_type, user_id, credential_id, public_key_pem, sign_count, aaguid, device_name) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$userType, $userId, $credentialIdB64, $publicKeyPem, $signCount, $aaguid, $deviceName]);

        return [
            'id' => (int)$this->pdo->lastInsertId(),
            'deviceName' => $deviceName,
        ];
    }

    public function authenticateBegin(string $userType, ?int $userId = null): array
    {
        $this->validateUserType($userType);

        $credentialIds = [];
        if ($userId !== null) {
            $credentialIds = $this->getCredentialIds($userType, $userId);
        }

        $getArgs = $this->webauthn->getGetArgs(
            $credentialIds,
            self::TIMEOUT_SECONDS,
            true, true, true, true, true,
            'preferred'
        );

        $challenge = $this->webauthn->getChallenge();
        $this->storeChallenge($challenge->getBinaryString(), $userType, $userId, 'authenticate');

        return json_decode(json_encode($getArgs), true);
    }

    public function authenticateFinish(
        string $userType,
        string $credentialIdB64,
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
        ?string $userHandle
    ): array {
        $this->validateUserType($userType);

        $st = $this->pdo->prepare('SELECT * FROM user_passkeys WHERE credential_id = ? AND user_type = ?');
        $st->execute([$credentialIdB64, $userType]);
        $passkey = $st->fetch();
        if (!$passkey) {
            throw new RuntimeException('Passkey không hợp lệ');
        }

        $userId = (int)$passkey['user_id'];

        $challengeRow = $this->consumeChallenge($userType, null, 'authenticate');
        $challengeBinary = $challengeRow['challenge_binary'];

        $this->webauthn->processGet(
            $clientDataJSON,
            $authenticatorData,
            $signature,
            $passkey['public_key_pem'],
            new ByteBuffer($challengeBinary),
            (int)$passkey['sign_count'],
            false,
            true
        );

        $newSignCount = $this->webauthn->getSignatureCounter();
        if ($newSignCount !== null) {
            $this->pdo->prepare('UPDATE user_passkeys SET sign_count = ?, last_used_at = NOW() WHERE id = ?')
                ->execute([$newSignCount, (int)$passkey['id']]);
        } else {
            $this->pdo->prepare('UPDATE user_passkeys SET last_used_at = NOW() WHERE id = ?')
                ->execute([(int)$passkey['id']]);
        }

        return [
            'userId' => $userId,
            'userType' => $userType,
        ];
    }

    public function listCredentials(string $userType, int $userId): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, device_name, created_at, last_used_at FROM user_passkeys WHERE user_type = ? AND user_id = ? ORDER BY id ASC'
        );
        $st->execute([$userType, $userId]);
        return $st->fetchAll();
    }

    public function renameCredential(string $userType, int $userId, int $passkeyId, string $deviceName): bool
    {
        $deviceName = trim($deviceName);
        $nameLength = function_exists('mb_strlen') ? mb_strlen($deviceName) : strlen($deviceName);
        if ($deviceName === '' || $nameLength > 128) {
            throw new InvalidArgumentException('Tên passkey không hợp lệ');
        }

        $st = $this->pdo->prepare('UPDATE user_passkeys SET device_name = ? WHERE id = ? AND user_type = ? AND user_id = ?');
        $st->execute([$deviceName, $passkeyId, $userType, $userId]);
        return $st->rowCount() > 0;
    }

    public function revokeCredential(string $userType, int $userId, int $passkeyId): bool
    {
        $st = $this->pdo->prepare('DELETE FROM user_passkeys WHERE id = ? AND user_type = ? AND user_id = ?');
        $st->execute([$passkeyId, $userType, $userId]);
        return $st->rowCount() > 0;
    }

    public function hasPasskey(string $userType, int $userId): bool
    {
        return $this->credentialCount($userType, $userId) > 0;
    }

    private function credentialCount(string $userType, int $userId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM user_passkeys WHERE user_type = ? AND user_id = ?');
        $st->execute([$userType, $userId]);
        return (int)$st->fetchColumn();
    }

    private function getCredentialIds(string $userType, int $userId): array
    {
        $st = $this->pdo->prepare('SELECT credential_id FROM user_passkeys WHERE user_type = ? AND user_id = ?');
        $st->execute([$userType, $userId]);
        $ids = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $b64) {
            $ids[] = new ByteBuffer($this->base64url_decode($b64));
        }
        return $ids;
    }

    private function storeChallenge(string $challengeBinary, string $userType, ?int $userId, string $type): void
    {
        $this->pdo->prepare('DELETE FROM webauthn_challenges WHERE expires_at < NOW()')->execute();

        $challengeB64 = $this->base64url_encode($challengeBinary);
        $expiresAt = date('Y-m-d H:i:s', time() + self::CHALLENGE_TTL);
        $this->pdo->prepare(
            'INSERT INTO webauthn_challenges (challenge, user_type, user_id, type, expires_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$challengeB64, $userType, $userId, $type, $expiresAt]);
    }

    private function consumeChallenge(string $userType, ?int $userId, string $type): array
    {
        if ($userId !== null) {
            $st = $this->pdo->prepare(
                'SELECT id, challenge FROM webauthn_challenges WHERE user_type = ? AND user_id = ? AND type = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1'
            );
            $st->execute([$userType, $userId, $type]);
        } else {
            $st = $this->pdo->prepare(
                'SELECT id, challenge FROM webauthn_challenges WHERE user_type = ? AND type = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1'
            );
            $st->execute([$userType, $type]);
        }

        $row = $st->fetch();
        if (!$row) {
            throw new RuntimeException('Challenge hết hạn hoặc không hợp lệ. Vui lòng thử lại.');
        }

        $this->pdo->prepare('DELETE FROM webauthn_challenges WHERE id = ?')->execute([(int)$row['id']]);

        return [
            'challenge_binary' => $this->base64url_decode((string)$row['challenge']),
        ];
    }

    private function validateUserType(string $type): void
    {
        if (!in_array($type, ['ctv', 'admin'], true)) {
            throw new InvalidArgumentException('Invalid user type');
        }
    }

    private function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
