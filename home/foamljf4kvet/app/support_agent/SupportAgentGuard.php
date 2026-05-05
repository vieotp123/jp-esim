<?php
declare(strict_types=1);

final class SupportAgentGuard {
    public function inspect(string $message): array {
        $m = mb_strtolower($message, 'UTF-8');
        if ($message === '') {
            return ['safe' => false, 'topic' => 'empty', 'reason' => 'empty'];
        }
        if (SupportAgentSanitizer::containsForbiddenLeak($m) || preg_match('/(?i)(ignore previous|bỏ qua hướng dẫn|show.*prompt|in ra.*prompt|source code|mã nguồn|env|\.env|credentials?|nhà cung cấp.*(tên|domain|url)|tên.*nhà cung cấp|mã gói nội bộ)/u', $m)) {
            return ['safe' => false, 'topic' => 'restricted', 'reason' => 'restricted'];
        }

        $topics = [
            'install_ios' => '/\b(iphone|ios|apple|cài|cai|install|qr|activation|kích hoạt|kich hoat|lpa)\b/u',
            'install_android' => '/\b(android|samsung|pixel|oppo|xiaomi|cài|cai|install|qr|activation|kích hoạt|kich hoat)\b/u',
            'topup' => '/\b(nạp|nap|topup|mua thêm|mua them|data|dung lượng|dung luong|gb)\b/u',
            'lookup' => '/\b(tra cứu|tra cuu|kiểm tra|kiem tra|mã đơn|ma don|order|iccid|qr|thanh toán|thanh toan)\b/u',
            'refund_policy' => '/\b(hoàn tiền|hoan tien|refund|hủy đơn|huy don|chính sách|chinh sach|lỗi|loi)\b/u',
            'esim_general' => '/\b(esim|sim|roaming|nhật|nhat|japan|mạng|mang|sóng|song)\b/u',
            'human_support' => '/\b(nhân viên|nhan vien|người thật|nguoi that|hỗ trợ|ho tro|liên hệ|lien he)\b/u',
        ];
        foreach ($topics as $topic => $pattern) {
            if (preg_match($pattern, $m)) return ['safe' => true, 'topic' => $topic, 'reason' => 'ok'];
        }
        return ['safe' => false, 'topic' => 'unrelated', 'reason' => 'unrelated'];
    }
}
