<?php
declare(strict_types=1);

// Đăng nhập chung web BigWIN (đại lý & quản trị) — P01 S1.3; từ P03 admin/login.php cũng chuyển về đây.

require __DIR__ . '/lib/auth.php';

if (nguoi_dung_hien_tai() !== null) {
    chuyen_huong('app/');
}

$ten = '';
$loi = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $ten = post_text('username', 50);
    if (!form_hop_le()) {
        http_response_code(403);
        $loi = 'Phiên làm việc đã hết hạn — vui lòng đăng nhập lại.';
    } else {
        $kq = dang_nhap($ten, is_string($_POST['password'] ?? null) ? $_POST['password'] : '');
        if ($kq['ok']) {
            chuyen_huong($kq['phai_doi_mat_khau'] ? 'doi_mat_khau.php' : 'app/');
        }
        $loi = $kq['thong_diep'];
    }
}

$o = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500';
trang_don_gian('Đăng nhập', ($loi !== '' ? '<p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">' . h($loi) . '</p>' : '')
    . '<form method="post" class="space-y-3">' . truong_csrf()
    . '<label class="block text-sm font-semibold">Tên đăng nhập<input name="username" value="' . h($ten) . '" autocomplete="username" required autofocus class="' . $o . '"></label>'
    . '<label class="block text-sm font-semibold">Mật khẩu<input type="password" name="password" autocomplete="current-password" required class="' . $o . '"></label>'
    . '<button class="w-full rounded-lg bg-blue-700 py-2 font-bold text-white hover:bg-blue-800">Đăng nhập</button></form>');
