<?php
declare(strict_types=1);

final class CtvWalletService {
    /**
     * Atomically debit a CTV's balance. Throws if insufficient funds.
     * Returns the new balance.
     */
    public function debit(int $ctvId, int $amount, string $reason, ?string $refType = null, ?string $refId = null, ?string $note = null, ?string $adminUser = null): int {
        if ($amount <= 0) throw new InvalidArgumentException('Số tiền không hợp lệ');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT balance FROM ctv_users WHERE id=? FOR UPDATE');
            $st->execute([$ctvId]);
            $bal = $st->fetchColumn();
            if ($bal === false) throw new RuntimeException('CTV không tồn tại');
            $bal = (int)$bal;
            if ($bal < $amount) throw new RuntimeException('Số dư không đủ');
            $newBal = $bal - $amount;
            $pdo->prepare('UPDATE ctv_users SET balance=? WHERE id=?')->execute([$newBal, $ctvId]);
            $pdo->prepare('INSERT INTO ctv_wallet_transactions(ctv_id,amount,balance_after,reason,ref_type,ref_id,note,admin_user) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$ctvId, -$amount, $newBal, $reason, $refType, $refId, $note, $adminUser]);
            $pdo->commit();
            return $newBal;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function credit(int $ctvId, int $amount, string $reason, ?string $refType = null, ?string $refId = null, ?string $note = null, ?string $adminUser = null): int {
        if ($amount <= 0) throw new InvalidArgumentException('Số tiền không hợp lệ');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT balance FROM ctv_users WHERE id=? FOR UPDATE');
            $st->execute([$ctvId]);
            $bal = $st->fetchColumn();
            if ($bal === false) throw new RuntimeException('CTV không tồn tại');
            $newBal = (int)$bal + $amount;
            $pdo->prepare('UPDATE ctv_users SET balance=? WHERE id=?')->execute([$newBal, $ctvId]);
            $pdo->prepare('INSERT INTO ctv_wallet_transactions(ctv_id,amount,balance_after,reason,ref_type,ref_id,note,admin_user) VALUES(?,?,?,?,?,?,?,?)')
                ->execute([$ctvId, $amount, $newBal, $reason, $refType, $refId, $note, $adminUser]);
            $pdo->commit();
            return $newBal;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function balance(int $ctvId): int {
        $st = db()->prepare('SELECT balance FROM ctv_users WHERE id=? LIMIT 1');
        $st->execute([$ctvId]);
        return (int)$st->fetchColumn();
    }

    public function transactions(int $ctvId, int $limit = 50, int $offset = 0): array {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);
        $st = db()->prepare('SELECT * FROM ctv_wallet_transactions WHERE ctv_id=? ORDER BY id DESC LIMIT '.(int)$limit.' OFFSET '.(int)$offset);
        $st->execute([$ctvId]);
        return $st->fetchAll();
    }
}
