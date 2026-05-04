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
  <p style="color:#888;margin-bottom:16px">Passkey cho phép xác thực hai lớp bằng Face ID, Touch ID hoặc Windows Hello. Tối đa 5 passkey mỗi tài khoản.</p>

  <div id="passkeyNotSupported" style="display:none">
    <div class="flash warn" style="background:#fff3cd;padding:12px;border-radius:6px">Trình duyệt không hỗ trợ Passkey. Vui lòng dùng Safari, Chrome hoặc Edge phiên bản mới.</div>
  </div>

  <div id="passkeySection">
    <h3>Passkey đã đăng ký (<?= count($passkeys) ?>/5)</h3>
    <?php if ($passkeys): ?>
    <table style="width:100%;border-collapse:collapse">
      <thead><tr><th style="text-align:left;padding:8px">Tên thiết bị</th><th style="text-align:left;padding:8px">Ngày tạo</th><th style="text-align:left;padding:8px">Lần dùng cuối</th><th style="padding:8px"></th></tr></thead>
      <tbody>
      <?php foreach ($passkeys as $pk): ?>
      <tr id="pk-<?= (int)$pk['id'] ?>" style="border-top:1px solid #eee">
        <td style="padding:8px"><strong><?= htmlspecialchars((string)($pk['device_name'] ?: 'Passkey #' . $pk['id'])) ?></strong></td>
        <td style="padding:8px;color:#888"><?= htmlspecialchars((string)$pk['created_at']) ?></td>
        <td style="padding:8px;color:#888"><?= $pk['last_used_at'] ? htmlspecialchars((string)$pk['last_used_at']) : 'Chưa dùng' ?></td>
        <td style="padding:8px"><button class="btn btn-sm" style="background:#dc3545;color:#fff;border:none;padding:4px 12px;border-radius:4px;cursor:pointer" onclick="revokePasskey(<?= (int)$pk['id'] ?>)">Xoá</button></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <p style="color:#888">Chưa có passkey nào.</p>
    <?php endif; ?>

    <?php if (count($passkeys) < 5): ?>
    <div style="margin-top:14px">
      <button class="btn" id="addPasskeyBtn" style="background:#f5a623;color:#fff;border:none;padding:10px 20px;border-radius:6px;cursor:pointer;font-size:14px" onclick="addPasskey()">Thêm Passkey</button>
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
    msgEl.innerHTML = '<div style="padding:10px;border-radius:6px;margin-top:8px;' +
      (type === 'ok' ? 'background:#d4edda;color:#155724' : 'background:#f8d7da;color:#721c24') +
      '">' + escHtml(text) + '</div>';
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
      await Passkey.register('/admin/ctv/passkey-api.php');
      showMsg('ok', 'Đăng ký passkey thành công!');
      setTimeout(function(){ location.reload(); }, 1200);
    } catch(e) {
      showMsg('error', e.message || 'Đăng ký passkey thất bại');
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
      showMsg('error', e.message || 'Xoá thất bại');
    }
  };
})();
</script>
<?php admin_layout_footer();
