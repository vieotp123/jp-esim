<?php
declare(strict_types=1);

/**
 * QrService — render QR PNG from raw payload (e.g. eSIM LPA string).
 *
 * Self-hosted to keep provider domains/branding (qrsim.net etc.) out of
 * customer-visible URLs. Output is a plain PNG byte stream of the encoded
 * payload — no extra text, no logo, no leak.
 */
final class QrService {
    private static bool $loaded = false;

    private static function load(): void {
        if (self::$loaded) return;
        $base = __DIR__ . '/../../lib/PHPQRCode';
        if (!is_file($base . '/Autoloader.php')) {
            throw new RuntimeException('PHPQRCode library missing at ' . $base);
        }
        require_once $base . '/Autoloader.php';
        \PHPQRCode\Autoloader::register();
        // Library writes traces if QR_LOG_DIR is set — keep it off.
        if (!defined('QR_LOG_DIR')) define('QR_LOG_DIR', false);
        if (!defined('QR_CACHEABLE')) define('QR_CACHEABLE', false);
        self::$loaded = true;
    }

    /**
     * Render PNG bytes for the given payload.
     *
     * @param string $payload  Raw text to encode (e.g. "LPA:1$smdp$matchingId").
     * @param int    $size     Module pixel size (default 8 → ~330px image).
     * @param int    $margin   Quiet-zone modules (default 2).
     * @param string $level    Error correction: L|M|Q|H (default M).
     */
    public static function pngBytes(string $payload, int $size = 8, int $margin = 2, string $level = 'M'): string {
        self::load();
        $payload = trim($payload);
        if ($payload === '') throw new RuntimeException('QrService: empty payload');
        $size   = max(1, min(20, $size));
        $margin = max(0, min(8, $margin));
        $ec = match (strtoupper($level)) {
            'L' => \PHPQRCode\Constants::QR_ECLEVEL_L,
            'Q' => \PHPQRCode\Constants::QR_ECLEVEL_Q,
            'H' => \PHPQRCode\Constants::QR_ECLEVEL_H,
            default => \PHPQRCode\Constants::QR_ECLEVEL_M,
        };
        $tmp = tempnam(sys_get_temp_dir(), 'jpqr_');
        if ($tmp === false) throw new RuntimeException('QrService: tempnam failed');
        try {
            \PHPQRCode\QRcode::png($payload, $tmp, $ec, $size, $margin);
            if (!is_file($tmp) || filesize($tmp) === 0) {
                throw new RuntimeException('QrService: render produced empty file');
            }
            $bytes = file_get_contents($tmp);
            if ($bytes === false) throw new RuntimeException('QrService: read failed');
            return $bytes;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Save PNG bytes to a file (for email inline attachments).
     *
     * @return string Path on success.
     */
    public static function pngToFile(string $payload, string $destPath, int $size = 8, int $margin = 2, string $level = 'M'): string {
        $bytes = self::pngBytes($payload, $size, $margin, $level);
        if (file_put_contents($destPath, $bytes) === false) {
            throw new RuntimeException('QrService: write failed: ' . $destPath);
        }
        return $destPath;
    }
}
