<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();

$kind = (string)($_GET['kind'] ?? 'api');
$kindAllowed = ['api','provider','wallet'];
if (!in_array($kind, $kindAllowed, true)) $kind = 'api';
$ctvFilter = max(0, (int)($_GET['ctv_id'] ?? 0));
$qs = function(array $extra) use ($kind, $ctvFilter): string {
    $params = array_filter(array_merge(['kind'=>$kind, 'ctv_id'=>$ctvFilter ?: null], $extra), fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($params);
};
admin_layout_header("Nhật ký đối tác", $admin);
?>
<div class="card">
  <h2>Nhật ký hệ thống</h2>
  <p>
    <a class="btn <?= $kind==='api'?'':'secondary' ?>" href="<?= htmlspecialchars($qs(['kind'=>'api'])) ?>">Nhật ký API</a>
    <a class="btn <?= $kind==='provider'?'':'secondary' ?>" href="<?= htmlspecialchars($qs(['kind'=>'provider'])) ?>">Nhật ký xử lý</a>
    <a class="btn <?= $kind==='wallet'?'':'secondary' ?>" href="<?= htmlspecialchars($qs(['kind'=>'wallet'])) ?>">Ví đối tác</a>
    <?php if ($ctvFilter): ?>
      <span class="tag">Lọc đối tác #<?= (int)$ctvFilter ?></span>
      <a class="btn secondary" href="?kind=<?= htmlspecialchars($kind) ?>">Xóa lọc</a>
    <?php endif; ?>
  </p>
  <?php if ($kind === 'provider'): ?>
    <?php
      $sql = 'SELECT * FROM ctv_provider_logs' . ($ctvFilter ? ' WHERE ctv_id=?' : '') . ' ORDER BY id DESC LIMIT 100';
      $st = db()->prepare($sql); $st->execute($ctvFilter ? [$ctvFilter] : []); $rows = $st->fetchAll();
    ?>
    <?php if (!$rows): ?><div class="empty"><div class="icon">📡</div><p>Chưa có nhật ký xử lý nào.</p></div><?php else: ?>
    <div class="m-cards">
      <?php foreach ($rows as $r): ?>
      <div class="m-card">
        <div class="m-head"><span class="tag <?= (int)$r['success'] === 1 ? 'ok' : 'err' ?>"><?= (int)$r['success'] === 1 ? 'OK' : 'Lỗi' ?></span><span class="muted" style="font-size:12px"><?= htmlspecialchars((string)$r['created_at']) ?></span></div>
        <div class="m-row"><span class="m-label">Đối tác</span><span class="m-val"><a href="/admin/ctv/view.php?id=<?= (int)$r['ctv_id'] ?>">#<?= (int)$r['ctv_id'] ?></a></span></div>
        <div class="m-row"><span class="m-label">Loại</span><span class="m-val"><?= htmlspecialchars((string)$r['ref_type']) ?> <span class="kbd"><?= htmlspecialchars((string)($r['ref_id'] ?? '')) ?></span></span></div>
        <div class="m-row"><span class="m-label">Endpoint</span><span class="m-val muted"><?= htmlspecialchars((string)$r['endpoint']) ?></span></div>
        <div class="m-row"><span class="m-label">HTTP</span><span class="m-val"><?= (int)$r['http_status'] ?></span></div>
        <?php if (!empty($r['error_message'])): ?>
        <div class="m-row"><span class="m-label">Lỗi</span><span class="m-val" style="font-size:12px"><?= htmlspecialchars(mb_strimwidth((string)$r['error_message'], 0, 100, '…')) ?></span></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="table-wrap">
    <table>
      <thead><tr><th>Thời gian</th><th>Đối tác</th><th>Loại</th><th>Endpoint</th><th>HTTP</th><th>Kết quả</th><th>Lỗi</th><th>Yêu cầu</th><th>Phản hồi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td style="white-space:nowrap"><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
          <td><a class="rowlink" href="/admin/ctv/view.php?id=<?= (int)$r['ctv_id'] ?>">#<?= (int)$r['ctv_id'] ?></a></td>
          <td><?= htmlspecialchars((string)$r['ref_type']) ?> <span class="kbd"><?= htmlspecialchars((string)($r['ref_id'] ?? '')) ?></span></td>
          <td><span class="muted"><?= htmlspecialchars((string)$r['endpoint']) ?></span></td>
          <td><?= (int)$r['http_status'] ?></td>
          <td><span class="tag <?= (int)$r['success'] === 1 ? 'ok' : 'err' ?>"><?= (int)$r['success'] === 1 ? 'OK' : 'Lỗi' ?></span></td>
          <td style="max-width:200px"><?= htmlspecialchars(mb_strimwidth((string)($r['error_message'] ?? ''), 0, 150, '…')) ?></td>
          <td style="max-width:240px;font-family:monospace;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['request_redacted'] ?? ''), 0, 180, '…')) ?></td>
          <td style="max-width:240px;font-family:monospace;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['response_redacted'] ?? ''), 0, 180, '…')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  <?php elseif ($kind === 'wallet'): ?>
    <?php
      $sql = 'SELECT t.*, u.email FROM ctv_wallet_transactions t LEFT JOIN ctv_users u ON u.id=t.ctv_id'
           . ($ctvFilter ? ' WHERE t.ctv_id=?' : '')
           . ' ORDER BY t.id DESC LIMIT 200';
      $st = db()->prepare($sql); $st->execute($ctvFilter ? [$ctvFilter] : []); $rows = $st->fetchAll();
    ?>
    <?php if (!$rows): ?><div class="empty"><div class="icon">💰</div><p>Chưa có giao dịch ví nào.</p></div><?php else: ?>
    <?php $reasonVi = ['admin_credit'=>'Admin nạp','admin_debit'=>'Admin trừ','order_charge'=>'Phí đơn','order_refund'=>'Hoàn tiền đơn','order_retry'=>'Thử lại đơn','topup_charge'=>'Phí nạp data','topup_refund'=>'Hoàn tiền nạp data','topup_request'=>'Yêu cầu nạp ví']; ?>
    <div class="m-cards">
      <?php foreach ($rows as $r): $amt = (int)$r['amount']; ?>
      <div class="m-card">
        <div class="m-head"><span class="tag <?= $amt >= 0 ? 'ok' : 'err' ?>"><?= htmlspecialchars($reasonVi[(string)$r['reason']] ?? (string)$r['reason']) ?></span><span style="font-weight:800;color:<?= $amt >= 0 ? 'var(--a-green)' : 'var(--a-red)' ?>"><?= $amt >= 0 ? '+' : '' ?><?= htmlspecialchars(format_vnd($amt)) ?></span></div>
        <div class="m-row"><span class="m-label">Đối tác</span><span class="m-val"><?= htmlspecialchars((string)$r['email']) ?></span></div>
        <div class="m-row"><span class="m-label">Số dư sau</span><span class="m-val"><?= htmlspecialchars(format_vnd((int)$r['balance_after'])) ?></span></div>
        <div class="m-row"><span class="m-label">Tham chiếu</span><span class="m-val"><?= htmlspecialchars((string)($r['ref_type'] ?? '')) ?> <span class="kbd"><?= htmlspecialchars((string)($r['ref_id'] ?? '')) ?></span></span></div>
        <?php if (!empty($r['note'])): ?><div class="m-row"><span class="m-label">Ghi chú</span><span class="m-val muted"><?= htmlspecialchars(mb_strimwidth((string)$r['note'], 0, 60, '…')) ?></span></div><?php endif; ?>
        <?php if (!empty($r['admin_user'])): ?><div class="m-row"><span class="m-label">Admin</span><span class="m-val muted"><?= htmlspecialchars((string)$r['admin_user']) ?></span></div><?php endif; ?>
        <div class="m-row"><span class="m-label">Thời gian</span><span class="m-val muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="table-wrap">
    <table>
      <thead><tr><th>Thời gian</th><th>Đối tác</th><th>Lý do</th><th>Số tiền</th><th>Số dư sau</th><th>Tham chiếu</th><th>Ghi chú</th><th>Admin</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $amt = (int)$r['amount']; ?>
        <tr>
          <td style="white-space:nowrap"><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
          <td><?= htmlspecialchars((string)$r['email']) ?></td>
          <td><span class="tag <?= $amt >= 0 ? 'ok' : 'err' ?>"><?= htmlspecialchars($reasonVi[(string)$r['reason']] ?? (string)$r['reason']) ?></span></td>
          <td style="white-space:nowrap;color:<?= $amt >= 0 ? 'var(--a-green)' : 'var(--a-red)' ?>"><?= $amt >= 0 ? '+' : '' ?><?= htmlspecialchars(format_vnd($amt)) ?></td>
          <td style="white-space:nowrap"><?= htmlspecialchars(format_vnd((int)$r['balance_after'])) ?></td>
          <td><?= htmlspecialchars((string)($r['ref_type'] ?? '')) ?> <span class="kbd"><?= htmlspecialchars((string)($r['ref_id'] ?? '')) ?></span></td>
          <td><span class="muted"><?= htmlspecialchars(mb_strimwidth((string)($r['note'] ?? ''), 0, 80, '…')) ?></span></td>
          <td><span class="muted"><?= htmlspecialchars((string)($r['admin_user'] ?? '')) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  <?php else: ?>
    <?php
      $sql = 'SELECT l.*, u.email FROM ctv_api_logs l LEFT JOIN ctv_users u ON u.id=l.ctv_id'
           . ($ctvFilter ? ' WHERE l.ctv_id=?' : '')
           . ' ORDER BY l.id DESC LIMIT 200';
      $st = db()->prepare($sql); $st->execute($ctvFilter ? [$ctvFilter] : []); $rows = $st->fetchAll();
    ?>
    <?php if (!$rows): ?><div class="empty"><div class="icon">🔌</div><p>Chưa có nhật ký API nào.</p></div><?php else: ?>
    <div class="m-cards">
      <?php foreach ($rows as $r): $httpCode = (int)($r['response_status'] ?? 0); ?>
      <div class="m-card">
        <div class="m-head"><span><span class="tag"><?= htmlspecialchars((string)$r['method']) ?></span> <span class="muted" style="font-size:12px"><?= htmlspecialchars((string)$r['endpoint']) ?></span></span><span class="tag <?= $httpCode >= 200 && $httpCode < 300 ? 'ok' : ($httpCode >= 400 ? 'err' : 'warn') ?>"><?= $httpCode ?></span></div>
        <div class="m-row"><span class="m-label">Đối tác</span><span class="m-val"><?= htmlspecialchars((string)($r['email'] ?? '')) ?></span></div>
        <div class="m-row"><span class="m-label">IP</span><span class="m-val muted"><?= htmlspecialchars((string)($r['ip'] ?? '')) ?></span></div>
        <div class="m-row"><span class="m-label">Thời lượng</span><span class="m-val"><?= (int)($r['duration_ms'] ?? 0) ?>ms</span></div>
        <div class="m-row"><span class="m-label">Thời gian</span><span class="m-val muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="table-wrap">
    <table>
      <thead><tr><th>Thời gian</th><th>Đối tác</th><th>IP</th><th>Endpoint</th><th>Phương thức</th><th>HTTP</th><th>Thời lượng</th><th>Yêu cầu</th><th>Phản hồi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $httpCode = (int)($r['response_status'] ?? 0); ?>
        <tr>
          <td style="white-space:nowrap"><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
          <td><?= htmlspecialchars((string)($r['email'] ?? '')) ?></td>
          <td><span class="muted"><?= htmlspecialchars((string)($r['ip'] ?? '')) ?></span></td>
          <td><span class="muted"><?= htmlspecialchars((string)$r['endpoint']) ?></span></td>
          <td><span class="tag"><?= htmlspecialchars((string)$r['method']) ?></span></td>
          <td><span class="tag <?= $httpCode >= 200 && $httpCode < 300 ? 'ok' : ($httpCode >= 400 ? 'err' : 'warn') ?>"><?= $httpCode ?></span></td>
          <td><?= (int)($r['duration_ms'] ?? 0) ?>ms</td>
          <td style="max-width:220px;font-family:monospace;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['request_summary'] ?? ''), 0, 180, '…')) ?></td>
          <td style="max-width:220px;font-family:monospace;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['response_summary'] ?? ''), 0, 180, '…')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
