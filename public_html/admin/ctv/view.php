<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();
$id = max(0, (int)($_GET['id'] ?? $_POST['ctv_id'] ?? 0));
if ($id <= 0) { http_response_code(400); echo 'Mã CTV không hợp lệ'; exit; }
$flash=null;
function load_ctv(int $id): array { $st=db()->prepare('SELECT * FROM ctv_users WHERE id=? LIMIT 1'); $st->execute([$id]); $r=$st->fetch(); if(!$r) { http_response_code(404); echo 'Không tìm thấy CTV'; exit; } return $r; }
try { if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=(string)($_POST['action']??''); $ctv=load_ctv($id);
    if ($action==='set_status') { db()->prepare('UPDATE ctv_users SET status=? WHERE id=?')->execute([(int)($_POST['status']??0)?1:0,$id]); $flash=['ok','Đã cập nhật trạng thái']; }
    elseif ($action==='set_discount') { $d=(int)($_POST['discount']??0); if($d<0||$d>500000) throw new RuntimeException('Chiết khấu không hợp lệ'); db()->prepare('UPDATE ctv_users SET discount_per_esim=? WHERE id=?')->execute([$d,$id]); $flash=['ok','Đã cập nhật chiết khấu']; }
    elseif ($action==='wallet_credit'||$action==='wallet_debit') { $amount=(int)($_POST['amount']??0); $note=trim((string)($_POST['note']??'')); $cap=(int)app_config('CTV_ADMIN_WALLET_CAP',100000000); if($amount<=0||$amount>$cap) throw new RuntimeException('Số tiền không hợp lệ'); if($note==='') throw new RuntimeException('Ghi chú là bắt buộc'); $svc=new CtvWalletService(); if($action==='wallet_credit') $svc->credit($id,$amount,'admin_credit','manual',null,$note,$admin['user']); else $svc->debit($id,$amount,'admin_debit','manual',null,$note,$admin['user']); $flash=['ok','Đã cập nhật ví']; }
} } catch(Throwable $e) { $flash=['err','Lỗi: '.$e->getMessage()]; }
$ctv=load_ctv($id);
$tx=(new CtvWalletService())->transactions($id,80);
$orders=db()->prepare('SELECT * FROM ctv_orders WHERE ctv_id=? ORDER BY id DESC LIMIT 50'); $orders->execute([$id]); $orders=$orders->fetchAll();
$topups=db()->prepare('SELECT * FROM ctv_topup_orders WHERE ctv_id=? ORDER BY id DESC LIMIT 50'); $topups->execute([$id]); $topups=$topups->fetchAll();
$api=db()->prepare('SELECT * FROM ctv_api_logs WHERE ctv_id=? ORDER BY id DESC LIMIT 80'); $api->execute([$id]); $api=$api->fetchAll();
$provider=db()->prepare('SELECT * FROM ctv_provider_logs WHERE ctv_id=? ORDER BY id DESC LIMIT 80'); $provider->execute([$id]); $provider=$provider->fetchAll();
admin_layout_header('CTV #'.$id, $admin); ?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>
<div class="card"><a class="btn secondary" href="/admin/ctv/index.php">← Danh sách</a><h2><?= htmlspecialchars((string)$ctv['email']) ?></h2><p>ID #<?= $id ?> · <span class="tag <?= (int)$ctv['status']?'ok':'err' ?>"><?= (int)$ctv['status']?'Hoạt động':'Đã khóa' ?></span> · Ví <?= htmlspecialchars(format_vnd((int)$ctv['balance'])) ?> · Chiết khấu <?= htmlspecialchars(format_vnd((int)$ctv['discount_per_esim'])) ?></p><p class="muted">Đăng nhập cuối: <?= htmlspecialchars((string)($ctv['last_login_at'] ?? '-')) ?> <?= htmlspecialchars((string)($ctv['last_login_ip'] ?? '')) ?></p></div>
<div class="card"><h3>Thao tác</h3><form method="post" class="inline"><?php admin_csrf_field(); ?><input type="hidden" name="ctv_id" value="<?= $id ?>"><input type="hidden" name="action" value="set_status"><input type="hidden" name="status" value="<?= (int)$ctv['status']?0:1 ?>"><button class="btn <?= (int)$ctv['status']?'danger':'' ?>"><?= (int)$ctv['status']?'Khóa':'Mở' ?></button></form><form method="post" class="inline"><?php admin_csrf_field(); ?><input type="hidden" name="ctv_id" value="<?= $id ?>"><input type="hidden" name="action" value="set_discount"><input type="number" name="discount" min="0" max="500000" value="<?= (int)$ctv['discount_per_esim'] ?>"><button class="btn">Lưu chiết khấu</button></form><form method="post"><?php admin_csrf_field(); ?><input type="hidden" name="ctv_id" value="<?= $id ?>"><input type="number" name="amount" min="1" placeholder="VND"><input name="note" required placeholder="Ghi chú"><button class="btn" name="action" value="wallet_credit">Nạp ví</button><button class="btn danger" name="action" value="wallet_debit" onclick="return confirm('Xác nhận trừ ví?')">Trừ ví</button></form></div>
<div class="card"><h3>Lịch sử ví</h3><table><tr><th>Thời gian</th><th>Số tiền</th><th>Số dư</th><th>Lý do</th><th>Ghi chú</th><th>Admin</th></tr><?php foreach($tx as $r): ?><tr><td><?= htmlspecialchars((string)$r['created_at']) ?></td><td><?= htmlspecialchars(format_vnd((int)$r['amount'])) ?></td><td><?= htmlspecialchars(format_vnd((int)$r['balance_after'])) ?></td><td><?= htmlspecialchars((string)$r['reason']) ?></td><td><?= htmlspecialchars((string)($r['note']??'')) ?></td><td><?= htmlspecialchars((string)($r['admin_user']??'')) ?></td></tr><?php endforeach; ?></table></div>
<div class="card"><h3>Đơn hàng</h3><table><tr><th>Mã đơn</th><th>Gói</th><th>Phí</th><th>Trạng thái</th><th>Lỗi</th></tr><?php foreach($orders as $r): ?><tr><td><?= htmlspecialchars((string)$r['ctv_order_id']) ?></td><td><?= htmlspecialchars((string)$r['plan_name']) ?></td><td><?= htmlspecialchars(format_vnd((int)$r['total_charge'])) ?></td><td><?= (int)$r['status'] ?></td><td><?= htmlspecialchars((string)($r['error_message']??'')) ?></td></tr><?php endforeach; ?></table></div>
<div class="card"><h3>Nạp data / Nhật ký</h3><p>Nạp data: <?= count($topups) ?> · Nhật ký API: <?= count($api) ?> · Nhật ký hệ thống: <?= count($provider) ?></p><p><a class="btn secondary" href="/admin/ctv/logs.php?kind=wallet&ctv_id=<?= $id ?>">Xem nhật ký ví</a> <a class="btn secondary" href="/admin/ctv/logs.php?kind=api&ctv_id=<?= $id ?>">Xem nhật ký API</a> <a class="btn secondary" href="/admin/ctv/logs.php?kind=provider&ctv_id=<?= $id ?>">Xem nhật ký hệ thống</a></p></div>
<?php admin_layout_footer();
