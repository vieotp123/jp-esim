<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();

$kind = (string)($_GET['kind'] ?? '');
if ($kind === 'orders' || $kind === 'topups' || $kind === 'wallet' || $kind === 'esims') {
    if (!CtvAuth::checkCsrf($_GET['_csrf'] ?? null)) { http_response_code(400); echo 'Invalid export token'; exit; }
    header('Cache-Control: no-store');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ctv_' . $kind . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    if ($kind === 'orders') {
        fputcsv($out, ['ctv_order_id','plan','retail','discount','ctv_price','quantity','total','status','provider_order_no','iccid','created_at']);
        $st = db()->prepare('SELECT ctv_order_id,carrier,plan_name,retail_price,discount,ctv_price,quantity,total_charge,status,provider_order_no,iccid,created_at FROM ctv_orders WHERE ctv_id=? ORDER BY id DESC LIMIT 5000');
        $st->execute([(int)$user['id']]);
        $statusMap=[0=>'pending',1=>'processing',2=>'success',3=>'failed'];
        foreach ($st->fetchAll() as $r) fputcsv($out, [$r['ctv_order_id'],$r['carrier'].' '.$r['plan_name'],$r['retail_price'],$r['discount'],$r['ctv_price'],$r['quantity'],$r['total_charge'],$statusMap[(int)$r['status']]??'',$r['provider_order_no'],$r['iccid'],$r['created_at']]);
    } elseif ($kind === 'topups') {
        fputcsv($out, ['ctv_topup_id','iccid','plan','retail','discount','ctv_price','total','status','created_at']);
        $st = db()->prepare('SELECT ctv_topup_id,iccid,carrier,plan_name,retail_price,discount,ctv_price,total_charge,status,created_at FROM ctv_topup_orders WHERE ctv_id=? ORDER BY id DESC LIMIT 5000');
        $st->execute([(int)$user['id']]);
        $statusMap=[0=>'pending',1=>'processing',2=>'success',3=>'failed'];
        foreach ($st->fetchAll() as $r) fputcsv($out, [$r['ctv_topup_id'],$r['iccid'],$r['carrier'].' '.$r['plan_name'],$r['retail_price'],$r['discount'],$r['ctv_price'],$r['total_charge'],$statusMap[(int)$r['status']]??'',$r['created_at']]);
    } elseif ($kind === 'wallet') {
        fputcsv($out, ['created_at','reason','amount','balance_after','ref_type','ref_id','note']);
        $st = db()->prepare('SELECT created_at,reason,amount,balance_after,ref_type,ref_id,note FROM ctv_wallet_transactions WHERE ctv_id=? ORDER BY id DESC LIMIT 5000');
        $st->execute([(int)$user['id']]);
        foreach ($st->fetchAll() as $r) fputcsv($out, [$r['created_at'],$r['reason'],$r['amount'],$r['balance_after'],$r['ref_type'],$r['ref_id'],$r['note']]);
    } else {
        fputcsv($out, ['iccid','ctv_order_id','carrier','package_name','expired_time','esim_status','created_at']);
        $st = db()->prepare('SELECT iccid,ctv_order_id,carrier,package_name,expired_time,esim_status,created_at FROM ctv_esims WHERE ctv_id=? ORDER BY id DESC LIMIT 5000');
        $st->execute([(int)$user['id']]);
        foreach ($st->fetchAll() as $r) fputcsv($out, [$r['iccid'],$r['ctv_order_id'],$r['carrier'],$r['package_name'],$r['expired_time'],$r['esim_status'],$r['created_at']]);
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
  <p class="muted">Tải xuống tối đa 5,000 dòng gần nhất cho mỗi loại.</p>
  <p>
    <a class="btn" href="?kind=orders&_csrf=<?= urlencode($csrf) ?>">Đơn eSIM</a>
    <a class="btn" href="?kind=topups&_csrf=<?= urlencode($csrf) ?>">Đơn nạp data</a>
    <a class="btn" href="?kind=esims&_csrf=<?= urlencode($csrf) ?>">eSIM</a>
    <a class="btn secondary" href="?kind=wallet&_csrf=<?= urlencode($csrf) ?>">Lịch sử ví</a>
  </p>
</div>
<?php ctv_layout_footer();
