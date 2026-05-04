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
admin_layout_header("Nhật ký CTV", $admin);
?>
<div class="card">
  <h2>Nhật ký hệ thống</h2>
  <p>
    <a class="btn <?= $kind==='api'?'':'secondary' ?>" href="<?= htmlspecialchars($qs(['kind'=>'api'])) ?>">Nhật ký API</a>
    <a class="btn <?= $kind==='provider'?'':'secondary' ?>" href="<?= htmlspecialchars($qs(['kind'=>'provider'])) ?>">Nhật ký xử lý</a>
    <a class="btn <?= $kind==='wallet'?'':'secondary' ?>" href="<?= htmlspecialchars($qs(['kind'=>'wallet'])) ?>">Ví CTV</a>
    <?php if ($ctvFilter): ?>
      <span class="tag">Lọc CTV #<?= (int)$ctvFilter ?></span>
      <a class="btn secondary" href="?kind=<?= htmlspecialchars($kind) ?>">Xóa lọc</a>
    <?php endif; ?>
  </p>
  <?php if ($kind === 'provider'): ?>
    <?php
      $sql = 'SELECT * FROM ctv_provider_logs' . ($ctvFilter ? ' WHERE ctv_id=?' : '') . ' ORDER BY id DESC LIMIT 100';
      $st = db()->prepare($sql); $st->execute($ctvFilter ? [$ctvFilter] : []); $rows = $st->fetchAll();
    ?>
    <table>
      <thead><tr><th>Thời gian</th><th>CTV</th><th>Loại</th><th>Endpoint</th><th>Mã trạng thái</th><th>Kết quả</th><th>Lỗi</th><th>Yêu cầu</th><th>Phản hồi</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars((string)$r['created_at']) ?></td>
          <td><?= (int)$r['ctv_id'] ?></td>
          <td><?= htmlspecialchars((string)$r['ref_type']) ?> <?= htmlspecialchars((string)($r['ref_id'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)$r['endpoint']) ?></td>
          <td><?= (int)$r['http_status'] ?></td>
          <td><?= (int)$r['success'] === 1 ? 'Có' : 'Không' ?></td>
          <td><?= htmlspecialchars((string)($r['error_message'] ?? '')) ?></td>
          <td style="max-width:280px;overflow:hidden;font-family:monospace;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['request_redacted'] ?? ''), 0, 220, '…')) ?></td>
          <td style="max-width:280px;overflow:hidden;font-family:monospace;font-size:12px;"><?= htmlspecialchars(mb_strimwidth((string)($r['response_redacted'] ?? ''), 0, 220, '…')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php elseif ($kind === 'wallet'): ?>
    <?php
      $sql = 'SELECT t.*, u.email FROM ctv_wallet_transactions t LEFT JOIN ctv_users u ON u.id=t.ctv_id'
           . ($ctvFilter ? ' WHERE t.ctv_id=?' : '')
           . ' ORDER BY t.id DESC LIMIT 200';
      $st = db()->prepare($sql); $st->execute($ctvFilter ? [$ctvFilter] : []); $rows = $st->fetchAll();
    ?>
    <table>
      <thead><tr><th>Thời gian</th><th>CTV</th><th>Lý do</th><th>Số tiền</th><th>Số dư sau</th><th>Tham chiếu</th><th>Ghi chú</th><th>Admin</th></tr></thead>
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
    <?php
      $sql = 'SELECT l.*, u.email FROM ctv_api_logs l LEFT JOIN ctv_users u ON u.id=l.ctv_id'
           . ($ctvFilter ? ' WHERE l.ctv_id=?' : '')
           . ' ORDER BY l.id DESC LIMIT 200';
      $st = db()->prepare($sql); $st->execute($ctvFilter ? [$ctvFilter] : []); $rows = $st->fetchAll();
    ?>
    <table>
      <thead><tr><th>Thời gian</th><th>CTV</th><th>IP</th><th>Endpoint</th><th>Phương thức</th><th>Mã trạng thái</th><th>Thời lượng</th><th>Yêu cầu</th><th>Phản hồi</th></tr></thead>
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
