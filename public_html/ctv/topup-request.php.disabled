<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$svc = new CtvTopupRequestService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!CtvAuth::checkCsrf($_POST['csrf'] ?? null)) {
            throw new RuntimeException('Phiên đã hết hạn, vui lòng thử lại');
        }
        $amount = (int)($_POST['amount'] ?? 0);
        $file = $_FILES['proof'] ?? null;
        $id = $svc->create((int)$user['id'], $amount, $file);
        ctv_flash_set('ok', 'Yêu cầu nạp ví #' . $id . ' đã gửi. Vui lòng chờ admin duyệt.');
        header('Location: /ctv/topup-request.php');
        exit;
    } catch (Throwable $e) {
        ctv_flash_set('error', $e->getMessage());
        header('Location: /ctv/topup-request.php');
        exit;
    }
}

$requests = $svc->listForCtv((int)$user['id'], 30);

ctv_layout_header('Yêu cầu nạp ví', $user);
ctv_flash_render();
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<div class="card">
  <h2>Gửi yêu cầu nạp ví</h2>
  <p class="muted">Chuyển khoản rồi gửi yêu cầu kèm ảnh chụp. Admin sẽ duyệt và nạp tiền vào ví.</p>
  <div class="card" style="background:#0a1020;border-color:var(--c-gold);margin:12px 0;padding:14px">
    <h3 style="color:var(--c-gold);margin-bottom:8px">Thông tin chuyển khoản</h3>
    <p style="margin:4px 0"><b>Ngân hàng:</b> <?= htmlspecialchars((string)app_config('BANK_NAME', 'Vietcombank')) ?></p>
    <p style="margin:4px 0"><b>Số TK:</b> <span class="kbd"><?= htmlspecialchars((string)app_config('BANK_ACCOUNT', '')) ?></span></p>
    <p style="margin:4px 0"><b>Chủ TK:</b> <?= htmlspecialchars((string)app_config('BANK_HOLDER', '')) ?></p>
    <p style="margin:4px 0"><b>Nội dung CK:</b> <span class="kbd">CTV<?= (int)$user['id'] ?> NAP</span></p>
  </div>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars(CtvAuth::csrfToken()) ?>">
    <div class="field">
      <label>Số tiền (VND)</label>
      <input type="number" name="amount" min="10000" max="100000000" step="1000" required placeholder="Ví dụ: 500000">
    </div>
    <div class="field">
      <label>Ảnh chụp chuyển khoản (tuỳ chọn)</label>
      <input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" style="background:none;border:none;padding:4px 0">
      <span class="muted">JPG, PNG, WebP hoặc PDF, tối đa 5MB</span>
    </div>
    <button class="btn gold">Gửi yêu cầu</button>
  </form>
</div>

<div class="card">
  <h2>Lịch sử yêu cầu</h2>
  <?php if (!$requests): ?>
    <p class="muted">Chưa có yêu cầu nào.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>#</th><th>Số tiền</th><th>Trạng thái</th><th>Ghi chú</th><th>Ngày gửi</th></tr></thead>
    <tbody>
    <?php foreach ($requests as $r):
      $sCls = match ($r['status']) { 'approved' => 'ok', 'rejected' => 'err', default => 'warn' };
      $sLabel = match ($r['status']) { 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', default => 'Chờ duyệt' };
    ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars(format_vnd((int)$r['amount'])) ?></td>
      <td><span class="tag <?= $sCls ?>"><?= $sLabel ?></span></td>
      <td><span class="muted"><?= htmlspecialchars(mb_strimwidth((string)($r['admin_note'] ?? ''), 0, 80, '…')) ?></span></td>
      <td><span class="muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
</div>
<?php ctv_layout_footer();
