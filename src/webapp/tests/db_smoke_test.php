<?php
declare(strict_types=1);

// P01 S1.0 — hạ tầng test DB: DB test dựng sạch từ schema.sql, cfg()/db() trỏ vào DB test,
// và schema.sql luôn khớp "baseline + migration mới" (P01-T02, định nghĩa lại theo Q-P01-3).

require_once __DIR__ . '/_db.php';

function test_p01_t01_cau_hinh_test_tro_vao_db_test(): void
{
    assert_same('test', cfg('moi_truong'), 'tools/test.php phải trỏ cfg() sang cấu hình test (BIGWIN_CONFIG_FILE)');
    assert_true(str_ends_with(bw_ten_db_test((string) cfg('db_dsn')), '_test'), 'db_dsn khi chạy test phải là DB *_test');
}

function test_p01_t01_dung_db_test_hai_lan_khong_loi(): void
{
    db_test();
    bw_test_dung_db_chinh();
    $bang = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($bang, SORT_STRING);
    $mong = bw_test_bang_trong_sql(file_get_contents(BW_WEBAPP_DIR . '/schema.sql') . file_get_contents(__DIR__ . '/_tenant_demo.sql'));
    assert_same($mong, $bang, 'DB test chính = schema.sql + bảng demo TenantScope (P02)');
    assert_same(bw_ten_db_test((string) cfg('db_dsn')), db()->query('SELECT DATABASE()')->fetchColumn());
}

function test_p01_t01_thieu_khoa_db_test_thi_bao_loi(): void
{
    $loi = bw_test_bat_loi(static fn () => bw_cau_hinh_test(['db_dsn' => 'mysql:host=127.0.0.1;dbname=bigwin_license']));
    assert_true($loi !== null && str_contains($loi, 'db_test_dsn'), 'Thiếu db_test_* phải báo lỗi rõ khoá thiếu, nhận: ' . var_export($loi, true));
}

function test_p01_t01_tu_choi_db_khong_ket_thuc_test(): void
{
    $loi = bw_test_bat_loi(static fn () => bw_ten_db_test('mysql:host=127.0.0.1;dbname=bigwin_license;charset=utf8mb4'));
    assert_true($loi !== null && str_contains($loi, '_test'), 'DB không kết thúc _test phải bị từ chối (chặn xoá nhầm DB thật)');
}

function test_p01_t02_baseline_cong_migration_moi_bang_schema_hien_tai(): void
{
    $chinh = bw_ten_db_test((string) cfg('db_dsn'));
    $mig = preg_replace('/_test$/', '_mig_test', $chinh);
    db_test();
    $sql = bw_test_git('show', BW_TEST_BASELINE . ':src/webapp/schema.sql');
    foreach (bw_test_migration_moi(BW_TEST_BASELINE) as $tep) {
        $sql .= "\n" . file_get_contents($tep);
    }
    bw_test_dung_db($mig, $sql);
    try {
        $pdo = bw_test_pdo_may_chu();
        assert_same(bw_test_cau_truc($pdo, $chinh), bw_test_cau_truc($pdo, $mig),
            "schema.sql hiện tại phải = schema.sql@" . BW_TEST_BASELINE . " + migration mới (cột, chỉ mục, khoá ngoại)");
    } finally {
        bw_test_pdo_may_chu()->exec("DROP DATABASE IF EXISTS `$mig`");
    }
}
