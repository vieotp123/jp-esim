<?php
declare(strict_types=1);
require_once '/home/foamljf4kvet/app/bootstrap.php';
require_once __DIR__ . '/_layout.php';

$user = CtvAuth::requireUser();
$user['balance'] = (new CtvWalletService())->balance((int)$user['id']);

$err = null; $createdResult = null;
$plans = (new CtvPricingService())->listFor($user, 'esim')['plans'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CtvAuth::checkCsrf($_POST['_csrf'] ?? null)) {
        $err = 'Phiên không hợp lệ.';
    } else {
        try {
            $createdResult = (new CtvOrderService())->createEsim(
                $user,
                (int)($_POST['plan_id'] ?? 0),
                (int)($_POST['quantity'] ?? 1),
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
<div class="card">
  <h2>Tạo eSIM mới</h2>
  <?php if ($err): ?><div class="flash error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <?php if ($createdResult): ?>
    <div class="flash <?= $createdResult['status']==='success' ? 'ok' : ($createdResult['status']==='failed' ? 'error' : 'warn') ?>">
      Đơn <strong><?= htmlspecialchars($createdResult['orderId']) ?></strong>: <?= htmlspecialchars($createdResult['status']) ?>
      <?php if (!empty($createdResult['errorMessage'])): ?> · <?= htmlspecialchars($createdResult['errorMessage']) ?><?php endif; ?>
    </div>
  <?php endif; ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <div class="row">
      <div class="field">
        <label>Gói eSIM</label>
        <select name="plan_id" required>
          <?php foreach ($plans as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['telecom'].' · '.$p['name'].' · '.$p['day'].' ngày · '.$p['ctvPriceText']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Số lượng</label><input type="number" name="quantity" min="1" max="50" value="1" required></div>
      <div class="field"><label>Email khách (tùy chọn)</label><input type="email" name="email" placeholder="customer@..."></div>
    </div>
    <div class="field"><label>Ghi chú</label><input type="text" name="notes"></div>
    <p class="muted">Số dư hiện tại: <strong><?= htmlspecialchars(format_vnd((int)$user['balance'])) ?></strong>. Hệ thống sẽ trừ số dư trước khi gọi nhà cung cấp; nếu lỗi sẽ hoàn tự động.</p>
    <button class="btn" type="submit">Tạo đơn</button>
  </form>
</div>
<?php ctv_layout_footer();
