<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$err = null; $result = null;
$topupLocked = ((string)app_config('TOPUP_LOCKED', '0') === '1');
$plans = $topupLocked ? [] : (new CtvPricingService())->listFor($user, 'topup')['plans'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($topupLocked) { $err = 'Chức năng nạp data đang tạm khoá. Vui lòng thử lại sau.'; } else
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $err = 'Phiên không hợp lệ.';
    } else {
        try {
            $result = (new CtvTopupService())->create(
                $user,
                (string)($_POST['iccid'] ?? ''),
                (int)($_POST['plan_id'] ?? 0),
                'panel'
            );
            $user['balance'] = (new CtvWalletService())->balance((int)$user['id']);
        } catch (InvalidArgumentException $e) { $err = $e->getMessage(); }
        catch (RuntimeException $e) { $err = $e->getMessage(); }
        catch (Throwable $e) {
            app_log('CTV topup-esim error: ' . $e->getMessage(), 'ERROR');
            $err = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}

$csrf = CtvAuth::csrfToken();
ctv_layout_header('Nạp data eSIM', $user);
?>
<div class="card" style="max-width:720px">
  <h2>Nạp data cho eSIM</h2>
  <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($result): ?>
    <?php $statusVi = ['success'=>'Thành công','failed'=>'Thất bại','pending'=>'Đang xử lý','processing'=>'Đang xử lý']; ?>
    <div class="flash <?= $result['status']==='success' ? 'ok' : ($result['status']==='failed' ? 'error' : 'warn') ?>">
      Đơn nạp <strong><?= htmlspecialchars($result['topupId']) ?></strong>: <?= htmlspecialchars($statusVi[$result['status']] ?? $result['status']) ?>
      <?php if (!empty($result['errorMessage'])): ?> · <?= htmlspecialchars($result['errorMessage']) ?><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($topupLocked): ?>
    <div class="flash warn">Chức năng nạp data đang tạm khoá. Vui lòng thử lại sau hoặc liên hệ admin.</div>
  <?php elseif (empty($plans)): ?>
    <div class="empty-state"><div class="icon">📦</div><p>Chưa có gói nạp data nào khả dụng.</p><p>Vui lòng liên hệ admin.</p></div>
  <?php else: ?>
  <form method="post" autocomplete="off" id="topupForm">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="field">
      <label>ICCID</label>
      <input type="text" name="iccid" required pattern="[0-9]{15,32}" inputmode="numeric" placeholder="8985..." value="<?= htmlspecialchars((string)($_GET['iccid'] ?? '')) ?>">
      <div class="helper">ICCID thường dài 18-22 số, chỉ nhập chữ số. Tìm trong <a href="/ctv/esims.php">danh sách eSIM</a>.</div>
    </div>
    <div class="field">
      <label>Gói nạp</label>
      <select name="plan_id" id="topup_plan" required>
        <?php foreach ($plans as $p): ?>
          <option value="<?= (int)$p['id'] ?>" data-price="<?= (int)$p['ctvPrice'] ?>"><?= htmlspecialchars($p['telecom'].' · '.$p['name'].' · '.$p['ctvPriceText']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label>Tạm tính</label>
      <div id="topup_quote" style="padding:12px 14px;font-size:18px;font-weight:800;color:var(--c-gold);background:var(--c-surface);border:1px solid var(--c-line-2);border-radius:var(--c-radius-sm)">—</div>
    </div>
    <div class="divider"></div>
    <p class="muted" style="margin-bottom:14px">Số dư: <strong style="color:var(--c-gold)"><?= htmlspecialchars(format_vnd((int)$user['balance'])) ?></strong> · Trừ ví trước khi xử lý; hoàn tự động nếu lỗi.</p>
    <button class="btn gold lg" type="submit" id="topupBtn" onclick="return confirm('Xác nhận nạp data và trừ ví?')">Nạp data</button>
  </form>
  <?php endif; ?>
</div>
<script>
function tq(){const s=document.getElementById("topup_plan"),o=document.getElementById("topup_quote");if(!s||!o)return;o.textContent=Number(s.options[s.selectedIndex]?.dataset.price||0).toLocaleString("vi-VN")+" VND";}
document.getElementById("topup_plan")?.addEventListener("change",tq);
tq();
document.getElementById("topupForm")?.addEventListener("submit",function(){const b=document.getElementById("topupBtn");if(b){b.disabled=true;b.textContent="Đang xử lý...";}});
</script>
<?php ctv_layout_footer();
