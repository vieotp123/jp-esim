<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();

$action = (string)($_POST['action'] ?? '');
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'set_status') {
            $id = (int)($_POST['ctv_id'] ?? 0);
            $status = (int)($_POST['status'] ?? 0) ? 1 : 0;
            db()->prepare('UPDATE ctv_users SET status=? WHERE id=?')->execute([$status, $id]);
            $flash = ['ok', 'Đã cập nhật trạng thái CTV #' . $id];
        } elseif ($action === 'set_discount') {
            $id = (int)($_POST['ctv_id'] ?? 0);
            $discount = max(0, (int)($_POST['discount'] ?? 0));
            $tier = (int)($_POST['tier_id'] ?? 0);
            db()->prepare('UPDATE ctv_users SET discount_per_esim=?, tier_id=? WHERE id=?')->execute([$discount, $tier ?: null, $id]);
            $flash = ['ok', 'Đã cập nhật chiết khấu CTV #' . $id];
        } elseif ($action === 'wallet') {
            $id = (int)($_POST['ctv_id'] ?? 0);
            $delta = (int)($_POST['amount'] ?? 0);
            $note = trim((string)($_POST['note'] ?? ''));
            $svc = new CtvWalletService();
            if ($delta > 0) $svc->credit($id, $delta, 'admin_credit', 'manual', null, $note, $admin['user']);
            elseif ($delta < 0) $svc->debit($id, -$delta, 'admin_debit', 'manual', null, $note);
            $flash = ['ok', 'Đã điều chỉnh ví CTV #' . $id . ' (' . format_vnd($delta) . ')'];
        }
    } catch (Throwable $e) {
        $flash = ['err', 'Lỗi: ' . $e->getMessage()];
    }
}

$ctvs = db()->query('SELECT id,email,display_name,status,email_verified,balance,discount_per_esim,tier_id,created_at FROM ctv_users ORDER BY id DESC LIMIT 200')->fetchAll();
$tiers = db()->query('SELECT id,name,discount_per_esim FROM ctv_tiers ORDER BY id ASC')->fetchAll();

admin_layout_header('Danh sách CTV', $admin);
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

<div class="card">
  <h2>CTV (<?= count($ctvs) ?>)</h2>
  <table>
    <thead><tr><th>ID</th><th>Email</th><th>Tên</th><th>Trạng thái</th><th>Email verified</th><th>Số dư</th><th>Chiết khấu</th><th>Tạo lúc</th><th>Thao tác</th></tr></thead>
    <tbody>
    <?php foreach ($ctvs as $c): ?>
      <tr>
        <td><?= (int)$c['id'] ?></td>
        <td><?= htmlspecialchars((string)$c['email']) ?></td>
        <td><?= htmlspecialchars((string)($c['display_name'] ?? '')) ?></td>
        <td><?= (int)$c['status'] === 1 ? 'active' : 'disabled' ?></td>
        <td><?= (int)$c['email_verified'] === 1 ? 'yes' : 'no' ?></td>
        <td><?= htmlspecialchars(format_vnd((int)$c['balance'])) ?></td>
        <td><?= htmlspecialchars(format_vnd((int)$c['discount_per_esim'])) ?> <?php if ($c['tier_id']): ?>(tier <?= (int)$c['tier_id'] ?>)<?php endif; ?></td>
        <td><?= htmlspecialchars((string)$c['created_at']) ?></td>
        <td>
          <details><summary>Sửa</summary>
          <form method="post" class="inline">
            <input type="hidden" name="action" value="set_status">
            <input type="hidden" name="ctv_id" value="<?= (int)$c['id'] ?>">
            <input type="hidden" name="status" value="<?= (int)$c['status'] === 1 ? 0 : 1 ?>">
            <button class="btn <?= (int)$c['status']===1?'danger':'' ?>" type="submit"><?= (int)$c['status']===1?'Khóa':'Mở' ?></button>
          </form>
          <form method="post" class="inline" style="margin-top:6px;">
            <input type="hidden" name="action" value="set_discount">
            <input type="hidden" name="ctv_id" value="<?= (int)$c['id'] ?>">
            Chiết khấu: <input type="number" name="discount" value="<?= (int)$c['discount_per_esim'] ?>" style="width:100px;">
            Tier:
            <select name="tier_id"><option value="0">-</option>
            <?php foreach ($tiers as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= (int)$c['tier_id']===(int)$t['id']?'selected':'' ?>><?= htmlspecialchars((string)$t['name']) ?> (-<?= (int)$t['discount_per_esim'] ?>)</option>
            <?php endforeach; ?>
            </select>
            <button class="btn" type="submit">Lưu</button>
          </form>
          <form method="post" class="inline" style="margin-top:6px;">
            <input type="hidden" name="action" value="wallet">
            <input type="hidden" name="ctv_id" value="<?= (int)$c['id'] ?>">
            Ví ±: <input type="number" name="amount" placeholder="VND (-debit)" style="width:130px;">
            <input type="text" name="note" placeholder="Ghi chú" style="width:170px;">
            <button class="btn" type="submit">Áp dụng</button>
          </form>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php admin_layout_footer();
