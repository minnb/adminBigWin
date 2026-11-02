<?php
declare(strict_types=1);

// Hạ tầng test đăng nhập / gateway (P01 S1.3) — CHỈ dùng trong tests/.

require_once __DIR__ . '/_db.php';
require_once __DIR__ . '/_http.php';

const BW_MK = 'MatKhau@2026x';

function bw_auth_lam_sach(): void
{
    bw_test_don_bang('phien_dang_nhap', 'user_login_fails', 'gioi_han_tan_suat', 'users', 'admins');
}

/** Tạo user (bcrypt cost 4 cho nhanh — vẫn là hash thật). $them: role, trang_thai, phai_doi_mat_khau. */
function bw_auth_tao_user(string $ten, array $them = []): int
{
    db_test()->prepare('INSERT INTO users (username, password_hash, role, trang_thai, phai_doi_mat_khau, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
        ->execute([$ten, password_hash($them['mat_khau'] ?? BW_MK, PASSWORD_BCRYPT, ['cost' => 4]),
            $them['role'] ?? 'user', $them['trang_thai'] ?? 'active', $them['phai_doi_mat_khau'] ?? 0]);
    return (int) db_test()->lastInsertId();
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

/** POST /app/ajax/_ping.php qua gateway; $tuyChon như bw_http (+ csrf mặc định lấy tự động). */
function bw_ping(string $jar, array $tuyChon = []): array
{
    $tuyChon += ['json' => []];
    if (!array_key_exists('csrf', $tuyChon)) {
        $tuyChon['csrf'] = bw_auth_csrf($jar);
    }
    return bw_http('POST', '/app/ajax/_ping.php', ['jar' => $jar] + $tuyChon);
}

function bw_auth_post_form(string $jar, string $duongDan, array $truong): array
{
    return bw_http('POST', $duongDan, ['jar' => $jar, 'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'body' => http_build_query($truong)]);
}
