<?php
declare(strict_types=1);

// Hạ tầng test DB (P01 S1.0) — CHỈ dùng trong tests/.
// cfg()/db() đã trỏ vào DB test nhờ tools/test.php (BIGWIN_CONFIG_FILE → tests/_cau_hinh.php).
// Mọi DROP/CREATE đi qua bw_kiem_ten_db_test() → chỉ chạm DB có tên kết thúc "_test".

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/_cau_hinh_ham.php';

const BW_WEBAPP_DIR = __DIR__ . '/..';
const BW_REPO_DIR = __DIR__ . '/../../..';
/** Mốc schema cho P01-T02 (Q-P01-3): commit cuối trước khi P01 thêm migration. */
const BW_TEST_BASELINE = '0533c33';

/** Chạy tiến trình con (kế thừa môi trường, gồm BIGWIN_CONFIG_FILE; $env ghi đè từng biến). Trả [mã thoát, stdout, stderr]. */
function bw_test_chay(array $lenh, array $env = []): array
{
    $p = proc_open($lenh, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $ong, null, $env === [] ? null : $env + getenv());
    if (!is_resource($p)) {
        throw new RuntimeException('Không chạy được: ' . implode(' ', $lenh));
    }
    $ra = stream_get_contents($ong[1]);
    $loi = stream_get_contents($ong[2]);
    return [proc_close($p), $ra, $loi];
}

/** PDO tới máy chủ MySQL test, chưa chọn DB (để DROP/CREATE). */
function bw_test_pdo_may_chu(): PDO
{
    static $pdo = null;
    return $pdo ??= new PDO(bw_tach_dsn_test((string) cfg('db_dsn'))[0], cfg('db_user'), cfg('db_pass'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

/** Tách tệp SQL thành từng câu: bỏ dòng comment `--`, tách ở `;` cuối dòng (schema/migration không dùng DELIMITER). */
function bw_test_tach_sql(string $sql): array
{
    $dong = array_filter(preg_split('/\R/', $sql), static fn (string $d): bool => !str_starts_with(ltrim($d), '--'));
    $cau = preg_split('/;\s*$/m', implode("\n", $dong));
    return array_values(array_filter(array_map('trim', $cau), static fn (string $c): bool => $c !== ''));
}

/** Chạy lần lượt mọi câu của một tệp SQL. */
function bw_test_chay_sql(PDO $pdo, string $sql): void
{
    foreach (bw_test_tach_sql($sql) as $cau) {
        $pdo->exec($cau);
    }
}

/** Tên bảng trong các câu CREATE TABLE của một tệp SQL, đã sắp xếp. */
function bw_test_bang_trong_sql(string $sql): array
{
    preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)?\s+`?(\w+)`?/i', $sql, $m);
    $bang = array_values(array_unique($m[1]));
    sort($bang, SORT_STRING);
    return $bang;
}

/** Xoá & dựng lại DB $ten (bắt buộc *_test) từ chuỗi SQL. */
function bw_test_dung_db(string $ten, string $sql): void
{
    bw_kiem_ten_db_test($ten);
    $pdo = bw_test_pdo_may_chu();
    $pdo->exec("DROP DATABASE IF EXISTS `$ten`");
    $pdo->exec("CREATE DATABASE `$ten` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$ten`");
    bw_test_chay_sql($pdo, $sql);
}

/** Bảng demo TenantScope — nguồn DUY NHẤT là tests/_tenant_demo.sql (chỉ nạp vào DB test chính, không có trong schema.sql). */
function bw_test_bang_demo(): array
{
    return bw_test_bang_trong_sql(file_get_contents(__DIR__ . '/_tenant_demo.sql'));
}

/** Mọi tệp .php của src/webapp ngoài tests/ và config.php (bí mật — không bao giờ đọc): [đường dẫn tương đối => tuyệt đối]. */
function bw_test_tep_php(): array
{
    $goc = realpath(BW_WEBAPP_DIR);
    $tep = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($goc, FilesystemIterator::SKIP_DOTS)) as $f) {
        $duong = str_replace('\\', '/', substr($f->getPathname(), strlen($goc) + 1));
        if ($f->getExtension() === 'php' && !str_starts_with($duong, 'tests/') && $duong !== 'config.php') {
            $tep[$duong] = $f->getPathname();
        }
    }
    ksort($tep, SORT_STRING);
    return $tep;
}

/** Dựng lại DB test chính từ schema.sql hiện tại + bảng demo TenantScope (luôn dựng, kể cả khi đã dựng trong lần chạy này). */
function bw_test_dung_db_chinh(): void
{
    $ten = bw_ten_db_test((string) cfg('db_dsn'));
    bw_test_dung_db($ten, file_get_contents(BW_WEBAPP_DIR . '/schema.sql') . "\n" . file_get_contents(__DIR__ . '/_tenant_demo.sql'));
    db()->exec("USE `$ten`"); // DROP DATABASE làm kết nối db() đang mở mất DB đang chọn
    $GLOBALS['bw_test_db_da_dung'] = true;
}

/** db() trên DB test — chỉ dựng DB lần đầu trong mỗi lần chạy runner. */
function db_test(): PDO
{
    if (empty($GLOBALS['bw_test_db_da_dung'])) {
        bw_test_dung_db_chinh();
    }
    return db();
}

/** Làm rỗng các bảng giữa các test (giữ cấu trúc). DELETE thay TRUNCATE: TRUNCATE tạo lại tệp .ibd, chậm ~10 lần. */
function bw_test_don_bang(string ...$bang): void
{
    $pdo = db_test();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($bang as $b) {
            $pdo->exec("DELETE FROM `$b`");
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

function bw_test_dem(string $bang): int
{
    return (int) db_test()->query("SELECT COUNT(*) FROM `$bang`")->fetchColumn();
}

/** Chạy git trong repo, trả stdout; lỗi → ném. */
function bw_test_git(string ...$thamSo): string
{
    [$ma, $ra, $loi] = bw_test_chay(['git', '-C', realpath(BW_REPO_DIR), ...$thamSo]);
    if ($ma !== 0) {
        throw new RuntimeException('git ' . implode(' ', $thamSo) . " lỗi: $loi");
    }
    return $ra;
}

/** Các tệp migration có trong thư mục hiện tại nhưng CHƯA có ở $rev, theo thứ tự tên (= thứ tự áp dụng). */
function bw_test_migration_moi(string $rev): array
{
    $cu = array_map('basename', preg_split('/\R/', trim(bw_test_git('ls-tree', '--name-only', $rev, 'src/webapp/migrations/'))));
    $moi = array_values(array_filter(glob(BW_WEBAPP_DIR . '/migrations/*.sql') ?: [], static fn (string $f): bool => !in_array(basename($f), $cu, true)));
    sort($moi, SORT_STRING);
    return $moi;
}

/** Cấu trúc DB để so sánh: cột (không tính thứ tự cột), chỉ mục, khoá ngoại. Bỏ bảng demo (bw_test_bang_demo — chỉ có ở DB test chính). */
function bw_test_cau_truc(PDO $pdo, string $db): array
{
    $hoi = static function (string $sql) use ($pdo, $db): array {
        $st = $pdo->prepare($sql);
        $st->execute([$db, implode(',', bw_test_bang_demo())]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    };
    return [
        'cot' => $hoi('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME
                         FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND NOT FIND_IN_SET(TABLE_NAME, ?) ORDER BY TABLE_NAME, COLUMN_NAME'),
        'chi_muc' => $hoi('SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, NON_UNIQUE
                             FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND NOT FIND_IN_SET(TABLE_NAME, ?) ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX'),
        'khoa_ngoai' => $hoi('SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
                                     r.UPDATE_RULE, r.DELETE_RULE
                                FROM information_schema.KEY_COLUMN_USAGE k
                                JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                                  ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                                 AND r.TABLE_NAME = k.TABLE_NAME
                               WHERE k.TABLE_SCHEMA = ? AND NOT FIND_IN_SET(k.TABLE_NAME, ?) ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION'),
    ];
}
