<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';

admin_session_start();
if (!empty($_SESSION['admin_authenticated']) && !empty($_SESSION['admin_user'])) {
    header('Location: /admin/ctv/dashboard-admin.php');
    exit;
}

$err = null;
if (isset($_GET['idle'])) { $err = 'Phiên đã hết hạn do không hoạt động. Vui lòng đăng nhập lại.'; }
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rl = new RateLimiter();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? $_GET['action'] ?? 'login');

    if ($action === 'login') {
        if (admin_passkey_enforced_strict()) {
            $err = 'Tài khoản admin yêu cầu đăng nhập bằng Passkey. Vui lòng dùng nút Passkey ở trên.';
        } elseif (!$rl->check('admin_login:' . $ip, 8, 300)) {
            $err = 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 5 phút.';
        } else {
            $u = trim((string)($_POST['username'] ?? ''));
            $p = (string)($_POST['password'] ?? '');
            $expectedUser = (string)app_config('ADMIN_USER', 'admin');
            $expectedPass = (string)app_config('ADMIN_PASS', '');
            if ($expectedPass !== '' && hash_equals($expectedUser, $u) && hash_equals($expectedPass, $p)) {
                admin_login($expectedUser);
                header('Location: /admin/ctv/dashboard-admin.php');
                exit;
            }
            $err = 'Tài khoản hoặc mật khẩu không đúng.';
        }
    } elseif ($action === 'passkey_login') {
        header('Content-Type: application/json; charset=utf-8');
        if (!$rl->check('admin_passkey_login:' . $ip, 15, 60)) {
            echo json_encode(['ok' => false, 'error' => 'Quá nhiều yêu cầu'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $step = (string)($_POST['step'] ?? $_GET['step'] ?? '');
        try {
            $svc = new PasskeyService();
            $expectedUser = (string)app_config('ADMIN_USER', 'admin');
            $adminId = crc32($expectedUser);
            if ($step === 'begin') {
                if (!$svc->hasPasskey('admin', $adminId)) {
                    echo json_encode(['ok' => false, 'error' => 'Chưa đăng ký passkey cho admin'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $options = $svc->authenticateBegin('admin', $adminId);
                echo json_encode(['ok' => true, 'data' => $options], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            } elseif ($step === 'finish') {
                $body = json_decode((string)file_get_contents('php://input'), true) ?: [];
                $credentialIdB64 = (string)($body['credentialId'] ?? '');
                $clientDataJSON = base64_decode((string)($body['clientDataJSON'] ?? ''));
                $authenticatorData = base64_decode((string)($body['authenticatorData'] ?? ''));
                $signature = base64_decode((string)($body['signature'] ?? ''));
                $userHandle = isset($body['userHandle']) ? (string)$body['userHandle'] : null;
                if ($credentialIdB64 === '' || $clientDataJSON === '' || $authenticatorData === '' || $signature === '') {
                    throw new InvalidArgumentException('Dữ liệu assertion không hợp lệ');
                }
                $svc->authenticateFinish('admin', $credentialIdB64, $clientDataJSON, $authenticatorData, $signature, $userHandle);
                admin_login($expectedUser);
                $_SESSION['admin_passkey_verified'] = 1;
                $_SESSION['admin_passkey_verified_at'] = time();
                AuditLog::log($expectedUser, 'admin_passkey_login', 'admin', (string)$adminId);
                echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (Throwable $e) {
            app_log('admin passkey login error: ' . $e->getMessage(), 'ERROR');
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['ok' => false, 'error' => 'Step không hợp lệ'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$hasPasskey = false;
try {
    $expectedUser = (string)app_config('ADMIN_USER', 'admin');
    $hasPasskey = (new PasskeyService())->hasPasskey('admin', crc32($expectedUser));
} catch (Throwable $e) {}
$passkeyOnly = $hasPasskey && admin_passkey_required();

security_headers(true);
$assetVer = '20260505a';
?>
<!doctype html><html lang="vi"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Đăng nhập · jp-esim Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/admin/ctv/admin_theme.css?v=<?= $assetVer ?>">
<style>
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px}
.login-card{width:100%;max-width:400px;background:var(--a-card);border:1px solid var(--a-line);border-radius:16px;padding:32px 28px;box-shadow:0 12px 48px rgba(0,0,0,.5)}
.login-card .brand{text-align:center;margin-bottom:24px}
.login-card .brand-mark{display:inline-block;font-weight:900;font-size:20px;padding:6px 14px;border-radius:10px;background:linear-gradient(180deg,var(--a-gold-2),var(--a-gold-deep));color:#241804;letter-spacing:1px}
.login-card h1{text-align:center;font-size:20px;font-weight:700;margin-bottom:6px;color:var(--a-ink)}
.login-card .sub{text-align:center;color:var(--a-muted);font-size:13px;margin-bottom:20px}
.login-card .field{margin-bottom:14px}
.login-card .field label{display:block;font-size:13px;font-weight:600;color:var(--a-ink-2);margin-bottom:5px}
.login-card .field input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid var(--a-line-2);background:var(--a-surface);color:var(--a-ink);font-size:15px;font-family:inherit}
.login-card .field input:focus{outline:none;border-color:var(--a-gold);box-shadow:0 0 0 3px rgba(230,192,104,.15)}
.login-card .divider{display:flex;align-items:center;gap:12px;margin:18px 0;color:var(--a-muted);font-size:12px}
.login-card .divider::before,.login-card .divider::after{content:'';flex:1;border-top:1px solid var(--a-line)}
.login-card .btn{width:100%;padding:13px;font-size:15px;font-weight:700}
.login-card .flash{margin-bottom:14px}
@media(max-width:480px){.login-card{padding:24px 18px;border-radius:12px}}
</style>
</head><body>
<div class="login-wrap">
<div class="login-card">
  <div class="brand"><span class="brand-mark">JP</span></div>
  <h1>Quản trị jp-esim</h1>
  <p class="sub"><?= $passkeyOnly ? 'Đăng nhập bằng Passkey (bắt buộc)' : 'Đăng nhập bằng tài khoản admin hoặc passkey' ?></p>

  <?php if ($err): ?><div class="flash err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <?php if ($hasPasskey): ?>
  <button class="btn gold" id="pkLoginBtn" onclick="passkeyLogin()" style="margin-bottom:4px">
    Đăng nhập bằng Passkey
  </button>
  <div id="pkMsg" style="margin-bottom:8px"></div>
  <?php if (!$passkeyOnly): ?><div class="divider">hoặc dùng mật khẩu</div><?php endif; ?>
  <?php endif; ?>

  <?php if (!$passkeyOnly): ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="action" value="login">
    <div class="field">
      <label>Tài khoản</label>
      <input type="text" name="username" required autocomplete="username" <?= $hasPasskey ? '' : 'autofocus' ?>>
    </div>
    <div class="field">
      <label>Mật khẩu</label>
      <input type="password" name="password" required autocomplete="current-password">
    </div>
    <button class="btn<?= $hasPasskey ? '' : ' gold' ?>" type="submit">Đăng nhập</button>
  </form>
  <?php else: ?>
  <p class="sub" style="margin-top:14px;font-size:12px">Mật khẩu đã bị vô hiệu hoá cho tài khoản admin để tăng cường bảo mật. Liên hệ quản trị nếu mất khoá Passkey.</p>
  <?php endif; ?>
</div>
</div>

<?php if ($hasPasskey): ?>
<script src="/assets/passkey.js?v=20260504"></script>
<script>
(function(){
  function escHtml(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}

  if (!Passkey.isSupported()) {
    document.getElementById('pkLoginBtn').style.display='none';
    return;
  }

  window.passkeyLogin = async function(){
    var btn=document.getElementById('pkLoginBtn');
    var msg=document.getElementById('pkMsg');
    btn.disabled=true; msg.innerHTML='';
    try {
      var beginResp = await fetch('/admin/ctv/login.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=passkey_login&step=begin'
      });
      var beginJson = await beginResp.json();
      if (!beginJson.ok) throw new Error(beginJson.error||'Lỗi khởi tạo');

      var opts = beginJson.data;
      var pk = opts.publicKey;
      if (pk.challenge) pk.challenge = b64url_decode(pk.challenge);
      if (pk.allowCredentials) pk.allowCredentials = pk.allowCredentials.map(function(c){c.id=b64url_decode(c.id);return c;});

      var assertion = await navigator.credentials.get(opts);
      var r = assertion.response;
      var finishBody = {
        credentialId: b64url_encode(assertion.rawId),
        clientDataJSON: buf_to_b64(r.clientDataJSON),
        authenticatorData: buf_to_b64(r.authenticatorData),
        signature: buf_to_b64(r.signature),
        userHandle: r.userHandle ? b64url_encode(r.userHandle) : null
      };

      var finishResp = await fetch('/admin/ctv/login.php?action=passkey_login&step=finish', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify(finishBody)
      });
      var finishJson = await finishResp.json();
      if (!finishJson.ok) throw new Error(finishJson.error||'Xác thực thất bại');
      window.location.href='/admin/ctv/dashboard-admin.php';
    } catch(e) {
      var text = e.message||'Xác thực passkey thất bại';
      if (e.name==='NotAllowedError') text='Đã huỷ xác thực';
      msg.innerHTML='<div class="flash err">'+escHtml(text)+'</div>';
      btn.disabled=false;
    }
  };

  function b64url_encode(buf){var bytes=new Uint8Array(buf);var s='';for(var i=0;i<bytes.length;i++)s+=String.fromCharCode(bytes[i]);return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'');}
  function b64url_decode(str){str=str.replace(/-/g,'+').replace(/_/g,'/');while(str.length%4)str+='=';var bin=atob(str);var buf=new Uint8Array(bin.length);for(var i=0;i<bin.length;i++)buf[i]=bin.charCodeAt(i);return buf.buffer;}
  function buf_to_b64(buf){var bytes=new Uint8Array(buf);var s='';for(var i=0;i<bytes.length;i++)s+=String.fromCharCode(bytes[i]);return btoa(s);}

  passkeyLogin();
})();
</script>
<?php endif; ?>
</body></html>
