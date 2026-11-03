<?php
declare(strict_types=1);

// P02 S1.4 — P02-T16: quét mã chống rò dữ liệu tenant (rule tenant-isolation.md, Q-P02-6). Dùng token_get_all,
// không regex thô trên mã. Mẫu vi phạm có chủ đích: tests/_vi_pham_mau/*.php (dòng "gia_lap" = đường dẫn giả định).

require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/../lib/bigwin/repo/TenantRepo.php';

/** Lớp nền được chạm DB / user_id trực tiếp (tương đối src/webapp). */
const BW_TSCAN_LOP_NEN = [
    'lib/bigwin/TenantContext.php',
    'lib/bigwin/repo/TenantRepo.php',
    'lib/bigwin/repo/SharedRepo.php',
    'lib/bigwin/repo/BoQuetSqlTenant.php',
    'lib/bigwin/repo/ThueBaoRepo.php',
    'lib/bigwin/repo/UserRepo.php', // P03 QĐ4: quản trị tài khoản, chỉ ngữ cảnh admin / hệ thống
    'lib/bigwin/repo/ThongKeRepo.php',
];

/** Tên (không dấu \ đầu) nếu token là tên hàm/lớp, ngược lại null. */
function bw_tscan_ten(mixed $tk): ?string
{
    return is_array($tk) && in_array($tk[0], [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true) ? ltrim($tk[1], '\\') : null;
}

/** Vi phạm của một tệp PHP coi như nằm ở $duong. Trả ["mã_luật:dòng chi tiết", ...]. */
function bw_tscan(string $duong, string $php): array
{
    $vungA = str_starts_with($duong, 'app/') || str_starts_with($duong, 'lib/bigwin/') || str_ends_with($duong, 'Service.php');
    $nen = in_array($duong, BW_TSCAN_LOP_NEN, true);
    $trongRepo = str_starts_with($duong, 'lib/bigwin/repo/');
    $bangCam = [...array_keys(TenantRepo::DANH_SACH_BANG_TENANT), ...TenantRepo::BANG_REPO_RIENG]; // bảng tenant + bảng thuê bao (Q-P02-1)
    $tk = array_values(array_filter(token_get_all($php), static fn (mixed $t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    $vp = [];
    foreach ($tk as $i => $t) {
        $loai = is_array($t) ? $t[0] : null;
        $chu = is_array($t) ? $t[1] : $t;
        $dong = is_array($t) ? $t[2] : 0;
        $truoc = $tk[$i - 1] ?? null;
        $loaiTruoc = is_array($truoc) ? $truoc[0] : null;
        $sau = $tk[$i + 1] ?? null;
        $chuSau = is_array($sau) ? $sau[1] : $sau;
        $laChuoi = in_array($loai, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true);

        if ($vungA && !$nen) {
            $ten = bw_tscan_ten($t);
            if ($ten !== null && strtolower($ten) === 'db' && $chuSau === '('
                && !in_array($loaiTruoc, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                $vp[] = "goi_db:$dong db()";
            }
            if ($loai === T_STRING && in_array(strtolower($chu), ['prepare', 'query', 'exec'], true) && $chuSau === '('
                && in_array($loaiTruoc, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                $vp[] = "goi_db:$dong ->$chu(";
            }
            if ($loai === T_NEW && strtolower((string) bw_tscan_ten($sau)) === 'pdo') {
                $vp[] = "goi_db:$dong new PDO";
            }
            if ($laChuoi && preg_match('/(?<![A-Za-z0-9_])user_id(?![A-Za-z0-9_])/i', $chu)) {
                $vp[] = "user_id_viet_tay:$dong $chu";
            }
            if ($loai === T_VARIABLE && in_array(strtolower($chu), ['$userid', '$user_id'], true)) {
                $vp[] = "tham_so_user_id:$dong $chu";
            }
        }

        if ($duong !== 'lib/bigwin/TenantContext.php') {
            if ($loai === T_NEW && bw_tscan_ten($sau) === 'TenantContext') {
                $vp[] = "tu_tao_ngu_canh:$dong new TenantContext";
            }
            $ham = is_array($tk[$i + 2] ?? null) ? $tk[$i + 2][1] : '';
            if (bw_tscan_ten($t) === 'TenantContext' && is_array($sau) && $sau[0] === T_DOUBLE_COLON && in_array($ham, ['tuPhien', 'choCli', 'heThong'], true)) {
                $choPhep = $ham === 'tuPhien'
                    ? in_array($duong, ['lib/security/AjaxGateway.php', 'app/_layout.php'], true)
                    : str_starts_with($duong, 'tools/');
                if (!$choPhep) {
                    $vp[] = "tu_tao_ngu_canh:$dong TenantContext::$ham";
                }
            }
        }

        if (!$trongRepo && $laChuoi && ($b = BoQuetSqlTenant::nhacBang($chu, $bangCam)) !== null) {
            $vp[] = "ten_bang_ngoai_repo:$dong $b";
        }
    }
    return $vp;
}

/** Mã luật của danh sách vi phạm (không trùng, đã sắp xếp). */
function bw_tscan_ma(array $viPham): array
{
    $ma = array_values(array_unique(array_map(static fn (string $v): string => strstr($v, ':', true), $viPham)));
    sort($ma, SORT_STRING);
    return $ma;
}

function test_p02_t16_bo_quet_bat_moi_mau_vi_pham(): void
{
    $mau = glob(__DIR__ . '/_vi_pham_mau/*.php') ?: [];
    assert_true(count($mau) >= 5, 'Thiếu mẫu vi phạm trong tests/_vi_pham_mau/');
    foreach ($mau as $f) {
        $php = file_get_contents($f);
        assert_true(preg_match('~^// gia_lap: (\S+)$~m', $php, $gl) === 1 && preg_match('~^// mong_doi: (.+)$~m', $php, $md) === 1, basename($f) . ': thiếu dòng gia_lap / mong_doi');
        $mong = array_map('trim', explode(',', $md[1]));
        sort($mong, SORT_STRING);
        assert_same($mong, bw_tscan_ma(bw_tscan($gl[1], $php)), basename($f) . ' (giả lập ' . $gl[1] . ')');
    }
}

function test_p02_t16_bo_quet_khong_bao_nham_ma_hop_le(): void
{
    $hopLe = <<<'PHP'
        <?php
        final class KhachHangRepo extends TenantRepo
        {
            protected const BANG = 'demo_cha';
            public function lay(int $id): ?array { return $this->timMot($id); }
            public function db(): void {}
            public function goi(): void { $this->db(); Lop::db(); }
        }
        PHP;
    assert_same([], bw_tscan('lib/bigwin/repo/KhachHangRepo.php', $hopLe));
    assert_same([], bw_tscan('lib/security/AjaxGateway.php', "<?php \$t = TenantContext::tuPhien(\$u);"), 'Gateway được dựng ngữ cảnh');
    assert_same([], bw_tscan('tools/cap_nhat.php', "<?php \$t = TenantContext::heThong('cron');"), 'tools/ được dùng ngữ cảnh CLI');
    assert_same([], bw_tscan('admin/users.php', "<?php \$m = 'chua_co_thue_bao_demo_cha_x';"), 'Tên bảng là một phần của từ khác không tính');
}

function test_p02_t16_ma_that_khong_vi_pham(): void
{
    $tep = bw_test_tep_php();
    assert_true(count($tep) > 20, 'Quét quá ít tệp (' . count($tep) . ')');
    $loi = [];
    foreach ($tep as $duong => $tuyetDoi) {
        foreach (bw_tscan($duong, file_get_contents($tuyetDoi)) as $v) {
            $loi[] = "$duong → $v";
        }
    }
    assert_same([], $loi, 'Mã thật phải 0 vi phạm TenantScope');
}
