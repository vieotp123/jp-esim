<?php
declare(strict_types=1);


if (!function_exists('ctv_flash_set')) {
    function ctv_flash_set(string $type, string $msg): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_name('jp_esim_ctv');
            @session_start();
        }
        $_SESSION['_ctv_flash'][] = ['type'=>$type, 'msg'=>$msg];
    }
}
if (!function_exists('ctv_flash_get')) {
    function ctv_flash_get(): array {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_name('jp_esim_ctv');
            @session_start();
        }
        $f = $_SESSION['_ctv_flash'] ?? [];
        unset($_SESSION['_ctv_flash']);
        return is_array($f) ? $f : [];
    }
}
if (!function_exists('ctv_flash_render')) {
    function ctv_flash_render(): void {
        foreach (ctv_flash_get() as $f) {
            $t = (string)($f['type'] ?? 'ok');
            $cls = in_array($t, ['ok','warn','error'], true) ? $t : 'ok';
            echo '<div class="flash '.htmlspecialchars($cls).'">'.htmlspecialchars((string)($f['msg'] ?? '')).'</div>';
        }
    }
}

if (!function_exists('ctv_nav_active')) {
    function ctv_nav_active(string $href): string {
        $cur = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        return $cur === $href ? ' class="active"' : '';
    }
}

if (!function_exists('ctv_layout_header')) {
    function ctv_layout_header(string $title, ?array $user = null): void {
        security_headers(true);
        ?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow,noarchive">
<title><?= htmlspecialchars($title) ?> · Đối tác jp-esim.vip</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{
  --c-bg:#060911;
  --c-surface:#0c1225;
  --c-card:#111a30;
  --c-card-2:#162040;
  --c-line:rgba(255,255,255,.06);
  --c-line-2:rgba(255,255,255,.10);
  --c-ink:#edf1fa;
  --c-ink-2:#b4c1dc;
  --c-muted:#6f809e;
  --c-gold:#e6c068;
  --c-gold-2:#f3d488;
  --c-gold-deep:#a98538;
  --c-blue:#5b8cff;
  --c-blue-2:#3a6dff;
  --c-green:#34d399;
  --c-red:#f87171;
  --c-amber:#fbbf24;
  --c-radius:14px;
  --c-radius-sm:10px;
  --c-shadow:0 8px 32px rgba(0,0,0,.35),0 2px 8px rgba(0,0,0,.20);
  color-scheme:dark;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:'Be Vietnam Pro',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
  background:var(--c-bg);
  color:var(--c-ink);
  min-height:100vh;
  -webkit-font-smoothing:antialiased;
  text-rendering:optimizeLegibility;
  line-height:1.5;
}
body::before{
  content:'';position:fixed;inset:0;z-index:-1;
  background:radial-gradient(ellipse 900px 500px at 5% -5%,rgba(91,140,255,.08),transparent),
             radial-gradient(ellipse 700px 400px at 95% -2%,rgba(230,192,104,.05),transparent);
  pointer-events:none;
}
a{color:var(--c-blue);text-decoration:none;transition:color .15s}
a:hover{color:#9fb6ff}

/* ===== HEADER ===== */
.hdr{
  position:sticky;top:0;z-index:100;
  background:rgba(12,18,37,.88);
  backdrop-filter:blur(16px) saturate(160%);
  -webkit-backdrop-filter:blur(16px) saturate(160%);
  border-bottom:1px solid var(--c-line-2);
  padding:0 20px;
}
.hdr-inner{
  max-width:1280px;margin:0 auto;
  display:flex;align-items:center;gap:16px;
  height:56px;
}
.hdr .brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:15px;flex-shrink:0}
.hdr .brand-mark{
  width:30px;height:30px;border-radius:9px;
  background:linear-gradient(135deg,var(--c-gold-2),var(--c-gold-deep));
  box-shadow:0 4px 14px rgba(230,192,104,.30),inset 0 0 0 1px rgba(255,255,255,.15);
  display:grid;place-items:center;color:#1a1206;font-weight:900;font-size:13px;
}
.hdr .brand-name{color:var(--c-ink)}
.hdr .brand-name em{font-style:normal;color:var(--c-gold);font-weight:700}

.hdr nav{display:flex;gap:2px;flex:1;overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none}
.hdr nav::-webkit-scrollbar{display:none}
.hdr nav a{
  white-space:nowrap;
  color:var(--c-ink-2);padding:7px 12px;border-radius:8px;
  font-weight:600;font-size:13px;
  transition:all .15s;
}
.hdr nav a:hover{background:rgba(91,140,255,.08);color:#fff}
.hdr nav a.active{
  background:rgba(230,192,104,.10);
  color:var(--c-gold);
  box-shadow:inset 0 0 0 1px rgba(230,192,104,.25);
}

.hdr .hdr-right{margin-left:auto;display:flex;align-items:center;gap:12px;flex-shrink:0}
.hdr .balance{
  font-size:12px;font-weight:700;color:var(--c-gold);
  background:rgba(230,192,104,.08);
  border:1px solid rgba(230,192,104,.20);
  padding:4px 10px;border-radius:999px;
}
.hdr .avatar{
  width:32px;height:32px;border-radius:999px;
  background:linear-gradient(135deg,#2a3760,#1f2a44);
  border:1px solid var(--c-line-2);
  display:grid;place-items:center;
  font-size:12px;font-weight:700;color:var(--c-ink-2);
}
.hdr .logout{
  font-size:12px;font-weight:600;color:var(--c-muted);
  padding:5px 10px;border-radius:7px;border:1px solid var(--c-line-2);
  transition:all .15s;
}
.hdr .logout:hover{color:var(--c-red);border-color:rgba(248,113,113,.30)}

/* ===== MAIN ===== */
main{max-width:1280px;margin:0 auto;padding:24px 20px 40px}
.page-head{margin-bottom:20px}
.page-head h1{font-size:22px;font-weight:800;letter-spacing:-.3px}
.page-head p{color:var(--c-muted);font-size:13px;margin-top:4px}

/* ===== CARDS ===== */
.card{
  background:linear-gradient(180deg,var(--c-card-2) 0%,var(--c-card) 100%);
  border:1px solid var(--c-line-2);
  border-radius:var(--c-radius);
  padding:20px;margin-bottom:16px;
  box-shadow:var(--c-shadow);
}
.card h2{font-size:16px;font-weight:700;margin-bottom:14px;letter-spacing:-.2px}
.card h3{font-size:13px;font-weight:700;color:var(--c-muted);text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px}

/* ===== FORMS ===== */
label{display:block;font-size:13px;font-weight:600;color:var(--c-ink-2);margin-bottom:5px}
input[type=text],input[type=email],input[type=password],input[type=number],input[type=date],select,textarea{
  width:100%;padding:11px 14px;font-size:14px;font-family:inherit;
  background:var(--c-surface);color:var(--c-ink);
  border:1px solid var(--c-line-2);border-radius:var(--c-radius-sm);
  transition:border-color .2s,box-shadow .2s;
}
input[type=checkbox]{
  width:18px;height:18px;accent-color:var(--c-gold);
  cursor:pointer;vertical-align:middle;flex:0 0 auto;
}
input:focus,select:focus,textarea:focus{
  outline:none;border-color:var(--c-gold);
  box-shadow:0 0 0 3px rgba(230,192,104,.12);
}
input::placeholder,textarea::placeholder{color:var(--c-muted)}
.field{margin-bottom:14px}
.field .helper{font-size:12px;color:var(--c-muted);margin-top:4px}

/* ===== BUTTONS ===== */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  background:linear-gradient(180deg,var(--c-blue),var(--c-blue-2));
  color:#fff;border:0;padding:10px 18px;border-radius:var(--c-radius-sm);
  font-weight:700;font-size:13.5px;cursor:pointer;text-decoration:none;
  box-shadow:0 4px 12px rgba(58,109,255,.25);
  transition:transform .08s,filter .12s,box-shadow .12s;
}
.btn:hover{filter:brightness(1.1);box-shadow:0 6px 18px rgba(58,109,255,.35);color:#fff}
.btn:active{transform:scale(.97)}
.btn:disabled{opacity:.5;cursor:not-allowed;pointer-events:none;filter:none}
.btn.secondary{
  background:var(--c-surface);box-shadow:none;
  border:1px solid var(--c-line-2);color:var(--c-ink-2);
}
.btn.secondary:hover{color:#fff;border-color:var(--c-gold)}
.btn.danger{background:linear-gradient(180deg,#ef5b5b,#c33b3b);box-shadow:0 4px 12px rgba(220,60,60,.25)}
.btn.gold{background:linear-gradient(180deg,var(--c-gold-2),var(--c-gold-deep));color:#1a1206;box-shadow:0 4px 12px rgba(230,192,104,.30)}
.btn.sm{padding:6px 12px;font-size:12px;border-radius:7px}
.btn.lg{padding:14px 24px;font-size:15px}

/* ===== TABLES ===== */
.table-wrap{overflow-x:auto;border-radius:var(--c-radius-sm);border:1px solid var(--c-line-2)}
table{width:100%;border-collapse:collapse;font-size:13px}
thead th{
  background:var(--c-surface);
  color:var(--c-muted);font-weight:700;text-align:left;
  padding:10px 14px;font-size:11px;text-transform:uppercase;letter-spacing:.6px;
  border-bottom:1px solid var(--c-line-2);
  position:sticky;top:0;
}
tbody td{padding:10px 14px;border-bottom:1px solid var(--c-line);vertical-align:middle}
tbody tr:last-child td{border-bottom:0}
tbody tr:hover{background:rgba(91,140,255,.03)}
tbody tr{transition:background .1s}

/* ===== TAGS / BADGES ===== */
.tag{
  display:inline-flex;align-items:center;gap:4px;
  padding:3px 9px;font-size:11px;font-weight:700;
  border-radius:999px;background:rgba(255,255,255,.05);
  color:var(--c-ink-2);border:1px solid var(--c-line-2);letter-spacing:.2px;
}
.tag.ok{background:rgba(52,211,153,.10);color:#4ade80;border-color:rgba(52,211,153,.25)}
.tag.warn{background:rgba(251,191,36,.10);color:var(--c-amber);border-color:rgba(251,191,36,.25)}
.tag.err{background:rgba(248,113,113,.10);color:#fca5a5;border-color:rgba(248,113,113,.25)}
.tag.gold{background:rgba(230,192,104,.10);color:var(--c-gold);border-color:rgba(230,192,104,.25)}
.tag.info{background:rgba(91,140,255,.10);color:#9fb6ff;border-color:rgba(91,140,255,.25)}

/* ===== FLASH ===== */
.flash{
  padding:12px 16px;border-radius:var(--c-radius-sm);margin-bottom:14px;
  font-weight:600;font-size:13.5px;border:1px solid transparent;
  display:flex;align-items:center;gap:8px;
}
.flash.ok{background:rgba(52,211,153,.08);color:#86efac;border-color:rgba(52,211,153,.25)}
.flash.error{background:rgba(248,113,113,.08);color:#fca5a5;border-color:rgba(248,113,113,.25)}
.flash.warn{background:rgba(251,191,36,.08);color:#fde68a;border-color:rgba(251,191,36,.25)}

/* ===== UTILITY ===== */
.muted{color:var(--c-muted);font-size:13px}
.kbd{
  font-family:ui-monospace,'SF Mono',Menlo,monospace;
  background:var(--c-surface);padding:2px 8px;border-radius:6px;
  border:1px solid var(--c-line-2);font-size:12px;color:var(--c-ink-2);
}
.row{display:flex;gap:14px;flex-wrap:wrap}
.row > *{flex:1 1 220px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
.metric{font-size:28px;font-weight:900;margin:6px 0;letter-spacing:-.5px}
.metric.gold{color:var(--c-gold)}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
.copy{cursor:pointer;transition:opacity .15s}
.copy:hover{opacity:.7}
.divider{height:1px;background:var(--c-line);margin:16px 0}

.empty-state{padding:40px 20px;text-align:center;color:var(--c-muted)}
.empty-state .icon{font-size:36px;margin-bottom:10px;opacity:.4}
.empty-state p{margin:4px 0;font-size:13px;max-width:300px;margin-left:auto;margin-right:auto}

.filter-row{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:14px}
.pill{
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 14px;border-radius:999px;font-size:12.5px;font-weight:600;
  background:var(--c-surface);color:var(--c-ink-2);border:1px solid var(--c-line-2);
  cursor:pointer;text-decoration:none;transition:all .15s;
}
.pill:hover{border-color:var(--c-gold);color:var(--c-ink)}
.pill.active{background:rgba(230,192,104,.10);color:var(--c-gold);border-color:rgba(230,192,104,.35)}
.pill .count{background:rgba(0,0,0,.30);padding:1px 7px;border-radius:999px;font-size:10px}

/* ===== NOTIFICATIONS ===== */
.notif-bell{position:relative;cursor:pointer;color:var(--c-ink-2);transition:color .15s}
.notif-bell:hover{color:var(--c-gold)}
.notif-count{
  position:absolute;top:-5px;right:-7px;min-width:16px;height:16px;line-height:16px;
  font-size:9px;font-weight:800;text-align:center;border-radius:999px;
  background:var(--c-red);color:#fff;padding:0 4px;
}
.notif-dropdown{
  display:none;position:fixed;top:56px;right:16px;width:340px;max-height:420px;
  background:var(--c-card);border:1px solid var(--c-line-2);border-radius:var(--c-radius);
  box-shadow:0 20px 60px rgba(0,0,0,.60);z-index:200;overflow:hidden;
}
.notif-dropdown.open{display:block}
.notif-header{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--c-line);font-size:14px;font-weight:700}
.notif-header a{font-size:12px;color:var(--c-blue)}
.notif-list{max-height:360px;overflow-y:auto}
.notif-item{padding:12px 16px;border-bottom:1px solid var(--c-line);cursor:pointer;transition:background .1s}
.notif-item:hover{background:rgba(91,140,255,.04)}
.notif-item.unread{border-left:3px solid var(--c-gold)}
.notif-item .ni-title{font-size:13px;font-weight:600;color:var(--c-ink);margin-bottom:3px}
.notif-item .ni-msg{font-size:12px;color:var(--c-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.notif-item .ni-time{font-size:11px;color:var(--c-muted);margin-top:4px}

/* ===== HAMBURGER / DRAWER ===== */
.hdr .hamburger{
  display:none;width:36px;height:36px;border-radius:8px;
  border:1px solid var(--c-line-2);background:transparent;
  cursor:pointer;position:relative;flex-shrink:0;
  align-items:center;justify-content:center;
}
.hdr .hamburger span,
.hdr .hamburger span::before,
.hdr .hamburger span::after{
  display:block;width:18px;height:2px;background:var(--c-ink-2);
  border-radius:2px;transition:all .25s;position:absolute;left:50%;transform:translateX(-50%);
}
.hdr .hamburger span{top:50%;margin-top:-1px}
.hdr .hamburger span::before{content:'';top:-6px;left:0;width:18px;position:absolute}
.hdr .hamburger span::after{content:'';top:6px;left:0;width:18px;position:absolute}
.nav-overlay{
  display:none;position:fixed;inset:0;z-index:90;
  background:rgba(0,0,0,.55);backdrop-filter:blur(3px);
  opacity:0;transition:opacity .2s;
}
.nav-overlay.open{display:block;opacity:1}

/* ===== MOBILE CARDS ===== */
.m-cards{display:none}
.m-card{
  background:linear-gradient(180deg,var(--c-card-2),var(--c-card));
  border:1px solid var(--c-line-2);border-radius:var(--c-radius-sm);
  padding:14px;margin-bottom:10px;
}
.m-card .m-row{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px}
.m-card .m-row:last-child{margin-bottom:0}
.m-card .m-label{font-size:11px;color:var(--c-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.m-card .m-val{font-size:13px;color:var(--c-ink);font-weight:500;text-align:right}
.m-card .m-head{font-size:14px;font-weight:700;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;gap:8px;min-width:0}
.m-card .m-head > :first-child{min-width:0;overflow:hidden;text-overflow:ellipsis}
.m-card .m-val{min-width:0;overflow-wrap:anywhere}
.m-card .m-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px solid var(--c-line)}
.m-card .m-actions .btn{flex:1;min-height:40px;justify-content:center}

/* ===== RESPONSIVE ===== */
@media(max-width:768px){
  .m-cards{display:block}
  .m-cards ~ .table-wrap{display:none}
  .has-mobile-cards .table-wrap{display:none}
  .hdr{padding:0 12px}
  .hdr-inner{height:54px;gap:10px}
  .hdr .brand{font-size:14px}
  .hdr .brand-mark{width:26px;height:26px;font-size:11px}
  .hdr .hamburger{display:flex}
  .hdr nav{
    position:fixed;top:0;left:0;width:280px;height:100vh;z-index:95;
    background:var(--c-card);border-right:1px solid var(--c-line-2);
    flex-direction:column;gap:0;padding:20px 0;
    transform:translateX(-100%);transition:transform .25s ease;
    overflow-y:auto;
  }
  .hdr nav.open{transform:translateX(0)}
  .hdr nav a{
    padding:14px 20px;font-size:14px;border-radius:0;
    border-bottom:1px solid var(--c-line);
    min-height:44px;display:flex;align-items:center;
  }
  .hdr nav a:last-child{border-bottom:0}
  .hdr nav a.active{background:rgba(230,192,104,.08);border-left:3px solid var(--c-gold)}
  .hdr .hdr-right{gap:8px}
  .hdr .balance{font-size:11px;padding:3px 8px}
  .hdr .avatar{width:30px;height:30px;font-size:11px}
  .hdr .logout{padding:6px 10px;font-size:11px;min-height:32px}
  main{padding:16px 12px 32px}
  .page-head h1{font-size:19px}
  .card{padding:16px 14px;border-radius:12px}
  .card h2{font-size:15px}
  .row{flex-direction:column;gap:10px}
  .row > *{flex:1 1 auto}
  .grid{grid-template-columns:1fr}
  .btn{padding:12px 16px;font-size:13px;min-height:44px}
  .btn.sm{min-height:36px;padding:8px 12px}
  input[type=checkbox]{width:22px;height:22px}
  input,select,textarea{font-size:16px;padding:12px 12px;min-height:44px}
  .actions{gap:8px}
  .actions .btn{flex:1}
  .notif-dropdown{width:calc(100vw - 24px);right:12px}
  .filter-row{gap:6px}
  .pill{padding:8px 14px;font-size:12.5px;min-height:40px}
}
@media(max-width:480px){
  .hdr nav{width:260px}
  .card{padding:14px 12px}
  .btn{width:100%;justify-content:center}
  .btn.sm{width:auto}
  .metric{font-size:24px}
  .pill{padding:7px 12px;font-size:12px}
}
</style>
</head>
<body>
<div class="nav-overlay" id="navOverlay"></div>
<header class="hdr">
  <div class="hdr-inner">
    <button class="hamburger" id="menuBtn" aria-label="Menu"><span></span></button>
    <a href="/ctv/dashboard.php" class="brand">
      <span class="brand-mark">JP</span>
      <span class="brand-name">jp-esim <em>Đối tác</em></span>
    </a>
    <nav id="mainNav">
      <?php if ($user): ?>
        <a href="/ctv/dashboard.php"<?= ctv_nav_active('/ctv/dashboard.php') ?>>Tổng quan</a>
        <a href="/ctv/create-esim.php"<?= ctv_nav_active('/ctv/create-esim.php') ?>>Tạo eSIM</a>
        <a href="/ctv/orders.php"<?= ctv_nav_active('/ctv/orders.php') ?>>Đơn hàng</a>
        <a href="/ctv/esims.php"<?= ctv_nav_active('/ctv/esims.php') ?>>eSIM</a>
        <a href="/ctv/topup-esim.php"<?= ctv_nav_active('/ctv/topup-esim.php') ?>>Nạp data</a>
        <a href="/ctv/topup-orders.php"<?= ctv_nav_active('/ctv/topup-orders.php') ?>>LS nạp data</a>
        <a href="/ctv/pricing.php"<?= ctv_nav_active('/ctv/pricing.php') ?>>Bảng giá</a>
        <a href="/ctv/topup-request.php"<?= ctv_nav_active('/ctv/topup-request.php') ?>>Nạp ví</a>
        <a href="/ctv/api-keys.php"<?= ctv_nav_active('/ctv/api-keys.php') ?>>API</a>
        <a href="/ctv/activity.php"<?= ctv_nav_active('/ctv/activity.php') ?>>Hoạt động</a>
        <a href="/ctv/security.php"<?= ctv_nav_active('/ctv/security.php') ?>>Bảo mật</a>
        <a href="/ctv/export.php"<?= ctv_nav_active('/ctv/export.php') ?>>Xuất CSV</a>
      <?php else: ?>
        <a href="/auth?role=partner">Đăng nhập</a>
        <a href="/ctv/register.php"<?= ctv_nav_active('/ctv/register.php') ?>>Đăng ký</a>
      <?php endif; ?>
    </nav>
    <div class="hdr-right">
      <?php if ($user): ?>
        <?php if (isset($user['balance'])): ?>
          <span class="balance"><?= htmlspecialchars(format_vnd((int)$user['balance'])) ?></span>
        <?php endif; ?>
        <span class="notif-bell" id="notifBell" title="Thông báo">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="notif-count" id="notifCount" style="display:none">0</span>
        </span>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-header"><b>Thông báo</b><a href="#" id="notifMarkAll">Đọc tất cả</a></div>
          <div class="notif-list" id="notifList"><p class="muted" style="padding:14px;text-align:center">Đang tải...</p></div>
        </div>
        <span class="avatar"><?= strtoupper(mb_substr((string)$user['email'], 0, 1)) ?></span>
        <a class="logout" href="/ctv/logout.php">Thoát</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<main>
<div class="page-head"><h1><?= htmlspecialchars($title) ?></h1></div>
<?php }
}
if (!function_exists('ctv_layout_footer')) {
    function ctv_layout_footer(): void {
        echo '</main>';
        ?>
<script>
(function(){
  var btn=document.getElementById('menuBtn'),nav=document.getElementById('mainNav'),ov=document.getElementById('navOverlay');
  if(btn&&nav){
    btn.addEventListener('click',function(){nav.classList.toggle('open');ov.classList.toggle('open');});
    ov.addEventListener('click',function(){nav.classList.remove('open');ov.classList.remove('open');});
    nav.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){nav.classList.remove('open');ov.classList.remove('open');});});
  }
})();
(function(){
  var bell=document.getElementById('notifBell'),dd=document.getElementById('notifDropdown'),
      cnt=document.getElementById('notifCount'),list=document.getElementById('notifList'),
      markAll=document.getElementById('notifMarkAll');
  if(!bell)return;
  function headers(){return {'Accept':'application/json','Content-Type':'application/json'};}
  function timeAgo(d){
    var s=Math.floor((Date.now()-new Date(d).getTime())/1000);
    if(s<60)return 'vừa xong';if(s<3600)return Math.floor(s/60)+'p trước';
    if(s<86400)return Math.floor(s/3600)+'h trước';return Math.floor(s/86400)+'d trước';
  }
  function renderList(items){
    if(!items||!items.length){list.innerHTML='<p class="muted" style="padding:20px;text-align:center">Không có thông báo</p>';return;}
    list.innerHTML=items.map(function(n){
      return '<div class="notif-item'+(n.is_read==0?' unread':'')+'" data-id="'+n.id+'">'
        +'<div class="ni-title">'+esc(n.title)+'</div>'
        +(n.message?'<div class="ni-msg">'+esc(n.message)+'</div>':'')
        +'<div class="ni-time">'+timeAgo(n.created_at)+'</div></div>';
    }).join('');
    list.querySelectorAll('.notif-item.unread').forEach(function(el){
      el.addEventListener('click',function(){
        var id=el.getAttribute('data-id');
        fetch('/ctv/notifications-api.php?action=read',{method:'POST',headers:headers(),body:JSON.stringify({id:parseInt(id)})}).then(function(){el.classList.remove('unread');loadCount();});
      });
    });
  }
  function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
  function loadCount(){
    fetch('/ctv/notifications-api.php?action=list&limit=1').then(function(r){return r.json()}).then(function(j){
      if(j.ok){var u=j.data.unread||0;cnt.textContent=u;cnt.style.display=u>0?'':'none';}
    }).catch(function(){});
  }
  function loadList(){
    list.innerHTML='<p class="muted" style="padding:14px;text-align:center">Đang tải...</p>';
    fetch('/ctv/notifications-api.php?action=list&limit=20').then(function(r){return r.json()}).then(function(j){
      if(j.ok)renderList(j.data.notifications);else list.innerHTML='<p class="muted" style="padding:14px;text-align:center">Lỗi</p>';
    }).catch(function(){list.innerHTML='<p class="muted" style="padding:14px;text-align:center">Lỗi kết nối</p>';});
  }
  bell.addEventListener('click',function(e){
    e.stopPropagation();
    var open=dd.classList.toggle('open');
    if(open)loadList();
  });
  document.addEventListener('click',function(e){if(!dd.contains(e.target)&&e.target!==bell)dd.classList.remove('open');});
  markAll.addEventListener('click',function(e){
    e.preventDefault();
    fetch('/ctv/notifications-api.php?action=read',{method:'POST',headers:headers(),body:JSON.stringify({})}).then(function(){
      loadCount();list.querySelectorAll('.notif-item.unread').forEach(function(el){el.classList.remove('unread');});
    });
  });
  loadCount();
  setInterval(loadCount,60000);
})();
document.addEventListener('click',function(e){
  var t=e.target.closest('[data-copy]');
  if(!t)return;
  var v=t.getAttribute('data-copy');
  if(v&&navigator.clipboard){navigator.clipboard.writeText(v);var o=t.style.opacity;t.style.opacity='.5';setTimeout(function(){t.style.opacity=o;},300);}
});
</script>
        <?php
        echo '</body></html>';
    }
}
