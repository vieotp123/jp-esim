<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);
$svc = new CtvApiKeyService();

$err = null; $newToken = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $err = 'Phiên không hợp lệ.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'generate') {
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '' || mb_strlen($name) > 60) throw new InvalidArgumentException('Tên key phải từ 1-60 ký tự.');
                $newToken = $svc->generate((int)$user['id'], $name);
                ctv_flash_set('warn', 'Lưu lại token này ngay – nó chỉ hiển thị một lần.');
            } elseif ($action === 'revoke') {
                $svc->revoke((int)$user['id'], (int)($_POST['key_id'] ?? 0));
                ctv_flash_set('ok', 'Đã thu hồi API key.');
                header('Location: /ctv/api-keys.php'); exit;
            }
        } catch (Throwable $e) {
            app_log('CTV api-keys error: ' . $e->getMessage(), 'ERROR');
            $err = 'Lỗi hệ thống.';
        }
    }
}

$keys = $svc->listForCtv((int)$user['id']);
$csrf = CtvAuth::csrfToken();
ctv_layout_header('Khoá API', $user);
ctv_flash_render();
?>
<div class="card">
  <h2>Khoá API</h2>
  <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($newToken): ?>
    <div class="flash warn">
      Token mới (chỉ hiển thị một lần): <span class="kbd" id="new-token"><?= htmlspecialchars($newToken['token']) ?></span> <button class="btn secondary" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('new-token').textContent)">Sao chép</button>
    </div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="generate">
    <div class="row">
      <div class="field"><label>Tên key</label><input type="text" name="name" placeholder="Ví dụ: integration-sandbox"></div>
    </div>
    <button class="btn" type="submit">Tạo API key mới</button>
  </form>
</div>

<div class="card">
  <h2>Danh sách key</h2>
  <?php if (!$keys): ?>
    <p class="muted">Chưa có key nào.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Tên</th><th>Prefix</th><th>Trạng thái</th><th>Tạo lúc</th><th>Lần dùng cuối</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($keys as $k): ?>
      <tr>
        <td><?= htmlspecialchars((string)$k['name']) ?></td>
        <td><span class="kbd">ctvK_<?= htmlspecialchars((string)$k['key_prefix']) ?>_***</span></td>
        <td>
          <?php if ((int)$k['status'] === 1): ?><span class="tag ok">Hoạt động</span><?php else: ?><span class="tag err">Đã thu hồi</span><?php endif; ?>
        </td>
        <td><?= htmlspecialchars((string)$k['created_at']) ?></td>
        <td><?= htmlspecialchars((string)($k['last_used_at'] ?? '')) ?></td>
        <td>
          <?php if ((int)$k['status'] === 1): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Thu hồi key <?= htmlspecialchars((string)$k['name']) ?>?')">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="revoke">
            <input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>">
            <button class="btn danger" type="submit">Thu hồi</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <p class="muted">Sử dụng header <span class="kbd">Authorization: Bearer ctvK_xxx_xxx</span> hoặc <span class="kbd">X-API-Key: ctvK_...</span> khi gọi <span class="kbd">/api/ctv/*</span>.</p>
</div>
<?php ctv_layout_footer();
