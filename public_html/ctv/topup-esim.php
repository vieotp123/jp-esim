<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

function ctv_topup_clean_iccid(string $iccid): string {
    return preg_replace('/\s+/', '', trim($iccid)) ?? '';
}

function ctv_topup_datetime(?string $value): string {
    $value = trim((string)$value);
    return $value !== '' ? $value : '—';
}

function ctv_topup_remaining_days(?string $expiredAt): string {
    $expiredAt = trim((string)$expiredAt);
    if ($expiredAt === '') return '—';
    $ts = strtotime($expiredAt);
    if (!$ts) return '—';
    $days = (int)ceil(($ts - time()) / 86400);
    return (string)max(0, $days) . ' ngày';
}

function ctv_topup_data_text(array $current): string {
    if (array_key_exists('remainingGB', $current) && $current['remainingGB'] !== null && $current['remainingGB'] !== '') {
        return rtrim(rtrim(number_format((float)$current['remainingGB'], 2, ',', '.'), '0'), ',') . ' GB';
    }
    if (array_key_exists('remainingVolume', $current) && $current['remainingVolume'] !== null && $current['remainingVolume'] !== '') {
        return rtrim(rtrim(number_format(((float)$current['remainingVolume']) / 1073741824, 2, ',', '.'), '0'), ',') . ' GB';
    }
    return '—';
}

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$err = null;
$warn = null;
$result = null;
$lookup = null;
$svc = new CtvTopupService();
$topupLocked = ((string)app_config('TOPUP_LOCKED', '0') === '1');
$requestedIccid = ctv_topup_clean_iccid((string)($_POST['iccid'] ?? $_GET['iccid'] ?? ''));
$action = (string)($_POST['action'] ?? ($requestedIccid !== '' ? 'lookup' : ''));

if ($action !== '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $err = 'Phiên không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } else {
        try {
            $lookup = $svc->lookup($user, $requestedIccid);
            if (!empty($lookup['message'])) {
                $warn = (string)$lookup['message'];
            }

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'topup') {
                $planId = (int)($_POST['plan_id'] ?? 0);
                if ($topupLocked) {
                    $err = 'Chức năng nạp data đang tạm khoá. Vui lòng thử lại sau hoặc liên hệ admin.';
                } elseif (!in_array($planId, $lookup['compatiblePlanIds'] ?? [], true)) {
                    $err = 'Gói nạp không tương thích với ICCID này. Vui lòng tra cứu lại trước khi nạp.';
                } else {
                    $result = $svc->create($user, (string)$lookup['iccid'], $planId, 'panel');
                    $user['balance'] = (new CtvWalletService())->balance((int)$user['id']);
                }
            }
        } catch (InvalidArgumentException $e) {
            $err = $e->getMessage();
            $lookup = null;
        } catch (RuntimeException $e) {
            $err = $e->getMessage();
            $lookup = null;
        } catch (Throwable $e) {
            app_log('CTV topup-esim error: ' . $e->getMessage(), 'ERROR');
            $err = 'Lỗi hệ thống. Vui lòng thử lại sau.';
            $lookup = null;
        }
    }
}

$plans = is_array($lookup['plans'] ?? null) ? $lookup['plans'] : [];
$current = is_array($lookup['current'] ?? null) ? $lookup['current'] : [];
$csrf = CtvAuth::csrfToken();
ctv_layout_header('Nạp data eSIM', $user);
?>
<div class="card" style="max-width:760px">
  <h2>Nạp data cho eSIM</h2>
  <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($warn): ?><div class="flash warn"><?= htmlspecialchars($warn) ?></div><?php endif; ?>
  <?php if ($result): ?>
    <?php $statusVi = ['success'=>'Thành công','failed'=>'Thất bại','pending'=>'Đang xử lý','processing'=>'Đang xử lý']; ?>
    <div class="flash <?= $result['status']==='success' ? 'ok' : ($result['status']==='failed' ? 'error' : 'warn') ?>">
      Đơn nạp <strong><?= htmlspecialchars($result['topupId']) ?></strong>: <?= htmlspecialchars($statusVi[$result['status']] ?? $result['status']) ?>
      <?php if (!empty($result['errorMessage'])): ?> · <?= htmlspecialchars($result['errorMessage']) ?><?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="get" autocomplete="off" style="margin-top:18px">
    <div class="field">
      <label>ICCID</label>
      <input type="text" name="iccid" required pattern="[0-9]{15,32}" inputmode="numeric" placeholder="8985..." value="<?= htmlspecialchars($requestedIccid) ?>">
      <div class="helper">Nhập 15-32 chữ số. Sau khi tra cứu thành công, hệ thống mới hiển thị gói nạp tương thích.</div>
    </div>
    <button class="btn gold" type="submit">Tra cứu ICCID</button>
  </form>

  <?php if ($lookup): ?>
    <div class="divider"></div>
    <h3 style="margin-bottom:12px">Thông tin eSIM</h3>
    <div class="kv">
      <b>ICCID</b><div><span class="kbd"><?= htmlspecialchars((string)$lookup['iccid']) ?></span></div>
      <b>Gói hiện tại</b><div><?= htmlspecialchars((string)($current['planName'] ?? '—') ?: '—') ?></div>
      <b>Nhà mạng</b><div><?= htmlspecialchars((string)($current['carrier'] ?? '—') ?: '—') ?></div>
      <b>Data còn lại</b><div><?= htmlspecialchars(ctv_topup_data_text($current)) ?></div>
      <b>Số ngày còn lại</b><div><?= htmlspecialchars(ctv_topup_remaining_days($current['expiredAt'] ?? null)) ?></div>
      <b>Kích hoạt</b><div><?= htmlspecialchars(ctv_topup_datetime($current['activatedAt'] ?? null)) ?></div>
      <b>Hết hạn</b><div><?= htmlspecialchars(ctv_topup_datetime($current['expiredAt'] ?? null)) ?></div>
      <b>Trạng thái</b><div><?= htmlspecialchars((string)($current['status'] ?? '—') ?: '—') ?></div>
    </div>

    <?php if ($topupLocked): ?>
      <div class="flash warn" style="margin-top:18px">Chức năng nạp data đang tạm khoá. Bạn có thể tra cứu ICCID, nhưng chưa thể tạo đơn nạp mới.</div>
    <?php elseif (empty($plans)): ?>
      <div class="empty-state" style="margin-top:18px"><div class="icon">!</div><p>Không có gói nạp data tương thích cho ICCID này.</p><p>Vui lòng liên hệ admin để kiểm tra.</p></div>
    <?php else: ?>
      <form method="post" autocomplete="off" id="topupForm" style="margin-top:18px">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="topup">
        <input type="hidden" name="iccid" value="<?= htmlspecialchars((string)$lookup['iccid']) ?>">
        <div class="field">
          <label>Gói nạp tương thích</label>
          <select name="plan_id" id="topup_plan" required>
            <option value="">Chọn gói nạp</option>
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
        <button class="btn gold lg" type="submit" id="topupBtn">Nạp data</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>
<script>
function tq(){
  const s=document.getElementById("topup_plan"),o=document.getElementById("topup_quote");
  if(!s||!o)return;
  const price=Number(s.options[s.selectedIndex]?.dataset.price||0);
  o.textContent=price>0?price.toLocaleString("vi-VN")+" VND":"—";
}
document.getElementById("topup_plan")?.addEventListener("change",tq);
tq();
document.getElementById("topupForm")?.addEventListener("submit",function(e){
  const s=document.getElementById("topup_plan");
  if(!s||!s.value){e.preventDefault();alert("Vui lòng chọn gói nạp tương thích.");return;}
  if(!confirm("Xác nhận nạp data và trừ ví?")){e.preventDefault();return;}
  const b=document.getElementById("topupBtn");
  if(b){b.disabled=true;b.textContent="Đang xử lý...";}
});
</script>
<?php ctv_layout_footer();
