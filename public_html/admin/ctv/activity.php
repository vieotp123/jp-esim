<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();
$pdo = db();

$LIMIT = 60;

// Build a UNION ALL feed across categories. We tag each row's source so the
// UI can render appropriate icon + link.
$sql = "
(SELECT 'ctv_register' AS kind, CAST(id AS CHAR) AS ref, email AS subject, NULL AS amount, created_at AS ts FROM ctv_users ORDER BY id DESC LIMIT $LIMIT)
UNION ALL
(SELECT 'ctv_order' AS kind, ctv_order_id AS ref, COALESCE(plan_name, '') AS subject, total_charge AS amount, created_at AS ts FROM ctv_orders ORDER BY id DESC LIMIT $LIMIT)
UNION ALL
(SELECT 'retail_order' AS kind, order_id AS ref, COALESCE(plan_name, '') AS subject, total AS amount, created_at AS ts FROM `order` ORDER BY created_at DESC LIMIT $LIMIT)
UNION ALL
(SELECT 'topup_order' AS kind, ctv_topup_id AS ref, COALESCE(plan_name, '') AS subject, total_charge AS amount, created_at AS ts FROM ctv_topup_orders ORDER BY id DESC LIMIT $LIMIT)
UNION ALL
(SELECT 'topup_request' AS kind, CAST(id AS CHAR) AS ref, status AS subject, amount, created_at AS ts FROM ctv_topup_requests ORDER BY id DESC LIMIT $LIMIT)
UNION ALL
(SELECT 'wallet_tx' AS kind, ref_id AS ref, reason AS subject, amount, created_at AS ts FROM ctv_wallet_transactions ORDER BY id DESC LIMIT $LIMIT)
UNION ALL
(SELECT CONCAT('audit:', action) AS kind, COALESCE(target_id, '') AS ref, admin_user AS subject, NULL AS amount, created_at AS ts FROM admin_audit_log ORDER BY id DESC LIMIT $LIMIT)
ORDER BY ts DESC LIMIT $LIMIT
";
$rows = $pdo->query($sql)->fetchAll();

function activity_icon(string $kind): string {
    if (str_starts_with($kind, 'audit:')) return '🛡';
    return match ($kind) {
        'ctv_register' => '👤',
        'ctv_order' => '📦',
        'retail_order' => '🛒',
        'topup_order' => '⚡',
        'topup_request' => '💵',
        'wallet_tx' => '💰',
        default => '•',
    };
}

function activity_label(string $kind): string {
    if (str_starts_with($kind, 'audit:')) return 'Admin: ' . substr($kind, 6);
    return match ($kind) {
        'ctv_register' => 'Đối tác đăng ký',
        'ctv_order' => 'Đơn đối tác',
        'retail_order' => 'Đơn lẻ',
        'topup_order' => 'Nạp data',
        'topup_request' => 'Yêu cầu nạp ví',
        'wallet_tx' => 'Giao dịch ví',
        default => $kind,
    };
}

function activity_link(string $kind, string $ref): string {
    if (str_starts_with($kind, 'audit:')) return '/admin/ctv/audit.php?q=' . rawurlencode($ref);
    return match ($kind) {
        'ctv_register' => '/admin/ctv/view.php?id=' . (int)$ref,
        'ctv_order', 'topup_order' => '/admin/ctv/order-view.php?id=' . rawurlencode($ref),
        'topup_request' => '/admin/ctv/topup-requests.php',
        'retail_order' => '/admin/ctv/search.php?q=' . rawurlencode($ref),
        'wallet_tx' => '/admin/ctv/audit.php?q=' . rawurlencode($ref),
        default => '#',
    };
}

admin_layout_header('Hoạt động gần đây', $admin);
?>
<style>
.feed{display:flex;flex-direction:column;gap:8px}
.feed-row{display:grid;grid-template-columns:32px 1fr auto auto;gap:14px;align-items:center;padding:12px 14px;background:var(--a-card);border:1px solid var(--a-line);border-radius:10px}
.feed-row .icon{font-size:18px;text-align:center}
.feed-row .body{min-width:0}
.feed-row .body b{font-size:13px;font-weight:600;color:var(--a-ink);display:block}
.feed-row .body .sub{font-size:12px;color:var(--a-muted);margin-top:2px}
.feed-row .amt{font-weight:700;color:var(--a-gold);font-size:13px;white-space:nowrap}
.feed-row .ts{color:var(--a-muted);font-size:11px;white-space:nowrap}
.feed-row .ref{font-family:ui-monospace,monospace;font-size:11px;background:var(--a-surface);padding:2px 6px;border-radius:4px;color:var(--a-ink)}
@media(max-width:760px){
.feed-row{grid-template-columns:24px 1fr;}
.feed-row .amt,.feed-row .ts{grid-column:2;font-size:11px}
}
</style>
<p class="muted" style="margin-bottom:14px">Tổng hợp <?= count($rows) ?> sự kiện gần nhất từ mọi nguồn (đăng ký, đơn hàng, ví, audit log).</p>
<div class="feed">
<?php foreach ($rows as $r):
  $kind = (string)$r['kind'];
  $ref = (string)$r['ref'];
  $subject = (string)($r['subject'] ?? '');
  $amount = $r['amount'] !== null ? (int)$r['amount'] : null;
  $ts = (string)$r['ts'];
  $link = activity_link($kind, $ref);
?>
  <a href="<?= htmlspecialchars($link) ?>" style="text-decoration:none;color:inherit">
  <div class="feed-row">
    <span class="icon"><?= activity_icon($kind) ?></span>
    <div class="body">
      <b><?= htmlspecialchars(activity_label($kind)) ?></b>
      <div class="sub"><span class="ref"><?= htmlspecialchars($ref) ?></span> <?= htmlspecialchars(mb_strimwidth($subject, 0, 80, '…')) ?></div>
    </div>
    <span class="amt"><?= $amount !== null ? htmlspecialchars(format_vnd($amount)) : '' ?></span>
    <span class="ts"><?= htmlspecialchars($ts) ?></span>
  </div>
  </a>
<?php endforeach; ?>
</div>
<?php admin_layout_footer();
