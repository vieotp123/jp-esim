<?php
declare(strict_types=1);

class CtvTopupRequestService
{
    private PDO $pdo;
    private const UPLOAD_DIR = '/home/levanrin2404/esimtravel/public_html/uploads/topup_proofs';
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    public function create(int $ctvId, int $amount, ?array $file = null): int
    {
        if ($amount < 10000 || $amount > 100000000) {
            throw new InvalidArgumentException('Số tiền phải từ 10,000 đến 100,000,000 VND');
        }

        $proofPath = null;
        if ($file && !empty($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
            $proofPath = $this->handleUpload($file, $ctvId);
        }

        $st = $this->pdo->prepare('INSERT INTO ctv_topup_requests (ctv_id, amount, proof_path) VALUES (?, ?, ?)');
        $st->execute([$ctvId, $amount, $proofPath]);
        return (int)$this->pdo->lastInsertId();
    }

    public function listForCtv(int $ctvId, int $limit = 20): array
    {
        $st = $this->pdo->prepare('SELECT id, amount, proof_path, status, admin_note, created_at, resolved_at FROM ctv_topup_requests WHERE ctv_id = ? ORDER BY id DESC LIMIT ' . max(1, min($limit, 100)));
        $st->execute([$ctvId]);
        return $st->fetchAll();
    }

    public function listPending(int $limit = 100): array
    {
        $st = $this->pdo->prepare('SELECT r.*, u.email, u.display_name AS company_name, u.balance FROM ctv_topup_requests r JOIN ctv_users u ON u.id = r.ctv_id WHERE r.status = ? ORDER BY r.id ASC LIMIT ' . max(1, min($limit, 500)));
        $st->execute(['pending']);
        return $st->fetchAll();
    }

    public function listAll(int $limit = 100, int $offset = 0, ?string $status = null): array
    {
        $where = '';
        $params = [];
        if ($status !== null && in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where = 'WHERE r.status = ?';
            $params[] = $status;
        }
        $st = $this->pdo->prepare('SELECT r.*, u.email, u.display_name AS company_name FROM ctv_topup_requests r JOIN ctv_users u ON u.id = r.ctv_id ' . $where . ' ORDER BY r.id DESC LIMIT ' . max(1, min($limit, 500)) . ' OFFSET ' . max(0, $offset));
        $st->execute($params);
        return $st->fetchAll();
    }

    public function approve(int $requestId, string $adminUser, ?string $note = null): void
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare('SELECT id, ctv_id, amount, status FROM ctv_topup_requests WHERE id = ? FOR UPDATE');
            $st->execute([$requestId]);
            $req = $st->fetch();
            if (!$req) throw new RuntimeException('Yêu cầu không tồn tại');
            if ($req['status'] !== 'pending') throw new RuntimeException('Yêu cầu đã được xử lý');

            (new CtvWalletService($this->pdo))->credit(
                (int)$req['ctv_id'],
                (int)$req['amount'],
                'topup_request',
                'topup_request',
                (string)$requestId,
                $note ?: 'Admin approved topup request #' . $requestId,
                $adminUser
            );

            $this->pdo->prepare('UPDATE ctv_topup_requests SET status = ?, admin_note = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?')
                ->execute(['approved', $note, $adminUser, $requestId]);

            $this->pdo->commit();

            (new CtvNotificationService($this->pdo))->create(
                (int)$req['ctv_id'],
                'Yêu cầu nạp ví đã được duyệt',
                'Số tiền ' . format_vnd((int)$req['amount']) . ' đã được nạp vào ví.',
                'wallet'
            );
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function reject(int $requestId, string $adminUser, ?string $note = null): void
    {
        $st = $this->pdo->prepare('SELECT id, ctv_id, amount, status FROM ctv_topup_requests WHERE id = ?');
        $st->execute([$requestId]);
        $req = $st->fetch();
        if (!$req) throw new RuntimeException('Yêu cầu không tồn tại');
        if ($req['status'] !== 'pending') throw new RuntimeException('Yêu cầu đã được xử lý');

        $this->pdo->prepare('UPDATE ctv_topup_requests SET status = ?, admin_note = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?')
            ->execute(['rejected', $note, $adminUser, $requestId]);

        (new CtvNotificationService($this->pdo))->create(
            (int)$req['ctv_id'],
            'Yêu cầu nạp ví bị từ chối',
            'Số tiền ' . format_vnd((int)$req['amount']) . ' không được duyệt.' . ($note ? ' Lý do: ' . $note : ''),
            'wallet'
        );
    }

    private function handleUpload(array $file, int $ctvId): string
    {
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('File quá lớn (tối đa 5MB)');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Chỉ chấp nhận file JPG, PNG, WebP hoặc PDF');
        }

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };

        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0750, true);
        }

        $name = $ctvId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = self::UPLOAD_DIR . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Upload thất bại');
        }

        return '/uploads/topup_proofs/' . $name;
    }
}
