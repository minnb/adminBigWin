<?php
declare(strict_types=1);

// P01 S1.3 — quét mã: mọi endpoint app/ajax/ đi qua gateway; phiên chỉ mở qua PhienBaoMat.

require_once __DIR__ . '/_db.php';

/** Câu lệnh thực thi ĐẦU TIÊN của tệp PHP, bỏ qua thẻ mở, comment, declare(...), require/include. */
function bw_scan_cau_lenh_dau(string $php): string
{
    $boQua = [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    $toi = [T_DECLARE, T_REQUIRE, T_REQUIRE_ONCE, T_INCLUDE, T_INCLUDE_ONCE];
    $cau = '';
    $dangBoCau = false;
    foreach (token_get_all($php) as $tk) {
        [$loai, $chu] = is_array($tk) ? [$tk[0], $tk[1]] : [null, $tk];
        if ($dangBoCau) {
            $dangBoCau = $chu !== ';';
            continue;
        }
        if ($cau === '' && in_array($loai, $boQua, true)) {
            continue;
        }
        if ($cau === '' && in_array($loai, $toi, true)) {
            $dangBoCau = true;
            continue;
        }
        if ($chu === ';') {
            return trim($cau);
        }
        $cau .= $chu;
    }
    return trim($cau);
}

function bw_scan_qua_gateway(string $php): bool
{
    return preg_match('/^(?:\$\w+\s*=\s*)?ajax_khoi_dong\s*\(/', bw_scan_cau_lenh_dau($php)) === 1;
}

function test_p01_t27_bo_quet_nhan_dung_mau(): void
{
    assert_true(bw_scan_qua_gateway("<?php\ndeclare(strict_types=1);\n// x\nrequire __DIR__ . '/a.php';\n\$ctx = ajax_khoi_dong('doc');\necho 1;"));
    assert_true(bw_scan_qua_gateway("<?php ajax_khoi_dong('ghi', ['get' => true]);"));
    assert_true(!bw_scan_qua_gateway("<?php\nrequire 'a.php';\n\$pdo = db();\n\$ctx = ajax_khoi_dong('doc');"), 'Có lệnh khác chạy TRƯỚC gateway');
    assert_true(!bw_scan_qua_gateway("<?php\necho json_encode([]);"));
}

function test_p01_t27_moi_endpoint_ajax_qua_gateway_o_cau_dau(): void
{
    $tep = glob(BW_WEBAPP_DIR . '/app/ajax/*.php') ?: [];
    assert_true($tep !== [], 'Phải có ít nhất endpoint thử _ping.php');
    foreach ($tep as $f) {
        assert_true(bw_scan_qua_gateway(file_get_contents($f)), basename($f) . ': câu lệnh đầu tiên phải là ajax_khoi_dong(...)');
    }
}

function test_p01_s13_session_start_chi_trong_phien_bao_mat(): void
{
    $choPhep = ['lib/security/PhienBaoMat.php', 'admin/_auth.php']; // admin/_auth.php: phiên admin cũ, bỏ ở P03
    $tim = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath(BW_WEBAPP_DIR), FilesystemIterator::SKIP_DOTS));
    $goc = strlen(realpath(BW_WEBAPP_DIR)) + 1;
    foreach ($tim as $f) {
        $duong = str_replace('\\', '/', substr($f->getPathname(), $goc));
        if ($f->getExtension() !== 'php' || str_starts_with($duong, 'tests/') || $duong === 'config.php' || in_array($duong, $choPhep, true)) {
            continue;
        }
        assert_true(!preg_match('/\bsession_start\s*\(/', file_get_contents($f->getPathname())), "$duong gọi session_start() — phải dùng PhienBaoMat::batDau()");
    }
}
