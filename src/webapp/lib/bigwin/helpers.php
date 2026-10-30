<?php
declare(strict_types=1);

// Port của BigWIN utils/helpers.py + nền tảng regex/Unicode cho lib/bigwin/.
// PURE: không DB, không mạng, không output. Xem .claude/rules/bigwin-core.md.

// ═══ ĐỒNG HỒ DUY NHẤT — UTC+7 ═══════════════════════════════════════════
// Việt Nam không đổi giờ mùa hè nên múi Asia/Ho_Chi_Minh luôn là +07:00.
// CẤM date()/time() trần trong lib/bigwin — luôn đi qua các hàm dưới đây.

const BW_TZ = 'Asia/Ho_Chi_Minh';

function now_vn(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(BW_TZ));
}

/** 'YYYY-MM-DD HH:MM:SS' theo giờ VN (tương đương now_vn_sql Python). */
function now_vn_sql(): string
{
    return now_vn()->format('Y-m-d H:i:s');
}

/** Ngày hôm nay theo giờ VN, 00:00:00. */
function today_vn(): DateTimeImmutable
{
    return now_vn()->setTime(0, 0);
}

/** Hôm nay dạng 'dd-mm-yyyy' — định dạng `ngay` của app Desktop. */
function hom_nay_vn_str(): string
{
    return now_vn()->format('d-m-Y');
}

// ═══ GIẢI THƯỞNG ═══════════════════════════════════════════════════════

const GIAI_MAP = [
    'giaidb' => 'Giải ĐB',
    'giai1'  => 'Giải Nhất',
    'giai2'  => 'Giải Nhì',
    'giai3'  => 'Giải Ba',
    'giai4'  => 'Giải Tư',
    'giai5'  => 'Giải Năm',
    'giai6'  => 'Giải Sáu',
    'giai7'  => 'Giải Bảy',
];

const GIAI_REVERSE_MAP = [
    'Giải ĐB'   => 'giaidb',
    'Giải Nhất' => 'giai1',
    'Giải Nhì'  => 'giai2',
    'Giải Ba'   => 'giai3',
    'Giải Tư'   => 'giai4',
    'Giải Năm'  => 'giai5',
    'Giải Sáu'  => 'giai6',
    'Giải Bảy'  => 'giai7',
];

// ═══ ĐỊNH DẠNG TIỀN ════════════════════════════════════════════════════

/** int(amount) của Python: cắt phần thập phân về 0 (không làm tròn). */
function bw_int(int|float|string $v): int
{
    return is_string($v) ? (int)trim($v) : (int)$v;
}

function bw_nhom_nghin(int $n): string
{
    return number_format($n, 0, ',', '.');
}

/** 1234567 → "1.234.567đ". */
function fmt_tien(int|float|string $amount): string
{
    return bw_nhom_nghin(bw_int($amount)) . 'đ';
}

/**
 * Ô TRÚNG của bảng báo cáo → [chuỗi hiển thị, có trúng?].
 * LÔ hiện ĐIỂM trúng ("1.234 điểm"), loại khác hiện TIỀN trúng.
 */
function fmt_o_trung(string $loaiKey, int|float|null $tienTrung, int|float|null $diemTrung = 0): array
{
    if ($loaiKey === 'LO') {
        $diem = bw_int($diemTrung ?? 0);
        return [$diem ? bw_nhom_nghin($diem) . ' điểm' : '0', $diem > 0];
    }
    $tien = bw_int($tienTrung ?? 0);
    return [bw_nhom_nghin($tien), $tien > 0];
}

// ═══ KÝ TỰ VÔ HÌNH ═════════════════════════════════════════════════════
// Khớp 1-1 với HeuristicCleaner._KY_TU_VO_HINH và MessageParser._INVISIBLE_CODEPOINTS.
// Mỗi ký tự → đúng 1 dấu cách: giữ nguyên độ dài (tính theo ký tự) để offset markup khớp tin gốc.

const BW_KY_TU_AN = [
    "\u{00A0}" => ' ', // no-break space
    "\u{00AD}" => ' ', // soft hyphen
    "\u{200B}" => ' ', // zero-width space
    "\u{200C}" => ' ', // zero-width non-joiner
    "\u{200D}" => ' ', // zero-width joiner
    "\u{2060}" => ' ', // word joiner
    "\u{202F}" => ' ', // narrow no-break space
    "\u{FEFF}" => ' ', // BOM / zero-width no-break space
];

/** MessageParser._sanitize: thay ký tự vô hình bằng dấu cách. */
function bw_sanitize(string $s): string
{
    return strtr($s, BW_KY_TU_AN);
}

/** HeuristicCleaner.chuan_hoa_ky_tu_an: NFC rồi thay ký tự vô hình. */
function bw_chuan_hoa_ky_tu_an(string $s): string
{
    $nfc = Normalizer::normalize($s, Normalizer::FORM_C);
    return bw_sanitize($nfc === false ? $s : $nfc);
}

// ═══ REGEX UNICODE ═════════════════════════════════════════════════════

/**
 * Dựng pattern PCRE có ngữ nghĩa giống `re` Python 3 trên str:
 * `/u` (UTF-8) + `(*UCP)` (\w \d \b \s theo Unicode). $body KHÔNG kèm delimiter;
 * $flags là cờ PCRE thêm (i, s, m, x).
 */
function bw_re(string $body, string $flags = ''): string
{
    foreach (['~', '#', '%', '!', '@', '`', ';'] as $d) {
        if (!str_contains($body, $d)) {
            return $d . '(*UCP)' . $body . $d . 'u' . $flags;
        }
    }
    return '~(*UCP)' . str_replace('~', '\~', $body) . '~u' . $flags;
}

/** Byte offset (PREG_OFFSET_CAPTURE) → char offset (code point, như Python). */
function bw_byte_to_char(string $s, int $byteOffset): int
{
    return mb_strlen(substr($s, 0, $byteOffset), 'UTF-8');
}

/** Char offset → byte offset. */
function bw_char_to_byte(string $s, int $charOffset): int
{
    return strlen(mb_substr($s, 0, $charOffset, 'UTF-8'));
}

/**
 * Tương đương `re.finditer`: mỗi match → ['groups' => [tên|chỉ số => chuỗi|null],
 * 'spans' => [tên|chỉ số => [start, end]|null]] với offset theo KÝ TỰ.
 * Nhóm không khớp = null (như Python None).
 */
function bw_finditer(string $pattern, string $subject): array
{
    $n = preg_match_all($pattern, $subject, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
    if ($n === false) {
        throw new RuntimeException('Regex lỗi: ' . preg_last_error_msg());
    }
    $out = [];
    // Chuyển byte→char tăng dần, không quét lại từ đầu chuỗi cho mỗi offset.
    $cacheByte = 0;
    $cacheChar = 0;
    $toChar = static function (int $b) use ($subject, &$cacheByte, &$cacheChar): int {
        if ($b < $cacheByte) {
            return bw_byte_to_char($subject, $b);
        }
        $cacheChar += mb_strlen(substr($subject, $cacheByte, $b - $cacheByte), 'UTF-8');
        $cacheByte = $b;
        return $cacheChar;
    };
    foreach ($m as $set) {
        $groups = [];
        $spans = [];
        foreach ($set as $k => [$text, $off]) {
            $groups[$k] = $text;
            if ($text === null) {
                $spans[$k] = null;
                continue;
            }
            $start = $toChar($off);
            $spans[$k] = [$start, $start + mb_strlen($text, 'UTF-8')];
        }
        $out[] = ['groups' => $groups, 'spans' => $spans];
    }
    return $out;
}

// ═══ CHUỖI KIỂU PYTHON ═════════════════════════════════════════════════

/** str.center của CPython (lệch trái/phải đúng như CPython khi số dư lẻ). */
function bw_py_center(string $s, int $width): string
{
    $marg = $width - mb_strlen($s, 'UTF-8');
    if ($marg <= 0) {
        return $s;
    }
    $left = intdiv($marg, 2) + ($marg & $width & 1);
    return str_repeat(' ', $left) . $s . str_repeat(' ', $marg - $left);
}

function bw_py_rjust(string $s, int $width): string
{
    $marg = $width - mb_strlen($s, 'UTF-8');
    return $marg > 0 ? str_repeat(' ', $marg) . $s : $s;
}

/**
 * Bảng tổng kết text viền Unicode (port nguyên văn build_summary_table).
 * $tableData: mảng dòng, mỗi ô đã format thành chuỗi.
 */
function build_summary_table(array $tableData, ?array $headers = null): string
{
    $headers ??= ['STT', 'TIỀN GHI', 'CHIẾT KHẤU', 'TỔNG ĐÁNH', 'TỔNG TRÚNG', 'TIỀN TRÚNG'];
    $headers = array_values($headers);
    $n = count($headers);
    $w = array_map(static fn($h): int => mb_strlen((string)$h, 'UTF-8'), $headers);
    foreach ($tableData as $row) {
        foreach (array_slice(array_values($row), 0, $n) as $i => $cell) {
            $w[$i] = max($w[$i], mb_strlen((string)$cell, 'UTF-8'));
        }
    }

    $hline = static function (string $l, string $mid, string $r) use ($w, $n): string {
        $parts = [];
        for ($i = 0; $i < $n; $i++) {
            $parts[] = str_repeat('═', $w[$i] + 2);
        }
        return $l . implode($mid, $parts) . $r;
    };
    $fmtRow = static function (array $cells) use ($w, $n): string {
        $parts = [];
        foreach (array_slice(array_values($cells), 0, $n) as $i => $cell) {
            $s = (string)$cell;
            $parts[] = ' ' . ($i === 0 ? bw_py_center($s, $w[$i]) : bw_py_rjust($s, $w[$i])) . ' ';
        }
        return '║' . implode('║', $parts) . '║';
    };

    $lines = [$hline('╔', '╦', '╗'), $fmtRow($headers), $hline('╠', '╬', '╣')];
    $last = count($tableData) - 1;
    foreach (array_values($tableData) as $idx => $row) {
        $lines[] = $fmtRow($row);
        if ($idx < $last) {
            $lines[] = $hline('╟', '╫', '╢');
        }
    }
    $lines[] = $hline('╚', '╩', '╝');
    return implode("\n", $lines);
}
