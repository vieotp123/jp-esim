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
        $fh = @fopen($file, 'c+b');
        if ($fh === false) {
            app_log('RateLimiter open failed key=' . md5($key), 'WARN');
            return false;
        }

        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            app_log('RateLimiter lock failed key=' . md5($key), 'WARN');
            return false;
        }

        $raw = stream_get_contents($fh);
        $data = $raw !== false && $raw !== '' ? json_decode($raw, true) : ['hits' => []];
        if (!is_array($data)) {
            $data = ['hits' => []];
        }

        $data['hits'] = array_values(array_filter(
            $data['hits'] ?? [],
            fn(int $t) => $t > ($now - $windowSeconds)
        ));

        if (count($data['hits']) >= $limit) {
            flock($fh, LOCK_UN);
            fclose($fh);
            return false;
        }

        $data['hits'][] = $now;
        $encoded = json_encode($data);
        if ($encoded === false) {
            flock($fh, LOCK_UN);
            fclose($fh);
            app_log('RateLimiter encode failed key=' . md5($key), 'WARN');
            return false;
        }
        rewind($fh);
        if (!ftruncate($fh, 0) || fwrite($fh, $encoded) === false || !fflush($fh)) {
            flock($fh, LOCK_UN);
            fclose($fh);
            app_log('RateLimiter write failed key=' . md5($key), 'WARN');
            return false;
        }
        flock($fh, LOCK_UN);
        fclose($fh);
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

}
