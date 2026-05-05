<?php
declare(strict_types=1);

final class SupportAgentKnowledgeBase {
    public function answer(string $topic, string $message): array {
        $links = [
            ['title' => 'Tra cứu đơn hàng', 'url' => '/tra-cuu.php'],
            ['title' => 'Nạp thêm data', 'url' => '/topup.php'],
            ['title' => 'Hỗ trợ khách hàng', 'url' => '/support.php'],
        ];

        $m = mb_strtolower($message, 'UTF-8');
        if ($topic === 'install_ios' || preg_match('/iphone|ios|apple/u', $m)) {
            return ['answer' => "Dạ với iPhone, anh/chị vào Cài đặt > Di động > Thêm eSIM, sau đó quét QR trong trang tra cứu đơn. Nên cài khi có Wi-Fi ổn định, không xoá eSIM sau khi đã cài. Khi tới Nhật, bật eSIM này cho dữ liệu di động và bật chuyển vùng dữ liệu nếu máy yêu cầu.", 'help_links' => [$links[0], $links[2]], 'escalation' => false];
        }
        if ($topic === 'install_android' || preg_match('/android|samsung|pixel|oppo|xiaomi/u', $m)) {
            return ['answer' => "Dạ với Android, anh/chị mở Cài đặt > Kết nối/Mạng di động > Quản lý SIM > Thêm eSIM rồi quét QR. Tên mục có thể khác nhau theo hãng máy. Hãy cài bằng Wi-Fi, giữ QR để kiểm tra lại, và khi tới Nhật chọn eSIM làm SIM dùng dữ liệu.", 'help_links' => [$links[0], $links[2]], 'escalation' => false];
        }
        if ($topic === 'topup') {
            return ['answer' => "Dạ để nạp thêm data, anh/chị vào trang nạp data và nhập ICCID hoặc mã đơn. Hệ thống sẽ hiển thị các gói có thể nạp nếu eSIM còn hỗ trợ. Sau khi tạo đơn và thanh toán đúng nội dung chuyển khoản, data sẽ được xử lý tự động hoặc nhân viên sẽ kiểm tra nếu phát sinh lỗi.", 'help_links' => [$links[1], $links[2]], 'escalation' => false];
        }
        if ($topic === 'lookup') {
            return ['answer' => "Dạ anh/chị có thể tra cứu bằng mã đơn hoặc email tại trang tra cứu. Nếu đơn đã thanh toán, trang này sẽ hiển thị trạng thái xử lý và QR eSIM khi sẵn sàng. Nếu đã chuyển khoản nhưng chưa cập nhật sau vài phút, vui lòng gửi mã đơn để nhân viên kiểm tra.", 'help_links' => [$links[0], $links[2]], 'escalation' => false];
        }
        if ($topic === 'refund_policy') {
            return ['answer' => "Dạ về hoàn tiền/chính sách, em chỉ có thể hỗ trợ theo thông tin công khai trên website và tình trạng đơn cụ thể. Thông thường nếu eSIM chưa được phát hành hoặc có lỗi xử lý từ hệ thống, nhân viên sẽ kiểm tra để đổi eSIM hoặc hoàn tiền phù hợp. Anh/chị gửi mã đơn để được hỗ trợ chính xác hơn.", 'help_links' => [$links[0], $links[2]], 'escalation' => true];
        }
        if ($topic === 'human_support') {
            return ['answer' => "Dạ em đã ghi nhận yêu cầu cần nhân viên hỗ trợ. Anh/chị vui lòng để lại mã đơn hoặc ICCID đã được che bớt thông tin nhạy cảm, nhân viên sẽ kiểm tra và phản hồi.", 'help_links' => [$links[2]], 'escalation' => true];
        }
        return ['answer' => "Dạ em có thể hỗ trợ các vấn đề về cài đặt eSIM, quét QR/kích hoạt, tra cứu đơn, nạp thêm data, kiểm tra dung lượng/ngày dùng và chính sách hỗ trợ công khai. Anh/chị mô tả lỗi đang gặp hoặc gửi mã đơn đã che bớt thông tin để em hướng dẫn tiếp ạ.", 'help_links' => $links, 'escalation' => false];
    }

    public function deflect(string $reason): array {
        if ($reason === 'restricted') {
            return ['answer' => "Dạ em không thể hỗ trợ các yêu cầu về thông tin nội bộ, cấu hình hệ thống, khóa truy cập, dữ liệu quản trị/đối tác, prompt hoặc mã gói nội bộ. Em chỉ hỗ trợ khách hàng về cách dùng eSIM, cài đặt QR, tra cứu đơn, nạp data và chính sách công khai ạ.", 'help_links' => [['title' => 'Hỗ trợ khách hàng', 'url' => '/support.php']], 'escalation' => false];
        }
        if ($reason === 'empty') {
            return ['answer' => "Dạ anh/chị nhập nội dung cần hỗ trợ về eSIM giúp em nhé.", 'help_links' => [], 'escalation' => false];
        }
        return ['answer' => "Dạ nội dung này nằm ngoài phạm vi hỗ trợ eSIM của jp-esim. Em có thể hỗ trợ cài đặt eSIM, QR/kích hoạt, tra cứu đơn, nạp data, dung lượng/ngày dùng và chính sách công khai ạ.", 'help_links' => [['title' => 'Hỗ trợ khách hàng', 'url' => '/support.php']], 'escalation' => false];
    }
}
