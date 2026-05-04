<?php
declare(strict_types=1);
final class EsimService {
    public function getByOrder(string $orderId): array {
        if (!preg_match('/^[A-Z0-9]{8}$/i', $orderId)) throw new InvalidArgumentException('Mã đơn không hợp lệ');
        $pdo = db();
        $stmt = $pdo->prepare('SELECT o.order_id,o.status,o.muasim,o.getinfosim,o.orderNo,o.transactionId,o.paid_at,o.created_at,o.plan_name,o.carrier,o.email,o.ratinghash,o.emailsent,o.plan_id,p.day,p.network,p.telecom FROM `order` o LEFT JOIN plan p ON p.id=o.plan_id WHERE o.order_id=? LIMIT 1');
        $stmt->execute([strtoupper($orderId)]);
        $order = $stmt->fetch();
        if (!$order) throw new RuntimeException('Đơn hàng không tồn tại');
        if ((int)$order['status'] < 2 && empty($order['paid_at'])) return ['status' => 'pending', 'message' => 'Đơn hàng chưa thanh toán'];
        if ((int)$order['muasim'] !== 1 || empty($order['orderNo'])) return ['status' => 'processing', 'message' => 'Đang khởi tạo eSIM'];

        $existing = $this->loadStored((string)$order['order_id']);
        if ($existing) {
            $this->sendEmailIfNeeded((string)$order['order_id']);
            return $this->buildReadyPayload($order, $existing);
        }

        $api = (new EsimAccessClient())->queryOrder((string)$order['orderNo'], (string)($order['transactionId'] ?: $order['order_id']));
        if (empty($api['success'])) return ['status' => 'processing', 'message' => 'Đang lấy thông tin eSIM', 'retry' => true];
        $list = $api['obj']['esimList'] ?? [];
        if (!$list) return ['status' => 'processing', 'message' => 'eSIM đang được tạo', 'retry' => true];

        $ins = $pdo->prepare('INSERT INTO esimlist (order_id,esimTranNo,orderNo,transactionId,imsi,iccid,qrCodeUrl,shortUrl,ac,apn,smdpStatus,esimStatus,eid,totalVolume,totalDuration,durationUnit,expiredTime,packageCode,packageName,locationCode,created_at) VALUES (:oid,:etr,:ono,:tid,:imsi,:iccid,:qr,:su,:ac,:apn,:smdp,:estatus,:eid,:vol,:dur,:unit,:exp,:pcode,:pname,:loc,NOW())');
        $first = null;
        foreach ($list as $e) {
            $pkg = $e['packageList'][0] ?? [];
            $ins->execute([
                ':oid' => $order['order_id'], ':etr' => $e['esimTranNo'] ?? null, ':ono' => $e['orderNo'] ?? $order['orderNo'],
                ':tid' => $e['transactionId'] ?? $order['transactionId'], ':imsi' => $e['imsi'] ?? null, ':iccid' => $e['iccid'] ?? null,
                ':qr' => $e['qrCodeUrl'] ?? null, ':su' => $e['shortUrl'] ?? null, ':ac' => $e['ac'] ?? null, ':apn' => $e['apn'] ?? null,
                ':smdp' => $e['smdpStatus'] ?? null, ':estatus' => $e['esimStatus'] ?? null, ':eid' => $e['eid'] ?? null,
                ':vol' => $e['totalVolume'] ?? 0, ':dur' => $e['totalDuration'] ?? 0, ':unit' => $e['durationUnit'] ?? 'DAY',
                ':exp' => $e['expiredTime'] ?? null, ':pcode' => $pkg['packageCode'] ?? null, ':pname' => $pkg['packageName'] ?? null,
                ':loc' => $pkg['locationCode'] ?? null,
            ]);
            if (!$first && !empty($e['iccid'])) $first = $e['iccid'];
        }
        $pdo->prepare('UPDATE `order` SET getinfosim=1, iccid=COALESCE(?,iccid), updated_at=NOW() WHERE order_id=?')->execute([$first, $order['order_id']]);
        $this->sendEmailIfNeeded((string)$order['order_id']);
        return $this->buildReadyPayload($order, $this->loadStored((string)$order['order_id']));
    }

    public function loadStored(string $orderId): array {
        $st = db()->prepare('SELECT * FROM esimlist WHERE BINARY order_id = BINARY ? ORDER BY id DESC');
        $st->execute([$orderId]);
        $out = [];
        foreach ($st->fetchAll() as $e) $out[] = $this->format($e, $orderId);
        return $out;
    }

    public function findByIccidOrOrder(string $id): ?array {
        $pdo = db();
        $id = trim($id);
        if (preg_match('/^[A-Z0-9]{8}$/i', $id)) {
            $sql = 'SELECT e.*, o.plan_name, o.carrier FROM esimlist e LEFT JOIN `order` o ON BINARY o.order_id = BINARY e.order_id WHERE BINARY e.order_id = BINARY ? ORDER BY e.id DESC LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute([strtoupper($id)]);
        } else {
            $sql = 'SELECT e.*, o.plan_name, o.carrier FROM esimlist e LEFT JOIN `order` o ON BINARY o.order_id = BINARY e.order_id WHERE BINARY e.iccid = BINARY ? ORDER BY e.id DESC LIMIT 1';
            $st = $pdo->prepare($sql);
            $st->execute([$id]);
        }
        $r = $st->fetch();
        return $r ?: null;
    }

    public function queryUsage(string $iccid): array {
        $e = $this->findByIccidOrOrder($iccid);
        if (!$e) return [];
        $usage = [];
        if (!empty($e['esimTranNo'])) {
            $api = (new EsimAccessClient())->queryUsage((string)$e['esimTranNo']);
            $usage = $api['obj']['esimUsageList'][0] ?? [];
        }
        $total = (int)($e['totalVolume'] ?? 0);
        $used = (int)($usage['dataUsage'] ?? 0);
        $remain = max(0, $total - $used);
        return [
            'iccid' => $e['iccid'], 'orderId' => $e['order_id'], 'carrier' => $e['carrier'] ?? '',
            'planName' => $e['plan_name'] ?? $e['packageName'] ?? '', 'totalVolume' => $total,
            'totalGB' => round($total / 1073741824, 2), 'usedVolume' => $used, 'usedGB' => round($used / 1073741824, 2),
            'remainingVolume' => $remain, 'remainingGB' => round($remain / 1073741824, 2),
            'expiredAt' => $e['expiredTime'] ?? null, 'status' => $e['esimStatus'] ?? $e['smdpStatus'] ?? '',
        ];
    }

    private function buildReadyPayload(array $order, array $esims): array {
        $visibleBase = (string)($order['paid_at'] ?: $order['created_at'] ?: '');
        $visibleUntil = $visibleBase !== '' ? date('Y-m-d H:i:s', strtotime($visibleBase . ' +24 hours')) : null;
        $isVisible = true;
        if ($visibleUntil !== null) {
            $isVisible = (time() <= strtotime($visibleUntil));
        }

        $carrier = (string)($order['carrier'] ?: $order['telecom'] ?: 'Docomo');
        $network = (string)($order['network'] ?: ($carrier === 'au' ? '5G' : '4G'));
        $day = (int)($order['day'] ?? 0);
        $title = trim('eSIM Nhật Bản ' . $carrier . ' ' . $network . ' ' . (string)$order['plan_name'] . ($day > 0 ? (' ' . $day . ' ngày') : ''));

        if (!$isVisible) {
            foreach ($esims as &$e) {
                $e['qrCodeUrl'] = '';
                $e['qrUrl'] = '';
                $e['install'] = ['ios'=>'', 'android'=>''];
            }
            unset($e);
        }

        return [
            'status' => 'ready',
            'orderId' => $order['order_id'],
            'carrier' => $carrier,
            'network' => $network,
            'day' => $day,
            'plan' => (string)$order['plan_name'],
            'title' => $title,
            'createdAt' => $order['created_at'],
            'paidAt' => $order['paid_at'],
            'visibleUntil' => $visibleUntil,
            'visible' => $isVisible,
            'esims' => $esims,
        ];
    }

    private function sendEmailIfNeeded(string $orderId): void {
        try { (new MailService())->sendOrderIfNeeded($orderId); }
        catch (Throwable $e) { app_log('Auto email failed ' . $orderId . ' ' . $e->getMessage(), 'ERROR'); }
    }

    private function format(array $e, string $orderId = ''): array {
        $vol = (int)($e['totalVolume'] ?? 0);
        $iccid = (string)($e['iccid'] ?? '');
        $oid = $orderId !== '' ? $orderId : (string)($e['order_id'] ?? '');
        $hasLpa = !empty($e['ac']);
        // NOTE: lpa/qrCodeUrl/shortUrl/smdpAddress intentionally OMITTED from public payload
        // to avoid leaking provider domains (e.g. qrsim.net, simlessly) and SM-DP+ host.
        // Frontend should use qrUrl + install.ios/android (proxied through /r/* endpoints).
        $qrUrl = ($hasLpa && $oid !== '' && $iccid !== '')
            ? '/r/qr.php?o=' . rawurlencode($oid) . '&i=' . rawurlencode($iccid)
            : '';
        return [
            'iccid' => $iccid,
            'qrUrl' => $qrUrl,
            'qrCodeUrl' => $qrUrl, // back-compat alias for older frontend code
            'apn' => (string)($e['apn'] ?? ''),
            'smdpStatus' => (string)($e['smdpStatus'] ?? ''),
            'esimStatus' => (string)($e['esimStatus'] ?? ''),
            'totalVolume' => $vol,
            'totalVolumeGB' => round($vol / 1073741824, 2),
            'totalDuration' => (int)($e['totalDuration'] ?? 0),
            'durationUnit' => (string)($e['durationUnit'] ?? 'DAY'),
            'expiredTime' => (string)($e['expiredTime'] ?? ''),
            'packageName' => (string)($e['packageName'] ?? ''),
            'packageCode' => (string)($e['packageCode'] ?? ''),
            'install' => [
                'ios' => ($hasLpa && $oid !== '' && $iccid !== '')
                    ? '/r/install.php?os=ios&o=' . rawurlencode($oid) . '&i=' . rawurlencode($iccid) : '',
                'android' => ($hasLpa && $oid !== '' && $iccid !== '')
                    ? '/r/install.php?os=android&o=' . rawurlencode($oid) . '&i=' . rawurlencode($iccid)
                    : 'intent:#Intent;action=android.settings.MANAGE_ALL_SIM_PROFILES_SETTINGS;end',
            ],
        ];
    }
}
