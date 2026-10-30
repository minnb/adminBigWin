<?php
declare(strict_types=1);

// Test runner tối giản (không Composer/PHPUnit): php tools/test.php [lọc_tên_tệp]
// Tự tìm tests/*_test.php, chạy mọi hàm test_*; warning/notice cũng tính là lỗi.
// Exit code 0 = tất cả xanh, 1 = có test lỗi.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const BW_TESTS_DIR = __DIR__ . '/../tests';

final class BwAssertionFailed extends RuntimeException {}

/** So sánh nghiêm ngặt (===), lỗi thì in diff dòng-theo-dòng. */
function assert_same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected === $actual) {
        return;
    }
    throw new BwAssertionFailed(($msg !== '' ? $msg . "\n" : '') . bw_diff(bw_dump($expected), bw_dump($actual)));
}

function assert_true(bool $cond, string $msg = 'Điều kiện phải đúng'): void
{
    if (!$cond) {
        throw new BwAssertionFailed($msg);
    }
}

/** Đọc tests/fixtures/<ten>.json (golden sinh từ BigWIN Python) → phần `data`. */
function bw_fixture(string $ten): mixed
{
    $file = BW_TESTS_DIR . '/fixtures/' . $ten . '.json';
    if (!is_file($file)) {
        throw new BwAssertionFailed("Thiếu fixture $file — chạy scripts/export_golden.py bên BigWIN.");
    }
    return json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR)['data'];
}

function bw_dump(mixed $v): string
{
    if (is_string($v)) {
        return 'string(' . mb_strlen($v, 'UTF-8') . ') "' . $v . '"';
    }
    return var_export($v, true);
}

function bw_diff(string $a, string $b): string
{
    $la = explode("\n", $a);
    $lb = explode("\n", $b);
    $out = ["--- mong đợi", "+++ thực tế"];
    $n = max(count($la), count($lb));
    $shown = 0;
    for ($i = 0; $i < $n && $shown < 40; $i++) {
        $x = $la[$i] ?? null;
        $y = $lb[$i] ?? null;
        if ($x === $y) {
            continue;
        }
        $out[] = sprintf('@@ dòng %d', $i + 1);
        if ($x !== null) {
            $out[] = '- ' . $x;
        }
        if ($y !== null) {
            $out[] = '+ ' . $y;
        }
        $shown++;
    }
    return implode("\n", $out);
}

set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
    throw new ErrorException($str, 0, $no, $file, $line);
});

$loc = $argv[1] ?? '';
$files = glob(BW_TESTS_DIR . '/*_test.php') ?: [];
sort($files, SORT_STRING);
$pass = 0;
$fail = 0;
$t0 = microtime(true);

foreach ($files as $file) {
    if ($loc !== '' && !str_contains(basename($file), $loc)) {
        continue;
    }
    $truoc = get_defined_functions()['user'];
    require $file;
    $tests = array_values(array_filter(
        array_diff(get_defined_functions()['user'], $truoc),
        static fn(string $f): bool => str_starts_with($f, 'test_')
    ));
    echo basename($file), PHP_EOL;
    foreach ($tests as $fn) {
        try {
            $fn();
            $pass++;
            echo "  \033[32m✔\033[0m $fn", PHP_EOL;
        } catch (Throwable $e) {
            $fail++;
            echo "  \033[31m✘ $fn\033[0m", PHP_EOL;
            $chiTiet = $e instanceof BwAssertionFailed
                ? $e->getMessage()
                : get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
            echo preg_replace('/^/m', '      ', $chiTiet), PHP_EOL;
        }
    }
}

printf("\n%s%d passed, %d failed\033[0m (%.2fs)\n", $fail ? "\033[31m" : "\033[32m", $pass, $fail, microtime(true) - $t0);
exit($fail > 0 || $pass === 0 ? 1 : 0);
