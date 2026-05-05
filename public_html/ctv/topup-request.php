<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$svc = new CtvTopupRequestService();
$err = null;
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $err = 'Phiên không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rl = new RateLimiter();
        if (!$rl->check('ctv_topup_req:' . (int)$user['id'], 5, 3600)) {
            $err = 'Quá nhiều yêu cầu. Vui lòng đợi 1 giờ.';
        } else {
            try {
                $amount = (int)($_POST['amount'] ?? 0);
                $file = $_FILES['proof'] ?? null;
                $svc->create((int)$user['id'], $amount, $file);
                $ok = true;
            } catch (Throwable $e) {
                $err = $e->getMessage();
            }
        }
    }
}

$requests = $svc->listForCtv((int)$user['id'], 20);
$csrf = CtvAuth::csrfToken();

$bankCode = (string)app_config('BANK_CODE', '');
$bankAccount = (string)app_config('BANK_ACCOUNT', '');
$bankName = (string)app_config('BANK_ACCOUNT_NAME', '');
$transferNote = 'CTV' . (int)$user['id'] . ' NapVi';

ctv_layout_header('Yêu cầu nạp ví', $user);
ctv_flash_render();
?>
<div class="card" style="max-width:600px">
  <h2>Yêu cầu nạp ví</h2>
  <p class="muted" style="margin-bottom:14px">Chuyển khoản theo thông tin bên dưới, sau đó gửi yêu cầu kèm ảnh chụp giao dịch. Admin sẽ duyệt và nạp ví trong vòng vài giờ.</p>

  <?php if ($bankAccount !== ''): ?>
  <div style="background:var(--c-surface);border:1px solid var(--c-line-2);border-radius:var(--c-radius-sm);padding:14px 16px;margin-bottom:16px">
    <h3 style="margin:0 0 10px;font-size:14px">Thông tin chuyển khoản</h3>
    <div style="display:grid;grid-template-columns:110px 1fr;gap:6px 12px;font-size:14px">
      <span class="muted">Ngân hàng</span><span style="font-weight:700"><?= htmlspecialchars($bankCode) ?></span>
      <span class="muted">Số tài khoản</span><span style="font-weight:700;font-family:ui-monospace,monospace" class="copy" data-copy="<?= htmlspecialchars($bankAccount) ?>"><?= htmlspecialchars($bankAccount) ?></span>
      <span class="muted">Chủ tài khoản</span><span style="font-weight:700"><?= htmlspecialchars($bankName) ?></span>
      <span class="muted">Nội dung CK</span><span style="font-weight:700;color:var(--c-gold);font-family:ui-monospace,monospace" class="copy" data-copy="<?= htmlspecialchars($transferNote) ?>"><?= htmlspecialchars($transferNote) ?></span>
    </div>
    <p class="muted" style="margin:10px 0 0;font-size:12px">Vui lòng ghi đúng nội dung chuyển khoản để admin xử lý nhanh hơn.</p>
  </div>
  <?php endif; ?>

  <?php if ($ok): ?>
    <div class="flash ok">Đã gửi yêu cầu nạp ví thành công. Admin sẽ xử lý sớm nhất.</div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="flash error"><?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="field">
      <label>Số tiền muốn nạp (VND)</label>
      <input type="number" name="amount" min="10000" max="100000000" step="1000" required placeholder="Ví dụ: 500000">
    </div>
    <div class="field">
      <label>Ảnh chụp chuyển khoản (JPG, PNG, WebP, PDF — tối đa 5MB)</label>
      <input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf">
    </div>
    <button class="btn gold" type="submit">Gửi yêu cầu</button>
  </form>
</div>

<div class="card" style="max-width:600px">
  <h2>Lịch sử yêu cầu</h2>
  <?php if (!$requests): ?>
    <div class="empty-state"><div class="icon">📋</div><p>Chưa có yêu cầu nạp ví nào.</p></div>
  <?php else: ?>
  <div class="m-cards">
    <?php foreach ($requests as $r):
      $sCls = match ($r['status']) { 'approved' => 'ok', 'rejected' => 'err', default => 'warn' };
      $sLabel = match ($r['status']) { 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối', default => 'Chờ duyệt' };
    ?>
    <div class="m-card">
      <div class="m-head"><span>#<?= (int)$r['id'] ?></span><span class="tag <?= $sCls ?>"><?= $sLabel ?></span></div>
      <div class="m-row"><span class="m-label">Số tiền</span><span class="m-val" style="font-weight:700;color:var(--c-gold)"><?= htmlspecialchars(format_vnd((int)$r['amount'])) ?></span></div>
      <div class="m-row"><span class="m-label">Ngày gửi</span><span class="m-val muted"><?= htmlspecialchars((string)$r['created_at']) ?></span></div>
      <?php if (!empty($r['resolved_at'])): ?>
      <div class="m-row"><span class="m-label">Xử lý lúc</span><span class="m-val muted"><?= htmlspecialchars((string)$r['resolved_at']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($r['admin_note'])): ?>
      <div class="m-row"><span class="m-label">Ghi chú</span><span class="m-val muted"><?= htmlspecialchars(mb_strimwidth((string)$r['admin_note'], 0, 80, '…')) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($r['proof_path'])): ?>
      <div class="m-row"><span class="m-label">Bằng chứng</span><span class="m-val"><a href="<?= htmlspecialchars((string)$r['proof_path']) ?>" target="_blank" class="btn sm secondary">Xem</a></span></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
