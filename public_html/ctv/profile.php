<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $flash = ['error', 'Phiên không hợp lệ. Tải lại trang.'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rl = new RateLimiter();
        if (!$rl->check('ctv_profile_edit:' . (int)$user['id'], 20, 600)) {
            $flash = ['error', 'Quá nhiều thay đổi. Vui lòng đợi.'];
        } else {
            $name = trim((string)($_POST['display_name'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            if (mb_strlen($name) > 100) { $flash = ['error', 'Tên hiển thị tối đa 100 ký tự.']; }
            elseif (mb_strlen($phone) > 30) { $flash = ['error', 'SĐT tối đa 30 ký tự.']; }
            elseif ($phone !== '' && !preg_match('/^[\d\s+().-]+$/', $phone)) { $flash = ['error', 'Số điện thoại không hợp lệ.']; }
            else {
                try {
                    db()->prepare('UPDATE ctv_users SET display_name=?, phone=?, updated_at=NOW() WHERE id=?')
                        ->execute([$name !== '' ? $name : null, $phone !== '' ? $phone : null, (int)$user['id']]);
                    $flash = ['ok', 'Đã cập nhật hồ sơ.'];
                    $user['display_name'] = $name;
                    $user['phone'] = $phone;
                } catch (Throwable $e) {
                    app_log('CTV profile update fail: ' . $e->getMessage(), 'ERROR');
                    $flash = ['error', 'Lỗi hệ thống. Thử lại.'];
                }
            }
        }
    }
}

$csrf = CtvAuth::csrfToken();
ctv_layout_header('Hồ sơ', $user);
?>
<div class="card" style="max-width:520px;margin:0 auto">
  <h2>Hồ sơ đối tác</h2>
  <?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="field">
      <label>Email <span class="muted" style="font-weight:400;font-size:11px">(không thể đổi)</span></label>
      <input type="email" value="<?= htmlspecialchars((string)$user['email']) ?>" disabled style="opacity:.65">
    </div>
    <div class="field">
      <label>Tên hiển thị</label>
      <input type="text" name="display_name" maxlength="100" value="<?= htmlspecialchars((string)($user['display_name'] ?? '')) ?>" placeholder="Tên cá nhân hoặc doanh nghiệp">
    </div>
    <div class="field">
      <label>Số điện thoại</label>
      <input type="text" name="phone" maxlength="30" value="<?= htmlspecialchars((string)($user['phone'] ?? '')) ?>" placeholder="0xxx xxx xxx" inputmode="tel">
    </div>
    <button class="btn gold" type="submit">Lưu thay đổi</button>
  </form>

  <div class="divider" style="margin:24px 0"></div>

  <h3 style="margin-bottom:10px">Thông tin tài khoản</h3>
  <div style="display:grid;gap:8px;font-size:14px">
    <div><span class="muted">Mã đối tác</span> <strong>#<?= (int)$user['id'] ?></strong></div>
    <div><span class="muted">Số dư ví</span> <strong style="color:var(--c-gold)"><?= htmlspecialchars(format_vnd((int)$user['balance'])) ?></strong></div>
    <div><span class="muted">Chiết khấu mỗi eSIM</span> <strong><?= htmlspecialchars(format_vnd((int)($user['discount_per_esim'] ?? 0))) ?></strong></div>
    <?php if (!empty($user['last_login_at'])): ?>
    <div><span class="muted">Đăng nhập gần nhất</span> <span><?= htmlspecialchars((string)$user['last_login_at']) ?> · <?= htmlspecialchars((string)($user['last_login_ip'] ?? '')) ?></span></div>
    <?php endif; ?>
    <div><span class="muted">Tạo tài khoản</span> <span><?= htmlspecialchars((string)($user['created_at'] ?? '')) ?></span></div>
  </div>

  <p class="muted" style="margin-top:18px;font-size:13px">Đổi mật khẩu hoặc Passkey tại <a href="/ctv/security.php" style="color:var(--c-gold)">trang Bảo mật</a>.</p>
</div>
<?php ctv_layout_footer();
