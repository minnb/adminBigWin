<?php
declare(strict_types=1);

// Port core/doi_chieu_tin.py (BigWIN @ port-baseline) — đối chiếu tin GỐC của khách với bản app THỰC SỰ dùng để tính
// tiền (output cleaner). THUẦN. Trả trên TOẠ ĐỘ TIN GỐC (offset KÝ TỰ):
//   - spans      lát kín tin gốc, severity lấy từ parse bản ĐÃ DỌN; đoạn cleaner tự viết lại → tối thiểu "warn";
//   - thay_doi   [{goc, thanh, start, end}] — app hiểu đoạn gốc nào thành cái gì;
//   - vi_tri_goc Closure(int): int — offset bản đã dọn → offset tin gốc.
// Python gọi parser_facade.parse_detailed() trực tiếp; ở đây parser được TIÊM vào (QĐ1 P04) dạng
// callable(string $tin): array{spans: list<array{text, start, end, severity}>} cho tới khi có RuleParser (P05).

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/SequenceMatcher.php';

final class DoiChieuTin
{
    // = MessageParser.SEVERITY_* / MAX_INPUT_LEN — khai báo tạm tới P05, rồi trỏ về MessageParser.
    public const SEVERITY_WARN = 'warn';
    public const SEVERITY_ERROR = 'error';
    public const MAX_KY_TU_DOI_CHIEU = 10000;

    // Cửa sổ tìm điểm khớp lại (token mỗi bên) và số token liền nhau phải khớp để chấp nhận.
    private const CUA_SO_KHOP_LAI = 48;
    private const SO_TOKEN_XAC_NHAN = 3;
    /** `*`/`×` vốn là dấu nhân — đổi thành `x` không đổi nghĩa. */
    private const DAU_NHAN_SAN = ['*' => true, '×' => true];
    /** Dấu tổ hợp rời của tin NFD — không bao giờ khớp ký tự nào. */
    private const DAU_TO_HOP = "\x00";

    public static function danhDauNghiNgo(string $goc, string $daDon, callable $parseDetailed): array
    {
        $daDon = $daDon === '' ? $goc : $daDon; // `da_don or goc`
        $g = mb_str_split($goc, 1, 'UTF-8');
        $d = mb_str_split($daDon, 1, 'UTF-8');
        $n = count($g);
        $m = count($d);

        if ($n > self::MAX_KY_TU_DOI_CHIEU || $m > self::MAX_KY_TU_DOI_CHIEU) {
            $spans = array_map(
                static fn (array $s): array => ['text' => $s['text'], 'start' => $s['start'], 'end' => $s['end'], 'severity' => $s['severity']],
                $parseDetailed($goc)['spans'],
            );
            return ['spans' => $spans, 'thay_doi' => [], 'vi_tri_goc' => static fn (int $i): int => max(0, min($i, $n))];
        }

        // Mức độ từng ký tự của bản ĐÃ DỌN — đúng thứ đã được tính tiền.
        $mucDon = $m ? array_fill(0, $m, null) : [];
        foreach ($parseDetailed($daDon)['spans'] as $s) {
            for ($k = $s['start']; $k < $s['end']; $k++) {
                $mucDon[$k] = $s['severity'];
            }
        }
        $mucGoc = $n ? array_fill(0, $n, null) : [];
        $banDo = array_fill(0, $m + 1, 0); // offset bản đã dọn → offset tin gốc
        $doanDoi = []; // [a, b, c, d] các đoạn cleaner viết lại có nghĩa

        /** Một opcode CẤP KÝ TỰ (toạ độ tuyệt đối). */
        $xuLyKyTu = static function (string $tag, int $i1, int $i2, int $j1, int $j2) use (&$banDo, &$mucGoc, &$doanDoi, $mucDon, $g, $d): void {
            for ($j = $j1; $j < $j2; $j++) {
                $banDo[$j] = $tag === 'equal' ? $i1 + ($j - $j1) : $i1;
            }
            if ($tag === 'equal') {
                for ($k = 0; $k < $i2 - $i1; $k++) {
                    $mucGoc[$i1 + $k] = $mucDon[$j1 + $k];
                }
                return;
            }
            if (($tag === 'replace' || $tag === 'delete') && self::coChuSo(array_slice($g, $i1, $i2 - $i1))) {
                $doanDoi[] = [...self::noRaCaToken($g, $i1, $i2), ...self::noRaCaToken($d, $j1, $j2)];
                return;
            }
            // Dấu câu (không phải dấu nhân sẵn có) bị đổi thành dấu nhân `x`. Chèn `x` thuần giữa hai dấu cách
            // ("de 12 10k") KHÔNG tính — khách quên `x` rất phổ biến.
            $tu = max(0, $i1 - 1);
            $cuaSo = array_slice($g, $tu, $i2 + 1 - $tu);
            if (self::coDauNhanMoi($d, $j1, $j2) && self::coDauCauKhacDauNhan($cuaSo)) {
                $doanDoi[] = [...self::noQuaDauCau($g, $i1, $i2), ...self::noQuaDauCau($d, $j1, $j2)];
            }
        };

        $tg = self::token($goc);
        $td = self::token($daDon);
        $viTri = static fn (array $tokens, int $i, int $cuoi): int => $i < count($tokens) ? $tokens[$i][1] : $cuoi;

        foreach (self::canToken(array_column($tg, 0), array_column($td, 0)) as [$tag, $t1, $t2, $u1, $u2]) {
            $i1 = $viTri($tg, $t1, $n);
            $i2 = $viTri($tg, $t2, $n);
            $j1 = $viTri($td, $u1, $m);
            $j2 = $viTri($td, $u2, $m);
            if ($tag === 'equal') {
                for ($p = 0; $p < $t2 - $t1; $p++) {
                    [, $gs, $ge] = $tg[$t1 + $p];
                    [, $ds, $de] = $td[$u1 + $p];
                    if ($ge - $gs === $de - $ds) {
                        $xuLyKyTu('equal', $gs, $ge, $ds, $de);
                        continue;
                    }
                    // khoảng trắng dài ngắn khác nhau
                    for ($j = $ds; $j < $de; $j++) {
                        $banDo[$j] = $gs;
                    }
                    for ($k = $gs; $k < $ge; $k++) {
                        $mucGoc[$k] = $mucDon[$ds];
                    }
                }
                continue;
            }
            // Đoạn khác nhau (thường ngắn): so tiếp TỪNG KÝ TỰ để "3c" → "3cang" hay "lo12" → "lo 12" chỉ là CHÈN.
            $sm = new SequenceMatcher(
                array_map(self::khoa(...), array_slice($g, $i1, $i2 - $i1)),
                array_map(self::khoa(...), array_slice($d, $j1, $j2 - $j1)),
            );
            foreach ($sm->getOpcodes() as [$st, $a1, $a2, $b1, $b2]) {
                $xuLyKyTu($st, $i1 + $a1, $i1 + $a2, $j1 + $b1, $j1 + $b2);
            }
        }
        $banDo[$m] = $n;

        // Gộp các đoạn chồng/chạm nhau (nới token làm chúng giao nhau).
        sort($doanDoi); // list 4 int cùng độ dài → so từng phần tử như list Python
        $gop = [];
        foreach ($doanDoi as [$a, $b, $c, $dd]) {
            $cuoi = count($gop) - 1;
            if ($cuoi >= 0 && $a <= $gop[$cuoi][1]) {
                $gop[$cuoi][1] = max($gop[$cuoi][1], $b);
                $gop[$cuoi][2] = min($gop[$cuoi][2], $c);
                $gop[$cuoi][3] = max($gop[$cuoi][3], $dd);
            } else {
                $gop[] = [$a, $b, $c, $dd];
            }
        }

        $thayDoi = [];
        foreach ($gop as [$a, $b, $c, $dd]) {
            // max(muc_don[c:d] or [None], key=_HANG): phần tử ĐẦU TIÊN có hạng cao nhất.
            $nangNhat = null;
            foreach (array_slice($mucDon, $c, $dd - $c) as $x) {
                if (self::hang($x) > self::hang($nangNhat)) {
                    $nangNhat = $x;
                }
            }
            $muc = self::hang($nangNhat) > self::hang(self::SEVERITY_WARN) ? $nangNhat : self::SEVERITY_WARN;
            for ($k = $a; $k < $b; $k++) {
                if (self::hang($mucGoc[$k]) < self::hang($muc)) {
                    $mucGoc[$k] = $muc;
                }
            }
            $thayDoi[] = [
                'goc' => trim(implode('', array_slice($g, $a, $b - $a)), " ,;.\n"),
                'thanh' => trim(implode('', array_slice($d, $c, $dd - $c)), " ,;.\n"),
                'start' => $a,
                'end' => $b,
            ];
        }

        return [
            'spans' => self::gopSpans($g, $mucGoc),
            'thay_doi' => $thayDoi,
            'vi_tri_goc' => static fn (int $i): int => $banDo[max(0, min($i, $m))],
        ];
    }

    private static function hang(?string $muc): int
    {
        return match ($muc) {
            null => 0,
            self::SEVERITY_WARN => 1,
            self::SEVERITY_ERROR => 2,
        };
    }

    // ── Vị từ ký tự kiểu Python (một ký tự) ───────────────────────────────

    private static function laKhoangTrang(string $ch): bool // str.isspace
    {
        return preg_match(bw_re('^' . BW_PY_S . '\z'), $ch) === 1;
    }

    private static function laChuSo(string $ch): bool // str.isalnum = \p{L} ∪ \p{N}
    {
        return preg_match(bw_re('^[^\W_]\z'), $ch) === 1;
    }

    private static function laChu(string $ch): bool // str.isalpha = \p{L}
    {
        return preg_match(bw_re('^\p{L}\z'), $ch) === 1;
    }

    /** Khoá so sánh 1-1 theo ký tự: bỏ hoa/thường, bỏ dấu, gộp khoảng trắng. */
    private static function khoa(string $ch): string
    {
        static $nho = [];
        if (isset($nho["k:$ch"])) {
            return $nho["k:$ch"];
        }
        if (self::laKhoangTrang($ch)) {
            return $nho["k:$ch"] = ' ';
        }
        $c = mb_strtolower($ch, 'UTF-8');
        if ($c === 'đ') {
            return $nho["k:$ch"] = 'd';
        }
        $nfd = Normalizer::normalize($c, Normalizer::FORM_D);
        $dau = mb_substr($nfd === false ? $c : $nfd, 0, 1, 'UTF-8');
        return $nho["k:$ch"] = preg_match(bw_re('^\p{Mn}\z'), $dau) === 1 ? self::DAU_TO_HOP : $dau;
    }

    /** any(ch.isalnum() and category(ch) != "Mn") — Mn vốn không là alnum. */
    private static function coChuSo(array $kyTu): bool
    {
        foreach ($kyTu as $ch) {
            if (self::laChuSo($ch)) {
                return true;
            }
        }
        return false;
    }

    /** any(not isalnum and not isspace and not dấu nhân sẵn). */
    private static function coDauCauKhacDauNhan(array $kyTu): bool
    {
        foreach ($kyTu as $ch) {
            if (!self::laChuSo($ch) && !self::laKhoangTrang($ch) && !isset(self::DAU_NHAN_SAN[$ch])) {
                return true;
            }
        }
        return false;
    }

    /** Nới [a, b) ra trọn cụm chữ/số dính liền hai bên — báo "1 củ" chứ không phải "củ". */
    private static function noRaCaToken(array $s, int $a, int $b): array
    {
        while ($a > 0 && self::laChuSo($s[$a - 1])) {
            $a--;
        }
        $n = count($s);
        while ($b < $n && self::laChuSo($s[$b])) {
            $b++;
        }
        return [$a, $b];
    }

    /** Trong s[j1:j2] có `x` ĐỨNG RIÊNG (dấu nhân) không. */
    private static function coDauNhanMoi(array $s, int $j1, int $j2): bool
    {
        $n = count($s);
        for ($k = $j1; $k < $j2; $k++) {
            if ($s[$k] === 'x'
                && ($k === 0 || !self::laChu($s[$k - 1]))
                && ($k + 1 === $n || !self::laChu($s[$k + 1]))) {
                return true;
            }
        }
        return false;
    }

    /** Nới [a, b) qua dấu câu/khoảng trắng rồi trọn cụm chữ/số mỗi bên: "12.500k". */
    private static function noQuaDauCau(array $s, int $a, int $b): array
    {
        while ($a > 0 && !self::laChuSo($s[$a - 1])) {
            $a--;
        }
        $n = count($s);
        while ($b < $n && !self::laChuSo($s[$b])) {
            $b++;
        }
        return self::noRaCaToken($s, $a, $b);
    }

    /** list<[khoá, start, end]> — cụm chữ / cụm số / khoảng trắng (khoá " ") / 1 ký tự khác. */
    private static function token(string $s): array
    {
        $out = [];
        foreach (bw_finditer(bw_re('[^\W\d_]+|\d+|' . BW_PY_S . '+|.', 's'), $s) as $m) {
            $t = $m['groups'][0];
            $khoa = preg_match(bw_re('^' . BW_PY_S . '+\z'), $t) === 1
                ? ' '
                : implode('', array_map(self::khoa(...), mb_str_split($t, 1, 'UTF-8')));
            $out[] = [$khoa, ...$m['spans'][0]];
        }
        return $out;
    }

    /**
     * Căn 2 dãy khoá token → opcodes kiểu difflib, TUYẾN TÍNH (không dùng SequenceMatcher ở tầng này: tin lặp cấu
     * trúc làm find_longest_match bùng nổ). Lệch thì tìm điểm khớp lại GẦN NHẤT cần SO_TOKEN_XAC_NHAN token liền khớp.
     */
    private static function canToken(array $a, array $b): array
    {
        $ops = [];
        [$i, $j, $n, $m] = [0, 0, count($a), count($b)];
        $khopTu = static function (int $x, int $y) use ($a, $b, $n, $m): bool {
            for ($k = 0; $k < self::SO_TOKEN_XAC_NHAN; $k++) {
                if ($x + $k >= $n && $y + $k >= $m) {
                    return true;
                }
                if ($x + $k >= $n || $y + $k >= $m || $a[$x + $k] !== $b[$y + $k]) {
                    return false;
                }
            }
            return true;
        };
        $them = static function (string $tag, int $i1, int $i2, int $j1, int $j2) use (&$ops): void {
            $cuoi = count($ops) - 1;
            if ($cuoi >= 0 && $tag === 'equal' && $ops[$cuoi][0] === 'equal') {
                $ops[$cuoi] = ['equal', $ops[$cuoi][1], $i2, $ops[$cuoi][3], $j2];
            } elseif ($i1 < $i2 || $j1 < $j2) {
                $ops[] = [$tag, $i1, $i2, $j1, $j2];
            }
        };

        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                $them('equal', $i, $i + 1, $j, $j + 1);
                $i++;
                $j++;
                continue;
            }
            $dich = null;
            for ($tong = 1; $tong <= 2 * self::CUA_SO_KHOP_LAI && $dich === null; $tong++) {
                for ($da = max(0, $tong - self::CUA_SO_KHOP_LAI); $da <= min($tong, self::CUA_SO_KHOP_LAI); $da++) {
                    if ($khopTu($i + $da, $j + $tong - $da)) {
                        $dich = [$da, $tong - $da];
                        break;
                    }
                }
            }
            // Không khớp lại trong cửa sổ = cả vùng bị viết lại: nhảy NGUYÊN cửa sổ để vẫn tuyến tính.
            [$da, $db] = $dich ?? [min(self::CUA_SO_KHOP_LAI, $n - $i), min(self::CUA_SO_KHOP_LAI, $m - $j)];
            $tag = $da && $db ? 'replace' : ($da ? 'delete' : 'insert');
            $them($tag, $i, $i + $da, $j, $j + $db);
            $i += $da;
            $j += $db;
        }
        return $ops;
    }

    /** Gộp ký tự liền nhau cùng mức độ thành span {text, start, end, severity}. */
    private static function gopSpans(array $g, array $muc): array
    {
        $spans = [];
        $n = count($g);
        for ($i = 0; $i < $n; $i = $j) {
            for ($j = $i; $j < $n && $muc[$j] === $muc[$i]; $j++) {
            }
            $spans[] = ['text' => implode('', array_slice($g, $i, $j - $i)), 'start' => $i, 'end' => $j, 'severity' => $muc[$i]];
        }
        return $spans;
    }
}
