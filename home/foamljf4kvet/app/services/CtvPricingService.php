<?php
declare(strict_types=1);

final class CtvPricingService {
    public function effectiveDiscount(array $ctv): int {
        $perEsim = (int)($ctv['discount_per_esim'] ?? 0);
        if ($perEsim > 0) return $perEsim;
        $tierId = (int)($ctv['tier_id'] ?? 0);
        if ($tierId > 0) {
            try {
                $st = db()->prepare('SELECT discount_per_esim FROM ctv_tiers WHERE id=? LIMIT 1');
                $st->execute([$tierId]);
                $d = (int)$st->fetchColumn();
                if ($d > 0) return $d;
            } catch (Throwable $e) { /* ignore */ }
        }
        return 0;
    }

    public function priceFor(array $ctv, array $plan): array {
        $retail = (int)$plan['price'];
        $discount = $this->effectiveDiscount($ctv);
        $ctvPrice = max(0, $retail - $discount);
        return [
            'retailPrice' => $retail,
            'discount' => $discount,
            'ctvPrice' => $ctvPrice,
            'retailPriceText' => format_vnd($retail),
            'ctvPriceText' => format_vnd($ctvPrice),
            'discountText' => format_vnd($discount),
        ];
    }

    public function listFor(array $ctv, string $type = 'esim', ?string $telecom = null): array {
        $base = (new PlanService())->list($type, $telecom);
        foreach ($base['plans'] as &$p) {
            $pricing = $this->priceFor($ctv, ['price' => $p['price']]);
            $p['retailPrice'] = $pricing['retailPrice'];
            $p['discount'] = $pricing['discount'];
            $p['ctvPrice'] = $pricing['ctvPrice'];
            $p['ctvPriceText'] = $pricing['ctvPriceText'];
            $p['retailPriceText'] = $p['priceText'];
        }
        unset($p);
        return $base;
    }
}
