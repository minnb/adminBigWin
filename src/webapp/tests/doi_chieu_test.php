<?php
declare(strict_types=1);

// P04 S2.6 — DiffAnalyzer (utils/diff_analyzer.py) + DoiChieuTin (core/doi_chieu_tin.py) khớp doi_chieu.json /
// learning.json. Parser (P05) được TIÊM vào DoiChieuTin dạng callable (QĐ1): ở đây tra `report.spans` THẬT của
// Python từ rule_parser.json theo `sach` — đúng thứ parser_facade (engine 'moi') trả khi sinh fixture.

require_once __DIR__ . '/../lib/bigwin/HeuristicCleaner.php';
require_once __DIR__ . '/../lib/bigwin/DiffAnalyzer.php';
require_once __DIR__ . '/../lib/bigwin/DoiChieuTin.php';

/** parse_detailed giả: văn bản đã dọn → ['spans' => …] lấy từ rule_parser.json. */
function bw_parser_gia(): callable
{
    static $theoSach = null;
    if ($theoSach === null) {
        $theoSach = [];
        foreach (bw_fixture('rule_parser') as $ca) {
            $theoSach['k:' . $ca['ok']['sach']] = $ca['ok']['report']['spans'];
        }
    }
    return static function (string $tin) use ($theoSach): array {
        $spans = $theoSach["k:$tin"] ?? throw new BwAssertionFailed('Parser giả: không có spans cho ' . json_encode($tin, JSON_UNESCAPED_UNICODE));
        return ['spans' => $spans];
    };
}

/** Fixture ghi JSON với sort_keys — sắp khoá dict (đệ quy) để === không phụ thuộc thứ tự khoá. */
function bw_sap_khoa(mixed $v): mixed
{
    if (!is_array($v)) {
        return $v;
    }
    $v = array_map('bw_sap_khoa', $v);
    if (!array_is_list($v)) {
        ksort($v, SORT_STRING);
    }
    return $v;
}

function test_p04_t08_doi_chieu_khop_100_phan_tram_fixture(): void
{
    $c = new HeuristicCleaner(null, false);
    $parser = bw_parser_gia();
    $lech = [];
    $ds = bw_fixture('doi_chieu');
    foreach ($ds as $i => $ca) {
        $sach = $c->clean($ca['input']);
        $sach = $sach === '' ? $ca['input'] : $sach; // don_moi(): rỗng → tin gốc
        assert_same($ca['ok']['sach'], $sach, "Ca $i: cleaner Mới lệch sach");
        $kq = DoiChieuTin::danhDauNghiNgo($ca['input'], $sach, $parser);
        if (bw_sap_khoa([$kq['spans'], $kq['thay_doi']]) !== bw_sap_khoa([$ca['ok']['spans'], $ca['ok']['thay_doi']])) {
            $lech[] = $i;
        }
    }
    $dau = $lech[0] ?? null;
    $chiTiet = $dau === null ? '' : json_encode([$ds[$dau]['input'], DoiChieuTin::danhDauNghiNgo($ds[$dau]['input'], $ds[$dau]['ok']['sach'], $parser)['thay_doi'], $ds[$dau]['ok']['thay_doi']], JSON_UNESCAPED_UNICODE);
    assert_true($lech === [], count($lech) . ' ca lệch, vd ' . $chiTiet);
    assert_true(count($ds) >= 1300, 'doi_chieu.json phủ toàn corpus');
}

function test_p04_t08_doi_chieu_offset_theo_ky_tu_va_ban_do_vi_tri(): void
{
    // Tin có chữ nhiều byte: offset spans/thay_doi phải là KÝ TỰ, spans lát kín tin gốc.
    $parser = static fn (string $t): array => ['spans' => [['text' => $t, 'start' => 0, 'end' => mb_strlen($t, 'UTF-8'), 'severity' => null]]];
    $goc = 'Đề 12 x 1 củ rưỡi';
    $sach = (new HeuristicCleaner(null, false))->clean($goc);
    assert_same('de 12 x 1500k', $sach);
    $kq = DoiChieuTin::danhDauNghiNgo($goc, $sach, $parser);
    $noi = implode('', array_column($kq['spans'], 'text'));
    assert_same($goc, $noi, 'spans phải lát kín tin gốc');
    assert_same(mb_strlen($goc, 'UTF-8'), end($kq['spans'])['end']);
    assert_same([['goc' => '1 củ rưỡi', 'thanh' => '1500k', 'start' => 8, 'end' => 17]], $kq['thay_doi']);
    assert_same(0, ($kq['vi_tri_goc'])(-5));
    assert_same(mb_strlen($goc, 'UTF-8'), ($kq['vi_tri_goc'])(999));

    // \x1c–\x1f là khoảng trắng với Python (token \s+) — PCRE \s thì không. Giá trị = output THẬT của Python.
    $ca = [
        ["de 12\x1c\x1c34 x 10k", 'de 12 34 x 10k', [['text' => "de 12\x1c\x1c34 x 10k", 'start' => 0, 'end' => 15, 'severity' => null]], [],
            [0, 1, 2, 3, 4, 5, 7, 8, 9, 10, 11, 12, 13, 14, 15]],
        ["de 12\x1c\x1d+34 x 10k", 'de 12 x 34 x 10k',
            [['text' => 'de ', 'start' => 0, 'end' => 3, 'severity' => null], ['text' => "12\x1c\x1d+34", 'start' => 3, 'end' => 10, 'severity' => 'warn'],
                ['text' => ' x 10k', 'start' => 10, 'end' => 16, 'severity' => null]],
            [['goc' => "12\x1c\x1d+34", 'thanh' => '12 x 34', 'start' => 3, 'end' => 10]],
            [0, 1, 2, 3, 4, 5, 7, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16]],
    ];
    foreach ($ca as [$goc, $sach, $spans, $thayDoi, $banDo]) {
        $kq = DoiChieuTin::danhDauNghiNgo($goc, $sach, $parser);
        assert_same([$spans, $thayDoi], [$kq['spans'], $kq['thay_doi']], json_encode($goc));
        assert_same($banDo, array_map($kq['vi_tri_goc'], range(0, mb_strlen($sach, 'UTF-8'))));
    }
}

function test_p04_t09_trich_xuat_tat_ca_khac_biet_khop_learning_va_doi_chieu(): void
{
    $lech = [];
    $tin = bw_fixture('learning')['tin'];
    foreach ($tin as $i => $ca) {
        // Cột thứ 3 của cac_cap (quy_tac_hop_le) thuộc learning_service — P09, không so ở đây.
        $mong = array_map(static fn (array $c): array => [$c[0], $c[1]], $ca['ok']['cac_cap']);
        if (DiffAnalyzer::trichXuatTatCaKhacBiet($ca['tin_goc'], $ca['tin_da_sua']) !== $mong) {
            $lech[] = "learning#$i";
        }
    }
    foreach (bw_fixture('doi_chieu') as $i => $ca) {
        if (DiffAnalyzer::trichXuatTatCaKhacBiet($ca['input'], $ca['ok']['sach']) !== $ca['ok']['khac_biet']) {
            $lech[] = "doi_chieu#$i";
        }
    }
    assert_true(count($tin) >= 300, 'learning.tin ≥ 300 cặp');
    assert_same([], array_slice($lech, 0, 10), count($lech) . ' cặp lệch');
}

function test_p04_t09_diff_analyzer_bat_bien(): void
{
    // Đầu vào không hợp lệ / giống nhau / chỉ CHÈN → rỗng (bất biến 2: không bịa quy tắc từ hư không).
    assert_same([], DiffAnalyzer::trichXuatTatCaKhacBiet(null, 'de 12'));
    assert_same([], DiffAnalyzer::trichXuatTatCaKhacBiet('de 12 x 10k', 'de 12 x 10k'));
    assert_same([], DiffAnalyzer::trichXuatTatCaKhacBiet('de 12 10k', 'de 12 x 10k'));
    assert_same(['', ''], DiffAnalyzer::trichXuatDiemKhacBiet('', 'x'));
    // Nới mảnh đơn vị tiền dính số: "10k" → "10n" chứ không phải "k" → "n".
    assert_same([['10k', '10n']], DiffAnalyzer::trichXuatTatCaKhacBiet('lo 12 x 10k', 'lo 12 x 10n'));
    // Ưu tiên khác biệt cấp token hơn khoảng trắng.
    assert_same(['1 lít', '100k'], DiffAnalyzer::trichXuatDiemKhacBiet('lo12 x 1 lít', 'lo 12 x 100k'));
    // Khoảng cách chứa "\n" là ranh giới "từ" — không nới qua (output Python: 12abc → 12 abc, KHÔNG kéo cả "lo\n").
    assert_same([['12abc', '12 abc']], DiffAnalyzer::trichXuatTatCaKhacBiet("lo\n12abc", "lo\n12 abc"));
}
