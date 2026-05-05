<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';

// Custom 404: if user requested a path that's not the homepage and not handled
// elsewhere, show a branded 404 page (default nginx try_files routed every
// miss back to /index.php).
$reqPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$reqPath = $reqPath === '' ? '/' : $reqPath;
$normPath = rtrim($reqPath, '/') ?: '/';
if ($normPath !== '/' && $normPath !== '/index.php') {
    http_response_code(404);
    security_headers(true);
    $safePath = htmlspecialchars($normPath, ENT_QUOTES, 'UTF-8');
    ?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>404 — Không tìm thấy · jp-esim.vip</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;800;900&display=swap" rel="stylesheet">
<style>
:root{--bg:#0d0f15;--ink:#e9ecf3;--muted:#7a8094;--gold:#e6c068}
*{box-sizing:border-box}
body{margin:0;font-family:'Be Vietnam Pro',system-ui,sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.box{max-width:520px;text-align:center}
.code{font-size:120px;font-weight:900;line-height:1;background:linear-gradient(180deg,#f4d684,#b8902e);-webkit-background-clip:text;background-clip:text;color:transparent;letter-spacing:-3px}
h1{font-size:22px;font-weight:800;margin:6px 0 10px}
p{color:var(--muted);font-size:14px;line-height:1.6;margin:0 0 8px}
code.path{display:inline-block;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);padding:4px 10px;border-radius:6px;font-size:12px;color:var(--ink);margin:8px 0 18px;word-break:break-all}
.actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:16px}
.btn{display:inline-block;padding:11px 22px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px}
.btn.gold{background:linear-gradient(180deg,#f4d684,#b8902e);color:#241804}
.btn.outline{border:1px solid rgba(255,255,255,.18);color:var(--ink)}
</style>
</head>
<body>
<div class="box">
  <div class="code">404</div>
  <h1>Trang không tồn tại</h1>
  <p>Đường dẫn bạn truy cập không có trên jp-esim.vip.</p>
  <code class="path"><?= $safePath ?></code>
  <div class="actions">
    <a class="btn gold" href="/">Về trang chủ</a>
    <a class="btn outline" href="/tra-cuu.php">Tra cứu đơn</a>
    <a class="btn outline" href="/#support">Hỗ trợ</a>
  </div>
</div>
</body>
</html>
    <?php
    exit;
}

security_headers(true);
$siteKey = htmlspecialchars((string)app_config('RECAPTCHA_SITE', app_config('RC_SITE','')), ENT_QUOTES, 'UTF-8');
$assetVer = '20260504c';
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#050607">
  <meta name="description" content="JP eSIM Nhật Bản Docomo 4G/au 5G, nhận QR tự động sau thanh toán VietQR. Hỗ trợ nạp thêm data, phát WiFi và kích hoạt nhanh iOS/Android.">
  <meta name="keywords" content="eSIM Nhật Bản, eSIM Japan, Docomo eSIM, au 5G eSIM, jp-esim Nhật, nạp data eSIM">
  <meta name="robots" content="index,follow,max-image-preview:large">
  <link rel="canonical" href="https://jp-esim.vip/">
  <link rel="alternate" hreflang="vi-VN" href="https://jp-esim.vip/">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="jp-esim.vip">
  <meta property="og:title" content="eSIM Nhật Bản - Internet Nhật Bản">
  <meta property="og:description" content="JP eSIM Nhật Bản, thanh toán VietQR và nhận QR eSIM tự động. Hỗ trợ nạp data, phát WiFi, kích hoạt nhanh iOS/Android.">
  <meta property="og:url" content="https://jp-esim.vip/">
  <meta property="og:image" content="https://jp-esim.vip/assets/images/banner.webp">
  <meta name="twitter:card" content="summary_large_image">
  <title>eSIM Nhật Bản Docomo 4G / au 5G - Nhận QR tự động</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/app.css?v=<?= $assetVer ?>">
  <script>window.RECAPTCHA_SITE = "<?= $siteKey ?>";</script>
  <?php if ($siteKey): ?><script src="https://www.google.com/recaptcha/api.js?render=explicit" defer></script><?php endif; ?>
  <script src="/assets/app.js?v=<?= $assetVer ?>" defer></script>

<script type="application/ld+json">[{"@context":"https://schema.org","@type":"Organization","name":"jp-esim.vip","url":"https://jp-esim.vip/","description":"Dịch vụ eSIM Nhật Bản Docomo 4G và au 5G, nhận QR tự động, hỗ trợ nạp thêm data.","areaServed":"JP","contactPoint":{"@type":"ContactPoint","contactType":"customer service","availableLanguage":["vi","ja"]}},{"@context":"https://schema.org","@type":"Product","name":"eSIM Nhật Bản","description":"eSIM du lịch Nhật Bản mạng Docomo 4G và au 5G, giao QR tự động sau thanh toán VietQR, hỗ trợ phát WiFi và nạp thêm data.","brand":{"@type":"Brand","name":"jp-esim.vip"},"offers":{"@type":"AggregateOffer","priceCurrency":"VND","lowPrice":"40000","availability":"https://schema.org/InStock","offerCount":"8"}}]</script>
</head>
<body>
<div id="app" class="app-shell">
  <header class="ios-header">
    <div><div class="eyebrow">JP eSIM</div><h1>Internet Nhật Bản</h1></div>
    <button class="theme-toggle" id="themeToggle" type="button" aria-label="Đổi giao diện" data-theme-icon></button>
  </header>

  <main>
    <section class="view active" id="view-buy">
      <div class="hero-card">
        <div><span class="badge">Không cần SIM vật lý</span><h2>eSIM Nhật Bản — Kết nối ngay khi hạ cánh</h2><p>Chọn gói, thanh toán VietQR, nhận QR eSIM tự động trong 1 phút. Dùng mạng Docomo 4G hoặc au 5G tốc độ cao khắp Nhật Bản.</p></div>
      </div>
      <div class="trust-strip" aria-label="Ưu điểm nổi bật">
        <span class="ts-item ts-gold"><span class="ts-dot"></span>Dành riêng cho du lịch Nhật</span>
        <span class="ts-item"><span class="carrier-chip docomo">Docomo 4G</span></span>
        <span class="ts-item"><span class="carrier-chip au">au 5G</span></span>
        <span class="ts-item"><span class="ts-dot"></span>Giao QR tự động</span>
        <span class="ts-item"><span class="ts-dot"></span>Hỗ trợ 24/7</span>
      </div>
      <div class="ad-carousel" id="adCarousel" aria-label="Ưu điểm dịch vụ">
        <div class="ad-track">
          <div class="ad-slide active"><b>Không cần SIM vật lý</b><span>Quét QR là dùng được, không cần tháo lắp SIM.</span></div>
          <div class="ad-slide"><b>Hỗ trợ phát WiFi</b><span>Cho phép phát WiFi để chia sẻ mạng cho laptop, iPad hoặc thiết bị khác.</span></div>
          <div class="ad-slide"><b>Data chưa dùng được cộng dồn</b><span>Nạp thêm data và tiếp tục dùng trên cùng eSIM, tiện cho chuyến đi dài.</span></div>
          <div class="ad-slide"><b>Nhận QR tự động</b><span>Thanh toán xong hệ thống gửi QR ngay trên web và qua email.</span></div>
        </div>
        <div class="ad-dots" id="adDots"></div>
      </div>
      <div class="segmented" id="telecomTabs"></div>
      <div class="plan-grid" id="plans"></div>
      <div class="form-card">
        <label>Email nhận QR</label><input id="orderEmail" type="email" placeholder="email@cuaban.com" autocomplete="email">
                <button class="primary" id="buyBtn">Mua ngay</button>
      </div>
      <div class="info-card trust-card">
        <div class="trust-head">
          <span class="trust-icon" data-i="checkShield"></span>
          <div>
            <b>Thanh toán an toàn</b>
            <span>Nội dung chuyển khoản là mã đơn riêng. QR eSIM chỉ hiển thị 24h trên web và luôn được gửi về email.</span>
          </div>
        </div>
        <div class="activate-steps" aria-label="Các bước kích hoạt eSIM">
          <div class="step-card"><span data-i="scan"></span><b>1. Nhận QR</b><small>Thanh toán xong, hệ thống tự tạo QR eSIM.</small></div>
          <div class="step-card"><span data-i="phoneBolt"></span><b>2. Quét QR</b><small>Thêm eSIM bằng QR hoặc link kích hoạt nhanh.</small></div>
          <div class="step-card"><span data-i="wifiFill"></span><b>3. Kết nối</b><small>Bật dữ liệu di động, dùng Internet và phát WiFi.</small></div>
        </div>
      </div>
      <div class="info-card faq-section">
        <h2 style="margin:0 0 14px;font-size:20px;letter-spacing:-.4px">Câu hỏi thường gặp</h2>
        <details class="faq-item"><summary>eSIM là gì? Điện thoại tôi có dùng được không?</summary><p>eSIM là SIM điện tử được tích hợp sẵn trong điện thoại, không cần lắp SIM vật lý. Hầu hết iPhone từ XS trở lên, Samsung Galaxy S20+, Google Pixel 3+ đều hỗ trợ eSIM.</p></details>
        <details class="faq-item"><summary>Sau khi thanh toán bao lâu nhận được eSIM?</summary><p>QR eSIM được tạo tự động ngay sau khi hệ thống xác nhận thanh toán, thường chỉ trong vòng 1-2 phút. QR hiển thị trên web và gửi qua email.</p></details>
        <details class="faq-item"><summary>Tôi có thể phát WiFi từ eSIM được không?</summary><p>Có. Tất cả gói eSIM Nhật Bản của chúng tôi đều hỗ trợ phát WiFi (hotspot), bạn có thể chia sẻ mạng cho laptop, iPad hoặc thiết bị khác.</p></details>
        <details class="faq-item"><summary>Data chưa dùng hết có mất không?</summary><p>Không. Data chưa sử dụng được cộng dồn khi bạn nạp thêm. Bạn có thể nạp data ngay trên trang web mà không cần mua eSIM mới.</p></details>
        <details class="faq-item"><summary>Tôi cần kích hoạt eSIM khi nào?</summary><p>Bạn nên kích hoạt eSIM trước khi bay hoặc ngay khi đến sân bay Nhật Bản. Chỉ cần quét QR, bật dữ liệu di động là dùng được ngay.</p></details>
        <details class="faq-item"><summary>Có hỗ trợ hoàn tiền không?</summary><p>Có. Nếu eSIM chưa được kích hoạt, chúng tôi hỗ trợ hoàn tiền 100%. Liên hệ bộ phận hỗ trợ qua Messenger hoặc email để được giải quyết nhanh chóng.</p></details>
      </div>
    </section>

    <section class="view" id="view-topup">
      <div class="hero-card small"><h2>Nạp thêm data</h2><p>Nhập ICCID hoặc mã đơn để kiểm tra và nạp thêm lưu lượng.</p></div>
      <div class="form-card"><label>ICCID hoặc mã đơn</label><div class="input-row"><input id="topupLookup" placeholder="8985... hoặc Nxxxxxxx"><button id="lookupTopupBtn">Kiểm tra</button></div></div>
      <div id="topupInfo"></div>
      <div class="plan-grid" id="topupPlans"></div>
      <div class="form-card hidden" id="topupForm"><label>Email nhận thông báo</label><input id="topupEmail" type="email" placeholder="email@cuaban.com"><button class="primary" id="createTopupBtn">Tạo đơn nạp data</button></div>
    </section>

    <section class="view" id="view-support">
      <div class="hero-card small"><h2>Hỗ trợ</h2><p>Chat với trợ lý để tư vấn gói, kiểm tra đơn hoặc nạp data.</p></div>
      <div class="support-links">
        <a href="https://m.me/jp-esim" target="_blank" rel="noopener">Messenger</a>
        <a href="https://fb.com/jp-esim" target="_blank" rel="noopener">Facebook Page</a>
      </div>
      <div class="chat-box" id="chatBox"></div>
      <div class="chat-input-wrap"><div class="chat-input"><input id="supportInput" placeholder="Nhập tin nhắn..." autocomplete="off"><button id="sendSupportBtn" aria-label="Gửi tin nhắn">Gửi</button></div></div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-brand"><span class="brand-mark">JP</span> jp-esim.vip</div>
      <div class="footer-links">
        <a href="/tra-cuu.php">Tra cứu đơn hàng</a>
        <a href="https://m.me/jp-esim" target="_blank" rel="noopener">Messenger</a>
        <a href="https://fb.com/jp-esim" target="_blank" rel="noopener">Facebook</a>
      </div>
      <p class="footer-copy">&copy; <?= date('Y') ?> jp-esim.vip — Dịch vụ eSIM Nhật Bản</p>
    </div>
  </footer>

  <nav class="tabbar">
    <button class="tab active" data-view="buy"><span class="tab-ico" data-i="sim"></span><span>JP eSIM</span></button>
    <button class="tab" data-view="topup"><span class="tab-ico" data-i="wifi"></span><span>Nạp data</span></button>
    <button class="tab" data-view="support"><span class="tab-ico" data-i="chat"></span><span>Hỗ trợ</span></button>
  </nav>
</div>

<div id="sheet" class="sheet" aria-hidden="true"><div class="sheet-backdrop"></div><div class="sheet-panel"><div class="grabber"></div><div id="sheetContent"></div></div></div>
<div id="toast" class="toast"></div>
</body>
</html>
