<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$err = null; $createdResult = null;
$plans = (new CtvPricingService())->listFor($user, 'esim')['plans'];
$planMap = []; foreach ($plans as $p) { $planMap[(int)$p['id']] = (int)$p['ctvPrice']; }
function ctv_create_plan_data(string $name): string {
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(GB|MB)\b/i', $name, $m)) {
        return str_replace(',', '.', $m[1]) . ' ' . strtoupper($m[2]);
    }
    return 'Data';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $err = 'Phiên không hợp lệ.';
    } else {
        try {
            $createdResult = (new CtvOrderService())->createEsim(
                $user,
                (int)($_POST['plan_id'] ?? 0),
                max(1, min(50, (int)($_POST['quantity'] ?? 1))),
                'panel',
                null,
                trim((string)($_POST['email'] ?? '')) ?: null,
                trim((string)($_POST['notes'] ?? '')) ?: null
            );
            $user['balance'] = (new CtvWalletService())->balance((int)$user['id']);
        } catch (InvalidArgumentException $e) { $err = $e->getMessage(); }
        catch (RuntimeException $e) { $err = $e->getMessage(); }
        catch (Throwable $e) {
            app_log('CTV create-esim error: ' . $e->getMessage(), 'ERROR');
            $err = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}

$csrf = CtvAuth::csrfToken();
ctv_layout_header('Tạo eSIM', $user);
?>
<div class="card" style="max-width:720px">
  <h2>Tạo eSIM mới</h2>
  <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($createdResult): ?>
    <?php $statusVi = ['success'=>'Thành công','failed'=>'Thất bại','pending'=>'Đang xử lý','processing'=>'Đang xử lý']; ?>
    <div class="flash <?= $createdResult['status']==='success' ? 'ok' : ($createdResult['status']==='failed' ? 'error' : 'warn') ?>">
      Đơn <strong><?= htmlspecialchars($createdResult['orderId']) ?></strong>: <?= htmlspecialchars($statusVi[$createdResult['status']] ?? $createdResult['status']) ?>
      <?php if (!empty($createdResult['errorMessage'])): ?> · <?= htmlspecialchars($createdResult['errorMessage']) ?><?php endif; ?>
    </div>
    <?php if ($createdResult['status'] === 'success' || $createdResult['status'] === 'pending'): ?>
      <div class="actions"><a class="btn gold" href="/ctv/orders/view.php?id=<?= htmlspecialchars(rawurlencode($createdResult['orderId'])) ?>">Xem chi tiết đơn →</a></div>
    <?php endif; ?>
  <?php endif; ?>
  <?php if (empty($plans)): ?>
    <div class="empty-state"><div class="icon">📦</div><p>Chưa có gói eSIM nào khả dụng.</p><p>Vui lòng liên hệ admin.</p></div>
  <?php else: ?>
  <form method="post" autocomplete="off" id="createForm">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="field">
      <label>Gói eSIM</label>
      <select name="plan_id" id="plan_id" required>
        <?php foreach ($plans as $p): ?>
          <option value="<?= (int)$p['id'] ?>" data-price="<?= (int)$p['ctvPrice'] ?>"><?= htmlspecialchars($p['telecom'].' · '.ctv_create_plan_data((string)$p['name']).' · '.$p['day'].' ngày · '.$p['ctvPriceText']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="helper">Chọn gói phù hợp nhu cầu khách hàng.</div>
    </div>
    <div class="row">
      <div class="field">
        <label>Số lượng</label>
        <input id="quantity" type="number" name="quantity" min="1" max="50" value="1" required inputmode="numeric">
        <div class="helper">Tối đa 50 eSIM mỗi đơn.</div>
      </div>
      <div class="field">
        <label>Tạm tính</label>
        <div id="quote" style="padding:12px 14px;font-size:18px;font-weight:800;color:var(--c-gold);background:var(--c-surface);border:1px solid var(--c-line-2);border-radius:var(--c-radius-sm)">—</div>
      </div>
    </div>
    <div class="divider"></div>
    <div class="field">
      <label>Email khách (tùy chọn)</label>
      <input type="email" name="email" placeholder="email@khachhang.com">
      <div class="helper">Nếu nhập, hệ thống sẽ gửi QR eSIM tới email này.</div>
    </div>
    <div class="field">
      <label>Ghi chú (tùy chọn)</label>
      <input type="text" name="notes" placeholder="VD: Tên khách, mã booking...">
    </div>
    <div class="divider"></div>
    <p class="muted" style="margin-bottom:14px">Số dư: <strong style="color:var(--c-gold)"><?= htmlspecialchars(format_vnd((int)$user['balance'])) ?></strong> · Trừ ví trước khi xử lý; hoàn tự động nếu lỗi.</p>
    <button class="btn gold lg" type="submit" id="submitBtn" onclick="return confirm('Xác nhận tạo đơn và trừ ví?')">Tạo đơn eSIM</button>
  </form>
  <?php endif; ?>
</div>
<script>
function upd(){const s=document.getElementById("plan_id"),q=document.getElementById("quantity"),o=document.getElementById("quote");if(!s||!q||!o)return;const p=Number(s.options[s.selectedIndex]?.dataset.price||0),n=Math.max(1,Number(q.value||1));o.textContent=(p*n).toLocaleString("vi-VN")+" VND";}
document.getElementById("plan_id")?.addEventListener("change",upd);
document.getElementById("quantity")?.addEventListener("input",upd);
upd();
document.getElementById("createForm")?.addEventListener("submit",function(){const b=document.getElementById("submitBtn");if(b){b.disabled=true;b.textContent="Đang xử lý...";}});
</script>
<?php ctv_layout_footer();
