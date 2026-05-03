<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();

$kind = (string)($_GET['kind'] ?? 'api');
admin_layout_header('Logs CTV', $admin);
?>
<div class="card">
  <h2>Logs</h2>
  <p>
    <a class="btn <?= $kind==='api'?'':'secondary' ?>" href="?kind=api">API logs</a>
    <a class="btn <?= $kind==='provider'?'':'secondary' ?>" href="?kind=provider">Provider logs</a>
    <a class="btn <?= $kind==='wallet'?'':'secondary' ?>" href="?kind=wallet">Wallet</a>
  </p>
  <?php if ($kind === 'provider'): ?>
    <?php $rows = db()->query('SELECT * FROM ctv_provider_logs ORDER BY id DESC LIMIT 100')->fetchAll(); ?>
    <table>
      <thead><tr><th>Thời gian</th><th>CTV</th><th>Loại</th><th>Endpoint</th><th>Status</th><th>OK</th><th>Lỗi</th><th>Request</th><th>Response</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
          <td><?= (int)$r['ctv_id'] ?></td>
          <td><?= htmlspecialchars((string)$r['ref_type']) ?> <?= htmlspecialchars((string)($r['ref_id'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)$r['endpoint']) ?></td>
          <td><?= (int)$r['http_status'] ?></td>
          <td><?= (int)$r['success'] === 1 ? 'yes' : 'no' ?></td>
          <td><?= htmlspecialchars((string)($r['error_message'] ?? '')) ?></td>
          <td style="max-width:280px;overflow:hidden;font-family:monospace;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['request_redacted'] ?? ''), 0, 220, '…')) ?></td>
          <td style="max-width:280px;overflow:hidden;font-family:monospace;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['response_redacted'] ?? ''), 0, 220, '…')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php elseif ($kind === 'wallet'): ?>
    <?php $rows = db()->query('SELECT t.*, u.email FROM ctv_wallet_transactions t LEFT JOIN ctv_users u ON u.id=t.ctv_id ORDER BY t.id DESC LIMIT 200')->fetchAll(); ?>
    <table>
      <thead><tr><th>Thời gian</th><th>CTV</th><th>Reason</th><th>Số tiền</th><th>Số dư sau</th><th>Ref</th><th>Note</th><th>Admin</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
          <td><?= htmlspecialchars((string)$r['email']) ?></td>
          <td><?= htmlspecialchars((string)$r['reason']) ?></td>
          <td><?= htmlspecialchars(format_vnd((int)$r['amount'])) ?></td>
          <td><?= htmlspecialchars(format_vnd((int)$r['balance_after'])) ?></td>
          <td><?= htmlspecialchars((string)($r['ref_type'] ?? '')) ?> <?= htmlspecialchars((string)($r['ref_id'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['note'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['admin_user'] ?? '')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <?php $rows = db()->query('SELECT l.*, u.email FROM ctv_api_logs l LEFT JOIN ctv_users u ON u.id=l.ctv_id ORDER BY l.id DESC LIMIT 200')->fetchAll(); ?>
    <table>
      <thead><tr><th>Thời gian</th><th>CTV</th><th>IP</th><th>Endpoint</th><th>Method</th><th>Status</th><th>Duration</th><th>Request</th><th>Response</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
          <td><?= htmlspecialchars((string)($r['email'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($r['ip'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)$r['endpoint']) ?></td>
          <td><?= htmlspecialchars((string)$r['method']) ?></td>
          <td><?= (int)($r['response_status'] ?? 0) ?></td>
          <td><?= (int)($r['duration_ms'] ?? 0) ?>ms</td>
          <td style="max-width:260px;font-family:monospace;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['request_summary'] ?? ''), 0, 200, '…')) ?></td>
          <td style="max-width:260px;font-family:monospace;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['response_summary'] ?? ''), 0, 200, '…')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
