<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;
$filterAction = trim((string)($_GET['action'] ?? ''));
$filterSearch = trim((string)($_GET['q'] ?? ''));
$filterFrom = trim((string)($_GET['from'] ?? ''));
$filterTo = trim((string)($_GET['to'] ?? ''));

$result = AuditLog::list(
    $perPage, $offset,
    $filterAction !== '' ? $filterAction : null,
    $filterSearch !== '' ? $filterSearch : null,
    $filterFrom !== '' ? $filterFrom : null,
    $filterTo !== '' ? $filterTo : null
);
$rows = $result['rows'];
$total = $result['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

$qs = function (array $extra) use ($filterAction, $filterSearch, $filterFrom, $filterTo, $page): string {
    $p = array_filter(array_merge([
        'action' => $filterAction ?: null,
        'q' => $filterSearch ?: null,
        'from' => $filterFrom ?: null,
        'to' => $filterTo ?: null,
        'page' => $page > 1 ? $page : null,
    ], $extra), fn($v) => $v !== null && $v !== '');
    return '?' . http_build_query($p);
};

admin_layout_header('Nhật ký kiểm toán', $admin);
?>
<div class="card">
  <form method="get" class="toolbar">
    <select name="action">
      <option value="">Tất cả hành động</option>
      <?php foreach (AuditLog::actions() as $a): ?>
        <option value="<?= htmlspecialchars($a) ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" name="q" value="<?= htmlspecialchars($filterSearch) ?>" placeholder="Tìm user/target/details">
    <input type="date" name="from" value="<?= htmlspecialchars($filterFrom) ?>">
    <input type="date" name="to" value="<?= htmlspecialchars($filterTo) ?>">
    <button class="btn">Lọc</button>
    <a class="btn secondary" href="/admin/ctv/audit.php">Đặt lại</a>
    <span class="spacer"></span>
    <span class="muted"><?= $total ?> kết quả</span>
  </form>

  <?php if (!$rows): ?>
    <div class="empty"><div class="icon">📋</div><p>Không có log nào khớp filter.</p></div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:50px">#</th>
        <th>Admin</th>
        <th>Hành động</th>
        <th>Đối tượng</th>
        <th>Chi tiết</th>
        <th>IP</th>
        <th>Thời gian</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><span class="kbd"><?= (int)$r['id'] ?></span></td>
        <td><?= htmlspecialchars((string)$r['admin_user']) ?></td>
        <td><span class="tag info"><?= htmlspecialchars((string)$r['action']) ?></span></td>
        <td>
          <?php if ($r['target_type']): ?>
            <span class="muted"><?= htmlspecialchars((string)$r['target_type']) ?></span>
            <?php if ($r['target_id']): ?><br><span class="kbd"><?= htmlspecialchars((string)$r['target_id']) ?></span><?php endif; ?>
          <?php else: ?>
            <span class="muted">–</span>
          <?php endif; ?>
        </td>
        <td style="max-width:350px">
          <?php if ($r['details_json']): ?>
            <details>
              <summary>Xem</summary>
              <pre style="white-space:pre-wrap;font-size:12px;color:var(--a-ink-2);background:#0a1020;padding:8px;border-radius:6px;margin-top:6px;max-height:180px;overflow:auto"><?= htmlspecialchars(mb_strimwidth((string)$r['details_json'], 0, 2000, '…')) ?></pre>
            </details>
          <?php else: ?>
            <span class="muted">–</span>
          <?php endif; ?>
        </td>
        <td><span class="muted"><?= htmlspecialchars((string)($r['ip'] ?? '')) ?></span></td>
        <td><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ($totalPages > 1): ?>
  <div class="filter-row" style="margin-top:12px;justify-content:center">
    <?php if ($page > 1): ?><a class="pill" href="<?= htmlspecialchars($qs(['page' => $page - 1])) ?>">← Trước</a><?php endif; ?>
    <span class="muted">Trang <?= $page ?>/<?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?><a class="pill" href="<?= htmlspecialchars($qs(['page' => $page + 1])) ?>">Sau →</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
