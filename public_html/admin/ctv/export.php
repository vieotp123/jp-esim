<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();

$kind = (string)($_GET['kind'] ?? '');
$from = (string)($_GET['from'] ?? '');
$to = (string)($_GET['to'] ?? '');
$ctvId = (int)($_GET['ctv_id'] ?? 0);

if (in_array($kind, ['orders', 'wallet', 'topup_requests'], true)) {
    $dateWhere = '';
    $params = [];
    if ($ctvId > 0) {
        $dateWhere .= ' AND ctv_id = ?';
        $params[] = $ctvId;
    }
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $dateWhere .= ' AND created_at >= ?';
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $dateWhere .= ' AND created_at <= ?';
        $params[] = $to . ' 23:59:59';
    }

    header('Cache-Control: no-store');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="admin_' . $kind . '_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    if ($kind === 'orders') {
        fputcsv($out, ['ctv_order_id', 'ctv_id', 'ctv_email', 'carrier', 'plan_name', 'quantity', 'retail_price', 'discount', 'ctv_price', 'total_charge', 'status', 'needs_admin', 'error_message', 'created_at', 'updated_at']);
        $st = db()->prepare('SELECT o.*, u.email AS ctv_email FROM ctv_orders o LEFT JOIN ctv_users u ON u.id=o.ctv_id WHERE 1' . str_replace('ctv_id', 'o.ctv_id', str_replace('created_at', 'o.created_at', $dateWhere)) . ' ORDER BY o.id DESC LIMIT 50000');
        $st->execute($params);
        $statusMap = [0 => 'pending', 1 => 'processing', 2 => 'success', 3 => 'failed'];
        foreach ($st->fetchAll() as $r) {
            fputcsv($out, [
                $r['ctv_order_id'], $r['ctv_id'], $r['ctv_email'] ?? '',
                $r['carrier'], $r['plan_name'], $r['quantity'],
                $r['retail_price'], $r['discount'], $r['ctv_price'], $r['total_charge'],
                $statusMap[(int)$r['status']] ?? '', (int)$r['needs_admin'],
                $r['error_message'] ?? '', $r['created_at'], $r['updated_at'],
            ]);
        }
    } elseif ($kind === 'wallet') {
        fputcsv($out, ['id', 'ctv_id', 'reason', 'amount', 'balance_after', 'ref_type', 'ref_id', 'note', 'admin_user', 'created_at']);
        $st = db()->prepare('SELECT * FROM ctv_wallet_transactions WHERE 1' . $dateWhere . ' ORDER BY id DESC LIMIT 50000');
        $st->execute($params);
        foreach ($st->fetchAll() as $r) {
            fputcsv($out, [
                $r['id'], $r['ctv_id'], $r['reason'], $r['amount'], $r['balance_after'],
                $r['ref_type'], $r['ref_id'], $r['note'] ?? '', $r['admin_user'] ?? '', $r['created_at'],
            ]);
        }
    } elseif ($kind === 'topup_requests') {
        fputcsv($out, ['id', 'ctv_id', 'ctv_email', 'amount', 'status', 'admin_note', 'resolved_by', 'created_at', 'resolved_at']);
        $st = db()->prepare('SELECT r.*, u.email AS ctv_email FROM ctv_topup_requests r LEFT JOIN ctv_users u ON u.id=r.ctv_id WHERE 1' . str_replace('ctv_id', 'r.ctv_id', str_replace('created_at', 'r.created_at', $dateWhere)) . ' ORDER BY r.id DESC LIMIT 50000');
        $st->execute($params);
        foreach ($st->fetchAll() as $r) {
            fputcsv($out, [
                $r['id'], $r['ctv_id'], $r['ctv_email'] ?? '', $r['amount'],
                $r['status'], $r['admin_note'] ?? '', $r['resolved_by'] ?? '',
                $r['created_at'], $r['resolved_at'] ?? '',
            ]);
        }
    }
    fclose($out);
    AuditLog::log($admin['user'], 'admin_export', 'system', $kind, ['from' => $from, 'to' => $to, 'ctv_id' => $ctvId]);
    exit;
}

admin_layout_header('Xuất báo cáo', $admin);
?>
<div class="card">
  <h2>Xuất báo cáo CSV</h2>
  <p class="muted" style="margin-bottom:14px">Tải xuống tối đa 50,000 dòng. Để trống ngày để lấy tất cả.</p>
  <form method="get" class="action-group">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:14px">
      <div class="field"><label>Từ ngày</label><input type="date" name="from"></div>
      <div class="field"><label>Đến ngày</label><input type="date" name="to"></div>
      <div class="field"><label>CTV ID (tuỳ chọn)</label><input type="number" name="ctv_id" min="0" placeholder="Tất cả"></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn gold" name="kind" value="orders">Đơn đối tác</button>
      <button class="btn" name="kind" value="wallet">Giao dịch ví</button>
      <button class="btn secondary" name="kind" value="topup_requests">Yêu cầu nạp ví</button>
    </div>
  </form>
</div>
<?php admin_layout_footer();
