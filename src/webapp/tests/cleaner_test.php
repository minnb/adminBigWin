<?php
declare(strict_types=1);

// P04 S2.2 — HeuristicCleaner (port core/heuristic_cleaner.py tại port-baseline) khớp cleaner.json
// (bungDanKep = true, engine cũ) và cleaner_moi.json (bungDanKep = false, engine Mới). Fixture lệch = bug PHP.
// Nhánh quy tắc tự học HOÃN sang P09 (QĐ2) — fixture sinh với quy_tac_tu_hoc=None.

require_once __DIR__ . '/../lib/bigwin/HeuristicCleaner.php';

/** Chỉ số các ca lệch giữa clean() PHP và fixture. */
function bw_cleaner_lech(string $fixture, bool $bungDanKep): array
{
    $c = new HeuristicCleaner(null, $bungDanKep);
    $lech = [];
    foreach (bw_fixture($fixture) as $i => $ca) {
        if ($c->clean($ca['input']) !== $ca['ok']['clean']) {
            $lech[] = $i;
        }
    }
    return $lech;
}

function bw_cleaner_bao_lech(string $fixture, array $lech, bool $bungDanKep): string
{
    $ds = bw_fixture($fixture);
    $c = new HeuristicCleaner(null, $bungDanKep);
    $dong = [count($lech) . ' ca lệch'];
    foreach (array_slice($lech, 0, 5) as $i) {
        $dong[] = json_encode([$i, $ds[$i]['input'], $ds[$i]['ok']['clean'], $c->clean($ds[$i]['input'])], JSON_UNESCAPED_UNICODE);
    }
    return implode("\n", $dong);
}

function test_p04_t03_cleaner_bung_dan_kep_khop_cleaner_json(): void
{
    $lech = bw_cleaner_lech('cleaner', true);
    assert_true($lech === [], bw_cleaner_bao_lech('cleaner', $lech, true));
}

function test_p04_t04_cleaner_moi_khong_bung_dan_kep_khop_cleaner_moi_json(): void
{
    $lech = bw_cleaner_lech('cleaner_moi', false);
    assert_true($lech === [], bw_cleaner_bao_lech('cleaner_moi', $lech, false));
}

function test_p04_t05_chuan_hoa_ky_tu_an_giu_do_dai_va_khop_fixture(): void
{
    $lech = [];
    foreach (bw_fixture('cleaner') as $i => $ca) {
        $ra = HeuristicCleaner::chuanHoaKyTuAn($ca['input']);
        if ($ra !== $ca['ok']['ky_tu_an']) {
            $lech[] = $i;
        }
    }
    assert_same([], array_slice($lech, 0, 10), 'chuanHoaKyTuAn lệch ky_tu_an');
    // Ký tự vô hình → ĐÚNG một dấu cách: độ dài (ký tự) không đổi trên chuỗi đã NFC.
    $vao = "de 12\u{200B}34\u{FEFF}x\u{00A0}10k\u{2060}\u{00AD}\u{202F}\u{200C}\u{200D}";
    $ra = HeuristicCleaner::chuanHoaKyTuAn($vao);
    assert_same(mb_strlen($vao, 'UTF-8'), mb_strlen($ra, 'UTF-8'));
    assert_same('de 12 34 x 10k     ', $ra); // 5 ký tự vô hình ở đuôi → 5 dấu cách
    assert_same('de 12 34 x 10k', (new HeuristicCleaner())->clean($vao), 'Ký tự vô hình KHÔNG được xoá (12 34 ≠ 1234)');
}

function test_p04_t06_nfd_va_nfc_cho_cung_ket_qua(): void
{
    $c = new HeuristicCleaner();
    $soCa = 0;
    $lech = [];
    foreach (bw_fixture('cleaner') as $i => $ca) {
        $nfd = Normalizer::normalize($ca['input'], Normalizer::FORM_D);
        if ($nfd === $ca['input'] || mb_strlen($nfd, 'UTF-8') > HeuristicCleaner::MAX_INPUT_LEN) {
            continue; // không có ký tự tổ hợp / NFD vượt trần (trả nguyên bản) — không phải ca của T06
        }
        $soCa++;
        if ($c->clean($nfd) !== $ca['ok']['clean']) {
            $lech[] = $i;
        }
    }
    assert_true($soCa >= 100, "Phải có ≥ 100 tin có dấu để thử NFD, nhận $soCa");
    assert_same([], array_slice($lech, 0, 10), 'Tin NFD phải dọn ra y hệt bản NFC (fixture)');
    // Output THẬT của Python: NFD("Đề 12 x 2 củ, đá 12 34 x 10k") — 2 củ = 2.000k, KHÔNG phải 2k.
    assert_same('de 12 x 2000k, xien 12 34 x 10k', $c->clean(Normalizer::normalize('Đề 12 x 2 củ, đá 12 34 x 10k', Normalizer::FORM_D)));
}

function test_p04_t07_tieng_long_lam_tron_banker(): void
{
    // Giá trị mong đợi = output THẬT của HeuristicCleaner Python (round() = banker's: 0.5→0, 2.5→2, 1.5→2).
    $ca = [
        'de 12 x 0.5 nghin' => 'de 12 x 0k',
        'de 12 x 1.5 nghin' => 'de 12 x 2k',
        'de 12 x 2.5 nghin' => 'de 12 x 2k',
        'de 12 x 3,5ng' => 'de 12 x 4k',
        'lo 12 x 0.5ng' => 'lo 12 x 0k',
        'de 12 x 2.5 chuc' => 'de 12 x 25k',
        'lo 12 x 1 cu ruoi' => 'lo 12 x 1500k',
        'de 12 x nua lit' => 'de 12 x 50k',
        'de 12 x 1c5' => 'de 12 x 1500k',
        'de 45 x 2l5' => 'de 45 x 250k',
        'lo 12 x 0.0005 cu' => 'lo 12 x 0k',
        'de 12 x 1.0005 cu' => 'de 12 x 1000k',
        'de 12 x 0.0025 lit' => 'de 12 x 0k',
        // 0.5015 × 1000 = 501.49999999999994: Python → 501; round() PHP 8.3 (làm tròn trước 15 chữ số) → 502 (P04-B04).
        'de 12 x 0.5015 cu' => 'de 12 x 501k',
        'de 12 x 0.5035 cu' => 'de 12 x 503k',
        // Số vượt int64: Python in đúng giá trị float → PHP không được tràn int.
        'de 12 x 99999999999999999999999 cu' => 'de 12 x 99999999999999987584860160k',
        // \x1c–\x1f là khoảng trắng với Python (str.isspace), PCRE \s thì không.
        "de 12\x1c10k" => 'de 12 x 10k',
        "\x1c de 12 x 10k \x1f" => 'de 12 x 10k',
    ];
    foreach ([true, false] as $bung) {
        $c = new HeuristicCleaner(null, $bung);
        foreach ($ca as $vao => $mong) {
            assert_same($mong, $c->clean($vao), json_encode($vao) . ($bung ? '' : ' (bungDanKep=false)'));
        }
    }
}

function test_p04_cleaner_quy_tac_tu_hoc_chua_port_bao_loi_ro(): void
{
    // QĐ2: nhánh tự học hoãn sang P09 — truyền quy tắc phải báo lỗi to, không im lặng bỏ qua.
    assert_same('LogicException', bw_test_loi_lop(static fn () => new HeuristicCleaner([['1 lit', '100k']])));
    assert_same('', (new HeuristicCleaner([]))->clean('  '));
}
