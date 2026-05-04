<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_guard.php';

$admin = admin_ctv_require();
$adminUser = $admin['user'];
$adminId = crc32($adminUser);

$passkeys = (new PasskeyService())->listCredentials('admin', $adminId);

admin_layout_header('Passkey', $admin);
?>
<script src="/assets/passkey.js?v=20260504"></script>
<div class="card" style="max-width:720px">
  <h2>Passkey / Khoá bảo mật</h2>
  <p class="muted" style="margin-bottom:16px">Passkey cho phép xác thực hai lớp bằng Face ID, Touch ID hoặc Windows Hello. Tối đa 5 passkey mỗi tài khoản.</p>
  <?php if (isset($_GET['passkey_required']) && !$passkeys): ?>
  <div class="flash warn">ADMIN_REQUIRE_PASSKEY đang bật. Vui lòng thêm passkey đầu tiên để kích hoạt xác thực passkey cho khu vực quản trị.</div>
  <?php endif; ?>

  <div id="passkeyNotSupported" style="display:none">
    <div class="flash warn">Trình duyệt không hỗ trợ Passkey. Vui lòng dùng Safari, Chrome hoặc Edge phiên bản mới.</div>
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
        <td><span class="muted"><?= htmlspecialchars((string)$pk['created_at']) ?></span></td>
        <td><span class="muted"><?= $pk['last_used_at'] ? htmlspecialchars((string)$pk['last_used_at']) : 'Chưa dùng' ?></span></td>
        <td><button class="btn sm danger" onclick="revokePasskey(<?= (int)$pk['id'] ?>)">Xoá</button></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="empty"><div class="icon">🔑</div><p>Chưa có passkey nào. Thêm passkey để xác thực nhanh hơn.</p></div>
    <?php endif; ?>

    <?php if (count($passkeys) < 5): ?>
    <div style="margin-top:14px">
      <button class="btn gold" id="addPasskeyBtn" onclick="addPasskey()">Thêm Passkey</button>
    </div>
    <?php endif; ?>

    <div id="passkeyMsg" style="margin-top:10px"></div>
  </div>
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
    msgEl.innerHTML = '<div class="flash ' + (type === 'ok' ? 'ok' : 'err') + '">' + escHtml(text) + '</div>';
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
      var result = await Passkey.register('/admin/ctv/passkey-api.php');
      var name = prompt('Đặt tên cho passkey (ví dụ: MacBook văn phòng):', '');
      if (name && name.trim() && result && result.id) {
        await fetch('/admin/ctv/passkey-api.php?action=rename', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({id: result.id, name: name.trim()})
        }).catch(function(){});
      }
      showMsg('ok', 'Đăng ký passkey thành công!');
      setTimeout(function(){ location.reload(); }, 1200);
    } catch(e) {
      showMsg('err', e.message || 'Đăng ký passkey thất bại');
      if (btn) btn.disabled = false;
    }
  };

  window.revokePasskey = async function(id) {
    if (!confirm('Xoá passkey này?')) return;
    try {
      var resp = await fetch('/admin/ctv/passkey-api.php?action=revoke', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({id: id})
      });
      var json = await resp.json();
      if (!json.ok) throw new Error(json.error || 'Lỗi');
      var row = document.getElementById('pk-' + id);
      if (row) row.remove();
      showMsg('ok', 'Đã xoá passkey');
    } catch(e) {
      showMsg('err', e.message || 'Xoá thất bại');
    }
  };
})();
</script>
<?php admin_layout_footer();
