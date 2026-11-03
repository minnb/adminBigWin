<?php
declare(strict_types=1);

// Hạ tầng test đăng nhập / gateway (P01 S1.3) — CHỈ dùng trong tests/.

require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_http.php';
require_once __DIR__ . '/../lib/auth.php';

const BW_MK = 'MatKhau@2026x';

function bw_auth_lam_sach(): void
{
    bw_test_don_bang('phien_dang_nhap', 'user_login_fails', 'gioi_han_tan_suat', 'thue_bao_lich_su', 'su_dung_ngay', 'thue_bao', 'users', 'admins');
}

/**
 * Tạo user (băm thật qua bam_mat_khau() — tham số nhẹ khi moi_truong = test). $them: role, trang_thai, phai_doi_mat_khau, mat_khau, thue_bao:
 *   (mặc định, role user) gói `unlimited` còn 30 ngày — test không về bản quyền khỏi bị 402 (Q-P02-2) · false = không cấp ·
 *   ['het_han' => 'Y-m-d', 'goi' => mã gói, 'tam_khoa' => 0|1] = thuê bao tuỳ chọn.
 */
function bw_auth_tao_user(string $ten, array $them = []): int
{
    $role = $them['role'] ?? 'user';
    db_test()->prepare('INSERT INTO users (username, password_hash, role, trang_thai, phai_doi_mat_khau, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$ten, bam_mat_khau($them['mat_khau'] ?? BW_MK), // tham số băm nhẹ khi moi_truong = test — không rehash khi đăng nhập
            $role, $them['trang_thai'] ?? 'active', $them['phai_doi_mat_khau'] ?? 0]);
    $id = (int) db_test()->lastInsertId();
    $tb = $them['thue_bao'] ?? ($role === 'user');
    if ($tb !== false) {
        $tb = (is_array($tb) ? $tb : []) + ['het_han' => null, 'goi' => 'unlimited', 'tam_khoa' => 0];
        db_test()->prepare('INSERT INTO thue_bao (user_id, goi_id, bat_dau, het_han, tam_khoa, cap_nhat_luc)
            SELECT ?, id, CURDATE() - INTERVAL 60 DAY, COALESCE(?, CURDATE() + INTERVAL 30 DAY), ?, NOW() FROM goi_dich_vu WHERE ma = ?')
            ->execute([$id, $tb['het_han'], $tb['tam_khoa'], $tb['goi']]);
    }
    return $id;
}

function bw_auth_meta_csrf(string $html): ?string
{
    return preg_match('/<meta name="csrf-token" content="([0-9a-f]{64})">/', $html, $m) ? $m[1] : null;
}

/** Mở /login.php lấy token rồi POST form đăng nhập (như trình duyệt). Trả response của POST. */
function bw_auth_dang_nhap(string $jar, string $ten, string $mk = BW_MK): array
{
    $csrf = bw_auth_meta_csrf(bw_http('GET', '/login.php', ['jar' => $jar])['than']);
    return bw_http('POST', '/login.php', ['jar' => $jar, 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'body' => http_build_query(['csrf' => $csrf, 'username' => $ten, 'password' => $mk])]);
}

/** Token CSRF của phiên đã đăng nhập (đọc từ trang đổi mật khẩu). */
function bw_auth_csrf(string $jar): string
{
    $tok = bw_auth_meta_csrf(bw_http('GET', '/doi_mat_khau.php', ['jar' => $jar])['than']);
    if ($tok === null) {
        throw new RuntimeException("Jar '$jar' chưa đăng nhập — không lấy được CSRF");
    }
    return $tok;
}

/** URL endpoint thử của gateway theo chế độ (tests/_router.php — P03 QĐ2). */
const BW_AJAX_THU = '/__bw_test__/ajax/';

/** POST endpoint thử qua gateway ('che_do' doc|ghi|phan_tich, mặc định doc); $tuyChon như bw_http (+ csrf lấy tự động). */
function bw_ping(string $jar, array $tuyChon = []): array
{
    $tuyChon += ['json' => [], 'che_do' => 'doc'];
    if (!array_key_exists('csrf', $tuyChon)) {
        $tuyChon['csrf'] = bw_auth_csrf($jar);
    }
    return bw_http('POST', BW_AJAX_THU . $tuyChon['che_do'], ['jar' => $jar] + $tuyChon);
}

function bw_auth_post_form(string $jar, string $duongDan, array $truong): array
{
    return bw_http('POST', $duongDan, ['jar' => $jar, 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'body' => http_build_query($truong)]);
}
