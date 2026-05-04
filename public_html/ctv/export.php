<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();

function ctv_export_table_columns(string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $allowed = ['ctv_esims', 'ctv_orders'];
    if (!in_array($table, $allowed, true)) return [];
    try {
        $rows = db()->query('SHOW COLUMNS FROM ' . $table)->fetchAll();
        $cols = [];
        foreach ($rows as $row) {
            $field = (string)($row['Field'] ?? '');
            if ($field !== '') $cols[$field] = true;
        }
        return $cache[$table] = $cols;
    } catch (Throwable $e) {
        app_log('ctv export column discovery failed for ' . $table . ': ' . $e->getMessage(), 'WARN');
        return $cache[$table] = [];
    }
}

function ctv_export_col(string $table, string $alias, array $candidates, string $as): string {
    $cols = ctv_export_table_columns($table);
    foreach ($candidates as $col) {
        if (isset($cols[$col]) && preg_match('/^[A-Za-z0-9_]+$/', $col)) {
            return $alias . '.`' . $col . '` AS `' . $as . '`';
        }
    }
    return "'' AS `" . $as . "`";
}

function ctv_export_bytes_to_gb($value): string {
    if ($value === null || $value === '') return '';
    $bytes = (float)$value;
    if ($bytes <= 0) return '';
    return rtrim(rtrim(number_format($bytes / 1073741824, 2, '.', ''), '0'), '.') . ' GB';
}

function ctv_export_remaining_days($expiredAt, $fallback = ''): string {
    $fallback = trim((string)$fallback);
    if ($fallback !== '') return $fallback;
    $expiredAt = trim((string)$expiredAt);
    if ($expiredAt === '') return '';
    $ts = strtotime($expiredAt);
    if (!$ts) return '';
    return (string)max(0, (int)ceil(($ts - time()) / 86400));
}

function ctv_export_status_label($status): string {
    if (is_numeric($status)) {
        return [0 => 'pending', 1 => 'processing', 2 => 'success', 3 => 'failed'][(int)$status] ?? '';
    }
    return (string)$status;
}

function ctv_export_esim_headers($out): void {
    fputcsv($out, [
        'order_id',
        'customer_email',
        'customer_name',
        'iccid',
        'carrier',
        'package',
        'plan',
        'status',
        'created_at',
        'activated_at',
        'expired_at',
        'remaining_data',
        'remaining_days',
    ]);
}

function ctv_export_esim_row($out, array $r): void {
    $remainingData = (string)($r['remaining_data'] ?? '');
    if ($remainingData === '' && isset($r['remaining_volume']) && $r['remaining_volume'] !== '') {
        $remainingData = ctv_export_bytes_to_gb($r['remaining_volume']);
    }
    fputcsv($out, [
        $r['order_id'] ?? '',
        $r['customer_email'] ?? '',
        $r['customer_name'] ?? '',
        $r['iccid'] ?? '',
        $r['carrier'] ?? '',
        $r['package'] ?? '',
        $r['plan'] ?? '',
        ctv_export_status_label($r['status'] ?? ''),
        $r['created_at'] ?? '',
        $r['activated_at'] ?? '',
        $r['expired_at'] ?? '',
        $remainingData,
        ctv_export_remaining_days($r['expired_at'] ?? '', $r['remaining_days'] ?? ''),
    ]);
}

function ctv_export_esim_select_sql(): string {
    return 'SELECT e.ctv_order_id AS order_id, '
        . ctv_export_col('ctv_orders', 'o', ['email'], 'customer_email') . ', '
        . ctv_export_col('ctv_orders', 'o', ['customer_name', 'name'], 'customer_name') . ', '
        . 'e.iccid AS iccid, e.carrier AS carrier, e.package_name AS `package`, o.plan_name AS plan, '
        . 'COALESCE(NULLIF(e.esim_status, \'\'), NULLIF(e.smdp_status, \'\'), o.status) AS status, '
        . 'e.created_at AS created_at, '
        . ctv_export_col('ctv_esims', 'e', ['activated_at', 'activatedAt'], 'activated_at') . ', '
        . ctv_export_col('ctv_esims', 'e', ['expired_at', 'expiredAt', 'expired_time'], 'expired_at') . ', '
        . ctv_export_col('ctv_esims', 'e', ['remaining_data', 'remaining_gb', 'remainingGB'], 'remaining_data') . ', '
        . ctv_export_col('ctv_esims', 'e', ['remaining_volume', 'remainingVolume'], 'remaining_volume') . ', '
        . ctv_export_col('ctv_esims', 'e', ['remaining_days'], 'remaining_days') . ' '
        . 'FROM ctv_esims e LEFT JOIN ctv_orders o ON o.ctv_order_id=e.ctv_order_id AND o.ctv_id=e.ctv_id ';
}

function ctv_export_parse_ids($raw): array {
    $parts = is_array($raw) ? $raw : preg_split('/[,\s]+/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY);
    $ids = [];
    foreach ($parts ?: [] as $part) {
        $value = trim((string)$part);
        if ($value === '') continue;
        if (!preg_match('/^\d+$/', $value)) {
            http_response_code(400);
            echo 'Danh sách eSIM không hợp lệ';
            exit;
        }
        $id = (int)$value;
        if ($id > 0) $ids[$id] = $id;
    }
    return array_values($ids);
}

$kind = (string)($_REQUEST['kind'] ?? $_REQUEST['type'] ?? '');
if ($kind === 'orders' || $kind === 'topups' || $kind === 'wallet' || $kind === 'esims') {
    if (!CtvAuth::checkCsrf($_REQUEST['_csrf'] ?? null)) { http_response_code(400); echo 'Mã xác thực không hợp lệ'; exit; }
    $from = (string)($_REQUEST['from'] ?? '');
    $to = (string)($_REQUEST['to'] ?? '');
    $ids = ctv_export_parse_ids($_REQUEST['ids'] ?? '');
    $orderId = strtoupper(trim((string)($_REQUEST['order_id'] ?? $_REQUEST['id'] ?? '')));
    $dateWhere = '';
    $dateParams = [(int)$user['id']];
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $dateWhere .= ' AND created_at >= ?'; $dateParams[] = $from . ' 00:00:00'; }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $dateWhere .= ' AND created_at <= ?'; $dateParams[] = $to . ' 23:59:59'; }
    if ($kind === 'esims' && $ids) {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $check = db()->prepare('SELECT COUNT(*) FROM ctv_esims WHERE ctv_id=? AND id IN (' . $marks . ')');
        $check->execute(array_merge([(int)$user['id']], $ids));
        if ((int)$check->fetchColumn() !== count($ids)) {
            http_response_code(403);
            echo 'Không thể xuất eSIM không thuộc tài khoản CTV hiện tại';
            exit;
        }
    }
    if ($kind === 'esims' && $orderId !== '') {
        if (!preg_match('/^[A-Z0-9]{2,16}$/', $orderId)) {
            http_response_code(400);
            echo 'Mã đơn không hợp lệ';
            exit;
        }
        $own = db()->prepare('SELECT 1 FROM ctv_orders WHERE ctv_order_id=? AND ctv_id=? LIMIT 1');
        $own->execute([$orderId, (int)$user['id']]);
        if (!$own->fetchColumn()) {
            http_response_code(404);
            echo 'Không tìm thấy đơn';
            exit;
        }
    }
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
        ctv_export_esim_headers($out);
        if ($ids) {
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $st = db()->prepare(ctv_export_esim_select_sql() . 'WHERE e.ctv_id=? AND e.id IN (' . $marks . ') ORDER BY e.id DESC LIMIT 10000');
            $st->execute(array_merge([(int)$user['id']], $ids));
        } elseif ($orderId !== '') {
            $st = db()->prepare(ctv_export_esim_select_sql() . 'WHERE e.ctv_id=? AND e.ctv_order_id=? ORDER BY e.id ASC LIMIT 10000');
            $st->execute([(int)$user['id'], $orderId]);
        } else {
            $st = db()->prepare(ctv_export_esim_select_sql() . 'WHERE e.ctv_id=?' . str_replace('created_at', 'e.created_at', $dateWhere) . ' ORDER BY e.id DESC LIMIT 10000');
            $st->execute($dateParams);
        }
        foreach ($st->fetchAll() as $r) ctv_export_esim_row($out, $r);
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
