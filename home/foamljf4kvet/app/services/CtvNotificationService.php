<?php
declare(strict_types=1);

class CtvNotificationService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    public function create(int $ctvId, string $title, ?string $message = null, string $type = 'system'): int
    {
        $st = $this->pdo->prepare('INSERT INTO ctv_notifications (ctv_id, type, title, message) VALUES (?, ?, ?, ?)');
        $st->execute([$ctvId, $type, $title, $message]);
        return (int)$this->pdo->lastInsertId();
    }

    public function countUnread(int $ctvId): int
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM ctv_notifications WHERE ctv_id = ? AND is_read = 0');
        $st->execute([$ctvId]);
        return (int)$st->fetchColumn();
    }

    public function list(int $ctvId, int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);
        $st = $this->pdo->prepare('SELECT id, type, title, message, is_read, created_at FROM ctv_notifications WHERE ctv_id = ? ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $st->execute([$ctvId]);
        return $st->fetchAll();
    }

    public function markRead(int $ctvId, int $notifId): bool
    {
        $st = $this->pdo->prepare('UPDATE ctv_notifications SET is_read = 1 WHERE id = ? AND ctv_id = ?');
        $st->execute([$notifId, $ctvId]);
        return $st->rowCount() > 0;
    }

    public function markAllRead(int $ctvId): int
    {
        $st = $this->pdo->prepare('UPDATE ctv_notifications SET is_read = 1 WHERE ctv_id = ? AND is_read = 0');
        $st->execute([$ctvId]);
        return $st->rowCount();
    }

    public function broadcast(string $title, ?string $message = null, string $type = 'system'): int
    {
        $st = $this->pdo->query('SELECT id FROM ctv_users WHERE status = 1');
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);
        $insert = $this->pdo->prepare('INSERT INTO ctv_notifications (ctv_id, type, title, message) VALUES (?, ?, ?, ?)');
        $count = 0;
        foreach ($ids as $id) {
            $insert->execute([(int)$id, $type, $title, $message]);
            $count++;
        }
        return $count;
    }
}
