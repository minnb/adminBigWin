<?php
declare(strict_types=1);

// P03 — quét mã khung app: JS chỉ gọi server qua goiApi() (T08); bỏ qua bản quyền chỉ ở endpoint đổi mật khẩu (QĐ5);
// không còn endpoint thử _ping*.php (QĐ2, cổng P03).

require_once __DIR__ . '/_db.php';

const BW_JS_CAM = '/\bfetch\s*\(|XMLHttpRequest|\$\.(?:ajax|get|post|getJSON)\s*\(|\baxios\b|sendBeacon\s*\(|new\s+EventSource\s*\(/';

/** Mã JS bỏ đi thân hàm goiApi() (nơi DUY NHẤT được gọi fetch). */
function bw_js_ngoai_goi_api(string $js): string
{
    return (string) preg_replace('/^async function goiApi\(.*?^}\s*$/ms', '', $js, 1, $dem) . ($dem === 1 ? '' : "\n/*THIEU goiApi*/ fetch(");
}

function test_p03_t08_bo_quet_nhan_dung_mau(): void
{
    $hopLe = "async function goiApi(url, d) {\n  const r = await fetch(url, {});\n  return r;\n}\nfunction x() { return goiApi('a', {}); }\n";
    assert_same(0, preg_match(BW_JS_CAM, bw_js_ngoai_goi_api($hopLe)));
    foreach (["fetch('x')", 'new XMLHttpRequest()', "$.ajax({})", "axios.get('x')", "navigator.sendBeacon('x')"] as $vp) {
        assert_same(1, preg_match(BW_JS_CAM, bw_js_ngoai_goi_api($hopLe . "\nfunction y() { $vp; }\n")), "Phải bắt: $vp");
    }
    assert_same(1, preg_match(BW_JS_CAM, bw_js_ngoai_goi_api("function y() { return 1; }\n")), 'Thiếu goiApi() cũng là vi phạm');
}

function test_p03_t08_khong_goi_server_ngoai_goi_api(): void
{
    $js = file_get_contents(BW_WEBAPP_DIR . '/app/assets/app.js');
    assert_same(0, preg_match(BW_JS_CAM, bw_js_ngoai_goi_api($js), $m), 'app.js gọi server ngoài goiApi(): ' . ($m[0] ?? ''));
    foreach (bw_test_tep_php() as $duong => $tuyetDoi) {
        if (str_starts_with($duong, 'app/') || in_array($duong, ['admin/users.php', 'admin/goi_thue_bao.php'], true)) {
            assert_same(0, preg_match(BW_JS_CAM, file_get_contents($tuyetDoi), $m), "$duong có JS gọi server trực tiếp: " . ($m[0] ?? ''));
        }
    }
}

function test_p03_s16_bo_qua_ban_quyen_chi_o_endpoint_doi_mat_khau(): void
{
    $co = [];
    foreach (bw_test_tep_php() as $duong => $tuyetDoi) {
        if (str_contains(file_get_contents($tuyetDoi), 'bo_qua_ban_quyen')) {
            $co[] = $duong;
        }
    }
    assert_same(['app/ajax/doi_mat_khau.php', 'lib/security/AjaxGateway.php'], $co, 'QĐ5: tuỳ chọn bo_qua_ban_quyen chỉ cho endpoint đổi mật khẩu');
}

function test_p03_s16_khong_con_endpoint_thu(): void
{
    assert_same([], glob(BW_WEBAPP_DIR . '/app/ajax/_ping*.php') ?: [], 'QĐ2: endpoint thử chỉ ở tests/_router.php');
}
