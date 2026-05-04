<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

$flash = null;
function admin_ctv_exists(int $id): bool { $st=db()->prepare('SELECT 1 FROM ctv_users WHERE id=?'); $st->execute([$id]); return (bool)$st->fetchColumn(); }
try {
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $id = max(0, (int)($_POST['ctv_id'] ?? 0));
    if ($id <= 0 || !admin_ctv_exists($id)) throw new RuntimeException('CTV không tồn tại');
    if ($action === 'set_status') {
        $status = (int)($_POST['status'] ?? 0) ? 1 : 0;
        db()->prepare('UPDATE ctv_users SET status=? WHERE id=?')->execute([$status, $id]);
        AuditLog::log($admin['user'], $status ? 'ctv_activate' : 'ctv_deactivate', 'ctv', (string)$id);
        $flash = ['ok', 'Đã cập nhật trạng thái CTV #' . $id];
    } elseif ($action === 'set_discount') {
        $discount = (int)($_POST['discount'] ?? 0); if ($discount < 0 || $discount > 500000) throw new RuntimeException('Chiết khấu không hợp lệ');
        $tier = (int)($_POST['tier_id'] ?? 0);
        if ($tier > 0) { $st=db()->prepare('SELECT 1 FROM ctv_tiers WHERE id=?'); $st->execute([$tier]); if (!$st->fetchColumn()) throw new RuntimeException('Tier không tồn tại'); }
        db()->prepare('UPDATE ctv_users SET discount_per_esim=?, tier_id=? WHERE id=?')->execute([$discount, $tier ?: null, $id]);
        AuditLog::log($admin['user'], 'ctv_discount_update', 'ctv', (string)$id, ['discount' => $discount, 'tier' => $tier]);
        $flash = ['ok', 'Đã cập nhật chiết khấu CTV #' . $id];
    } elseif ($action === 'wallet_credit' || $action === 'wallet_debit') {
        $amount = (int)($_POST['amount'] ?? 0); $note = trim((string)($_POST['note'] ?? ''));
        $cap = (int)app_config('CTV_ADMIN_WALLET_CAP', 100000000);
        if ($amount <= 0 || $amount > $cap) throw new RuntimeException('Số tiền không hợp lệ');
        if ($note === '') throw new RuntimeException('Ghi chú là bắt buộc');
        $svc = new CtvWalletService();
        if ($action === 'wallet_credit') $svc->credit($id, $amount, 'admin_credit', 'manual', null, $note, $admin['user']);
        else $svc->debit($id, $amount, 'admin_debit', 'manual', null, $note, $admin['user']);
        AuditLog::log($admin['user'], $action === 'wallet_credit' ? 'wallet_credit' : 'wallet_debit', 'ctv', (string)$id, ['amount' => $amount, 'note' => $note]);
        $flash = ['ok', 'Đã cập nhật ví CTV #' . $id];
    }
}
} catch (Throwable $e) { $flash = ['err', 'Lỗi: ' . $e->getMessage()]; }

$q = trim((string)($_GET['q'] ?? ''));
$params=[]; $where='WHERE 1';
if ($q !== '') { $where .= ' AND (email LIKE ? OR id=?)'; $params[]='%'.$q.'%'; $params[]=(int)$q; }
$st=db()->prepare("SELECT u.id,u.email,u.display_name,u.company_name,u.phone,u.status,u.email_verified,u.balance,u.discount_per_esim,u.tier_id,u.last_login_at,u.last_login_ip,u.created_at, (SELECT COUNT(*) FROM ctv_orders WHERE ctv_id=u.id) AS total_orders, (SELECT COALESCE(SUM(CASE WHEN status=2 THEN total_charge ELSE 0 END),0) FROM ctv_orders WHERE ctv_id=u.id) AS total_spent FROM ctv_users u $where ORDER BY u.id DESC LIMIT 200"); $st->execute($params); $ctvs=$st->fetchAll();
$tiers = db()->query('SELECT id,name,discount_per_esim FROM ctv_tiers ORDER BY id ASC')->fetchAll();
$sum = db()->query('SELECT COUNT(*) total, SUM(status=1) active, COALESCE(SUM(balance),0) balance FROM ctv_users')->fetch();
admin_layout_header('Danh sách CTV', $admin);
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>
<div class="summary"><div class="card"><b>Tổng CTV</b><h2><?= (int)$sum['total'] ?></h2></div><div class="card"><b>Active</b><h2><?= (int)$sum['active'] ?></h2></div><div class="card"><b>Tổng ví</b><h2><?= htmlspecialchars(format_vnd((int)$sum['balance'])) ?></h2></div></div>
<div class="card"><form method="get"><input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Tìm email hoặc ID"><button class="btn">Tìm</button><a class="btn secondary" href="/admin/ctv/index.php">Reset</a></form></div>
<div class="card"><h2>CTV (<?= count($ctvs) ?>)</h2><table><thead><tr><th>ID</th><th>Email / Công ty</th><th>Trạng thái</th><th>Số dư</th><th>Chiết khấu</th><th>Đơn / Doanh thu</th><th>Tạo lúc</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($ctvs as $c): ?><tr>
<td><a class="rowlink" href="/admin/ctv/view.php?id=<?= (int)$c['id'] ?>">#<?= (int)$c['id'] ?></a></td>
<td><?= htmlspecialchars((string)$c['email']) ?><?php if (!empty($c['company_name'])): ?><br><span class="muted"><?= htmlspecialchars((string)$c['company_name']) ?></span><?php endif; ?><?php if (!empty($c['phone'])): ?><br><span class="muted"><?= htmlspecialchars((string)$c['phone']) ?></span><?php endif; ?></td>
<td><span class="tag <?= (int)$c['status']===1?'ok':((int)$c['email_verified']?'err':'warn') ?>"><?= (int)$c['status']===1?'active':((int)$c['email_verified']?'disabled':'pending') ?></span></td>
<td><?= htmlspecialchars(format_vnd((int)$c['balance'])) ?></td>
<td><?= htmlspecialchars(format_vnd((int)$c['discount_per_esim'])) ?><?= $c['tier_id']?' / tier '.(int)$c['tier_id']:'' ?></td>
<td><?= (int)$c['total_orders'] ?> đơn<br><span class="muted"><?= htmlspecialchars(format_vnd((int)$c['total_spent'])) ?></span></td>
<td><span class="muted"><?= htmlspecialchars((string)($c['created_at'] ?? '')) ?></span></td>
<td>
<details><summary>Quản lý</summary>
<div style="margin-top:8px">
<form method="post" class="inline" style="margin-bottom:6px"><?php admin_csrf_field(); ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="ctv_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="status" value="<?= (int)$c['status']===1?0:1 ?>"><button class="btn sm <?= (int)$c['status']===1?'danger':'' ?>"><?= (int)$c['status']===1?'Khóa':'Mở' ?></button></form>
<form method="post" style="margin-bottom:6px"><?php admin_csrf_field(); ?><input type="hidden" name="action" value="set_discount"><input type="hidden" name="ctv_id" value="<?= (int)$c['id'] ?>">Chiết khấu <input type="number" name="discount" min="0" max="500000" value="<?= (int)$c['discount_per_esim'] ?>" style="width:100px"><select name="tier_id"><option value="0">-</option><?php foreach ($tiers as $t): ?><option value="<?= (int)$t['id'] ?>" <?= (int)$c['tier_id']===(int)$t['id']?'selected':'' ?>><?= htmlspecialchars((string)$t['name']) ?></option><?php endforeach; ?></select><button class="btn sm">Lưu</button></form>
<form method="post" style="margin-bottom:6px"><?php admin_csrf_field(); ?><input type="hidden" name="ctv_id" value="<?= (int)$c['id'] ?>"><input type="number" name="amount" min="1" placeholder="VND" style="width:100px"><input name="note" required placeholder="Ghi chú" style="width:140px"><button name="action" value="wallet_credit" class="btn sm gold">Nạp</button><button name="action" value="wallet_debit" class="btn sm danger" onclick="return confirm('Xác nhận trừ ví?')">Trừ</button></form>
</div>
<details style="margin-top:8px"><summary>Lịch sử ví (gần nhất)</summary>
<?php
$txSt = db()->prepare('SELECT amount, balance_after, reason, note, admin_user, created_at FROM ctv_wallet_transactions WHERE ctv_id=? ORDER BY id DESC LIMIT 10');
$txSt->execute([(int)$c['id']]);
$txRows = $txSt->fetchAll();
if ($txRows): ?>
<table style="margin-top:6px;font-size:12px"><thead><tr><th>Số tiền</th><th>Sau GD</th><th>Lý do</th><th>Ghi chú</th><th>Thời gian</th></tr></thead><tbody>
<?php foreach ($txRows as $tx): ?>
<tr>
<td><span style="color:<?= (int)$tx['amount'] >= 0 ? 'var(--a-green)' : 'var(--a-red)' ?>"><?= (int)$tx['amount'] >= 0 ? '+' : '' ?><?= htmlspecialchars(format_vnd((int)$tx['amount'])) ?></span></td>
<td><?= htmlspecialchars(format_vnd((int)$tx['balance_after'])) ?></td>
<td><span class="muted"><?= htmlspecialchars((string)$tx['reason']) ?></span></td>
<td><span class="muted"><?= htmlspecialchars(mb_strimwidth((string)($tx['note'] ?? ''), 0, 60, '…')) ?></span></td>
<td><span class="muted"><?= htmlspecialchars((string)$tx['created_at']) ?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php else: ?><p class="muted" style="margin-top:6px">Chưa có giao dịch.</p><?php endif; ?>
</details>
</details>
</td>
</tr><?php endforeach; ?></tbody></table></div><?php admin_layout_footer();
