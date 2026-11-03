<?php
declare(strict_types=1);

// Port core/heuristic_cleaner.py (BigWIN @ port-baseline) — tầng chuẩn hoá tin nhắn cược, THUẦN (không DB/I/O).
// Port NGUYÊN VĂN: thứ tự pipeline, thứ tự bảng luật và từng regex là đặc tả (xem docstring bên Python).
// Khác biệt Python ↔ PCRE đã xử lý: `\s` Python gồm cả \x1c–\x1f (BW_PY_S); float() nhận chữ số Unicode;
// round() banker's chính xác (bw_py_round); int lớn in đúng giá trị float.
// Quy tắc tự học (bien_dich_cum_tu / quy_tac_tu_boc / _ap_dung_quy_tac_tu_hoc) HOÃN sang P09 (QĐ2 P04).

require_once __DIR__ . '/helpers.php';

/** Lỗi trong pipeline (tương đương Exception bị `clean()` Python nuốt) → trả bản gốc đã strip. */
final class LoiCleaner extends RuntimeException {}

final class HeuristicCleaner
{
    public const MAX_INPUT_LEN = 10000;

    private const L = '[^\W\d_]';
    private const KW_ALT = '3cang|xien|xq|chia|tong|de|lo|bo';
    private const TIEN_UNIT = '(?:k|n|d|đ)';
    private const KEP_BANG = '00 11 22 33 44 55 66 77 88 99';
    private const KEP_LECH = '05 50 16 61 27 72 38 83 49 94';
    private const CHU_DAN_KEP = [self::KEP_BANG => 'kep bang', self::KEP_LECH => 'kep lech'];
    private const DON_VI_LONG = 'củ|cu|lít|lit|loét|loet|chục|chuc|trăm|tram|triệu|trieu|tr|quả|qua|chai';

    private const SLANG_FACTOR = [
        'nghìn' => 1, 'nghin' => 1, 'ngàn' => 1, 'ngan' => 1, 'ng' => 1,
        'chục' => 10, 'chuc' => 10,
        'lít' => 100, 'lit' => 100, 'loét' => 100, 'loet' => 100,
        'củ' => 1000, 'cu' => 1000, 'quả' => 1000, 'qua' => 1000, 'chai' => 1000,
        'trăm' => 100, 'tram' => 100,
        'triệu' => 1000, 'trieu' => 1000, 'tr' => 1000,
    ];

    // Biến thể từ khoá → dạng CHUẨN. Thứ tự BẮT BUỘC giữ như Python (xiên quay trước xiên, bao lô trước bao/lô).
    private const KEYWORD_RULES = [
        ['(?<!\w)xi[eên]n\s*quay(?!' . self::L . ')', 'xq'],
        ['(?<!\w)3\s*(?:cang|càng|cg|c)(?!' . self::L . ')', '3cang'],
        ['(?<!\w)bao\s*l[oô](?!' . self::L . ')', 'lo'],
        ['(?<!\w)bao(?=\s*\d)', 'lo'],
        ['(?<!\w)(?:đá|dá|xiên)(?!' . self::L . ')', 'xien'],
        ['(?<!\w)(?:lx|xien)(?:\s*[234](?!\d))?(?=\s*[:.]?\s*\d)', 'xien'],
        ['(?<!\w)x[234](?=\s+\d)', 'xien'],
        ['(?<!\w)(?:đề|đê|đe|dê)(?!' . self::L . ')', 'de'],
        ['(?<!\w)(?:đ[aặ]c\s*bi[eệ]t|dac\s*biet|đb|db)(?=\s*\d)', 'de'],
        ['(?<!\w)lô(?!' . self::L . ')', 'lo'],
        ['(?<!\w)bộ(?!' . self::L . ')', 'bo'],
        ['(?<!\w)b(?=\s*\d)', 'bo'],
        ['(?<!\w)tổng(?!' . self::L . ')', 'tong'],
        ['(?<!\w)chạm(?!' . self::L . ')', 'cham'],
        ['(?<!\w)đầu(?!' . self::L . ')', 'dau'],
        ['(?<!\w)(?:[đd][íi]t|[đd]u[ôo]i)(?=\s*\d)', 'dit'],
    ];

    // "kép lệch" phải đứng TRƯỚC "kép bằng".
    private const NUMBER_SET_RULES = [
        ['(?<!\w)(?:k[ée]p\s*l[eệ]ch|kl)(?!' . self::L . ')', self::KEP_LECH],
        ['(?<!\w)(?:k[ée]p\s*b[aằ]ng|kb)(?!' . self::L . ')', self::KEP_BANG],
    ];

    private const RE_VI_TRI_SO_CUOC = '(?:\d(?:\s*[.,\-\']\s*|\s+)'
        . '|(?<!\w)(?:đề|đe|đê|dê|de|lô|lo|xiên|xien|xq|bộ|bo|3\s*c\w*)\s*[:.]?\s*)$';

    // Thứ tự pipeline là một phần của đặc tả (_PIPELINE bên Python). `_ap_dung_quy_tac_tu_hoc` (bước 2) vắng mặt:
    // không có quy tắc nào được nạp (QĐ2) nên bước đó là no-op.
    private const PIPELINE = [
        'buocChuanHoaKyTuAn', 'normalizeBase', 'fixThousandSeparators', 'fixDecimalComma',
        'tachTuKhoaDinhSauDonViTien', 'tachTuKhoaDinhSauTienTron', 'fixKeywords', 'expandNumberSets',
        'quyDoiRuoiNua', 'normalizeMoneySlang', 'ngatBoSoBangDauChamPhay', 'fixDotBeforeOperator',
        'normalizeOperators', 'ngatSoBangCongGach', 'khoiPhucDauLeTrongDan', 'separateKeywords', 'fixOToZero',
        'fixRanges', 'insertMissingX', 'chenXTruocTienTronCuoiCum', 'ngatCumSauDonViTien', 'ngatCauTruocTuKhoa',
        'tidy', 'khoiPhucDanKep',
    ];

    /** @var list<array{string, list<int>, int}> [dãy số, thứ tự các dãy do cleaner chèn, tổng số dãy giống hệt] */
    private array $danKepDaBung = [];

    public function __construct(?array $quyTacTuHoc = null, private readonly bool $bungDanKep = true)
    {
        if ($quyTacTuHoc) {
            throw new LogicException('HeuristicCleaner: quy tắc tự học chưa được port (hoãn sang P09).');
        }
    }

    /** Chuẩn hoá 1 tin nhắn. Không ném lỗi — pipeline hỏng thì trả bản gốc đã strip (như Python). */
    public function clean(string $text): string
    {
        if ($text === '' || bw_py_strip($text) === '') {
            return '';
        }
        // Quá trần → TRẢ NGUYÊN BẢN (CẤM cắt cụt — xem docstring Python).
        if (mb_strlen($text, 'UTF-8') > self::MAX_INPUT_LEN) {
            return $text;
        }
        $goc = $text;
        $this->danKepDaBung = [];
        try {
            foreach (self::PIPELINE as $buoc) {
                $text = $this->$buoc($text);
            }
            return $text;
        } catch (LoiCleaner) {
            return bw_py_strip($goc);
        }
    }

    /** NFC + mọi ký tự vô hình → đúng MỘT dấu cách (giữ độ dài). Công khai: thước đo Unicode chung của tự học. */
    public static function chuanHoaKyTuAn(string $text): string
    {
        return bw_chuan_hoa_ky_tu_an($text);
    }

    // ── Tiện ích port re.sub / str Python ──────────────────────────────────

    /** re.sub(pattern, repl, text): repl là chuỗi (tham chiếu ${n}) hoặc callable(nhóm, null = không khớp). */
    private static function thay(string $body, string|callable $repl, string $text): string
    {
        $re = bw_re($body);
        $kq = is_string($repl)
            ? preg_replace($re, $repl, $text)
            : preg_replace_callback($re, $repl, $text, -1, $dem, PREG_UNMATCHED_AS_NULL);
        if ($kq === null) {
            throw new LoiCleaner('Regex lỗi: ' . preg_last_error_msg());
        }
        return $kq;
    }

    /** float() Python: nhận mọi chữ số Unicode (\d = Nd) — PHP (float) chỉ hiểu chữ số ASCII. */
    private static function soThuc(string $s): float
    {
        return (float) self::thay('\d', static fn (array $m): string => (string) IntlChar::charDigitValue($m[0]), $s);
    }

    /** f"{int(round(x))}" Python: banker's chính xác, in đúng số nguyên kể cả vượt int64; inf → OverflowError. */
    private static function soNguyenPy(float $x): string
    {
        $r = bw_py_round($x);
        if (!is_finite($r)) {
            throw new LoiCleaner('OverflowError: cannot convert float infinity to integer');
        }
        return sprintf('%.0f', $r + 0.0); // + 0.0: -0.0 → 0.0 (Python in "0")
    }

    // ── Các bước pipeline (cùng tên/thứ tự với Python) ─────────────────────

    private function buocChuanHoaKyTuAn(string $t): string
    {
        return self::chuanHoaKyTuAn($t);
    }

    private function normalizeBase(string $t): string
    {
        $t = mb_strtolower($t, 'UTF-8');
        $t = self::thay('[\x{2010}-\x{2015}]', '-', $t); // – — ‒ → gạch ngang ASCII
        $t = self::thay('\.\.+', ', ', $t);
        return bw_py_strip(self::thay(BW_PY_S . '+', ' ', $t));
    }

    private function fixThousandSeparators(string $t): string
    {
        $re = bw_re('(?<!\d)(\d+)((?:[.,]\d{3})+)(?=\s*' . self::TIEN_UNIT . '(?!' . self::L . '))');
        $ra = '';
        $cuoi = 0;
        foreach (bw_finditer($re, $t) as $m) {
            [$dau, $cuoiKhop] = $m['spans'][0];
            $ra .= mb_substr($t, $cuoi, $dau - $cuoi, 'UTF-8');
            // Chỉ cần ĐUÔI 32 ký tự trước phần đầu (như Python).
            $tu = max(0, $dau - 32);
            $truoc = mb_substr($t, $tu, $dau - $tu, 'UTF-8');
            [, $phanDau, $nhom] = $m['groups'];
            $ra .= self::laSoCuocTruocTien($truoc, $phanDau, $nhom)
                ? $m['groups'][0]
                : $phanDau . self::thay('[.,]', '', $nhom);
            $cuoi = $cuoiKhop;
        }
        return $ra . mb_substr($t, $cuoi, null, 'UTF-8');
    }

    /** `34.100k` là SỐ CƯỢC 34 + TIỀN 100k? (đủ cả bốn điều kiện — xem Python). */
    private static function laSoCuocTruocTien(string $truoc, string $dau, string $nhom): bool
    {
        $n = mb_strlen($dau, 'UTF-8');
        return $n >= 2 && $n <= 3
            && mb_strlen($nhom, 'UTF-8') === 4
            && mb_substr($nhom, 1, null, 'UTF-8') !== '000'
            && preg_match(bw_re(self::RE_VI_TRI_SO_CUOC), $truoc) === 1;
    }

    private function fixDecimalComma(string $t): string
    {
        return self::thay('(?<=\d),(?=\d{1,2}\s*' . self::TIEN_UNIT . '(?!' . self::L . '))', '.', $t);
    }

    private function tachTuKhoaDinhSauDonViTien(string $t): string
    {
        return self::thay('(?<=\d[kn])(?=(?:đề|đê|đe|dê|de|lô|lo|xiên|xien|xq|bộ|bo)(?!' . self::L . '))', ', ', $t);
    }

    private function tachTuKhoaDinhSauTienTron(string $t): string
    {
        return self::thay(
            '((?<![^\W\d_])[x×*]\s*\d{1,12})(?=(?:đề|đê|đe|dê|de|lô|lo|xiên|xien|xq|bộ|bo)(?!' . self::L . '))',
            '${1}, ',
            $t,
        );
    }

    private function fixKeywords(string $t): string
    {
        foreach (self::KEYWORD_RULES as [$re, $thay]) {
            $t = self::thay($re, $thay, $t);
        }
        return $t;
    }

    private function expandNumberSets(string $t): string
    {
        if ($this->bungDanKep) {
            foreach (self::NUMBER_SET_RULES as [$re, $thay]) {
                $t = self::thay($re, $thay, $t);
            }
            return $t;
        }
        // Engine Mới: vẫn bung (các bước chèn `x` sau chỉ nhận chữ số), nhưng ghi sổ THỨ TỰ của dãy do cleaner chèn
        // giữa mọi dãy giống hệt — để khoiPhucDanKep trả lại chữ dàn đúng chỗ. Bước 1: ký tự giữ chỗ \x02<i>\x03.
        $cho = [];
        foreach (self::NUMBER_SET_RULES as [$re, $thay]) {
            $t = self::thay($re, static function () use (&$cho, $thay): string {
                $cho[] = $thay;
                return "\x02" . (count($cho) - 1) . "\x03";
            }, $t);
        }
        if (!$cho) {
            return $t;
        }
        // Bước 2: đổi chỗ giữ thành dãy số, nhớ vị trí (ký tự) bắt đầu của từng dãy chèn.
        $batDau = [];
        $ra = '';
        $doDaiRa = 0;
        $cuoi = 0;
        foreach (bw_finditer(bw_re('\x02(\d+)\x03'), $t) as $m) {
            [$dau, $cuoiKhop] = $m['spans'][0];
            $doan = mb_substr($t, $cuoi, $dau - $cuoi, 'UTF-8');
            $ra .= $doan;
            $doDaiRa += mb_strlen($doan, 'UTF-8');
            $batDau[$doDaiRa] = true;
            $chen = $cho[(int) self::soThuc($m['groups'][1])] ?? throw new LoiCleaner('IndexError: list index out of range');
            $ra .= $chen;
            $doDaiRa += mb_strlen($chen, 'UTF-8');
            $cuoi = $cuoiKhop;
        }
        $t = $ra . mb_substr($t, $cuoi, null, 'UTF-8');
        // Bước 3: thứ tự (ordinal) của dãy chèn giữa mọi dãy giống hệt + tổng số dãy (dict.fromkeys → $daCo).
        $daCo = [];
        foreach ($cho as $daySo) {
            if (isset($daCo["k:$daySo"])) {
                continue;
            }
            $daCo["k:$daySo"] = true;
            $ds = bw_finditer(bw_re('(?<!\d)' . $daySo . '(?!\d)'), $t);
            $thuTu = [];
            foreach ($ds as $i => $m) {
                if (isset($batDau[$m['spans'][0][0]])) {
                    $thuTu[] = $i;
                }
            }
            $this->danKepDaBung[] = [$daySo, $thuTu, count($ds)];
        }
        return $t;
    }

    /** Bước CUỐI (chỉ có việc khi bungDanKep=false): dãy do cleaner chèn → trả lại chữ dàn cho engine Mới. */
    private function khoiPhucDanKep(string $t): string
    {
        foreach ($this->danKepDaBung as [$daySo, $thuTu, $tong]) {
            $khop = bw_finditer(bw_re('(?<!\d)' . $daySo . '(?!\d)'), $t);
            if (count($khop) !== $tong) {
                continue; // số dãy giống hệt đã đổi qua pipeline → thứ tự không tin được, giữ dãy số
            }
            foreach (array_reverse($thuTu) as $i) { // phải → trái: vị trí không lệch
                [$dau, $cuoi] = $khop[$i]['spans'][0];
                $t = mb_substr($t, 0, $dau, 'UTF-8') . self::CHU_DAN_KEP[$daySo] . mb_substr($t, $cuoi, null, 'UTF-8');
            }
        }
        $this->danKepDaBung = [];
        return $t;
    }

    private function quyDoiRuoiNua(string $t): string
    {
        $dv = self::DON_VI_LONG;
        $t = self::thay('(?<![\w.,])(\d+)\s*(' . $dv . ')\s+(?:rưỡi|rưởi|ruoi)(?!' . self::L . ')', '${1}.5 ${2}', $t);
        $t = self::thay('(?<!\w)(?:nửa|nua)\s+(' . $dv . ')(?!' . self::L . ')', '0.5 ${1}', $t);
        // "5 trăm nghìn"/"3 trăm k" = 500k/300k: bỏ đuôi nghìn để bảng hệ số không nhân hai lần.
        return self::thay('(?<=\d)(\s*)(trăm|tram)\s*(?:nghìn|nghin|ngàn|ngan|k)(?!' . self::L . ')', '${1}${2}', $t);
    }

    /** "|".join(sorted(_SLANG_FACTOR, key=len, reverse=True)) — sort ổn định, hoà giữ thứ tự khai báo. */
    private static function slangAlt(): string
    {
        static $alt = null;
        if ($alt === null) {
            $khoa = array_map('strval', array_keys(self::SLANG_FACTOR));
            usort($khoa, static fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
            $alt = implode('|', $khoa);
        }
        return $alt;
    }

    private function normalizeMoneySlang(string $t): string
    {
        $L = self::L;
        $wGuard = '(?<![a-wyzA-WYZ0-9_])';
        $unambiguous = 'triệu|trieu|tr|nghìn|nghin|ngàn|ngan|ng';
        $ambiguous = 'chục|chuc|loét|loet|lít|lit|chai|quả|qua|củ|cu|trăm|tram';
        $nextBet = '\s+\d{1,4}(?:[\d,.\-\'\s]{0,60}?\s*[x×*]|\s+\d+\s*(?:k|n|d|đ|' . self::slangAlt() . '))';
        $baseLookahead = '(?=\s*(?:$|[,;.!?]|(?:' . self::KW_ALT . ")(?!$L)))";
        $extLookahead = '(?=\s*(?:$|[,;.!?]|(?:' . self::KW_ALT . ")(?!$L)|$nextBet))";

        // 1. Tiền lóng ghép: 1c5 → 1500k, 2l5 → 250k, 1tr5 → 1500k.
        // `(?![\d[^\W\d_]])` chép NGUYÊN VĂN: cả `re` Python lẫn PCRE đều KHÔNG có lớp lồng — cùng hiểu là lớp
        // {chữ số, '[', '^', \W, '_'} theo sau bởi ']' literal (hành vi "lạ" nhưng hai bên giống hệt).
        $patternGhep = $wGuard . '(\d+)\s*([củu]|c|cu|củ|tr(?:i[eệ]u)?|trieu|triệu|l(?:[íi]t)?|lo[eé]t)\s*'
            . "([1-9])(?![\\d$L])" . $extLookahead;
        $t = self::thay($patternGhep, static function (array $m): string {
            $unit = mb_strtolower($m[2], 'UTF-8');
            $factor = match (true) {
                in_array($unit, ['c', 'cu', 'củ', 'tr', 'trieu', 'triệu'], true) => 1000,
                in_array($unit, ['l', 'lit', 'lít', 'loet', 'loét'], true) => 100,
                default => self::SLANG_FACTOR[$unit] ?? 1000,
            };
            return self::soNguyenPy(self::soThuc($m[1] . '.' . $m[3]) * $factor) . 'k';
        }, $t);

        $chuan = static fn (array $m): string
            => self::soNguyenPy(self::soThuc(str_replace(',', '.', $m[1])) * self::SLANG_FACTOR[$m[2]]) . 'k';
        // 2. Đơn vị rõ ràng → ext_lookahead.
        $t = self::thay($wGuard . '(\d+(?:[.,]\d+)?)\s*(' . $unambiguous . ")(?!$L)" . $extLookahead, $chuan, $t);
        // 3. Đơn vị đa nghĩa ĐỨNG SAU toán tử nhân → chắc chắn là tiền → ext_lookahead.
        $t = self::thay('([x×*]\s*)(\d+(?:[.,]\d+)?)\s*(' . $ambiguous . ")(?!$L)" . $extLookahead,
            static fn (array $m): string
                => $m[1] . self::soNguyenPy(self::soThuc(str_replace(',', '.', $m[2])) * self::SLANG_FACTOR[$m[3]]) . 'k',
            $t);
        // 4. Đơn vị đa nghĩa không có x trước → base_lookahead (Do-No-Harm).
        $t = self::thay($wGuard . '(\d+(?:[.,]\d+)?)\s*(' . $ambiguous . ")(?!$L)" . $baseLookahead, $chuan, $t);

        // "đ" → "d" CHỈ khi đứng ngay sau chữ số ("đầu" phải giữ nguyên).
        return self::thay('(?<=\d)đ(?!\w)', 'd', $t);
    }

    private function ngatBoSoBangDauChamPhay(string $t): string
    {
        return self::thay('(?:(?<=\d)|(?<=k[eé]p)|(?<=sk))\s*;\s*(?=\d|k[eé]p|sk)', ' ', $t);
    }

    private function fixDotBeforeOperator(string $t): string
    {
        return self::thay('(?<=\w)\s*[.,]+\s*(?=[x×*]\s*\d)', ' ', $t);
    }

    private function normalizeOperators(string $t): string
    {
        $t = self::thay('(?<!\w)(?:mc|mỗi\s*con|moi\s*con)\s*(?=\d)', ' x ', $t);
        $t = self::thay('(?<=\w)\s*[x×*]\s*(?=\d)', ' x ', $t);
        return self::thay('(?<=\d)\s*[+/]\s*(?=\d+\s*' . self::TIEN_UNIT . '(?!' . self::L . '))', ' x ', $t);
    }

    private function ngatSoBangCongGach(string $t): string
    {
        return self::thay(
            '(?<![\d+/.,])\d{1,3}(?:\s*[+/]\s*\d{1,3}(?!\d))+(?=\s*[x×*])',
            static fn (array $m): string => self::thay('\s*[+/]\s*', ' ', $m[0]),
            $t,
        );
    }

    private function khoiPhucDauLeTrongDan(string $t): string
    {
        $dan = '(?:chan|chẵn|chăn|lẻ|le)';
        return self::thay(
            '(?<!\w)((?:de|lo)\s*[:.]?\s*)(' . $dan . '(?:\s+' . $dan . ')?)(?=\s*x\s*\d)',
            static fn (array $m): string => $m[1] . self::thay('(?<!\w)le(?!\w)', 'lẻ', $m[2]),
            $t,
        );
    }

    private function separateKeywords(string $t): string
    {
        return self::thay('(?<!\w)(' . self::KW_ALT . ')(?=\d)', '${1} ', $t);
    }

    private function fixOToZero(string $t): string
    {
        $L = self::L;
        $t = self::thay("(?<!$L)o\\s*o(?!$L)", '00', $t);
        $t = self::thay("(?<!$L)o(?=\\d)", '0', $t);
        return self::thay("(?<=\\d)o(?!$L)", '0', $t);
    }

    private function fixRanges(string $t): string
    {
        return self::thay('(?<=\d)\s*-\s*(?=\d)', ' - ', $t);
    }

    private function insertMissingX(string $t): string
    {
        $L = self::L;
        // 1. "12 10k", "12. 10k", "12, 10k" → "12 x 10k"
        $t = self::thay('(?<=\d)\s*[.,]?\s+(?=\d+\s*' . self::TIEN_UNIT . "(?!$L))", ' x ', $t);
        // 2. Dấu chấm giữa số cược và tiền: 393.50n / 12.10k / 00.10k (giữ 0.5d và ca mơ hồ "12 2.5k").
        return self::thay(
            '(?<!\bx\s)(?:(\d+)\s+)?\b(\d+)\.(\d+)\s*(' . self::TIEN_UNIT . ")(?!$L)",
            static function (array $m): string {
                // Python `if so_truoc_do`: None/"" là sai — "0" vẫn là ĐÚNG (PHP coi "0" là falsy → so tường minh).
                $coSoTruocDo = $m[1] !== null && $m[1] !== '';
                $prefix = $coSoTruocDo ? $m[1] . ' ' : '';
                if ($m[2] === '0') {
                    return $prefix . $m[2] . '.' . $m[3] . $m[4];
                }
                if ($coSoTruocDo && mb_strlen($m[2], 'UTF-8') === 1) {
                    return $m[0];
                }
                return $prefix . $m[2] . ' x ' . $m[3] . $m[4];
            },
            $t,
        );
    }

    private function chenXTruocTienTronCuoiCum(string $t): string
    {
        $L = self::L;
        return self::thay(
            '(?<!\w)((?:de|lo|xien|xq|bo)\s*[:.]?\s*'
            . '(?:\d{1,3}(?:\s*[,.\-\']\s*|\s+))*?\d{1,3})'
            . '(?:\s*\.\s*|\s+)'
            . "([1-9]\\d{1,4}0)(?!\\d)(?!$L)"
            . '(?=\s*(?:$|[,;!?]|\.(?!\d)|(?:' . self::KW_ALT . ")(?!$L)))",
            '${1} x ${2}',
            $t,
        );
    }

    private function ngatCumSauDonViTien(string $t): string
    {
        return self::thay('(?<=\d)((?:k|n))(?=\d{2}[\d,.\-\'\s]{0,60}?\s*x\s*\d)', '${1}, ', $t);
    }

    private function ngatCauTruocTuKhoa(string $t): string
    {
        return self::thay('(?<=\d)(' . self::TIEN_UNIT . ')\s+(?=(?:' . self::KW_ALT . ')(?!' . self::L . '))', '${1}, ', $t);
    }

    private function tidy(string $t): string
    {
        $t = self::thay('\s*,\s*(?=,)', '', $t); // gộp dấu phẩy lặp
        $t = self::thay('\s+,', ',', $t);
        $t = self::thay('\s+', ' ', $t);
        return trim($t, ' ,');
    }
}
