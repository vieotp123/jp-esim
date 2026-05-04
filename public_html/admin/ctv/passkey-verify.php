<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';

$admin = admin_ctv_require();

if (admin_passkey_verified()) {
    header('Location: /admin/ctv/dashboard-admin.php');
    exit;
}

$adminUser = $admin['user'];
$adminId = crc32($adminUser);
$hasPasskey = (new PasskeyService())->hasPasskey('admin', $adminId);

if (!$hasPasskey) {
    admin_session_start();
    $_SESSION['admin_passkey_verified'] = 1;
    $_SESSION['admin_passkey_verified_at'] = time();
    header('Location: /admin/ctv/dashboard-admin.php');
    exit;
}

admin_layout_header('Xác thực Passkey', $admin);
?>
<script src="/assets/passkey.js?v=20260504"></script>
<div class="card" style="max-width:480px;margin:40px auto;text-align:center">
  <h2>Xác thực Passkey</h2>
  <p class="muted" style="margin-bottom:20px">Vui lòng xác thực passkey để tiếp tục.</p>
  <button class="btn gold" id="verifyBtn" style="width:100%;font-size:16px;padding:14px 28px" onclick="verifyPasskey()">
    Xác thực bằng Passkey
  </button>
  <div id="verifyMsg" style="margin-top:12px"></div>
</div>
<script>
(function(){
  function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  window.verifyPasskey = async function() {
    var btn = document.getElementById('verifyBtn');
    var msg = document.getElementById('verifyMsg');
    btn.disabled = true;
    msg.innerHTML = '';
    try {
      await Passkey.login('/admin/ctv/passkey-api.php');
      window.location.href = '/admin/ctv/dashboard-admin.php';
    } catch(e) {
      var text = e.message || 'Xác thực passkey thất bại';
      if (e.name === 'NotAllowedError') text = 'Đã huỷ xác thực';
      msg.innerHTML = '<div class="flash err">' + escHtml(text) + '</div>';
      btn.disabled = false;
    }
  };
  verifyPasskey();
})();
</script>
<?php admin_layout_footer();
