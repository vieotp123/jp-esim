<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';
security_headers(true);

$err = null;
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $err = 'Phiên không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rl = new RateLimiter();
        if (!$rl->check('ctv_forgot_ip:' . $ip, 5, 900)) {
            $err = 'Quá nhiều yêu cầu. Vui lòng đợi 15 phút.';
        } else {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if (!valid_email($email)) {
                $err = 'Email không hợp lệ.';
            } else {
                $st = db()->prepare('SELECT id, email, status, email_verified FROM ctv_users WHERE email=? LIMIT 1');
                $st->execute([$email]);
                $u = $st->fetch();
                if ($u && (int)$u['status'] === 1 && (int)$u['email_verified'] === 1) {
                    $token = bin2hex(random_bytes(24));
                    db()->prepare('UPDATE ctv_users SET password_reset_token=?, password_reset_sent_at=NOW() WHERE id=?')
                        ->execute([$token, (int)$u['id']]);
                    try { (new CtvMailer())->sendPasswordResetEmail($email, $token); }
                    catch (Throwable $e) { app_log('Password reset email failed ' . $email . ' ' . $e->getMessage(), 'ERROR'); }
                }
                $ok = true;
            }
        }
    }
}

$csrf = CtvAuth::csrfToken();
ctv_layout_header('Quên mật khẩu', null);
?>
<div class="card" style="max-width:420px;margin:40px auto">
  <h2>Quên mật khẩu</h2>
  <?php if ($ok): ?>
    <div class="flash ok">Nếu email tồn tại trong hệ thống, bạn sẽ nhận được liên kết đặt lại mật khẩu trong vài phút.</div>
    <p class="muted">Kiểm tra hộp thư (bao gồm thư rác). Liên kết có hiệu lực 30 phút.</p>
    <a class="btn secondary" href="/ctv/login.php">Quay lại đăng nhập</a>
  <?php else: ?>
    <p class="muted" style="margin-bottom:14px">Nhập email đã đăng ký. Hệ thống sẽ gửi liên kết đặt lại mật khẩu.</p>
    <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
      <div class="field"><label>Email</label><input type="email" name="email" required autofocus autocomplete="email" placeholder="email@example.com"></div>
      <button class="btn" type="submit">Gửi liên kết đặt lại</button>
    </form>
    <p style="margin-top:14px"><a href="/ctv/login.php" style="color:var(--c-gold)">Quay lại đăng nhập</a></p>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
