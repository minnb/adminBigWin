<?php
declare(strict_types=1);

// Endpoint THỬ cho gateway (P01 S1.3) — xoá ở P03 khi có endpoint nghiệp vụ thật.

require __DIR__ . '/../../lib/security/AjaxGateway.php';

$ctx = ajax_khoi_dong('doc');
if (($ctx['du_lieu']['nem_loi'] ?? false) === true && cfg('moi_truong') === 'test') {
    throw new RuntimeException('Lỗi thử nghiệm của T24 — C:\\duong_dan\\bi_mat.php'); // không được lộ ra phản hồi
}
ajax_tra(['uid' => $ctx['user']['id']]);
