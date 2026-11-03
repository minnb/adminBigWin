<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bigwin/helpers.php';

// Giá trị mong đợi = output THẬT của BigWIN utils/helpers.py (Python 3.13), không tự suy.

function test_now_vn_luon_utc7(): void
{
    $now = now_vn();
    assert_same('+07:00', $now->format('P'));
    $utc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    assert_true(abs($now->getTimestamp() - $utc->getTimestamp()) <= 2, 'now_vn lệch giờ thực');
    assert_same(1, preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', now_vn_sql()));
    assert_same(1, preg_match('/^\d{2}-\d{2}-\d{4}$/', hom_nay_vn_str()));
    assert_same('00:00:00', today_vn()->format('H:i:s'));
    assert_same(now_vn()->format('Y-m-d'), today_vn()->format('Y-m-d'));
}

function test_ngay_vn_khac_ngay_utc_luc_rang_sang(): void
{
    // Bug "hai đồng hồ" bên Desktop: 22:56 UTC ngày 09 = 05:56 ngày 10 ở VN.
    $utc = new DateTimeImmutable('2026-08-09 22:56:00', new DateTimeZone('UTC'));
    assert_same('10-08-2026', $utc->setTimezone(new DateTimeZone(BW_TZ))->format('d-m-Y'));
}

function test_p04_bw_py_round_khop_round_python(): void
{
    // [x, round(x) Python 3.13] — banker's chính xác trên double, KHÔNG làm tròn trước như round() PHP 8.3.
    $ca = [[0.5, 0.0], [1.5, 2.0], [2.5, 2.0], [-0.5, 0.0], [-1.5, -2.0], [-2.5, -2.0], [501.49999999999994, 501.0],
        [1001.4999999999999, 1001.0], [0.49999999999999994, 0.0], [2.675, 3.0], [1.0000000000000002e+16, 1.0000000000000002e+16], [-0.3, 0.0]];
    foreach ($ca as [$x, $mong]) {
        assert_same($mong, bw_py_round($x), "round($x)");
    }
    assert_true(is_infinite(bw_py_round(INF)) && is_nan(bw_py_round(NAN)));
    assert_same(1002.0, round(1001.4999999999999, 0, PHP_ROUND_HALF_EVEN), 'Bằng chứng P04-B04: round() PHP 8.3 lệch Python — nếu PHP nâng cấp hết lệch, xem lại bw_py_round');
}

function test_p04_bw_py_strip_va_khoang_trang_python(): void
{
    assert_same('de 12', bw_py_strip("\x1c\u{3000}\t de 12 \u{00A0}\x1f\n"));
    assert_same("\u{200B}x", bw_py_strip(" \u{200B}x "), 'U+200B không phải khoảng trắng Python');
    assert_same('', bw_py_strip(" \x1d "));
}

function test_fmt_tien_khop_python(): void
{
    $vao = [0, 5, 999, 1000, 12345, -1234, 1234567.9, '42', 10 ** 12];
    $mongDoi = ['0đ', '5đ', '999đ', '1.000đ', '12.345đ', '-1.234đ', '1.234.567đ', '42đ', '1.000.000.000.000đ'];
    assert_same($mongDoi, array_map('fmt_tien', $vao));
}

function test_fmt_o_trung_khop_python(): void
{
    assert_same(['0', false], fmt_o_trung('LO', 0, 0));
    assert_same(['1.234 điểm', true], fmt_o_trung('LO', 0, 1234));
    assert_same(['80.000', true], fmt_o_trung('DE', 80000));
    assert_same(['0', false], fmt_o_trung('DE', 0));
    assert_same(['-5', false], fmt_o_trung('3C', -5));
}

function test_giai_map_hai_chieu(): void
{
    assert_same(8, count(GIAI_MAP));
    assert_same(GIAI_REVERSE_MAP, array_flip(GIAI_MAP));
}

function test_ky_tu_an_giu_do_dai(): void
{
    $tin = "de 12\u{200B}34 x\u{00A0}10k\u{FEFF}";
    $sach = bw_sanitize($tin);
    assert_same('de 12 34 x 10k ', $sach);
    assert_same(mb_strlen($tin, 'UTF-8'), mb_strlen($sach, 'UTF-8'));
    assert_same(8, count(BW_KY_TU_AN));
}

function test_chuan_hoa_ky_tu_an_nfc(): void
{
    $nfd = "d\u{0065}\u{0302}\u{0300}"; // "đề" kiểu Mac: e + dấu mũ + dấu huyền rời
    assert_same("d\u{1EC1}", bw_chuan_hoa_ky_tu_an($nfd));
}

function test_chuan_hoa_ky_tu_an_khop_fixture_cleaner(): void
{
    $sai = [];
    foreach (bw_fixture('cleaner') as $i => $ca) {
        $thuc = bw_chuan_hoa_ky_tu_an($ca['input']);
        if ($thuc !== $ca['ok']['ky_tu_an']) {
            $sai[] = "#$i " . json_encode($ca['input'], JSON_UNESCAPED_UNICODE);
        }
    }
    assert_same([], array_slice($sai, 0, 10), count($sai) . ' ca lệch fixture');
}

function test_bw_re_unicode_nhu_python(): void
{
    // Python 3: \w khớp chữ có dấu, \d khớp chữ số Unicode (full-width).
    assert_same(1, preg_match(bw_re('^\w+$'), 'Đềlô'));
    assert_same(1, preg_match(bw_re('^\d+$'), '１２'));
    assert_same(1, preg_match(bw_re('^đề$', 'i'), 'ĐỀ'));
    assert_same(0, preg_match(bw_re('^\w+$'), 'a-b'));
    // Delimiter tự chọn khi pattern chứa ~ hoặc #.
    assert_same(1, preg_match(bw_re('a~#%!@`;b'), 'a~#%!@`;b'));
}

function test_named_group_va_nhom_khong_khop_la_null(): void
{
    $m = bw_finditer(bw_re('(?P<kw>đề|lô)(?P<x>x)?\s*(?P<so>\d+)'), 'đề 12, lô 34');
    assert_same(2, count($m));
    assert_same('đề', $m[0]['groups']['kw']);
    assert_same(null, $m[0]['groups']['x']);
    assert_same(null, $m[0]['spans']['x']);
    assert_same([3, 5], $m[0]['spans']['so']);
    assert_same([7, 9], $m[1]['spans']['kw']);
    assert_same([10, 12], $m[1]['spans']['so']);
}

function test_offset_ky_tu_tieng_viet_phuc_tap(): void
{
    // NFC: mỗi chữ có dấu = 1 code point nhưng 2–3 byte UTF-8.
    $s = 'Đặt đề ở Nghệ An: lô 27 x 10k';
    $m = bw_finditer(bw_re('\d+'), $s);
    assert_same([[21, 23], [26, 28]], array_map(static fn($x) => $x['spans'][0], $m));
    assert_same('27', mb_substr($s, 21, 2, 'UTF-8'));
    // NFD: dấu tổ hợp là code point riêng — Python cũng đếm như vậy.
    $nfd = "de\u{0302}\u{0300} 45";
    assert_same([[5, 7]], array_map(static fn($x) => $x['spans'][0], bw_finditer(bw_re('\d+'), $nfd)));
    // Emoji (4 byte) đứng trước.
    $emoji = "🎯😀 lô 99";
    assert_same([[6, 8]], array_map(static fn($x) => $x['spans'][0], bw_finditer(bw_re('\d+'), $emoji)));
}

function test_byte_char_offset_hai_chieu(): void
{
    $s = 'xổ số Miền Bắc 🎯 01';
    for ($c = 0; $c <= mb_strlen($s, 'UTF-8'); $c++) {
        assert_same($c, bw_byte_to_char($s, bw_char_to_byte($s, $c)), "offset ký tự $c");
    }
    assert_same(0, bw_byte_to_char($s, 0));
    assert_same(4, bw_char_to_byte($s, 2)); // "x" 1 byte + "ổ" 3 byte
    assert_same(2, bw_byte_to_char($s, 4));
}

function test_py_center_rjust(): void
{
    assert_same(' A  ', bw_py_center('A', 4));
    assert_same('1 ', bw_py_center('1', 2));
    assert_same(' ab ', bw_py_center('ab', 4));
    assert_same('Đề', bw_py_center('Đề', 1));
    assert_same('  Đề', bw_py_rjust('Đề', 4));
}

function test_build_summary_table_khop_python(): void
{
    $mong = [
        't1' => "╔═════╦═══════════╦════════════╦═══════════╦════════════╦════════════╗\n║ STT ║  TIỀN GHI ║ CHIẾT KHẤU ║ TỔNG ĐÁNH ║ TỔNG TRÚNG ║ TIỀN TRÚNG ║\n╠═════╬═══════════╬════════════╬═══════════╬════════════╬════════════╣\n║  1  ║    10.000 ║      2.300 ║     7.700 ║          0 ║          0 ║\n╟═════╫═══════════╫════════════╫═══════════╫════════════╫════════════╢\n║  2  ║ 1.234.567 ║          0 ║ 1.234.567 ║          1 ║     80.000 ║\n╚═════╩═══════════╩════════════╩═══════════╩════════════╩════════════╝",
        't2' => "╔══════╦═════╗\n║  A   ║  BC ║\n╠══════╬═════╣\n║ Tổng ║   5 ║\n╟══════╫═════╢\n║  Đề  ║ 123 ║\n╚══════╩═════╝",
        't3' => "╔═══╗\n║ X ║\n╠═══╣\n╚═══╝",
        't4' => "╔════╦═══╗\n║ AB ║ C ║\n╠════╬═══╣\n║ 1  ║\n╚════╩═══╝",
        't5' => "╔═══════╦═══╗\n║   A   ║ B ║\n╠═══════╬═══╣\n║ 12345 ║ x ║\n╚═══════╩═══╝",
        't6' => "╔══════╦════╗\n║ TIỀN ║  Y ║\n╠══════╬════╣\n║  ab  ║  1 ║\n╟══════╫════╢\n║ abcd ║ 22 ║\n╚══════╩════╝",
    ];
    assert_same($mong['t1'], build_summary_table([
        ['1', '10.000', '2.300', '7.700', '0', '0'],
        ['2', '1.234.567', '0', '1.234.567', '1', '80.000'],
    ]));
    assert_same($mong['t2'], build_summary_table([['Tổng', '5'], ['Đề', '123']], ['A', 'BC']));
    assert_same($mong['t3'], build_summary_table([], ['X']));
    assert_same($mong['t4'], build_summary_table([['1']], ['AB', 'C']));
    assert_same($mong['t5'], build_summary_table([['12345', 'x']], ['A', 'B']));
    assert_same($mong['t6'], build_summary_table([['ab', '1'], ['abcd', '22']], ['TIỀN', 'Y']));
}
