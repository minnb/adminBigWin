<?php
declare(strict_types=1);

// Endpoint THỬ chế độ `ghi` cho bản quyền (P02 S1.5) — xoá ở P03 khi có endpoint nghiệp vụ thật.
// Body {"dem": true} → đếm 1 tin đối soát (thử hạn mức so_tin_ngay_toi_da của gói).

require __DIR__ . '/../../lib/security/AjaxGateway.php';

$ctx = ajax_khoi_dong('ghi');
if (($ctx['du_lieu']['dem'] ?? false) === true) {
    ajax_dem_han_muc($ctx, 'so_tin_doi_soat');
}
ajax_tra(['uid' => $ctx['user']['id'], 'trang_thai' => $ctx['giay_phep']['trang_thai'], 'canh_bao' => $ctx['giay_phep']['canh_bao']]);
