<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();

$kind = (string)($_GET['kind'] ?? '');
if ($kind === 'orders' || $kind === 'topups' || $kind === 'wallet' || $kind === 'esims') {
    if (!CtvAuth::checkCsrf($_GET['_csrf'] ?? null)) { http_response_code(400); echo 'Mã xác thực không hợp lệ'; exit; }
    $from = (string)($_GET['from'] ?? '');
    $to = (string)($_GET['to'] ?? '');
    $dateWhere = '';
    $dateParams = [(int)$user['id']];
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $dateWhere .= ' AND created_at >= ?'; $dateParams[] = $from . ' 00:00:00'; }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $dateWhere .= ' AND created_at <= ?'; $dateParams[] = $to . ' 23:59:59'; }
    header('Cache-Control: no-store');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ctv_' . $kind . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($kind === 'orders') {
        fputcsv($out, ['ctv_order_id','plan','retail','discount','ctv_price','quantity','total','status','iccid','created_at']);
        $st = db()->prepare('SELECT ctv_order_id,carrier,plan_name,retail_price,discount,ctv_price,quantity,total_charge,status,iccid,created_at FROM ctv_orders WHERE ctv_id=?' . $dateWhere . ' ORDER BY id DESC LIMIT 10000');
        $st->execute($dateParams);
        $statusMap = [0 => 'pending', 1 => 'processing', 2 => 'success', 3 => 'failed'];
        foreach ($st->fetchAll() as $r) fputcsv($out, [$r['ctv_order_id'], $r['carrier'] . ' ' . $r['plan_name'], $r['retail_price'], $r['discount'], $r['ctv_price'], $r['quantity'], $r['total_charge'], $statusMap[(int)$r['status']] ?? '', $r['iccid'], $r['created_at']]);
    } elseif ($kind === 'topups') {
        fputcsv($out, ['ctv_topup_id','iccid','plan','retail','discount','ctv_price','total','status','created_at']);
        $st = db()->prepare('SELECT ctv_topup_id,iccid,carrier,plan_name,retail_price,discount,ctv_price,total_charge,status,created_at FROM ctv_topup_orders WHERE ctv_id=?' . $dateWhere . ' ORDER BY id DESC LIMIT 10000');
        $st->execute($dateParams);
        $statusMap = [0 => 'pending', 1 => 'processing', 2 => 'success', 3 => 'failed'];
        foreach ($st->fetchAll() as $r) fputcsv($out, [$r['ctv_topup_id'], $r['iccid'], $r['carrier'] . ' ' . $r['plan_name'], $r['retail_price'], $r['discount'], $r['ctv_price'], $r['total_charge'], $statusMap[(int)$r['status']] ?? '', $r['created_at']]);
    } elseif ($kind === 'wallet') {
        fputcsv($out, ['created_at','reason','amount','balance_after','ref_type','ref_id','note']);
        $st = db()->prepare('SELECT created_at,reason,amount,balance_after,ref_type,ref_id,note FROM ctv_wallet_transactions WHERE ctv_id=?' . $dateWhere . ' ORDER BY id DESC LIMIT 10000');
        $st->execute($dateParams);
        foreach ($st->fetchAll() as $r) fputcsv($out, [$r['created_at'], $r['reason'], $r['amount'], $r['balance_after'], $r['ref_type'], $r['ref_id'], $r['note']]);
    } else {
        fputcsv($out, ['iccid','ctv_order_id','carrier','package_name','expired_time','esim_status','created_at']);
        $st = db()->prepare('SELECT iccid,ctv_order_id,carrier,package_name,expired_time,esim_status,created_at FROM ctv_esims WHERE ctv_id=?' . $dateWhere . ' ORDER BY id DESC LIMIT 10000');
        $st->execute($dateParams);
        foreach ($st->fetchAll() as $r) fputcsv($out, [$r['iccid'], $r['ctv_order_id'], $r['carrier'], $r['package_name'], $r['expired_time'], $r['esim_status'], $r['created_at']]);
    }
    fclose($out);
    exit;
}

$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);
$csrf = CtvAuth::csrfToken();
ctv_layout_header('Xuất CSV', $user);
?>
<div class="card">
  <h2>Xuất dữ liệu CSV</h2>
  <p class="muted">Tải xuống tối đa 10,000 dòng. Để trống ngày để lấy tất cả.</p>
  <div class="row" style="margin-bottom:14px">
    <div class="field"><label>Từ ngày</label><input type="date" id="exportFrom"></div>
    <div class="field"><label>Đến ngày</label><input type="date" id="exportTo"></div>
  </div>
  <div class="actions">
    <a class="btn" onclick="doExport('orders')" href="#">Đơn eSIM</a>
    <a class="btn" onclick="doExport('topups')" href="#">Đơn nạp data</a>
    <a class="btn" onclick="doExport('esims')" href="#">eSIM</a>
    <a class="btn secondary" onclick="doExport('wallet')" href="#">Lịch sử ví</a>
  </div>
</div>
<script>
function doExport(kind){
  var from=document.getElementById('exportFrom').value;
  var to=document.getElementById('exportTo').value;
  var url='?kind='+kind+'&_csrf=<?= urlencode($csrf) ?>';
  if(from)url+='&from='+from;
  if(to)url+='&to='+to;
  window.location.href=url;
}
</script>
<?php ctv_layout_footer();
