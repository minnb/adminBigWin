<?php
declare(strict_types=1);

// Hạ tầng test TenantScope (P02 S1.4) — CHỈ dùng trong tests/.

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_tenant_demo.php';

function bw_tenant_lam_sach(): void
{
    bw_auth_lam_sach();
    bw_test_don_bang('demo_con', 'demo_cha', 'demo_dung_chung');
}

/** Ngữ cảnh như sau đăng nhập. Test được tự dựng; mã thật chỉ gateway / app/_layout.php (P02-T16 quét). */
function bw_ctx(int $uid, string $role = 'user'): TenantContext
{
    return TenantContext::tuPhien(['id' => $uid, 'role' => $role]);
}

/** Chạy $fn với error_log trỏ vào tệp tạm (dòng log của ngữ cảnh CLI không lẫn vào output runner). Trả [kết quả, nội dung log]. */
function bw_voi_nhat_ky(callable $fn): array
{
    $tep = tempnam(sys_get_temp_dir(), 'bwlog');
    $cu = ini_set('error_log', $tep);
    try {
        return [$fn(), (string) file_get_contents($tep)];
    } finally {
        ini_set('error_log', (string) $cu);
        @unlink($tep);
    }
}

/** Đọc thẳng một dòng (bỏ qua repo) để đối chiếu. */
function bw_tenant_dong(string $bang, int $id): ?array
{
    $st = db_test()->prepare("SELECT * FROM `$bang` WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * 2 đại lý A, B. Mỗi bên: cha1 (còn), cha_xoa (đã xoá mềm), con1 thuộc cha1.
 * Trả ['a', 'b', 'cha_a', 'cha_xoa_a', 'con_a', 'cha_b', 'cha_xoa_b', 'con_b'].
 */
function bw_tenant_hai_dai_ly(): array
{
    bw_tenant_lam_sach();
    $pdo = db_test();
    $id = ['a' => bw_auth_tao_user('daily_a'), 'b' => bw_auth_tao_user('daily_b')];
    foreach (['a', 'b'] as $k) {
        $uid = $id[$k];
        $pdo->prepare('INSERT INTO demo_cha (user_id, ten, so_tien) VALUES (?, ?, 100)')->execute([$uid, "cha1_$k"]);
        $id["cha_$k"] = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO demo_cha (user_id, ten, so_tien, deleted_at, deleted_by, ly_do_xoa) VALUES (?, ?, 200, NOW(), ?, 'seed')")
            ->execute([$uid, "cha_xoa_$k", $uid]);
        $id["cha_xoa_$k"] = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO demo_con (user_id, cha_id, ten) VALUES (?, ?, ?)')->execute([$uid, $id["cha_$k"], "con1_$k"]);
        $id["con_$k"] = (int) $pdo->lastInsertId();
    }
    return $id;
}

/** Tổng số câu lệnh client đã gửi trong phiên DB (để chứng minh "chưa chạm DB"). */
function bw_tenant_so_cau_lenh(): int
{
    $dem = 0;
    foreach (db_test()->query("SHOW SESSION STATUS WHERE Variable_name IN ('Questions', 'Com_stmt_prepare')")->fetchAll() as $d) {
        $dem += (int) $d['Value'];
    }
    return $dem;
}
