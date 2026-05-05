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
<div class="card" style="max-width:720px">
  <h2>Tạo API key</h2>
  <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($newToken): ?>
    <div class="flash warn">
      Token mới (chỉ hiển thị một lần):<br>
      <span class="kbd" id="new-token" style="display:inline-block;margin-top:6px;word-break:break-all"><?= htmlspecialchars($newToken['token']) ?></span>
      <button class="btn sm secondary" type="button" style="margin-left:8px" onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('new-token').textContent)">Sao chép</button>
    </div>
  <?php endif; ?>
  <form method="post" class="row">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="generate">
    <div class="field"><label>Tên key</label><input type="text" name="name" placeholder="Ví dụ: integration-sandbox" required></div>
    <div class="field"><label>&nbsp;</label><button class="btn gold" type="submit">Tạo API key mới</button></div>
  </form>
</div>

<div class="card">
  <h2>Danh sách key</h2>
  <?php if (!$keys): ?>
    <div class="empty-state"><div class="icon">🔑</div><p>Chưa có API key nào.</p><p>Tạo key để tích hợp API vào hệ thống của bạn.</p></div>
  <?php else: ?>
  <div class="m-cards">
    <?php foreach ($keys as $k): ?>
    <div class="m-card">
      <div class="m-head">
        <span><?= htmlspecialchars((string)$k['name']) ?></span>
        <?php if ((int)$k['status'] === 1): ?><span class="tag ok">Hoạt động</span><?php else: ?><span class="tag err">Đã thu hồi</span><?php endif; ?>
      </div>
      <div class="m-row"><span class="m-label">Prefix</span><span class="m-val"><span class="kbd">ctvK_<?= htmlspecialchars((string)$k['key_prefix']) ?>_***</span></span></div>
      <div class="m-row"><span class="m-label">Tạo lúc</span><span class="m-val muted"><?= htmlspecialchars((string)$k['created_at']) ?></span></div>
      <div class="m-row"><span class="m-label">Lần dùng cuối</span><span class="m-val muted"><?= htmlspecialchars((string)($k['last_used_at'] ?? 'Chưa dùng')) ?></span></div>
      <?php if ((int)$k['status'] === 1): ?>
      <div class="m-actions">
        <form method="post" onsubmit="return confirm('Thu hồi key <?= htmlspecialchars((string)$k['name']) ?>?')">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="revoke">
          <input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>">
          <button class="btn sm danger" type="submit">Thu hồi</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Tên</th><th>Prefix</th><th>Trạng thái</th><th>Tạo lúc</th><th>Lần dùng cuối</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($keys as $k): ?>
      <tr>
        <td><strong><?= htmlspecialchars((string)$k['name']) ?></strong></td>
        <td><span class="kbd">ctvK_<?= htmlspecialchars((string)$k['key_prefix']) ?>_***</span></td>
        <td>
          <?php if ((int)$k['status'] === 1): ?><span class="tag ok">Hoạt động</span><?php else: ?><span class="tag err">Đã thu hồi</span><?php endif; ?>
        </td>
        <td style="white-space:nowrap"><span class="muted"><?= htmlspecialchars((string)$k['created_at']) ?></span></td>
        <td style="white-space:nowrap"><span class="muted"><?= htmlspecialchars((string)($k['last_used_at'] ?? 'Chưa dùng')) ?></span></td>
        <td>
          <?php if ((int)$k['status'] === 1): ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Thu hồi key <?= htmlspecialchars((string)$k['name']) ?>?')">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="revoke">
            <input type="hidden" name="key_id" value="<?= (int)$k['id'] ?>">
            <button class="btn sm danger" type="submit">Thu hồi</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
  <div class="divider"></div>
  <p class="muted">Gửi header <span class="kbd">Authorization: Bearer ctvK_xxx_xxx</span> hoặc <span class="kbd">X-API-Key: ctvK_...</span> khi gọi <span class="kbd">/api/ctv/*</span>.</p>
</div>

<div class="card" style="max-width:720px">
  <h2>Tài liệu API</h2>
  <p class="muted" style="margin-bottom:14px">Base URL: <span class="kbd">https://jp-esim.vip/api/ctv/</span> — Tất cả response trả về JSON với <code>{"ok":true,"data":{...}}</code> hoặc <code>{"ok":false,"error":{...}}</code>.</p>

  <details open><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">Xác thực & Rate Limit</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <p>Gửi API key qua header:</p>
    <pre class="kbd" style="display:block;padding:10px;white-space:pre-wrap">Authorization: Bearer ctvK_xxx_xxxxxxxx
<span class="muted"># hoặc</span>
X-API-Key: ctvK_xxx_xxxxxxxx</pre>
    <p style="margin-top:8px">Rate limit: <strong>60 request/phút</strong> (mặc định). Response headers: <span class="kbd">X-RateLimit-Limit</span>, <span class="kbd">X-RateLimit-Remaining</span>, <span class="kbd">X-RateLimit-Reset</span>.</p>
  </div>
  </details>

  <details><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">GET /products.php — Danh sách gói</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <p><strong>Query:</strong> <span class="kbd">?type=esim</span> (hoặc <code>topup</code>), <span class="kbd">&telecom=JP</span> (tuỳ chọn)</p>
    <p><strong>Response:</strong> <code>data.plans[]</code> — mảng gói với <code>id, name, telecom, day, retailPrice, ctvPrice, discount</code></p>
  </div>
  </details>

  <details><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">POST /quote.php — Báo giá</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <p><strong>Body:</strong> <code>{"planId": 5, "quantity": 2}</code></p>
    <p><strong>Response:</strong> <code>data.pricing</code> (giá lẻ, chiết khấu, giá đối tác), <code>data.totalCharge</code></p>
  </div>
  </details>

  <details><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">POST /orders.php?action=create — Tạo đơn eSIM</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <p><strong>Body:</strong></p>
    <pre class="kbd" style="display:block;padding:10px;white-space:pre-wrap">{
  "planId": 5,
  "quantity": 1,
  "email": "khach@email.com",
  "clientRef": "REF-001",
  "notes": "Ghi chú tuỳ chọn"
}</pre>
    <p style="margin-top:8px"><strong>Response:</strong> <code>data.orderId</code>, <code>data.status</code>, <code>data.totalCharge</code></p>
    <p class="muted">Số dư ví sẽ bị trừ ngay. Nếu thất bại, tiền hoàn tự động.</p>
  </div>
  </details>

  <details><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">GET /orders.php?action=list — Danh sách đơn</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <p><strong>Query:</strong> <span class="kbd">?action=list&limit=50&offset=0&status=success</span></p>
    <p><strong>Response:</strong> <code>data.orders[]</code></p>
  </div>
  </details>

  <details><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">GET /orders.php?action=get&id=XXX — Chi tiết đơn</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <p><strong>Query:</strong> <span class="kbd">?action=get&id=ORDER_ID</span></p>
    <p><strong>Response:</strong> <code>data.order</code>, <code>data.esims[]</code> (ICCID, QR URL, activation links, status)</p>
  </div>
  </details>

  <details><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">GET /esims.php — Danh sách eSIM</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <p><strong>Query:</strong> <span class="kbd">?limit=50&offset=0</span></p>
    <p><strong>Response:</strong> <code>data.esims[]</code> — ICCID, carrier, data, days, expiry, QR URL, activation URLs</p>
  </div>
  </details>

  <details><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">GET /wallet.php — Số dư ví</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <p><strong>Response:</strong> <code>data.balance</code> (VND)</p>
  </div>
  </details>

  <details><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">POST /topup.php — Nạp data cho eSIM</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <p><strong>Body:</strong> <code>{"iccid": "89...", "planId": 10, "clientRef": "TOP-001"}</code></p>
    <p><strong>Response:</strong> <code>data.topupId</code>, <code>data.status</code></p>
    <p class="muted">Cần ICCID của eSIM đã mua trước đó.</p>
  </div>
  </details>

  <details><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">GET /notifications.php — Thông báo</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <p><strong>Query:</strong> <span class="kbd">?limit=20&offset=0</span></p>
    <p><strong>Response:</strong> <code>data.unread</code>, <code>data.notifications[]</code></p>
  </div>
  </details>

  <details><summary style="font-weight:700;cursor:pointer;margin-bottom:8px">Mã lỗi</summary>
  <div style="font-size:13px;margin-bottom:14px">
    <div style="display:grid;grid-template-columns:150px 1fr;gap:4px 12px">
      <span class="kbd">UNAUTHORIZED</span><span>401 — API key thiếu hoặc không hợp lệ</span>
      <span class="kbd">RATE_LIMITED</span><span>429 — Vượt rate limit</span>
      <span class="kbd">VALIDATION_ERROR</span><span>400 — Dữ liệu đầu vào sai</span>
      <span class="kbd">RUNTIME_ERROR</span><span>422 — Lỗi nghiệp vụ (ví dụ: hết số dư)</span>
      <span class="kbd">SERVER_ERROR</span><span>500 — Lỗi hệ thống</span>
    </div>
  </div>
  </details>
</div>
<?php ctv_layout_footer();
