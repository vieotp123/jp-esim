<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';

$role = strtolower(trim((string)($_GET['role'] ?? '')));
$qs = isset($_GET['idle']) ? '?idle=1' : '';
if ($role === 'admin') { header('Location: /admin/ctv/login.php' . $qs); exit; }
if ($role === 'partner' || $role === 'ctv') { header('Location: /ctv/login.php' . $qs); exit; }
$idleNotice = isset($_GET['idle']);

if (function_exists('security_headers')) { security_headers(true); }
$assetVer = '20260505a';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title>Đăng nhập · jp-esim</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#0d0f15;--card:#161a23;--line:#272d3b;--ink:#e9ecf3;--ink-2:#a9b0bf;--muted:#7a8094;--gold:#e6c068;--gold-2:#f4d684;--gold-deep:#b8902e}
*{box-sizing:border-box}
body{margin:0;font-family:'Be Vietnam Pro',system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px}
.wrap{width:100%;max-width:760px}
.brand{text-align:center;margin-bottom:28px}
.brand-mark{display:inline-block;font-weight:900;font-size:22px;padding:8px 18px;border-radius:12px;background:linear-gradient(180deg,var(--gold-2),var(--gold-deep));color:#241804;letter-spacing:1.5px}
h1{text-align:center;font-size:24px;font-weight:800;margin:18px 0 6px}
.sub{text-align:center;color:var(--muted);font-size:14px;margin-bottom:28px}
.cards{display:grid;grid-template-columns:1fr 1fr;gap:18px}
@media(max-width:640px){.cards{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:28px 24px;text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;text-align:center;transition:transform .15s,border-color .15s,box-shadow .15s}
.card:hover,.card:focus{border-color:var(--gold);transform:translateY(-2px);box-shadow:0 12px 36px rgba(230,192,104,.08)}
.icon{width:64px;height:64px;border-radius:18px;background:rgba(230,192,104,.1);display:flex;align-items:center;justify-content:center;margin-bottom:16px;color:var(--gold)}
.card h2{font-size:18px;font-weight:700;margin:0 0 6px}
.card p{font-size:13px;color:var(--ink-2);margin:0 0 16px;line-height:1.55}
.card .btn{margin-top:auto;padding:11px 20px;border-radius:10px;background:rgba(230,192,104,.12);color:var(--gold);font-weight:700;font-size:14px;letter-spacing:.3px}
.card.gold .btn{background:linear-gradient(180deg,var(--gold-2),var(--gold-deep));color:#241804}
.foot{text-align:center;margin-top:28px;color:var(--muted);font-size:12px}
.foot a{color:var(--ink-2);text-decoration:none;border-bottom:1px dotted var(--line)}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand"><span class="brand-mark">JP</span></div>
  <h1>Đăng nhập jp-esim</h1>
  <p class="sub">Chọn loại tài khoản để tiếp tục</p>
  <?php if (!empty($idleNotice)): ?>
  <p class="sub" style="color:#e6c068;background:rgba(230,192,104,.06);border:1px solid rgba(230,192,104,.2);padding:10px 14px;border-radius:10px;margin-bottom:18px">
    Phiên làm việc đã hết hạn do không hoạt động. Vui lòng đăng nhập lại.
  </p>
  <?php endif; ?>
  <div class="cards">
    <a class="card gold" href="/admin/ctv/login.php" aria-label="Đăng nhập quản trị">
      <div class="icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 5v6c0 5 3.4 9.6 8 11 4.6-1.4 8-6 8-11V5l-8-3z"/></svg>
      </div>
      <h2>Quản trị viên</h2>
      <p>Truy cập bảng điều khiển admin. Yêu cầu Passkey đã đăng ký.</p>
      <span class="btn">Đăng nhập admin →</span>
    </a>
    <a class="card" href="/ctv/login.php" aria-label="Đăng nhập đối tác">
      <div class="icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <h2>Đối tác (Partner)</h2>
      <p>Cộng tác viên / đại lý: tạo eSIM, nạp data, quản lý ví.</p>
      <span class="btn">Đăng nhập đối tác →</span>
    </a>
  </div>
  <div class="foot">
    Chưa có tài khoản đối tác? <a href="/ctv/register.php">Đăng ký tại đây</a>
  </div>
</div>
</body>
</html>
