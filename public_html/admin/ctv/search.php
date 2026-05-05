<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
$pdo = db();

$q = trim((string)($_GET['q'] ?? ''));
$retail = $ctvOrders = $esims = $users = $topupOrders = [];
$total = 0;

if ($q !== '' && strlen($q) <= 64) {
    $like = '%' . $q . '%';
    $emailQ = strtolower($q);

    $st = $pdo->prepare("SELECT order_id,email,plan_name,total,status,created_at FROM `order` WHERE order_id LIKE ? OR email LIKE ? OR iccid LIKE ? OR transactionId LIKE ? ORDER BY created_at DESC LIMIT 30");
    $st->execute([$like, $like, $like, $like]);
    $retail = $st->fetchAll();

    $st = $pdo->prepare("SELECT o.ctv_order_id,o.plan_name,o.total_charge,o.status,o.created_at,o.email AS customer_email,u.email AS ctv_email FROM ctv_orders o LEFT JOIN ctv_users u ON u.id=o.ctv_id WHERE o.ctv_order_id LIKE ? OR o.email LIKE ? OR o.iccid LIKE ? OR u.email LIKE ? OR o.client_ref LIKE ? ORDER BY o.id DESC LIMIT 30");
    $st->execute([$like, $like, $like, $like, $like]);
    $ctvOrders = $st->fetchAll();

    $st = $pdo->prepare("SELECT iccid,ctv_order_id,carrier,esim_status,smdp_status,email_sent_at,created_at FROM ctv_esims WHERE iccid LIKE ? OR ctv_order_id LIKE ? OR package_code LIKE ? ORDER BY id DESC LIMIT 30");
    $st->execute([$like, $like, $like]);
    $esims = $st->fetchAll();

    $st = $pdo->prepare("SELECT id,email,display_name,status,balance,created_at FROM ctv_users WHERE email LIKE ? OR display_name LIKE ? OR phone LIKE ? OR id = ? ORDER BY id DESC LIMIT 30");
    $st->execute([$like, $like, $like, ctype_digit($q) ? (int)$q : -1]);
    $users = $st->fetchAll();

    $st = $pdo->prepare("SELECT t.ctv_topup_id,t.iccid,t.plan_name,t.total_charge,t.status,t.created_at,u.email AS ctv_email FROM ctv_topup_orders t LEFT JOIN ctv_users u ON u.id=t.ctv_id WHERE t.ctv_topup_id LIKE ? OR t.iccid LIKE ? OR t.client_ref LIKE ? OR u.email LIKE ? ORDER BY t.id DESC LIMIT 30");
    $st->execute([$like, $like, $like, $like]);
    $topupOrders = $st->fetchAll();

    $total = count($retail) + count($ctvOrders) + count($esims) + count($users) + count($topupOrders);
}

admin_layout_header('Tìm kiếm', $admin);
?>
<style>
.search-form{background:var(--a-card);border:1px solid var(--a-line);border-radius:14px;padding:20px;margin-bottom:18px}
.search-form input[type=text]{width:100%;padding:14px 16px;font-size:15px;border-radius:10px;border:1px solid var(--a-line-2);background:var(--a-surface);color:var(--a-ink);font-family:inherit}
.search-form .hint{margin-top:8px;font-size:12px;color:var(--a-muted)}
.section{margin-bottom:18px}
.section h3{font-size:14px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--a-muted);margin-bottom:8px}
.section .count{background:rgba(230,192,104,.12);color:var(--a-gold);padding:2px 8px;border-radius:6px;font-size:12px;margin-left:6px}
</style>

<form method="get" class="search-form">
  <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Tìm theo ICCID, email, mã đơn, tên đối tác, số điện thoại..." autofocus>
  <p class="hint">Tìm kiếm trên: đơn lẻ, đơn đối tác, eSIM, đối tác, đơn nạp data. Tối đa 30 kết quả mỗi loại.</p>
</form>

<?php if ($q !== '' && $total === 0): ?>
<div class="card"><div class="empty"><div class="icon">🔍</div><p>Không tìm thấy kết quả nào cho <strong><?= htmlspecialchars($q) ?></strong>.</p></div></div>
<?php elseif ($q !== ''): ?>

<?php if ($users): ?>
<div class="section">
  <h3>Đối tác <span class="count"><?= count($users) ?></span></h3>
  <div class="card"><div class="table-wrap">
  <table><thead><tr><th>ID</th><th>Email</th><th>Tên</th><th>Trạng thái</th><th>Số dư</th><th>Tạo lúc</th></tr></thead><tbody>
  <?php foreach ($users as $u): ?>
  <tr>
    <td><a class="rowlink" href="/admin/ctv/view.php?id=<?= (int)$u['id'] ?>">#<?= (int)$u['id'] ?></a></td>
    <td><?= htmlspecialchars((string)$u['email']) ?></td>
    <td><?= htmlspecialchars((string)($u['display_name'] ?? '')) ?></td>
    <td><span class="tag <?= (int)$u['status']===1?'ok':'err' ?>"><?= (int)$u['status']===1?'Hoạt động':'Khóa' ?></span></td>
    <td><?= htmlspecialchars(format_vnd((int)$u['balance'])) ?></td>
    <td><span class="muted"><?= htmlspecialchars((string)$u['created_at']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div></div>
</div>
<?php endif; ?>

<?php if ($ctvOrders): ?>
<div class="section">
  <h3>Đơn đối tác <span class="count"><?= count($ctvOrders) ?></span></h3>
  <div class="card"><div class="table-wrap">
  <table><thead><tr><th>Mã đơn</th><th>Đối tác</th><th>Khách</th><th>Gói</th><th>Tổng</th><th>Trạng thái</th><th>Tạo lúc</th></tr></thead><tbody>
  <?php foreach ($ctvOrders as $o):
    $s=(int)$o['status']; $sLabel=match($s){0=>'Chờ',1=>'Hết hạn',2=>'Thành công',3=>'Thất bại',default=>(string)$s};
    $sCls=match($s){2=>'ok',3=>'err',0=>'warn',default=>'info'};
  ?>
  <tr>
    <td><a class="rowlink kbd" href="/admin/ctv/order-view.php?id=<?= htmlspecialchars(urlencode((string)$o['ctv_order_id'])) ?>"><?= htmlspecialchars((string)$o['ctv_order_id']) ?></a></td>
    <td><?= htmlspecialchars((string)($o['ctv_email'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($o['customer_email'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)$o['plan_name']) ?></td>
    <td><?= htmlspecialchars(format_vnd((int)$o['total_charge'])) ?></td>
    <td><span class="tag <?= $sCls ?>"><?= $sLabel ?></span></td>
    <td><span class="muted"><?= htmlspecialchars((string)$o['created_at']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div></div>
</div>
<?php endif; ?>

<?php if ($retail): ?>
<div class="section">
  <h3>Đơn lẻ <span class="count"><?= count($retail) ?></span></h3>
  <div class="card"><div class="table-wrap">
  <table><thead><tr><th>Mã đơn</th><th>Email</th><th>Gói</th><th>Tổng</th><th>Trạng thái</th><th>Tạo lúc</th></tr></thead><tbody>
  <?php foreach ($retail as $r):
    $s=(int)$r['status']; $sLabel=match($s){0=>'Chờ',1=>'Hết hạn',2=>'Thành công',3=>'Thất bại',default=>(string)$s};
    $sCls=match($s){2=>'ok',3=>'err',0=>'warn',default=>'info'};
  ?>
  <tr>
    <td><span class="kbd"><?= htmlspecialchars((string)$r['order_id']) ?></span></td>
    <td><?= htmlspecialchars((string)$r['email']) ?></td>
    <td><?= htmlspecialchars((string)$r['plan_name']) ?></td>
    <td><?= htmlspecialchars(format_vnd((int)$r['total'])) ?></td>
    <td><span class="tag <?= $sCls ?>"><?= $sLabel ?></span></td>
    <td><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div></div>
</div>
<?php endif; ?>

<?php if ($esims): ?>
<div class="section">
  <h3>eSIM <span class="count"><?= count($esims) ?></span></h3>
  <div class="card"><div class="table-wrap">
  <table><thead><tr><th>ICCID</th><th>Đơn</th><th>Carrier</th><th>SM-DP+</th><th>eSIM</th><th>Email</th><th>Tạo lúc</th></tr></thead><tbody>
  <?php foreach ($esims as $e): ?>
  <tr>
    <td><span class="kbd"><?= htmlspecialchars((string)$e['iccid']) ?></span></td>
    <td><a class="rowlink" href="/admin/ctv/order-view.php?id=<?= htmlspecialchars(urlencode((string)$e['ctv_order_id'])) ?>"><?= htmlspecialchars((string)$e['ctv_order_id']) ?></a></td>
    <td><?= htmlspecialchars((string)$e['carrier']) ?></td>
    <td><span class="muted"><?= htmlspecialchars((string)$e['smdp_status']) ?></span></td>
    <td><span class="muted"><?= htmlspecialchars((string)$e['esim_status']) ?></span></td>
    <td><span class="tag <?= !empty($e['email_sent_at'])?'ok':'warn' ?>"><?= !empty($e['email_sent_at'])?'Đã gửi':'Chưa gửi' ?></span></td>
    <td><span class="muted"><?= htmlspecialchars((string)$e['created_at']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div></div>
</div>
<?php endif; ?>

<?php if ($topupOrders): ?>
<div class="section">
  <h3>Đơn nạp data <span class="count"><?= count($topupOrders) ?></span></h3>
  <div class="card"><div class="table-wrap">
  <table><thead><tr><th>Mã</th><th>ICCID</th><th>Đối tác</th><th>Gói</th><th>Tổng</th><th>Trạng thái</th><th>Tạo lúc</th></tr></thead><tbody>
  <?php foreach ($topupOrders as $t):
    $s=(int)$t['status']; $sLabel=match($s){0=>'Chờ',1=>'Hết hạn',2=>'Thành công',3=>'Thất bại',default=>(string)$s};
    $sCls=match($s){2=>'ok',3=>'err',0=>'warn',default=>'info'};
  ?>
  <tr>
    <td><span class="kbd"><?= htmlspecialchars((string)$t['ctv_topup_id']) ?></span></td>
    <td><span class="kbd"><?= htmlspecialchars((string)$t['iccid']) ?></span></td>
    <td><?= htmlspecialchars((string)($t['ctv_email'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)$t['plan_name']) ?></td>
    <td><?= htmlspecialchars(format_vnd((int)$t['total_charge'])) ?></td>
    <td><span class="tag <?= $sCls ?>"><?= $sLabel ?></span></td>
    <td><span class="muted"><?= htmlspecialchars((string)$t['created_at']) ?></span></td>
  </tr>
  <?php endforeach; ?>
  </tbody></table>
  </div></div>
</div>
<?php endif; ?>

<?php endif; admin_layout_footer();
