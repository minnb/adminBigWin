<?php
declare(strict_types=1);

// Port utils/diff_analyzer.py (BigWIN @ port-baseline) — so tin TRƯỚC / SAU khi user sửa, rút cặp (cụm gốc, cụm thay).
// THUẦN. Hai bất biến: cụm trả về CẮT NGUYÊN VĂN từ chuỗi vào (đã NFC + strip); CHÈN thuần bị bỏ qua (không bịa
// quy tắc vế-trái-rỗng). Diff cấp token (cụm chữ HOẶC cụm số), offset theo KÝ TỰ.

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/SequenceMatcher.php';

final class DiffAnalyzer
{
    public const MAX_SO_TOKEN = 3000;

    /** _DON_VI_TIEN | _TU_KHOA_CUOC — chỉ các mảnh chữ này được NỚI ngược vào cụm số đứng trước. */
    private const MANH_DUOC_NO = [
        'k' => true, 'n' => true, 'd' => true, 'đ' => true,
        'lit' => true, 'lít' => true, 'cu' => true, 'củ' => true, 'chuc' => true, 'chục' => true,
        'loet' => true, 'loét' => true, 'xu' => true, 'nghin' => true, 'nghìn' => true,
        'tr' => true, 'trieu' => true, 'triệu' => true,
        'de' => true, 'đe' => true, 'đề' => true, 'lo' => true, 'lô' => true,
        'xien' => true, 'xiên' => true, 'xq' => true, '3c' => true, '3cang' => true, 'cang' => true, 'càng' => true,
        'bo' => true, 'tong' => true, 'tổng' => true, 'chia' => true,
        'dau' => true, 'đầu' => true, 'dit' => true, 'đít' => true, 'cham' => true, 'chạm' => true,
        'kep' => true, 'kép' => true, 'x' => true,
    ];

    /** Mọi cặp [cụm gốc, cụm thay] theo thứ tự xuất hiện trong tin gốc. Không ném lỗi; vào không phải chuỗi → []. */
    public static function trichXuatTatCaKhacBiet(mixed $old, mixed $new): array
    {
        return array_map(static fn (array $x): array => [$x[1], $x[2]], self::phanTich($old, $new));
    }

    /** MỘT cặp tiêu biểu: ưu tiên khác biệt cấp token hơn khoảng trắng; ['', ''] nếu không có gì. */
    public static function trichXuatDiemKhacBiet(mixed $old, mixed $new): array
    {
        $khacBiet = self::phanTich($old, $new);
        foreach ($khacBiet as [, $goc, $moi, $laToken]) {
            if ($laToken) {
                return [$goc, $moi];
            }
        }
        return $khacBiet ? [$khacBiet[0][1], $khacBiet[0][2]] : ['', ''];
    }

    /** → list<[giá trị đã lower, start, end]> (offset ký tự). */
    private static function tachToken(string $text): array
    {
        $out = [];
        foreach (bw_finditer(bw_re('[^\W\d_]+|\d+'), $text) as $m) {
            $out[] = [mb_strtolower($m['groups'][0], 'UTF-8'), ...$m['spans'][0]];
        }
        return $out;
    }

    /** Cắt NGUYÊN VĂN, phủ trọn các token [dau, cuoi). */
    private static function cat(string $text, array $tok, int $dau, int $cuoi): string
    {
        if ($dau >= $cuoi) {
            return '';
        }
        return mb_substr($text, $tok[$dau][1], $tok[$cuoi - 1][2] - $tok[$dau][1], 'UTF-8');
    }

    /** Đoạn nằm giữa token k và k+1. */
    private static function khoangCach(string $text, array $tok, int $k): string
    {
        return mb_substr($text, $tok[$k][2], $tok[$k + 1][1] - $tok[$k][2], 'UTF-8');
    }

    private static function dinhLienMotChuoi(string $text, array $tok, int $k): bool
    {
        return preg_match(bw_re(BW_PY_S), self::khoangCach($text, $tok, $k)) === 0;
    }

    /** s.isalpha() Python: khác rỗng, mọi ký tự là chữ cái (\p{L}). */
    private static function chiCoChu(string $s): bool
    {
        return preg_match(bw_re('^\p{L}+\z'), $s) === 1;
    }

    private static function laManhDangNho(string $s): bool
    {
        return self::chiCoChu($s) && isset(self::MANH_DUOC_NO[mb_strtolower($s, 'UTF-8')]);
    }

    /** Nới block replace sang TRÁI khi cụm đổi chỉ là mảnh đơn vị tiền / từ khoá dính số ("10k" → "10n"). */
    private static function noManhChu(string $old, string $new, array $tokOld, array $tokNew, int $i1, int $i2, int $j1, int $j2): array
    {
        while ($i1 > 0 && $j1 > 0
            && self::laManhDangNho(self::cat($old, $tokOld, $i1, $i2))
            && self::laManhDangNho(self::cat($new, $tokNew, $j1, $j2))
            && self::dinhLienMotChuoi($old, $tokOld, $i1 - 1)
            && self::dinhLienMotChuoi($new, $tokNew, $j1 - 1)) {
            $i1--;
            $j1--;
        }
        return [$i1, $j1];
    }

    /** Khác biệt KHOẢNG CÁCH trong một đoạn equal ("lo12" → "lo 12"), nới ra trọn "từ" dính liền ở cả hai chuỗi. */
    private static function timKhacBietKhoangTrang(string $old, string $new, array $tokOld, array $tokNew, int $i1, int $i2, int $j1, int $j2): array
    {
        $lech = $j1 - $i1;
        $gap = static fn (int $k): array => [self::khoangCach($old, $tokOld, $k), self::khoangCach($new, $tokNew, $k + $lech)];
        // Python chỉ xét ' ', '\t', '\n' ở đây (không phải mọi khoảng trắng) — giữ nguyên văn.
        $coTrang = static fn (string $g): bool => str_contains($g, ' ') || str_contains($g, "\t") || str_contains($g, "\n");
        $dinhLien = static function (int $k) use ($gap, $coTrang): bool {
            [$gOld, $gNew] = $gap($k);
            return !$coTrang($gOld) && !$coTrang($gNew);
        };

        $khoang = [];
        for ($k = $i1; $k < $i2 - 1; $k++) {
            [$gOld, $gNew] = $gap($k);
            if ($gOld === $gNew) {
                continue;
            }
            [$dau, $cuoi] = [$k, $k + 1];
            while ($dau > $i1 && $dinhLien($dau - 1)) {
                $dau--;
            }
            while ($cuoi < $i2 - 1 && $dinhLien($cuoi)) {
                $cuoi++;
            }
            $khoang[] = [$dau, $cuoi];
        }

        $gop = [];
        foreach ($khoang as [$dau, $cuoi]) {
            $n = count($gop);
            if ($n && $dau <= $gop[$n - 1][1]) {
                $gop[$n - 1][1] = max($gop[$n - 1][1], $cuoi);
            } else {
                $gop[] = [$dau, $cuoi];
            }
        }

        return array_map(static fn (array $g): array => [
            $tokOld[$g[0]][1],
            self::cat($old, $tokOld, $g[0], $g[1] + 1),
            self::cat($new, $tokNew, $g[0] + $lech, $g[1] + $lech + 1),
            false,
        ], $gop);
    }

    /** → list<[vị trí trong tin gốc, cụm gốc, cụm mới, là khác biệt token?]> sắp theo vị trí (ổn định). */
    private static function phanTich(mixed $textOld, mixed $textNew): array
    {
        if (!is_string($textOld) || !is_string($textNew)) {
            return [];
        }
        $old = bw_py_strip(bw_nfc($textOld));
        $new = bw_py_strip(bw_nfc($textNew));
        if ($old === '' || $new === '' || $old === $new) {
            return [];
        }
        $tokOld = self::tachToken($old);
        $tokNew = self::tachToken($new);
        if (!$tokOld || !$tokNew || count($tokOld) > self::MAX_SO_TOKEN || count($tokNew) > self::MAX_SO_TOKEN) {
            return [];
        }

        $matcher = new SequenceMatcher(array_column($tokOld, 0), array_column($tokNew, 0));
        $ketQua = [];
        foreach ($matcher->getOpcodes() as [$tag, $i1, $i2, $j1, $j2]) {
            if ($tag === 'equal') {
                array_push($ketQua, ...self::timKhacBietKhoangTrang($old, $new, $tokOld, $tokNew, $i1, $i2, $j1, $j2));
            } elseif ($tag === 'replace' || $tag === 'delete') {
                [$i1, $j1] = self::noManhChu($old, $new, $tokOld, $tokNew, $i1, $i2, $j1, $j2);
                $ketQua[] = [$tokOld[$i1][1], self::cat($old, $tokOld, $i1, $i2), self::cat($new, $tokNew, $j1, $j2), true];
            }
            // insert: bỏ qua — không có cụm gốc để khớp (bất biến 2).
        }
        usort($ketQua, static fn (array $a, array $b): int => $a[0] <=> $b[0]); // usort ổn định (PHP ≥ 8) như sort Python
        return $ketQua;
    }
}
