<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

if (CtvAuth::currentUser()) { header('Location: /ctv/dashboard.php'); exit; }

$err = null;
$ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $err = 'Phiên không hợp lệ, vui lòng thử lại.';
    } else {
        try {
            $res = CtvAuth::register(
                (string)($_POST['email'] ?? ''),
                (string)($_POST['password'] ?? ''),
                trim((string)($_POST['display_name'] ?? '')) ?: null,
                trim((string)($_POST['phone'] ?? '')) ?: null
            );
            $ok = 'Đăng ký thành công. Vui lòng kiểm tra email để xác thực tài khoản.';
        } catch (InvalidArgumentException $e) {
            $err = $e->getMessage();
        } catch (Throwable $e) {
            app_log('CTV register error: ' . $e->getMessage(), 'ERROR');
            $err = 'Lỗi hệ thống, thử lại sau.';
        }
    }
}

$csrf = CtvAuth::csrfToken();
ctv_layout_header('Đăng ký Partner', null);
?>
<div class="card" style="max-width:520px;margin:auto;">
  <h2>Đăng ký tài khoản Partner</h2>
  <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($ok): ?><div class="flash ok"><?= htmlspecialchars($ok) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="field"><label>Email</label><input type="email" name="email" autocomplete="email" required value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>"></div>
    <div class="field"><label>Mật khẩu (tối thiểu 8 ký tự)</label><input type="password" name="password" autocomplete="new-password" minlength="8" required></div>
    <div class="field"><label>Tên hiển thị (tùy chọn)</label><input type="text" name="display_name" value="<?= htmlspecialchars((string)($_POST['display_name'] ?? '')) ?>"></div>
    <div class="field"><label>Số điện thoại (tùy chọn)</label><input type="text" name="phone" value="<?= htmlspecialchars((string)($_POST['phone'] ?? '')) ?>"></div>
    <button class="btn" type="submit">Đăng ký</button>
    <p class="muted" style="margin-top:14px;">Đã có tài khoản? <a href="/ctv/login.php">Đăng nhập</a></p>
  </form>
</div>
<?php ctv_layout_footer();
