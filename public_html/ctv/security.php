<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

if ((int)($user['email_verified'] ?? 0) !== 1) {
    ctv_flash_set('error', 'Vui lòng xác thực email trước khi thiết lập Passkey.');
    header('Location: /ctv/dashboard.php');
    exit;
}

$pwFlash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $pwFlash = ['error', 'Phiên làm việc hết hạn. Vui lòng tải lại trang.'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rl = new RateLimiter();
        if (!$rl->check('ctv_pw_change:' . (int)$user['id'], 5, 900)) {
            $pwFlash = ['error', 'Quá nhiều lần thử. Vui lòng đợi 15 phút.'];
        } else {
            $currentPw = (string)($_POST['current_password'] ?? '');
            $newPw = (string)($_POST['new_password'] ?? '');
            $confirmPw = (string)($_POST['confirm_password'] ?? '');
            $st = db()->prepare('SELECT password_hash FROM ctv_users WHERE id=? LIMIT 1');
            $st->execute([(int)$user['id']]);
            $hash = (string)$st->fetchColumn();
            if (!password_verify($currentPw, $hash)) {
                $pwFlash = ['error', 'Mật khẩu hiện tại không đúng.'];
            } elseif (strlen($newPw) < 8) {
                $pwFlash = ['error', 'Mật khẩu mới tối thiểu 8 ký tự.'];
            } elseif ($newPw !== $confirmPw) {
                $pwFlash = ['error', 'Mật khẩu xác nhận không khớp.'];
            } elseif ($currentPw === $newPw) {
                $pwFlash = ['error', 'Mật khẩu mới phải khác mật khẩu hiện tại.'];
            } else {
                $newHash = password_hash($newPw, PASSWORD_BCRYPT);
                db()->prepare('UPDATE ctv_users SET password_hash=? WHERE id=?')->execute([$newHash, (int)$user['id']]);
                $pwFlash = ['ok', 'Đã đổi mật khẩu thành công.'];
            }
        }
    }
}

$csrf = CtvAuth::csrfToken();
$passkeys = (new PasskeyService())->listCredentials('ctv', (int)$user['id']);

ctv_layout_header('Bảo mật', $user);
ctv_flash_render();
?>
<script src="/assets/passkey.js?v=20260504"></script>
<div class="card" style="max-width:720px">
  <h2>Passkey / Khoá bảo mật</h2>
  <p class="muted" style="margin-bottom:14px">Passkey cho phép đăng nhập bằng Face ID, Touch ID hoặc Windows Hello — không cần nhập mật khẩu. Mỗi tài khoản tối đa 5 passkey.</p>

  <div id="passkeyNotSupported" style="display:none">
    <div class="flash warn">Trình duyệt của bạn không hỗ trợ Passkey. Vui lòng sử dụng Safari, Chrome hoặc Edge phiên bản mới.</div>
  </div>

  <div id="passkeySection">
    <h3>Passkey đã đăng ký (<?= count($passkeys) ?>/5)</h3>
    <?php if ($passkeys): ?>
    <div class="m-cards">
      <?php foreach ($passkeys as $pk): ?>
      <div class="m-card" id="pk-m-<?= (int)$pk['id'] ?>">
        <div class="m-head"><span><?= htmlspecialchars((string)($pk['device_name'] ?: 'Passkey #' . $pk['id'])) ?></span></div>
        <div class="m-row"><span class="m-label">Ngày tạo</span><span class="m-val muted"><?= htmlspecialchars((string)$pk['created_at']) ?></span></div>
        <div class="m-row"><span class="m-label">Lần dùng cuối</span><span class="m-val muted"><?= $pk['last_used_at'] ? htmlspecialchars((string)$pk['last_used_at']) : 'Chưa dùng' ?></span></div>
        <div class="m-actions"><button class="btn sm danger" onclick="revokePasskey(<?= (int)$pk['id'] ?>)">Xoá</button></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="table-wrap">
    <table>
      <thead><tr><th>Tên thiết bị</th><th>Ngày tạo</th><th>Lần dùng cuối</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($passkeys as $pk): ?>
      <tr id="pk-<?= (int)$pk['id'] ?>">
        <td><strong><?= htmlspecialchars((string)($pk['device_name'] ?: 'Passkey #' . $pk['id'])) ?></strong></td>
        <td style="white-space:nowrap"><span class="muted"><?= htmlspecialchars((string)$pk['created_at']) ?></span></td>
        <td style="white-space:nowrap"><span class="muted"><?= $pk['last_used_at'] ? htmlspecialchars((string)$pk['last_used_at']) : 'Chưa dùng' ?></span></td>
        <td><button class="btn sm danger" onclick="revokePasskey(<?= (int)$pk['id'] ?>)">Xoá</button></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="icon">🔑</div><p>Chưa có passkey nào. Thêm passkey để đăng nhập nhanh hơn.</p></div>
    <?php endif; ?>

    <?php if (count($passkeys) < 5): ?>
    <div style="margin-top:14px">
      <button class="btn gold" id="addPasskeyBtn" onclick="addPasskey()">Thêm Passkey</button>
    </div>
    <?php endif; ?>

    <div id="passkeyMsg" style="margin-top:10px"></div>
  </div>
</div>

<div class="card" style="max-width:720px">
  <h2>Đổi mật khẩu</h2>
  <p class="muted" style="margin-bottom:14px">Mật khẩu luôn khả dụng để đăng nhập, kể cả khi bạn có passkey.</p>
  <?php if ($pwFlash): ?><div class="flash <?= htmlspecialchars($pwFlash[0]) ?>"><?= htmlspecialchars($pwFlash[1]) ?></div><?php endif; ?>
  <form method="post" style="max-width:360px">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="change_password">
    <div class="field"><label>Mật khẩu hiện tại</label><input type="password" name="current_password" required autocomplete="current-password"></div>
    <div class="field"><label>Mật khẩu mới (tối thiểu 8 ký tự)</label><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
    <div class="field"><label>Xác nhận mật khẩu mới</label><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
    <button class="btn" type="submit">Đổi mật khẩu</button>
  </form>
</div>

<script>
(function(){
  var msgEl = document.getElementById('passkeyMsg');
  var section = document.getElementById('passkeySection');
  var notSupported = document.getElementById('passkeyNotSupported');

  if (!Passkey.isSupported()) {
    section.style.display = 'none';
    notSupported.style.display = '';
    return;
  }

  function showMsg(type, text) {
    msgEl.innerHTML = '<div class="flash ' + (type === 'ok' ? 'ok' : 'error') + '">' + escHtml(text) + '</div>';
    setTimeout(function(){ msgEl.innerHTML = ''; }, 6000);
  }

  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  window.addPasskey = async function() {
    var btn = document.getElementById('addPasskeyBtn');
    if (btn) btn.disabled = true;
    try {
      var result = await Passkey.register('/ctv/passkey-api.php');
      var name = prompt('Đặt tên cho passkey này (ví dụ: iPhone của tôi):', '');
      if (name && name.trim()) {
        await fetch('/ctv/passkey-api.php?action=rename', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({id: result.id, name: name.trim()})
        });
      }
      showMsg('ok', 'Đăng ký passkey thành công!');
      setTimeout(function(){ location.reload(); }, 1200);
    } catch(e) {
      showMsg('error', e.message || 'Đăng ký passkey thất bại');
      if (btn) btn.disabled = false;
    }
  };

  window.revokePasskey = async function(id) {
    if (!confirm('Xoá passkey này? Bạn có thể thêm lại sau.')) return;
    try {
      var resp = await fetch('/ctv/passkey-api.php?action=revoke', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: id})
      });
      var json = await resp.json();
      if (!json.ok) throw new Error(json.error || 'Lỗi');
      var row = document.getElementById('pk-' + id);
      if (row) row.remove();
      var mcard = document.getElementById('pk-m-' + id);
      if (mcard) mcard.remove();
      showMsg('ok', 'Đã xoá passkey');
    } catch(e) {
      showMsg('error', e.message || 'Xoá thất bại');
    }
  };
})();
</script>
<?php ctv_layout_footer();
