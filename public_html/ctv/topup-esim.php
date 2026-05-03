<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$err = null; $result = null;
$plans = (new CtvPricingService())->listFor($user, 'topup')['plans'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
<div class="card">
  <h2>Nạp data cho eSIM</h2>
  <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($result): ?>
    <div class="flash <?= $result['status']==='success' ? 'ok' : ($result['status']==='failed' ? 'error' : 'warn') ?>">
      Đơn nạp <strong><?= htmlspecialchars($result['topupId']) ?></strong>: <?= htmlspecialchars($result['status']) ?>
      <?php if (!empty($result['errorMessage'])): ?> · <?= htmlspecialchars($result['errorMessage']) ?><?php endif; ?>
    </div>
  <?php endif; ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="row">
      <div class="field"><label>ICCID</label><input type="text" name="iccid" required pattern="[0-9]{15,32}" placeholder="8985..."></div>
      <div class="field">
        <label>Gói nạp</label>
        <select name="plan_id" required>
          <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['telecom'].' · '.$p['name'].' · '.$p['ctvPriceText']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <p class="muted">Số dư hiện tại: <strong><?= htmlspecialchars(format_vnd((int)$user['balance'])) ?></strong>. Hệ thống sẽ trừ số dư trước khi gọi nhà cung cấp.</p>
    <button class="btn" type="submit">Nạp data</button>
  </form>
</div>
<?php ctv_layout_footer();
