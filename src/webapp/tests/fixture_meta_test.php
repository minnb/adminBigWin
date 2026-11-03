<?php
declare(strict_types=1);

// P00 — kiểm bộ Golden Fixture (tests/fixtures/*.json, sinh bởi D:\Projects\bigwin\scripts\export_golden.py tại tag
// `port-baseline`): cùng một commit sạch (T01), đủ số ca tối thiểu (T02), exporter không ném lỗi (T03).
// Fixture lệch = bug PHP, KHÔNG sửa fixture (rule bigwin-core.md) — test này chỉ khoá NGUỒN GỐC & ĐỘ PHỦ của fixture.

const BW_FIXTURE_DU = [
    'cleaner', 'parser', 'tinh_tien', 'do_trung', 'seqmatch', 'learning', // engine cũ (S0.4 — xuất lại tại tag)
    'cleaner_moi', 'rule_parser', 'dan_so', 'tong_hop', 'doi_chieu', 'pipeline', // engine Mới (S0.5)
];
/** Ca `{"loi": …}` (exporter ghi nhận exception) được phép — rỗng: mọi ca hiện chạy được bên Python. */
const BW_FIXTURE_LOI_CHO_PHEP = [];

/** ['meta' => …, 'data' => …] của tests/fixtures/<ten>.json. */
function bw_fixture_day_du(string $ten): array
{
    static $daDoc = [];
    return $daDoc[$ten] ??= json_decode((string) file_get_contents(BW_TESTS_DIR . "/fixtures/$ten.json"), true, 512, JSON_THROW_ON_ERROR);
}

/** Số ca `{"loi": "<TênException>"}` (không có "ok") ở mọi độ sâu. */
function bw_fixture_dem_loi(mixed $v): int
{
    if (!is_array($v)) {
        return 0;
    }
    $dem = isset($v['loi']) && is_string($v['loi']) && !array_key_exists('ok', $v) && count($v) <= 4 ? 1 : 0;
    foreach ($v as $con) {
        $dem += bw_fixture_dem_loi($con);
    }
    return $dem;
}

function test_p00_t01_du_12_fixture_cung_mot_commit_sach(): void
{
    $tep = array_map(static fn (string $f): string => basename($f, '.json'), glob(BW_TESTS_DIR . '/fixtures/*.json') ?: []);
    sort($tep, SORT_STRING);
    $mong = BW_FIXTURE_DU;
    sort($mong, SORT_STRING);
    assert_same($mong, $tep, 'Đúng 12 tệp Golden Fixture');
    $commit = [];
    foreach (BW_FIXTURE_DU as $ten) {
        $commit[$ten] = bw_fixture_day_du($ten)['meta']['bigwin_commit'] ?? '';
    }
    $khac = array_values(array_unique($commit));
    assert_same(1, count($khac), 'Mọi fixture phải cùng meta.bigwin_commit: ' . json_encode($commit));
    assert_true(preg_match('/^[0-9a-f]{7,40}$/', $khac[0]) === 1, "bigwin_commit phải là hash commit sạch (không -dirty / unknown), nhận: {$khac[0]}");
}

function test_p00_t02_so_ca_dat_toi_thieu(): void
{
    $corpus = bw_fixture_day_du('cleaner_moi')['meta']['so_tin_corpus'] ?? 0;
    assert_true($corpus >= 1300, "Corpus ≥ 1.300 tin, nhận $corpus");
    foreach (['cleaner', 'parser', 'cleaner_moi', 'rule_parser', 'doi_chieu'] as $ten) {
        assert_same($corpus, count(bw_fixture_day_du($ten)['data']), "$ten.json phải phủ TOÀN BỘ corpus");
    }
    $danSo = bw_fixture_day_du('dan_so')['data'];
    assert_true(count($danSo['kiem_tra_dan']) >= 200, 'dan_so.kiem_tra_dan ≥ 200 ca');
    assert_true(count($danSo['anh_huong']) >= 30, 'dan_so.anh_huong ≥ 30 ca');
    assert_true(count($danSo['tach_danh_sach_so']) > 0 && count($danSo['tach_khoa']) > 0, 'dan_so có ca tach_danh_sach_so / tach_khoa');
    assert_true(count(bw_fixture_day_du('tong_hop')['data']) >= 100, 'tong_hop ≥ 100 ca');
    $pipeline = bw_fixture_day_du('pipeline')['data']['cases'];
    $theoKhach = array_count_values(array_map(static fn (array $c): string => var_export($c['khach_hang_id'], true), $pipeline));
    assert_same(3, count($theoKhach), 'pipeline: đúng 3 bộ hệ số (mặc định, khách 7, khách 8)');
    assert_true(min($theoKhach) >= 300, 'pipeline: mỗi bộ hệ số ≥ 300 tin, nhận ' . json_encode($theoKhach));
    foreach (['cleaner', 'parser', 'tinh_tien', 'do_trung', 'seqmatch', 'learning'] as $ten) {
        assert_true(bw_fixture_day_du($ten)['data'] !== [], "$ten.json không rỗng");
    }
}

function test_p00_t02_kiem_tra_dan_phu_moi_loai_loi(): void
{
    $loi = [];
    foreach (bw_fixture_day_du('dan_so')['data']['kiem_tra_dan'] as $ca) {
        foreach ($ca['ok'] ?? [] as $l) {
            $loi[] = $l;
        }
    }
    $tatCa = implode("\n", $loi);
    foreach ([
        'tên trùng' => "đã có dàn khác dùng", 'nhóm sai' => 'không hợp lệ', 'Bộ/Tổng/Chia áp LÔ' => 'chỉ áp cho ĐỀ',
        'khoá hệ thống' => 'trùng từ khoá hệ thống', 'khoá trùng dàn khác' => 'đã thuộc dàn', 'khoá trùng dàn TẮT' => "đã thuộc dàn 'dan_tat'",
        'sai tiền tố họ' => 'tiền tố của nhóm', 'trùng tham số bo 2 ≡ bo 02' => 'trùng tham số', 'từ cấm trong cụm' => 'chứa từ khoá hệ thống',
        'dạng tiền' => 'dạng tiền cược', 'khoá toàn số' => 'phải có chữ cái', 'số trùng' => 'bị trùng', 'số > 2 chữ số' => 'phải đủ 2 chữ số',
    ] as $loai => $mau) {
        assert_true(str_contains($tatCa, $mau), "kiem_tra_dan thiếu ca lỗi: $loai");
    }
    assert_true(in_array([], array_column(bw_fixture_day_du('dan_so')['data']['kiem_tra_dan'], 'ok'), true), 'Có ca dàn hợp lệ (không lỗi)');
}

function test_p00_t02_pipeline_co_ca_trung_moi_loai_cuoc(): void
{
    $trung = [];
    foreach (bw_fixture_day_du('pipeline')['data']['cases'] as $ca) {
        foreach ($ca['ok']['items_detail'] ?? [] as $it) {
            if ($it['ket_qua'] === 'TRÚNG') {
                $trung[$it['loai_so']] = true;
            }
        }
    }
    $loai = array_keys($trung);
    sort($loai, SORT_STRING);
    assert_same(['3C', 'DE', 'LO', 'LX2', 'LX3', 'LX4'], $loai, 'pipeline phải có ca TRÚNG ở cả 6 loại cược');
}

function test_p00_t03_khong_ca_nao_exporter_nem_loi(): void
{
    $dem = [];
    foreach (BW_FIXTURE_DU as $ten) {
        $n = bw_fixture_dem_loi(bw_fixture_day_du($ten)['data']);
        if ($n > 0 && !in_array($ten, BW_FIXTURE_LOI_CHO_PHEP, true)) {
            $dem[$ten] = $n;
        }
    }
    assert_same([], $dem, 'Exporter ném lỗi ở các ca ngoài danh sách cho phép');
}
