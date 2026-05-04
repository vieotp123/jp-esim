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
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow,noarchive">
<title><?= htmlspecialchars($title) ?> · CTV jp-esim.vip</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{
  --c-bg:#070a13; --c-card:#11182b; --c-card-2:#172041;
  --c-line:#1f2a44; --c-line-2:#2a3760;
  --c-ink:#e8edf7; --c-ink-2:#aebbd6; --c-muted:#7c8aac;
  --c-gold:#e6c068; --c-gold-2:#f3d488; --c-gold-deep:#a98538;
  --c-blue:#5b8cff; --c-blue-2:#3a6dff;
  --c-green:#34d399; --c-red:#f87171; --c-amber:#fbbf24;
  --c-shadow:0 16px 40px rgba(0,0,0,.40);
  color-scheme:dark;
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
  font-family:'Be Vietnam Pro',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
  background:radial-gradient(1100px 540px at 12% -8%, rgba(91,140,255,.10), transparent 58%),
             radial-gradient(900px 480px at 108% 0%, rgba(230,192,104,.07), transparent 55%),
             var(--c-bg);
  color:var(--c-ink); min-height:100vh;
  -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility;
}
a{color:#9fb6ff;text-decoration:none}
a:hover{color:#cfe0ff}

header.ctv-h{
  background:linear-gradient(180deg, rgba(23,32,65,.85), rgba(13,19,34,.92));
  border-bottom:1px solid var(--c-line-2);
  padding:14px 22px; display:flex; gap:18px; align-items:center; flex-wrap:wrap;
  backdrop-filter:saturate(140%) blur(8px);
  position:sticky; top:0; z-index:50;
}
header.ctv-h .brand{display:flex; gap:10px; align-items:center; font-weight:800; font-size:16px}
header.ctv-h .brand-mark{
  width:28px; height:28px; border-radius:8px;
  background:linear-gradient(135deg, var(--c-gold-2), var(--c-gold-deep));
  box-shadow:0 4px 12px rgba(230,192,104,.35), inset 0 0 0 1px rgba(255,255,255,.18);
  display:grid; place-items:center; color:#1a1206; font-weight:900; font-size:13px;
}
header.ctv-h .brand-name em{font-style:normal;color:var(--c-gold);font-weight:700}
header.ctv-h nav{display:flex; gap:4px; flex-wrap:wrap}
header.ctv-h nav a{
  color:var(--c-ink-2); padding:8px 12px; border-radius:8px; font-weight:600; font-size:13.5px;
  transition:background .15s, color .15s;
}
header.ctv-h nav a:hover{background:rgba(91,140,255,.10); color:#fff}
header.ctv-h nav a.active{
  background:linear-gradient(180deg, rgba(230,192,104,.16), rgba(230,192,104,.06));
  color:var(--c-gold); border:1px solid rgba(230,192,104,.30);
}
header.ctv-h .right{margin-left:auto; font-size:13px; color:var(--c-ink-2); display:flex; gap:12px; align-items:center}
header.ctv-h .vip-tag{
  font-size:10.5px; font-weight:800; letter-spacing:.5px; padding:3px 9px; border-radius:999px;
  background:linear-gradient(180deg, var(--c-gold-2), var(--c-gold-deep)); color:#241804;
  box-shadow:0 4px 14px rgba(230,192,104,.35);
}

main{max-width:1200px; margin:22px auto; padding:0 16px}
.page-title{display:flex;align-items:baseline;gap:12px;margin:4px 0 14px}
.page-title h1{margin:0;font-size:22px;font-weight:800}

.card{
  background:linear-gradient(180deg, var(--c-card-2), var(--c-card));
  border:1px solid var(--c-line); border-radius:16px;
  padding:18px 20px; margin-bottom:16px;
  box-shadow:var(--c-shadow); overflow:auto;
}
.card h2{margin:0 0 12px; font-size:17px; font-weight:700}
.card h3{margin:0 0 10px; font-size:14px; color:var(--c-ink-2); font-weight:700; text-transform:uppercase; letter-spacing:.6px}

label{display:block;font-size:13px;color:var(--c-ink-2);margin-bottom:5px}
input[type=text],input[type=email],input[type=password],input[type=number],select,textarea{
  width:100%; padding:10px 12px; font-size:14px; font-family:inherit;
  background:#0a1020; color:var(--c-ink);
  border:1px solid var(--c-line-2); border-radius:9px;
  transition:border-color .15s, box-shadow .15s;
}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--c-gold);box-shadow:0 0 0 3px rgba(230,192,104,.18)}
.field{margin-bottom:12px}

button.btn,.btn{
  display:inline-flex; align-items:center; gap:6px;
  background:linear-gradient(180deg, var(--c-blue), var(--c-blue-2));
  color:#fff; border:0; padding:10px 16px; border-radius:9px;
  font-weight:700; font-size:14px; cursor:pointer; text-decoration:none;
  box-shadow:0 6px 16px rgba(58,109,255,.30);
  transition:transform .08s, filter .15s;
}
.btn:hover{filter:brightness(1.08)}
.btn:active{transform:translateY(1px)}
.btn:disabled{opacity:.55;cursor:not-allowed}
.btn.secondary{background:linear-gradient(180deg, #2a3760, #1f2a44); box-shadow:none; border:1px solid var(--c-line-2); color:var(--c-ink-2)}
.btn.secondary:hover{color:#fff;border-color:var(--c-gold);filter:none}
.btn.danger{background:linear-gradient(180deg, #ef5b5b, #c33b3b); box-shadow:0 6px 16px rgba(220,60,60,.30)}
.btn.gold{background:linear-gradient(180deg, var(--c-gold-2), var(--c-gold-deep)); color:#241804; box-shadow:0 6px 16px rgba(230,192,104,.35)}

table{width:100%;border-collapse:separate;border-spacing:0;font-size:13.5px}
thead th{
  background:linear-gradient(180deg, #131c34, #0d1427);
  color:var(--c-ink-2); font-weight:700; text-align:left;
  padding:11px 12px; font-size:11.5px; text-transform:uppercase; letter-spacing:.6px;
  border-bottom:1px solid var(--c-line-2);
}
tbody td{padding:11px 12px;border-bottom:1px solid var(--c-line);vertical-align:top;color:var(--c-ink)}
tbody tr:hover{background:rgba(91,140,255,.05)}

.flash{padding:11px 14px;border-radius:10px;margin-bottom:12px;font-weight:600;font-size:14px;border:1px solid transparent}
.flash.ok{background:rgba(52,211,153,.10);color:#86efac;border-color:rgba(52,211,153,.32)}
.flash.error{background:rgba(248,113,113,.10);color:#fca5a5;border-color:rgba(248,113,113,.30)}
.flash.warn{background:rgba(251,191,36,.10);color:#fde68a;border-color:rgba(251,191,36,.30)}

.muted{color:var(--c-muted);font-size:13px}
.kbd{font-family:ui-monospace,Menlo,monospace;background:#0a1020;padding:2px 7px;border-radius:5px;border:1px solid var(--c-line-2);font-size:12px;color:var(--c-ink-2)}
.row{display:flex;gap:12px;flex-wrap:wrap}
.row > *{flex:1 1 220px}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.metric{font-size:28px;font-weight:900;margin:8px 0;letter-spacing:.2px}
.metric.gold{color:var(--c-gold)}
.actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}
.copy{cursor:pointer}

.tag{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;font-size:11.5px;font-weight:700;border-radius:999px;background:#1a2440;color:var(--c-ink-2);border:1px solid var(--c-line-2);letter-spacing:.3px}
.tag.ok{background:rgba(52,211,153,.12);color:#4ade80;border-color:rgba(52,211,153,.30)}
.tag.warn{background:rgba(251,191,36,.13);color:var(--c-amber);border-color:rgba(251,191,36,.30)}
.tag.err{background:rgba(248,113,113,.13);color:#fca5a5;border-color:rgba(248,113,113,.30)}
.tag.gold{background:rgba(230,192,104,.14);color:var(--c-gold);border-color:rgba(230,192,104,.32)}

.empty-state{padding:32px 20px;text-align:center;color:var(--c-muted)}
.empty-state .icon{font-size:32px;margin-bottom:8px;opacity:.5}
.empty-state p{margin:6px 0;font-size:13px}

@media (max-width:760px){
  header.ctv-h{padding:10px 14px}
  header.ctv-h nav{gap:2px}
  header.ctv-h nav a{padding:6px 8px;font-size:12px;min-height:36px;display:inline-flex;align-items:center}
  header.ctv-h .right{margin-left:0;width:100%;justify-content:flex-end}
  main{padding:0 10px}
  .card{padding:14px 12px;border-radius:12px;overflow-x:auto}
  .card h2{font-size:16px}
  .row{flex-direction:column;gap:8px}
  .row > *{flex:1 1 auto}
  .grid{grid-template-columns:1fr}
  table{font-size:12px;min-width:560px}
  thead th{padding:8px 8px;font-size:10.5px}
  tbody td{padding:8px 8px}
  .metric{font-size:22px}
  .page-title h1{font-size:18px}
  .actions{gap:6px}
  .btn{padding:10px 14px;font-size:13px;min-height:40px}
  input,select,textarea{font-size:16px;padding:10px 12px}
  .field{margin-bottom:10px}
  .field label{font-size:13px;margin-bottom:4px}
}
@media (max-width:480px){
  header.ctv-h nav a{padding:5px 7px;font-size:11.5px}
  .card{padding:12px 10px}
  table{min-width:480px}
  .btn{padding:10px 12px;font-size:12.5px;width:100%}
}

.notif-bell{position:relative;cursor:pointer;color:var(--c-ink-2);transition:color .15s}
.notif-bell:hover{color:var(--c-gold)}
.notif-count{
  position:absolute;top:-6px;right:-8px;min-width:17px;height:17px;line-height:17px;
  font-size:10px;font-weight:800;text-align:center;border-radius:999px;
  background:var(--c-red);color:#fff;padding:0 4px;
}
.notif-dropdown{
  display:none;position:absolute;top:52px;right:16px;width:340px;max-height:420px;
  background:var(--c-card);border:1px solid var(--c-line-2);border-radius:14px;
  box-shadow:0 20px 50px rgba(0,0,0,.55);z-index:100;overflow:hidden;
}
.notif-dropdown.open{display:block}
.notif-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid var(--c-line);font-size:14px}
.notif-header a{font-size:12px;color:var(--c-blue)}
.notif-list{max-height:360px;overflow-y:auto}
.notif-item{padding:10px 16px;border-bottom:1px solid var(--c-line);cursor:pointer;transition:background .1s}
.notif-item:hover{background:rgba(91,140,255,.06)}
.notif-item.unread{border-left:3px solid var(--c-gold)}
.notif-item .ni-title{font-size:13px;font-weight:600;color:var(--c-ink);margin-bottom:2px}
.notif-item .ni-msg{font-size:12px;color:var(--c-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.notif-item .ni-time{font-size:11px;color:var(--c-muted);margin-top:3px}
</style>
</head>
<body>
<header class="ctv-h">
  <div class="brand">
    <span class="brand-mark">JP</span>
    <span class="brand-name">jp-esim <em>CTV</em></span>
  </div>
  <nav>
    <?php if ($user): ?>
      <a href="/ctv/dashboard.php"<?= ctv_nav_active('/ctv/dashboard.php') ?>>Tổng quan</a>
      <a href="/ctv/pricing.php"<?= ctv_nav_active('/ctv/pricing.php') ?>>Bảng giá</a>
      <a href="/ctv/orders.php"<?= ctv_nav_active('/ctv/orders.php') ?>>Đơn eSIM</a>
      <a href="/ctv/esims.php"<?= ctv_nav_active('/ctv/esims.php') ?>>eSIM</a>
      <a href="/ctv/create-esim.php"<?= ctv_nav_active('/ctv/create-esim.php') ?>>Tạo eSIM</a>
      <a href="/ctv/topup-esim.php"<?= ctv_nav_active('/ctv/topup-esim.php') ?>>Nạp data</a>
      <a href="/ctv/security.php"<?= ctv_nav_active('/ctv/security.php') ?>>Bảo mật</a>
      <a href="/ctv/api-keys.php"<?= ctv_nav_active('/ctv/api-keys.php') ?>>Khoá API</a>
      <a href="/ctv/export.php"<?= ctv_nav_active('/ctv/export.php') ?>>Xuất CSV</a>
    <?php else: ?>
      <a href="/ctv/login.php"<?= ctv_nav_active('/ctv/login.php') ?>>Đăng nhập</a>
      <a href="/ctv/register.php"<?= ctv_nav_active('/ctv/register.php') ?>>Đăng ký</a>
    <?php endif; ?>
  </nav>
  <span class="right">
    <span class="vip-tag">PARTNER</span>
    <?php if ($user): ?>
      <span class="notif-bell" id="notifBell" title="Thông báo">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="notif-count" id="notifCount" style="display:none">0</span>
      </span>
      <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header"><b>Thông báo</b><a href="#" id="notifMarkAll">Đọc tất cả</a></div>
        <div class="notif-list" id="notifList"><p class="muted" style="padding:12px">Đang tải...</p></div>
      </div>
      <span><?= htmlspecialchars((string)$user['email']) ?></span>
      <a class="btn secondary" href="/ctv/logout.php" style="padding:6px 12px;font-size:12.5px">Thoát</a>
    <?php endif; ?>
  </span>
</header>
<main>
<div class="page-title"><h1><?= htmlspecialchars($title) ?></h1></div>
<?php }
}
if (!function_exists('ctv_layout_footer')) {
    function ctv_layout_footer(): void {
        echo '</main>';
        ?>
<script>
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
    if(!items||!items.length){list.innerHTML='<p class="muted" style="padding:14px;text-align:center">Không có thông báo</p>';return;}
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
    list.innerHTML='<p class="muted" style="padding:12px">Đang tải...</p>';
    fetch('/ctv/notifications-api.php?action=list&limit=20').then(function(r){return r.json()}).then(function(j){
      if(j.ok)renderList(j.data.notifications);else list.innerHTML='<p class="muted" style="padding:12px">Lỗi</p>';
    }).catch(function(){list.innerHTML='<p class="muted" style="padding:12px">Lỗi kết nối</p>';});
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
</script>
        <?php
        echo '</body></html>';
    }
}
