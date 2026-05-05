<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$ctvId = (int)$user['id'];
$LIMIT = 50;

$pdo = db();
$sql = "
(SELECT 'order' AS kind, ctv_order_id AS ref, COALESCE(plan_name,'') AS subject, total_charge AS amount, status AS status, created_at AS ts FROM ctv_orders WHERE ctv_id=? ORDER BY id DESC LIMIT $LIMIT)
UNION ALL
(SELECT 'topup' AS kind, ctv_topup_id AS ref, COALESCE(plan_name,'') AS subject, total_charge AS amount, status AS status, created_at AS ts FROM ctv_topup_orders WHERE ctv_id=? ORDER BY id DESC LIMIT $LIMIT)
UNION ALL
(SELECT 'wallet' AS kind, COALESCE(ref_id,'') AS ref, reason AS subject, amount AS amount, NULL AS status, created_at AS ts FROM ctv_wallet_transactions WHERE ctv_id=? ORDER BY id DESC LIMIT $LIMIT)
UNION ALL
(SELECT 'topup_request' AS kind, CAST(id AS CHAR) AS ref, status AS subject, amount AS amount, status AS status, created_at AS ts FROM ctv_topup_requests WHERE ctv_id=? ORDER BY id DESC LIMIT $LIMIT)
ORDER BY ts DESC LIMIT $LIMIT
";
$st = $pdo->prepare($sql);
$st->execute([$ctvId, $ctvId, $ctvId, $ctvId]);
$rows = $st->fetchAll();

function ctv_act_icon(string $kind): string {
    return match ($kind) { 'order' => '📦', 'topup' => '⚡', 'wallet' => '💰', 'topup_request' => '💵', default => '•' };
}
function ctv_act_label(string $kind): string {
    return match ($kind) { 'order' => 'Đơn eSIM', 'topup' => 'Nạp data', 'wallet' => 'Giao dịch ví', 'topup_request' => 'Yêu cầu nạp ví', default => $kind };
}
function ctv_act_link(string $kind, string $ref): string {
    return match ($kind) {
        'order' => '/ctv/orders.php',
        'topup' => '/ctv/topup-orders.php',
        'topup_request' => '/ctv/topup-request.php',
        default => '#',
    };
}
function ctv_act_status_label(string $kind, $status): string {
    if ($kind === 'topup_request') {
        return match ((string)$status) { 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', 'pending' => 'Chờ duyệt', default => (string)$status };
    }
    if ($status === null) return '';
    return match ((int)$status) { 0 => 'Chờ TT', 1 => 'Hết hạn', 2 => 'Thành công', 3 => 'Thất bại', default => (string)$status };
}
function ctv_act_status_class(string $kind, $status): string {
    if ($kind === 'topup_request') {
        return match ((string)$status) { 'approved' => 'ok', 'rejected' => 'err', 'pending' => 'warn', default => 'info' };
    }
    if ($status === null) return '';
    return match ((int)$status) { 2 => 'ok', 3 => 'err', 0 => 'warn', default => 'info' };
}

ctv_layout_header('Hoạt động của tôi', $user);
?>
<style>
.ct-feed{display:flex;flex-direction:column;gap:8px}
.ct-row{display:grid;grid-template-columns:32px 1fr auto auto;gap:12px;align-items:center;padding:12px 14px;background:var(--c-card);border:1px solid var(--c-line-2);border-radius:10px}
.ct-row .icon{font-size:18px;text-align:center}
.ct-row .body b{font-size:13px;font-weight:600;color:var(--c-ink);display:block}
.ct-row .body .sub{font-size:12px;color:var(--c-muted);margin-top:2px}
.ct-row .amt{font-weight:700;font-size:13px;white-space:nowrap}
.ct-row .amt.pos{color:var(--c-green)}
.ct-row .amt.neg{color:var(--c-red)}
.ct-row .ts{color:var(--c-muted);font-size:11px;white-space:nowrap}
.ct-row .ref{font-family:ui-monospace,monospace;font-size:11px;background:var(--c-card-2);padding:2px 6px;border-radius:4px;color:var(--c-ink)}
.ct-row .tag{font-size:10px;padding:2px 6px;border-radius:4px}
.ct-row .tag.ok{background:rgba(74,222,128,.12);color:#4ade80}
.ct-row .tag.warn{background:rgba(250,204,21,.12);color:#facc15}
.ct-row .tag.err{background:rgba(239,68,68,.12);color:#ef4444}
.ct-row .tag.info{background:rgba(180,180,180,.1);color:var(--c-muted)}
@media(max-width:760px){.ct-row{grid-template-columns:24px 1fr}.ct-row .amt,.ct-row .ts{grid-column:2;font-size:11px}}
</style>

<?php if (!$rows): ?>
<div class="card"><div class="empty-state" style="padding:40px 20px;text-align:center"><div class="icon" style="font-size:32px;margin-bottom:8px">📋</div><p>Chưa có hoạt động nào. Hãy <a href="/ctv/create-esim.php" style="color:var(--c-gold)">tạo đơn eSIM đầu tiên</a>.</p></div></div>
<?php else: ?>

<p class="muted" style="margin-bottom:14px"><?= count($rows) ?> sự kiện gần nhất của bạn (đơn hàng, nạp data, ví, yêu cầu nạp ví).</p>

<div class="ct-feed">
<?php foreach ($rows as $r):
  $kind = (string)$r['kind'];
  $ref = (string)$r['ref'];
  $subject = (string)($r['subject'] ?? '');
  $amount = $r['amount'] !== null ? (int)$r['amount'] : null;
  $ts = (string)$r['ts'];
  $statusLabel = ctv_act_status_label($kind, $r['status'] ?? null);
  $statusCls = ctv_act_status_class($kind, $r['status'] ?? null);
?>
  <a href="<?= htmlspecialchars(ctv_act_link($kind, $ref)) ?>" style="text-decoration:none;color:inherit">
  <div class="ct-row">
    <span class="icon"><?= ctv_act_icon($kind) ?></span>
    <div class="body">
      <b><?= htmlspecialchars(ctv_act_label($kind)) ?> <?php if ($statusLabel): ?><span class="tag <?= $statusCls ?>"><?= htmlspecialchars($statusLabel) ?></span><?php endif; ?></b>
      <div class="sub"><span class="ref"><?= htmlspecialchars($ref) ?></span> <?= htmlspecialchars(mb_strimwidth($subject, 0, 60, '…')) ?></div>
    </div>
    <span class="amt <?= $kind === 'wallet' ? ($amount >= 0 ? 'pos' : 'neg') : '' ?>"><?= $amount !== null ? ($kind === 'wallet' && $amount >= 0 ? '+' : '') . htmlspecialchars(format_vnd($amount)) : '' ?></span>
    <span class="ts"><?= htmlspecialchars($ts) ?></span>
  </div>
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php ctv_layout_footer();
