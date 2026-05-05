<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';
security_headers(true);

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$err = null;
$ok = false;
$validToken = false;
$featureReady = false;
try {
    $featureReady = (bool)db()->query("SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='ctv_users' AND column_name='password_reset_token' LIMIT 1")->fetchColumn();
} catch (Throwable $e) {}

if (!$featureReady) {
    ctv_layout_header('Đặt lại mật khẩu', null);
    echo '<div class="card" style="max-width:420px;margin:40px auto"><h2>Đặt lại mật khẩu</h2>'
       . '<div class="flash error">Tính năng đặt lại mật khẩu đang bảo trì. Vui lòng liên hệ <a href="/support.php">/support</a>.</div>'
       . '</div>';
    ctv_layout_footer();
    return;
}

if ($token !== '' && strlen($token) <= 64) {
    $st = db()->prepare('SELECT id, email, password_reset_sent_at FROM ctv_users WHERE password_reset_token=? AND status=1 LIMIT 1');
    $st->execute([$token]);
    $u = $st->fetch();
    if ($u && $u['password_reset_sent_at']) {
        $sentAt = strtotime((string)$u['password_reset_sent_at']);
        if ($sentAt && (time() - $sentAt) < 1800) {
            $validToken = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $err = 'Phiên không hợp lệ. Vui lòng tải lại trang.';
    } else {
        $newPw = (string)($_POST['new_password'] ?? '');
        $confirmPw = (string)($_POST['confirm_password'] ?? '');
        if (strlen($newPw) < 8) {
            $err = 'Mật khẩu mới tối thiểu 8 ký tự.';
        } elseif ($newPw !== $confirmPw) {
            $err = 'Mật khẩu xác nhận không khớp.';
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT);
            db()->prepare('UPDATE ctv_users SET password_hash=?, password_reset_token=NULL, password_reset_sent_at=NULL WHERE id=?')
                ->execute([$hash, (int)$u['id']]);
            db()->prepare('DELETE FROM ctv_sessions WHERE ctv_id=?')->execute([(int)$u['id']]);
            $ok = true;
        }
    }
}

$csrf = CtvAuth::csrfToken();
ctv_layout_header('Đặt lại mật khẩu', null);
?>
<div class="card" style="max-width:420px;margin:40px auto">
  <h2>Đặt lại mật khẩu</h2>
  <?php if ($ok): ?>
    <div class="flash ok">Đã đặt lại mật khẩu thành công. Tất cả phiên cũ đã bị thu hồi.</div>
    <a class="btn gold" href="/auth?role=partner">Đăng nhập ngay</a>
  <?php elseif (!$validToken): ?>
    <div class="flash error">Liên kết đặt lại không hợp lệ hoặc đã hết hạn (30 phút).</div>
    <a class="btn secondary" href="/ctv/forgot-password.php">Yêu cầu liên kết mới</a>
  <?php else: ?>
    <p class="muted" style="margin-bottom:14px">Nhập mật khẩu mới cho tài khoản <strong><?= htmlspecialchars((string)$u['email']) ?></strong>.</p>
    <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <div class="field"><label>Mật khẩu mới (tối thiểu 8 ký tự)</label><input type="password" name="new_password" required minlength="8" autofocus autocomplete="new-password"></div>
      <div class="field"><label>Xác nhận mật khẩu mới</label><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
      <button class="btn gold" type="submit">Đặt lại mật khẩu</button>
    </form>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
