<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';
$admin = admin_ctv_require();

$dir = '/home/levanrin2404/db_backups';
$files = [];
if (is_dir($dir)) {
    foreach ((array)scandir($dir) as $f) {
        if (!is_string($f) || str_starts_with($f, '.')) continue;
        $path = $dir . '/' . $f;
        if (!is_file($path)) continue;
        $stat = @stat($path);
        $files[] = [
            'name' => $f,
            'size' => (int)($stat['size'] ?? 0),
            'mtime' => (int)($stat['mtime'] ?? 0),
            'ext' => pathinfo($f, PATHINFO_EXTENSION),
        ];
    }
    usort($files, fn($a, $b) => $b['mtime'] - $a['mtime']);
}
$totalSize = array_sum(array_map(fn($f) => $f['size'], $files));

function fmt_bytes(int $b): string {
    if ($b < 1024) return $b . ' B';
    if ($b < 1024*1024) return number_format($b/1024, 1) . ' KB';
    if ($b < 1024*1024*1024) return number_format($b/(1024*1024), 1) . ' MB';
    return number_format($b/(1024*1024*1024), 2) . ' GB';
}

admin_layout_header('Sao lưu CSDL', $admin);
?>
<div class="summary">
  <div class="card"><b>Tổng số bản sao lưu</b><h2><?= count($files) ?></h2></div>
  <div class="card"><b>Tổng dung lượng</b><h2><?= htmlspecialchars(fmt_bytes($totalSize)) ?></h2></div>
  <div class="card"><b>Bản mới nhất</b><h2><?= $files ? htmlspecialchars(date('d/m H:i', $files[0]['mtime'])) : '—' ?></h2></div>
</div>

<div class="card">
  <h2>Danh sách sao lưu</h2>
  <p class="muted" style="margin-bottom:12px">
    Lịch chạy: hằng ngày 04:00 UTC qua <code>jpesim-db-backup.timer</code>. Lưu giữ 7 ngày, gzipped, mode 0600 owned by levanrin2404.<br>
    Đường dẫn: <code>/home/levanrin2404/db_backups/</code>. Tải về qua SSH: <code>scp jp-esim:/home/levanrin2404/db_backups/&lt;file&gt;.sql.gz .</code>
  </p>
  <?php if (!$files): ?>
    <div class="empty"><div class="icon">💾</div><p>Chưa có bản sao lưu nào. Timer sẽ chạy lúc 04:00 UTC.</p></div>
  <?php else: ?>
    <div class="table-wrap">
    <table><thead><tr><th>Tên file</th><th>Kích thước</th><th>Thời gian tạo</th><th>Tuổi</th></tr></thead><tbody>
    <?php foreach ($files as $f):
      $age = time() - $f['mtime'];
      $ageH = (int)round($age / 3600);
      $ageDays = (int)round($age / 86400);
      $ageLabel = $ageDays >= 1 ? "$ageDays ngày" : "$ageH giờ";
      $isOld = $ageDays >= 7;
    ?>
    <tr>
      <td><span class="kbd"><?= htmlspecialchars($f['name']) ?></span></td>
      <td><?= htmlspecialchars(fmt_bytes($f['size'])) ?></td>
      <td><span class="muted"><?= htmlspecialchars(date('Y-m-d H:i:s', $f['mtime'])) ?></span></td>
      <td><span class="tag <?= $isOld ? 'err' : ($ageDays >= 3 ? 'warn' : 'ok') ?>"><?= htmlspecialchars($ageLabel) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    </div>
  <?php endif; ?>
</div>
<?php admin_layout_footer();
