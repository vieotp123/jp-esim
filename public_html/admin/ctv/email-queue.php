<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
admin_require_post();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $orderId = trim((string)($_POST['order_id'] ?? ''));
    try {
        if ($action === 'retry' && $orderId !== '') {
            $result = (new CtvMailService())->sendForOrderIfNeeded($orderId);
            AuditLog::log($admin['user'], 'email_retry', 'ctv_order', $orderId, $result);
            if ($result['sent'] > 0) {
                admin_flash_set('ok', 'Đã gửi lại ' . $result['sent'] . ' email cho đơn ' . $orderId);
            } else {
                admin_flash_set('warn', 'Không có email nào cần gửi lại cho đơn ' . $orderId . ' (' . ($result['reason'] ?? 'đã gửi hoặc thiếu dữ liệu') . ')');
            }
        }
    } catch (Throwable $e) {
        admin_flash_set('err', 'Lỗi: ' . $e->getMessage());
    }
    admin_redirect_self();
}

$status = (string)($_GET['status'] ?? 'all');
$where = 'WHERE 1'; $params = [];
if ($status === 'sent') $where .= ' AND e.email_sent_at IS NOT NULL';
elseif ($status === 'pending') $where .= ' AND e.email_sent_at IS NULL AND (e.email_last_error IS NULL OR e.email_last_error = "")';
elseif ($status === 'error') $where .= ' AND e.email_sent_at IS NULL AND e.email_last_error IS NOT NULL AND e.email_last_error <> ""';

$sum = db()->query("SELECT COUNT(*) total, SUM(email_sent_at IS NOT NULL) sent, SUM(email_sent_at IS NULL AND (email_last_error IS NULL OR email_last_error='')) pending, SUM(email_sent_at IS NULL AND email_last_error IS NOT NULL AND email_last_error<>'') failed FROM ctv_esims")->fetch();
$st = db()->prepare("SELECT e.iccid,e.ctv_order_id,e.carrier,e.total_volume,e.total_duration,e.duration_unit,e.email_sent_at,e.email_attempts,e.email_last_error,e.created_at,o.email customer_email,u.email ctv_email FROM ctv_esims e LEFT JOIN ctv_orders o ON o.ctv_order_id=e.ctv_order_id LEFT JOIN ctv_users u ON u.id=e.ctv_id $where ORDER BY COALESCE(e.email_sent_at,e.created_at) DESC LIMIT 300");
$st->execute($params); $rows = $st->fetchAll();

admin_layout_header('Hàng đợi Email QR', $admin);
function _pill(string $key, string $label, $count, string $active): void { $cls=$active===$key?'pill active':'pill'; echo '<a class="'.$cls.'" href="?status='.htmlspecialchars($key).'">'.htmlspecialchars($label).' <span class="count">'.(int)$count.'</span></a>'; }
function admin_email_data_label($bytes): string {
    $bytes = (float)$bytes;
    if ($bytes <= 0) return 'Data';
    return rtrim(rtrim(number_format($bytes / 1073741824, 2, '.', ''), '0'), '.') . ' GB';
}
function admin_email_profile_label(array $r): string {
    $parts = [];
    $carrier = trim((string)($r['carrier'] ?? ''));
    if ($carrier !== '') $parts[] = $carrier;
    $parts[] = admin_email_data_label($r['total_volume'] ?? 0);
    $days = (int)($r['total_duration'] ?? 0);
    if ($days > 0) $parts[] = $days . ' ngày';
    return implode(' · ', $parts);
}
?>
<?php admin_flash_render(); ?>
<div class="summary">
  <div class="card"><b>Tổng eSIM</b><h2><?= (int)$sum['total'] ?></h2></div>
  <div class="card green"><b>Đã gửi</b><h2><?= (int)$sum['sent'] ?></h2></div>
  <div class="card gold"><b>Chưa gửi</b><h2><?= (int)$sum['pending'] ?></h2></div>
  <div class="card danger"><b>Lỗi email</b><h2><?= (int)$sum['failed'] ?></h2></div>
</div>
<div class="card">
  <div class="filter-row">
    <?php _pill('all','Tất cả',$sum['total'],$status); _pill('sent','Đã gửi',$sum['sent'],$status); _pill('pending','Chưa gửi',$sum['pending'],$status); _pill('error','Lỗi',$sum['failed'],$status); ?>
    <span class="spacer"></span><a class="btn secondary" href="/admin/ctv/orders.php">Đơn đối tác</a>
  </div>
</div>
<div class="card"><h2>Email QR (<?= count($rows) ?>)</h2>
  <?php if (!$rows): ?>
    <div class="empty"><div class="icon">📧</div><p>Không có email nào trong bộ lọc hiện tại.</p></div>
  <?php else: ?>
  <div class="m-cards">
  <?php foreach ($rows as $r): $sent=!empty($r['email_sent_at']); $err=!$sent && !empty($r['email_last_error']); ?>
  <div class="m-card">
    <div class="m-head">
      <span class="kbd" style="font-size:10px"><?= htmlspecialchars(mb_strimwidth((string)$r['iccid'],0,20,'…')) ?></span>
      <?php if($sent): ?><span class="tag ok">Đã gửi</span><?php elseif($err): ?><span class="tag err">Lỗi</span><?php else: ?><span class="tag warn">Chờ</span><?php endif; ?>
    </div>
    <div class="m-row"><span class="m-label">Đơn</span><span class="m-val"><a href="/admin/ctv/orders.php?q=<?= rawurlencode((string)$r['ctv_order_id']) ?>"><?= htmlspecialchars((string)$r['ctv_order_id']) ?></a></span></div>
    <div class="m-row"><span class="m-label">Đối tác</span><span class="m-val"><?= htmlspecialchars((string)($r['ctv_email'] ?? '')) ?></span></div>
    <div class="m-row"><span class="m-label">Khách</span><span class="m-val"><?= htmlspecialchars((string)($r['customer_email'] ?? '')) ?></span></div>
    <div class="m-row"><span class="m-label">Gói</span><span class="m-val"><?= htmlspecialchars(admin_email_profile_label($r)) ?></span></div>
    <?php if($err): ?><div style="font-size:11px;color:var(--a-muted);margin-top:4px"><?= htmlspecialchars(mb_strimwidth((string)$r['email_last_error'],0,100,'...')) ?></div>
    <div class="m-actions"><form method="post"><?php admin_csrf_field(); ?><input type="hidden" name="action" value="retry"><input type="hidden" name="order_id" value="<?= htmlspecialchars((string)$r['ctv_order_id']) ?>"><button class="btn sm" type="submit">Gửi lại</button></form></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  </div>
  <div class="table-wrap">
  <table><thead><tr><th>eSIM</th><th>Đơn</th><th>Đối tác</th><th>Email khách</th><th>Gói</th><th>Trạng thái</th><th>Lần thử</th><th>Lỗi cuối</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): $sent=!empty($r['email_sent_at']); $err=!$sent && !empty($r['email_last_error']); ?>
  <tr>
    <td><span class="kbd"><?= htmlspecialchars((string)$r['iccid']) ?></span><br><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
    <td><a class="rowlink" href="/admin/ctv/orders.php?q=<?= rawurlencode((string)$r['ctv_order_id']) ?>"><?= htmlspecialchars((string)$r['ctv_order_id']) ?></a></td>
    <td><?= htmlspecialchars((string)($r['ctv_email'] ?? '')) ?></td>
    <td><?= htmlspecialchars((string)($r['customer_email'] ?? '')) ?></td>
    <td><?= htmlspecialchars(admin_email_profile_label($r)) ?></td>
    <td><?php if($sent): ?><span class="tag ok">Đã gửi</span><br><span class="muted"><?= htmlspecialchars((string)$r['email_sent_at']) ?></span><?php elseif($err): ?><span class="tag err">Lỗi</span><?php else: ?><span class="tag warn">Chờ gửi</span><?php endif; ?></td>
    <td><?= (int)($r['email_attempts'] ?? 0) ?></td>
    <td style="max-width:360px"><?= htmlspecialchars(mb_strimwidth((string)($r['email_last_error'] ?? ''), 0, 220, '...')) ?></td>
    <td><?php if($err): ?><form method="post" class="inline"><?php admin_csrf_field(); ?><input type="hidden" name="action" value="retry"><input type="hidden" name="order_id" value="<?= htmlspecialchars((string)$r['ctv_order_id']) ?>"><button class="btn sm" type="submit">Gửi lại</button></form><?php endif; ?></td>
  </tr>
  <?php endforeach; ?></tbody></table>
  </div>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
