<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

if (CtvAuth::currentUser()) { header('Location: /ctv/dashboard.php'); exit; }

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $err = 'Phiên không hợp lệ, vui lòng thử lại.';
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $emailKey = strtolower(trim((string)($_POST['email'] ?? '')));
        $emailKey = hash('sha256', $emailKey);
        $rl = new RateLimiter();
        try {
            if (!$rl->check('ctv_login_ip:' . $ip, 30, 300) || !$rl->check('ctv_login_email:' . $ip . ':' . $emailKey, 10, 300)) {
                throw new RuntimeException('Quá nhiều lần đăng nhập. Vui lòng thử lại sau.');
            }
            CtvAuth::login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
            header('Location: /ctv/dashboard.php');
            exit;
        } catch (InvalidArgumentException $e) {
            $err = $e->getMessage();
        } catch (Throwable $e) {
            app_log('CTV login error: ' . $e->getMessage(), 'ERROR');
            $err = 'Lỗi hệ thống, thử lại sau.';
        }
    }
}

$csrf = CtvAuth::csrfToken();
ctv_layout_header('Đăng nhập Partner', null);
?>
<script src="/assets/passkey.js?v=20260504"></script>
<div class="card" style="max-width:480px;margin:auto;">
  <h2>Đăng nhập Partner</h2>
  <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <div id="passkeyLoginWrap" style="display:none;margin-bottom:16px">
    <button class="btn gold" style="width:100%;padding:14px;font-size:16px" id="passkeyLoginBtn" onclick="passkeyLogin()">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-4px;margin-right:6px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Đăng nhập bằng Passkey
    </button>
    <div id="passkeyLoginMsg" style="margin-top:8px"></div>
    <div style="text-align:center;margin:12px 0;color:var(--c-muted);font-size:13px">— hoặc dùng mật khẩu —</div>
  </div>
  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="field"><label>Email</label><input type="email" name="email" autocomplete="email" required value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>"></div>
    <div class="field"><label>Mật khẩu</label><input type="password" name="password" autocomplete="current-password" required></div>
    <button class="btn" type="submit">Đăng nhập</button>
    <p class="muted" style="margin-top:14px;">Chưa có tài khoản? <a href="/ctv/register.php">Đăng ký</a></p>
  </form>
</div>
<script>
(function(){
  if (!Passkey.isSupported()) return;
  document.getElementById('passkeyLoginWrap').style.display = '';

  window.passkeyLogin = async function() {
    var btn = document.getElementById('passkeyLoginBtn');
    var msg = document.getElementById('passkeyLoginMsg');
    btn.disabled = true;
    msg.innerHTML = '';
    try {
      await Passkey.login('/ctv/passkey-api.php');
      window.location.href = '/ctv/dashboard.php';
    } catch(e) {
      var text = e.message || 'Đăng nhập passkey thất bại';
      if (e.name === 'NotAllowedError') text = 'Đã huỷ xác thực passkey';
      msg.innerHTML = '<div class="flash error">' + text + '</div>';
      btn.disabled = false;
    }
  };
})();
</script>
<?php ctv_layout_footer();
