<?php
declare(strict_types=1);

final class RateLimiter {
    private string $dir;

    public function __construct(?string $dir = null) {
        $this->dir = $dir ?? sys_get_temp_dir() . '/jpesim_ratelimit';
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0700, true);
        }
    }

    public function check(string $key, int $limit, int $windowSeconds): bool {
        $file = $this->dir . '/' . md5($key) . '.json';
        $now = time();
        $data = $this->load($file);

        $data['hits'] = array_values(array_filter(
            $data['hits'] ?? [],
            fn(int $t) => $t > ($now - $windowSeconds)
        ));

        if (count($data['hits']) >= $limit) {
            return false;
        }

        $data['hits'][] = $now;
        $this->save($file, $data);
        return true;
    }

    public function remaining(string $key, int $limit, int $windowSeconds): int {
        $file = $this->dir . '/' . md5($key) . '.json';
        $now = time();
        $data = $this->load($file);
        $hits = array_filter($data['hits'] ?? [], fn(int $t) => $t > ($now - $windowSeconds));
        return max(0, $limit - count($hits));
    }

    public static function isAdminIp(): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $raw = (string)app_config('ADMIN_IPS', getenv('ADMIN_IPS') ?: '');
        if ($raw === '') return false;
        $list = array_map('trim', explode(',', $raw));
        return in_array($ip, $list, true);
    }

    public function cleanup(int $maxAgeSeconds = 3600): void {
        $cutoff = time() - $maxAgeSeconds;
        $files = glob($this->dir . '/*.json');
        if (!$files) return;
        foreach ($files as $f) {
            if (filemtime($f) < $cutoff) {
                @unlink($f);
            }
        }
    }

    private function load(string $file): array {
        if (!is_file($file)) return ['hits' => []];
        $raw = @file_get_contents($file);
        if ($raw === false) return ['hits' => []];
        $data = json_decode($raw, true);
        return is_array($data) ? $data : ['hits' => []];
    }

    private function save(string $file, array $data): void {
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
