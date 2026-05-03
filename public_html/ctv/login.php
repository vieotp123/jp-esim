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
        try {
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
ctv_layout_header('Đăng nhập CTV', null);
?>
<div class="card" style="max-width:480px;margin:auto;">
  <h2>Đăng nhập CTV</h2>
  <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="field"><label>Email</label><input type="email" name="email" autocomplete="email" required value="<?= htmlspecialchars((string)($_POST['email'] ?? '')) ?>"></div>
    <div class="field"><label>Mật khẩu</label><input type="password" name="password" autocomplete="current-password" required></div>
    <button class="btn" type="submit">Đăng nhập</button>
    <p class="muted" style="margin-top:14px;">Chưa có tài khoản? <a href="/ctv/register.php">Đăng ký</a></p>
  </form>
</div>
<?php ctv_layout_footer();
