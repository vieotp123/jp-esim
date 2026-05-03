<?php
declare(strict_types=1);
final class PlanService {
    public function list(string $type = 'esim', ?string $telecom = null): array {
        $pdo = db();
        $where = ['status=1']; $params=[];
        if ($type === 'topup') $where[] = "topup_packcode IS NOT NULL AND topup_packcode <> ''";
        else $where[] = "pack_code IS NOT NULL AND pack_code <> ''";
        if ($telecom) { $where[] = 'telecom=?'; $params[] = $telecom; }
        $sql = 'SELECT id, telecom, plan, day, price, cost, pack_code, topup_packcode, network FROM plan WHERE '.implode(' AND ', $where).' ORDER BY FIELD(telecom,\'Docomo\',\'au\') DESC, telecom, price';
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $plans = [];
        foreach ($stmt->fetchAll() as $p) $plans[] = $this->format($p);
        $telecoms = array_values(array_unique(array_map(fn($p)=>$p['telecom'], $plans)));
        usort($telecoms, fn($a,$b)=>($a==='Docomo'?-1:0) <=> ($b==='Docomo'?-1:0));
        return ['telecoms'=>$telecoms, 'plans'=>$plans];
    }
    public function findActive(int $id): ?array {
        $stmt = db()->prepare('SELECT id, telecom, plan, day, price, cost, pack_code, topup_packcode, network FROM plan WHERE id=? AND status=1 LIMIT 1');
        $stmt->execute([$id]); $p=$stmt->fetch(); return $p ?: null;
    }
    public function format(array $p): array {
        return ['id'=>(int)$p['id'], 'telecom'=>(string)$p['telecom'], 'name'=>(string)$p['plan'], 'day'=>(int)$p['day'], 'price'=>(int)$p['price'], 'priceText'=>format_vnd((int)$p['price']), 'cost'=>(int)($p['cost'] ?? 0), 'network'=>(string)($p['network'] ?? ''), 'packCode'=>(string)($p['pack_code'] ?? ''), 'topupPackCode'=>(string)($p['topup_packcode'] ?? '')];
    }
}
