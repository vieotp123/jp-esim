<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = (string)($_POST['action'] ?? '');
        $email = trim((string)($_POST['email'] ?? ''));
        $ctvId = (int)($_POST['ctv_id'] ?? 0);
        $amount = (int)($_POST['amount'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));
        $cap = (int)app_config('CTV_ADMIN_WALLET_CAP', 100000000);

        if ($ctvId <= 0 && $email !== '') {
            $st = db()->prepare('SELECT id FROM ctv_users WHERE email=? LIMIT 1');
            $st->execute([$email]);
            $ctvId = (int)$st->fetchColumn();
            if ($ctvId <= 0) throw new RuntimeException('Không tìm thấy đối tác với email: ' . $email);
        }
        if ($ctvId <= 0) throw new RuntimeException('Chưa chọn đối tác');
        if ($amount <= 0 || $amount > $cap) throw new RuntimeException('Số tiền không hợp lệ (1 - ' . format_vnd($cap) . ')');
        if ($note === '') throw new RuntimeException('Ghi chú là bắt buộc');

        $svc = new CtvWalletService();
        if ($action === 'credit') {
            $svc->credit($ctvId, $amount, 'admin_credit', 'manual', null, $note, $admin['user']);
            AuditLog::log($admin['user'], 'wallet_credit', 'ctv', (string)$ctvId, ['amount' => $amount, 'note' => $note]);
            (new CtvNotificationService())->create($ctvId, 'Nạp ví thành công', 'Admin đã nạp ' . format_vnd($amount) . ' vào ví của bạn.', 'wallet');
            admin_flash_set('ok', 'Đã nạp ' . format_vnd($amount) . ' cho đối tác #' . $ctvId);
        } elseif ($action === 'debit') {
            $svc->debit($ctvId, $amount, 'admin_debit', 'manual', null, $note, $admin['user']);
            AuditLog::log($admin['user'], 'wallet_debit', 'ctv', (string)$ctvId, ['amount' => $amount, 'note' => $note]);
            admin_flash_set('ok', 'Đã trừ ' . format_vnd($amount) . ' từ đối tác #' . $ctvId);
        } else {
            throw new RuntimeException('Hành động không hợp lệ');
        }
    } catch (Throwable $e) {
        admin_flash_set('err', 'Lỗi: ' . $e->getMessage());
    }
    admin_redirect_self();
}

$q = trim((string)($_GET['q'] ?? ''));
$selected = null;
if ($q !== '') {
    $st = db()->prepare('SELECT id, email, display_name, balance, status FROM ctv_users WHERE email LIKE ? OR display_name LIKE ? OR id=? ORDER BY id DESC LIMIT 10');
    $st->execute(['%'.$q.'%', '%'.$q.'%', (int)$q]);
    $results = $st->fetchAll();
    if (count($results) === 1) $selected = $results[0];
} else {
    $results = [];
}

$recent = db()->query('SELECT t.ctv_id, t.amount, t.reason, t.note, t.admin_user, t.created_at, u.email FROM ctv_wallet_transactions t JOIN ctv_users u ON u.id=t.ctv_id WHERE t.reason IN ("admin_credit","admin_debit") ORDER BY t.id DESC LIMIT 20')->fetchAll();

admin_layout_header('Nạp ví đối tác', $admin);
?>
<?php admin_flash_render(); ?>
<div class="card">
  <h2>Tìm đối tác</h2>
  <form method="get" class="toolbar">
    <input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nhập email, tên, hoặc ID đối tác..." style="flex:1;min-width:250px" autofocus>
    <button class="btn">Tìm</button>
  </form>
  <?php if ($q !== '' && $results): ?>
  <div style="margin-top:12px">
    <?php foreach ($results as $r): ?>
    <div class="m-card" style="padding:10px 14px;margin-bottom:6px;display:flex;align-items:center;justify-content:space-between">
      <div>
        <strong>#<?= (int)$r['id'] ?></strong> <?= htmlspecialchars((string)$r['email']) ?>
        <?php if (!empty($r['display_name'])): ?><span class="muted"> · <?= htmlspecialchars((string)$r['display_name']) ?></span><?php endif; ?>
        <span style="margin-left:8px;color:var(--a-gold);font-weight:700"><?= htmlspecialchars(format_vnd((int)$r['balance'])) ?></span>
        <?php if (!(int)$r['status']): ?><span class="tag err">Đã khóa</span><?php endif; ?>
      </div>
      <a class="btn sm" href="?q=<?= rawurlencode((string)$r['email']) ?>">Chọn</a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php elseif ($q !== '' && !$results): ?>
  <p class="muted" style="margin-top:10px">Không tìm thấy đối tác với "<?= htmlspecialchars($q) ?>"</p>
  <?php endif; ?>
</div>

<?php if ($selected): ?>
<div class="card">
  <h2>Nạp / Trừ ví cho: <?= htmlspecialchars((string)$selected['email']) ?></h2>
  <p style="margin-bottom:14px">Số dư hiện tại: <strong style="color:var(--a-gold)"><?= htmlspecialchars(format_vnd((int)$selected['balance'])) ?></strong></p>
  <form method="post" style="max-width:480px" onsubmit="return confirm('Xác nhận thao tác ví?')">
    <?php admin_csrf_field(); ?>
    <input type="hidden" name="ctv_id" value="<?= (int)$selected['id'] ?>">
    <div class="field">
      <label>Số tiền (VNĐ)</label>
      <input type="number" name="amount" min="1" max="100000000" required placeholder="Ví dụ: 500000">
    </div>
    <div class="field">
      <label>Ghi chú (bắt buộc)</label>
      <input type="text" name="note" required placeholder="Lý do nạp/trừ tiền" maxlength="500">
    </div>
    <div style="display:flex;gap:10px">
      <button class="btn gold" type="submit" name="action" value="credit">Nạp tiền</button>
      <button class="btn danger" type="submit" name="action" value="debit">Trừ tiền</button>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Lịch sử nạp/trừ gần đây</h2>
  <?php if (!$recent): ?>
  <p class="muted">Chưa có thao tác nào.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table><thead><tr><th>Thời gian</th><th>Đối tác</th><th>Số tiền</th><th>Loại</th><th>Ghi chú</th><th>Admin</th></tr></thead><tbody>
  <?php foreach ($recent as $t): ?>
  <tr>
    <td class="muted" style="white-space:nowrap"><?= htmlspecialchars((string)$t['created_at']) ?></td>
    <td><a href="/admin/ctv/view.php?id=<?= (int)$t['ctv_id'] ?>"><?= htmlspecialchars((string)$t['email']) ?></a></td>
    <td style="font-weight:700;color:<?= (int)$t['amount'] > 0 ? 'var(--a-green,#4caf50)' : 'var(--a-danger,#e53935)' ?>"><?= (int)$t['amount'] > 0 ? '+' : '' ?><?= htmlspecialchars(format_vnd((int)$t['amount'])) ?></td>
    <td><span class="tag <?= (int)$t['amount'] > 0 ? 'ok' : 'err' ?>"><?= (int)$t['amount'] > 0 ? 'Nạp' : 'Trừ' ?></span></td>
    <td><?= htmlspecialchars(mb_strimwidth((string)($t['note'] ?? ''), 0, 60, '...')) ?></td>
    <td class="muted"><?= htmlspecialchars((string)($t['admin_user'] ?? '')) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
