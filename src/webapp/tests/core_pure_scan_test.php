<?php
declare(strict_types=1);

// P04 — quét mã lõi lib/bigwin/ (trừ repo/** và *Service.php) bằng token_get_all (không regex thô trên mã):
//   T10 PURE: không DB / PDO / cURL / session / I/O tệp / output / đồng hồ trần (date, time, DateTime không múi giờ).
//   T11 UTF-8 & kiểu (QĐ3, QĐ4 P04): CẤM strlen / substr / strtolower / str_split … trừ dòng có chú thích `// bw-byte`;
//       CẤM array_unique / array_flip / ctype_*; regex chỉ dựng qua bw_re() (không literal trần); PREG_OFFSET_CAPTURE
//       chỉ trong helpers.php (bw_finditer đổi offset byte → ký tự).

require_once __DIR__ . '/_db.php';

const BW_CSCAN_GOI_CAM_PURE = [
    'db', 'curl_init', 'curl_exec', 'session_start', 'session_id', 'header', 'setcookie', 'http_response_code',
    'file_get_contents', 'file_put_contents', 'fopen', 'fwrite', 'readfile', 'unlink', 'mail',
    'date', 'time', 'mktime', 'strtotime', 'microtime', 'hrtime', 'printf', 'vprintf', 'print_r', 'var_dump', 'var_export',
];
const BW_CSCAN_GOI_CAM_BYTE = [
    'strlen', 'substr', 'strtolower', 'strtoupper', 'str_split', 'ucfirst', 'lcfirst', 'ucwords', 'strrev',
    'str_pad', 'wordwrap', 'str_word_count', 'substr_count', 'strpos', 'stripos', 'strrpos', 'strripos',
];
const BW_CSCAN_GOI_CAM_MANG = ['array_unique', 'array_flip'];

/** Tệp lõi PURE (tương đối src/webapp). */
function bw_cscan_tep_loi(): array
{
    return array_filter(
        bw_test_tep_php(),
        static fn (string $duong): bool => str_starts_with($duong, 'lib/bigwin/') && !str_starts_with($duong, 'lib/bigwin/repo/')
            && !str_ends_with($duong, 'Service.php'),
        ARRAY_FILTER_USE_KEY,
    );
}

/** Vi phạm của một tệp lõi: ["T10|T11 luật:dòng chi tiết", ...]. */
function bw_cscan(string $duong, string $php): array
{
    $tatCa = token_get_all($php);
    $dongBwByte = [];
    foreach ($tatCa as $t) {
        if (is_array($t) && $t[0] === T_COMMENT && str_contains($t[1], 'bw-byte')) {
            $dongBwByte[$t[2]] = true;
        }
    }
    $tk = array_values(array_filter($tatCa, static fn (mixed $t): bool => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    $vp = [];
    foreach ($tk as $i => $t) {
        $loai = is_array($t) ? $t[0] : null;
        $dong = is_array($t) ? $t[2] : 0;
        if ($loai === T_ECHO || $loai === T_PRINT || $loai === T_INLINE_HTML || $loai === T_EXIT) {
            $vp[] = "T10 output:$dong {$t[1]}";
            continue;
        }
        if ($loai === T_NEW) {
            $ten = strtolower(ltrim(is_array($tk[$i + 1] ?? null) ? $tk[$i + 1][1] : '', '\\'));
            if ($ten === 'pdo') {
                $vp[] = "T10 pdo:$dong new PDO";
            }
            if (in_array($ten, ['datetime', 'datetimeimmutable'], true) && !bw_cscan_co_mui_gio($tk, $i + 2)) {
                $vp[] = "T10 dong_ho:$dong new $ten không kèm DateTimeZone";
            }
            continue;
        }
        if ($loai === T_STRING && str_contains(strtolower($t[1]), 'preg_offset_capture') && $duong !== 'lib/bigwin/helpers.php') {
            $vp[] = "T11 offset_byte:$dong {$t[1]} — dùng bw_finditer()";
            continue;
        }
        // Lời gọi hàm toàn cục: tên + '(' và không phải method / static / khai báo.
        $ten = is_array($t) && in_array($loai, [T_STRING, T_NAME_FULLY_QUALIFIED], true) ? strtolower(ltrim($t[1], '\\')) : null;
        $truoc = $tk[$i - 1] ?? null;
        if ($ten === null || ($tk[$i + 1] ?? null) !== '('
            || (is_array($truoc) && in_array($truoc[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST], true))) {
            continue;
        }
        if (in_array($ten, BW_CSCAN_GOI_CAM_PURE, true) || str_starts_with($ten, 'curl_') || str_starts_with($ten, 'session_')) {
            $vp[] = "T10 goi_cam:$dong $ten()";
        } elseif (in_array($ten, BW_CSCAN_GOI_CAM_BYTE, true) && !isset($dongBwByte[$dong])) {
            $vp[] = "T11 ham_byte:$dong $ten() — dùng mb_* hoặc ghi chú // bw-byte";
        } elseif (in_array($ten, BW_CSCAN_GOI_CAM_MANG, true)) {
            $vp[] = "T11 khoa_mang:$dong $ten() — dedup bằng \$daCo[\"k:\$x\"]";
        } elseif (str_starts_with($ten, 'ctype_')) {
            $vp[] = "T11 ctype:$dong $ten() — dùng preg_match(bw_re(...))";
        } elseif (str_starts_with($ten, 'preg_') && $ten !== 'preg_last_error_msg' && $ten !== 'preg_last_error' && $ten !== 'preg_quote') {
            $dau = $tk[$i + 2] ?? null;
            if (is_array($dau) && $dau[0] === T_CONSTANT_ENCAPSED_STRING) {
                $vp[] = "T11 regex_tran:$dong $ten({$dau[1]}…) — dựng bằng bw_re()";
            }
        }
    }
    return $vp;
}

/** Trong cặp ngoặc của `new DateTime…(` bắt đầu ở $i có tham số DateTimeZone không. */
function bw_cscan_co_mui_gio(array $tk, int $i): bool
{
    if (($tk[$i] ?? null) !== '(') {
        return false;
    }
    $sau = 0;
    for ($k = $i; $k < count($tk); $k++) {
        $sau += $tk[$k] === '(' ? 1 : ($tk[$k] === ')' ? -1 : 0);
        if (is_array($tk[$k]) && strcasecmp(ltrim($tk[$k][1], '\\'), 'DateTimeZone') === 0) {
            return true;
        }
        if ($sau === 0) {
            return false;
        }
    }
    return false;
}

function test_p04_t10_bo_quet_nhan_dung_mau(): void
{
    $mau = static fn (string $ma): array => array_map(static fn (string $v): string => explode(':', $v)[0], bw_cscan('lib/bigwin/Mau.php', "<?php\n$ma"));
    assert_same(['T10 goi_cam'], $mau('$x = db()->query("SELECT 1");'));
    assert_same(['T10 pdo'], $mau('$p = new \PDO("x");'));
    assert_same(['T10 goi_cam'], $mau('curl_setopt($c, 1, 2);'));
    assert_same(['T10 output'], $mau('echo "x";'));
    assert_same(['T10 output'], $mau('print "x";'));
    assert_same(['T10 goi_cam'], $mau('$d = date("Y");'));
    assert_same(['T10 dong_ho'], $mau('$d = new DateTimeImmutable("now");'));
    assert_same([], $mau('$d = new DateTimeImmutable("now", new DateTimeZone(BW_TZ));'));
    assert_same([], $mau('$o->date(); X::time(); function date() {}'), 'method / static / khai báo không phải lời gọi hàm toàn cục');
}

function test_p04_t10_loi_bigwin_pure(): void
{
    $tep = bw_cscan_tep_loi();
    foreach (['SequenceMatcher', 'HeuristicCleaner', 'DiffAnalyzer', 'DoiChieuTin'] as $lop) {
        assert_true(isset($tep["lib/bigwin/$lop.php"]), "Thiếu lib/bigwin/$lop.php trong phạm vi quét");
    }
    $vp = [];
    foreach ($tep as $duong => $tuyetDoi) {
        foreach (bw_cscan($duong, (string) file_get_contents($tuyetDoi)) as $v) {
            if (str_starts_with($v, 'T10')) {
                $vp[] = "$duong $v";
            }
        }
    }
    assert_same([], $vp);
}

function test_p04_t11_bo_quet_nhan_dung_mau(): void
{
    $mau = static fn (string $ma, string $duong = 'lib/bigwin/Mau.php'): array
        => array_map(static fn (string $v): string => explode(':', $v)[0], bw_cscan($duong, "<?php\n$ma"));
    assert_same(['T11 ham_byte'], $mau('$n = strlen($tin);'));
    assert_same(['T11 ham_byte'], $mau('$s = \substr($tin, 0, 3);'));
    assert_same(['T11 ham_byte'], $mau('$s = strtolower($tin);'));
    assert_same(['T11 ham_byte'], $mau('$a = str_split($tin);'));
    assert_same([], $mau("\$n = strlen(\$tin); // bw-byte: offset PREG_OFFSET_CAPTURE", 'lib/bigwin/helpers.php'), 'Dòng có // bw-byte được miễn');
    assert_same(['T11 khoa_mang'], $mau('$a = array_unique(["05", "5"]);'));
    assert_same(['T11 khoa_mang'], $mau('$a = array_flip($so);'));
    assert_same(['T11 ctype'], $mau('$b = ctype_digit($s);'));
    assert_same(['T11 regex_tran'], $mau("\$b = preg_match('/\\d+/', \$s);"));
    assert_same([], $mau("\$b = preg_match(bw_re('\\d+'), \$s);"));
    assert_same(['T11 offset_byte'], $mau('preg_match_all($re, $s, $m, PREG_OFFSET_CAPTURE);'));
    assert_same([], $mau('$n = mb_strlen($tin, "UTF-8"); $s = mb_substr($tin, 0, 1, "UTF-8"); $o->strlen();'));
}

function test_p04_t11_loi_bigwin_utf8_kieu_mang_regex(): void
{
    $vp = [];
    foreach (bw_cscan_tep_loi() as $duong => $tuyetDoi) {
        foreach (bw_cscan($duong, (string) file_get_contents($tuyetDoi)) as $v) {
            if (str_starts_with($v, 'T11')) {
                $vp[] = "$duong $v";
            }
        }
    }
    assert_same([], $vp);
}
