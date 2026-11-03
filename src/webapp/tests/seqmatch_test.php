<?php
declare(strict_types=1);

// P04 S2.1 — SequenceMatcher (port difflib.SequenceMatcher(None, a, b, autojunk=False)) khớp seqmatch.json
// (cấp ký tự + token, sinh từ Python 3.13 tại port-baseline). Fixture lệch = bug PHP.

require_once __DIR__ . '/../lib/bigwin/SequenceMatcher.php';

/** Python round(x, 12) ↔ PHP: so 12 chữ số thập phân (QĐ5) — chuỗi '%.12f' của cả hai phải trùng. */
function bw_ratio_12(float $r): string
{
    return sprintf('%.12f', round($r, 12));
}

/** [opcodes, matching_blocks, ratio-12] của PHP cho một cặp — dạng giống fixture. */
function bw_seqmatch(array $a, array $b): array
{
    $m = new SequenceMatcher($a, $b);
    return [$m->getOpcodes(), $m->getMatchingBlocks(), bw_ratio_12($m->ratio())];
}

function test_p04_t01_seqmatch_khop_100_phan_tram_fixture(): void
{
    $lech = [];
    $ca = bw_fixture('seqmatch');
    foreach ($ca as $i => $c) {
        $mong = [$c['opcodes'], $c['matching_blocks'], bw_ratio_12((float) $c['ratio'])];
        if (bw_seqmatch($c['a'], $c['b']) !== $mong) {
            $lech[] = $i;
        }
    }
    assert_true(count($ca) >= 1400, 'seqmatch.json phải có ≥ 1.400 ca, nhận ' . count($ca));
    assert_same([], array_slice($lech, 0, 20), 'Ca lệch (' . count($lech) . ')');
}

function test_p04_t02_seqmatch_ca_bien(): void
{
    // Giá trị mong đợi = output THẬT của difflib Python 3.13 (cùng phiên bản sinh fixture).
    $ca = [
        [[], [], [], [[0, 0, 0]], 1.0],
        [[], ['a', 'b', 'c'], [['insert', 0, 0, 0, 3]], [[0, 3, 0]], 0.0],
        [['abc'], [], [['delete', 0, 1, 0, 0]], [[1, 0, 0]], 0.0],
        [['a', 'b', 'c'], ['a', 'b', 'c'], [['equal', 0, 3, 0, 3]], [[0, 0, 3], [3, 3, 0]], 1.0],
        [['a', 'b', 'c'], ['x', 'y', 'z'], [['replace', 0, 3, 0, 3]], [[3, 3, 0]], 0.0],
        // Token chuỗi số: "05" ≠ "5" — khoá mảng không được ép về int (QĐ4).
        [['05', '5', '05'], ['5', '05', '05'], [['delete', 0, 1, 0, 0], ['equal', 1, 3, 0, 2], ['insert', 3, 3, 2, 3]], [[1, 0, 2], [3, 3, 0]], 0.666666666667],
        [['q', 'a', 'b', 'x', 'c', 'd'], ['a', 'b', 'y', 'c', 'd', 'f'],
            [['delete', 0, 1, 0, 0], ['equal', 1, 3, 0, 2], ['replace', 3, 4, 2, 3], ['equal', 4, 6, 3, 5], ['insert', 6, 6, 5, 6]],
            [[1, 0, 2], [4, 3, 2], [6, 6, 0]], 0.666666666667],
        [['đề', '12', 'lô'], ['de', '12', 'lo'], [['replace', 0, 1, 0, 1], ['equal', 1, 2, 1, 2], ['replace', 2, 3, 2, 3]], [[1, 1, 1], [3, 3, 0]], 0.333333333333],
    ];
    foreach ($ca as [$a, $b, $op, $mb, $r]) {
        assert_same([$op, $mb, bw_ratio_12($r)], bw_seqmatch($a, $b), json_encode([$a, $b], JSON_UNESCAPED_UNICODE));
    }
    // Chuỗi (không phải mảng) → so theo ký tự UTF-8, không theo byte.
    $m = new SequenceMatcher('đề 12 lô', 'de 12 lo');
    assert_same([['replace', 0, 2, 0, 2], ['equal', 2, 7, 2, 7], ['replace', 7, 8, 7, 8]], $m->getOpcodes());
    assert_same('0.625000000000', bw_ratio_12($m->ratio()));
}
