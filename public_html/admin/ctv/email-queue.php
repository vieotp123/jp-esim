<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();

$status = (string)($_GET['status'] ?? 'all');
$where = 'WHERE 1'; $params = [];
if ($status === 'sent') $where .= ' AND e.email_sent_at IS NOT NULL';
elseif ($status === 'pending') $where .= ' AND e.email_sent_at IS NULL AND (e.email_last_error IS NULL OR e.email_last_error = "")';
elseif ($status === 'error') $where .= ' AND e.email_sent_at IS NULL AND e.email_last_error IS NOT NULL AND e.email_last_error <> ""';

$sum = db()->query("SELECT COUNT(*) total, SUM(email_sent_at IS NOT NULL) sent, SUM(email_sent_at IS NULL AND (email_last_error IS NULL OR email_last_error='')) pending, SUM(email_sent_at IS NULL AND email_last_error IS NOT NULL AND email_last_error<>'') failed FROM ctv_esims")->fetch();
$st = db()->prepare("SELECT e.iccid,e.ctv_order_id,e.package_name,e.email_sent_at,e.email_attempts,e.email_last_error,e.created_at,o.email customer_email,u.email ctv_email FROM ctv_esims e LEFT JOIN ctv_orders o ON o.ctv_order_id=e.ctv_order_id LEFT JOIN ctv_users u ON u.id=e.ctv_id $where ORDER BY COALESCE(e.email_sent_at,e.created_at) DESC LIMIT 300");
$st->execute($params); $rows = $st->fetchAll();

admin_layout_header('Hàng đợi Email QR', $admin);
function _pill(string $key, string $label, $count, string $active): void { $cls=$active===$key?'pill active':'pill'; echo '<a class="'.$cls.'" href="?status='.htmlspecialchars($key).'">'.htmlspecialchars($label).' <span class="count">'.(int)$count.'</span></a>'; }
?>
<div class="summary">
  <div class="card"><b>Tổng eSIM</b><h2><?= (int)$sum['total'] ?></h2></div>
  <div class="card green"><b>Đã gửi</b><h2><?= (int)$sum['sent'] ?></h2></div>
  <div class="card gold"><b>Chưa gửi</b><h2><?= (int)$sum['pending'] ?></h2></div>
  <div class="card danger"><b>Lỗi email</b><h2><?= (int)$sum['failed'] ?></h2></div>
</div>
<div class="card">
  <div class="filter-row">
    <?php _pill('all','Tất cả',$sum['total'],$status); _pill('sent','Đã gửi',$sum['sent'],$status); _pill('pending','Chưa gửi',$sum['pending'],$status); _pill('error','Lỗi',$sum['failed'],$status); ?>
    <span class="spacer"></span><a class="btn secondary" href="/admin/ctv/orders.php">Đơn CTV</a>
  </div>
</div>
<div class="card"><h2>Email QR (<?= count($rows) ?>)</h2>
  <?php if (!$rows): ?>
    <div class="empty"><div class="icon">📧</div><p>Không có email nào trong bộ lọc hiện tại.</p></div>
  <?php else: ?>
  <div class="table-wrap">
  <table><thead><tr><th>eSIM</th><th>Đơn</th><th>CTV</th><th>Email khách</th><th>Gói</th><th>Trạng thái</th><th>Lần thử</th><th>Lỗi cuối</th></tr></thead><tbody>
  <?php foreach ($rows as $r): $sent=!empty($r['email_sent_at']); $err=!$sent && !empty($r['email_last_error']); ?>
  <tr>
    <td><span class="kbd"><?= htmlspecialchars((string)$r['iccid']) ?></span><br><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
    <td><a class="rowlink" href="/admin/ctv/orders.php?q=<?= rawurlencode((string)$r['ctv_order_id']) ?>"><?= htmlspecialchars((string)$r['ctv_order_id']) ?></a></td>
    <td><?= htmlspecialchars((string)($r['ctv_email'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($r['customer_email'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($r['package_name'] ?? '')) ?></td>
    <td><?php if($sent): ?><span class="tag ok">Đã gửi</span><br><span class="muted"><?= htmlspecialchars((string)$r['email_sent_at']) ?></span><?php elseif($err): ?><span class="tag err">Lỗi</span><?php else: ?><span class="tag warn">Chờ gửi</span><?php endif; ?></td>
    <td><?= (int)($r['email_attempts'] ?? 0) ?></td>
    <td style="max-width:360px"><?= htmlspecialchars(mb_strimwidth((string)($r['email_last_error'] ?? ''), 0, 220, '...')) ?></td>
  </tr>
  <?php endforeach; ?></tbody></table>
  </div>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
