<?php
declare(strict_types=1);

// Endpoint THỬ chế độ `phan_tich` cho bản quyền (P02 S1.5) — xoá ở P03 khi có endpoint nghiệp vụ thật.

require __DIR__ . '/../../lib/security/AjaxGateway.php';

$ctx = ajax_khoi_dong('phan_tich');
ajax_tra(['uid' => $ctx['user']['id'], 'trang_thai' => $ctx['giay_phep']['trang_thai'], 'canh_bao' => $ctx['giay_phep']['canh_bao']]);
