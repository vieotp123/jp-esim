<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$token = (string)($_GET['token'] ?? '');
$ok = $token !== '' ? CtvAuth::verifyEmail($token) : false;

ctv_layout_header('Xác thực email', null);
?>
<div class="card" style="max-width:520px;margin:auto;">
  <h2>Xác thực email</h2>
  <?php if ($ok): ?>
    <div class="flash ok">Xác thực thành công. Bạn có thể đăng nhập ngay.</div>
    <a class="btn" href="/auth?role=partner">Đến trang đăng nhập</a>
  <?php else: ?>
    <div class="flash error">Liên kết xác thực không hợp lệ hoặc đã được sử dụng.</div>
    <a class="btn secondary" href="/ctv/register.php">Đăng ký lại</a>
  <?php endif; ?>
</div>
<?php ctv_layout_footer();
