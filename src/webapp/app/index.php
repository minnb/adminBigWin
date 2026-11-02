<?php
declare(strict_types=1);

// Trang đích TẠM sau đăng nhập (P01 S1.3) — P03 (S1.6) thay bằng khung app 6 page.

require __DIR__ . '/../lib/auth.php';

$u = require_user();
trang_don_gian('BigWIN',
    '<p class="mb-4">Xin chào <b>' . h($u['ten_hien_thi'] !== '' ? $u['ten_hien_thi'] : $u['username']) . '</b> (' . h($u['role']) . ').</p>'
    . '<p class="mb-4 text-sm text-slate-500">Khung ứng dụng sẽ có ở Phase P03.</p>'
    . '<div class="flex gap-2"><a class="flex-1 rounded-lg border border-slate-300 py-2 text-center text-sm font-semibold" href="' . h(url_web('doi_mat_khau.php')) . '">Đổi mật khẩu</a>'
    . '<form method="post" action="' . h(url_web('logout.php')) . '" class="flex-1"><input type="hidden" name="csrf" value="' . h(Csrf::token()) . '">'
    . '<button class="w-full rounded-lg bg-slate-800 py-2 text-sm font-semibold text-white">Đăng xuất</button></form></div>');
