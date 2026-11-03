<?php
declare(strict_types=1);

// Đổi mật khẩu (P01 S1.3) — bắt buộc ở lần đăng nhập đầu (cờ phai_doi_mat_khau, O5); đổi xong thu hồi mọi phiên khác.
// Trong app dùng modal "Đổi mật khẩu" (app/ajax/doi_mat_khau.php — P03); trang này vẫn là đích của luồng bắt buộc.

require __DIR__ . '/lib/auth.php';

$u = require_user(true);
$loi = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $truong = static fn (string $k): string => is_string($_POST[$k] ?? null) ? $_POST[$k] : '';
    if (!Csrf::nguonHopLe($_SERVER) || !Csrf::hopLe($truong('csrf'))) {
        http_response_code(403);
        $loi = 'Phiên làm việc đã hết hạn — vui lòng thử lại.';
    } else {
        $loi = implode(' ', doi_mat_khau($u, $truong('mat_khau_cu'), $truong('mat_khau_moi'), $truong('xac_nhan')));
        if ($loi === '') {
            chuyen_huong('app/');
        }
    }
}

$o = 'mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500';
$o_mk = static fn (string $ten, string $nhan, string $ac): string => '<label class="block text-sm font-semibold">' . $nhan
    . '<input type="password" name="' . $ten . '" autocomplete="' . $ac . '" required class="' . $o . '"></label>';
trang_don_gian('Đổi mật khẩu',
    ($u['phai_doi_mat_khau'] === 1 ? '<p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Lần đăng nhập đầu: bạn cần đặt mật khẩu mới để tiếp tục.</p>' : '')
    . ($loi !== '' ? '<p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">' . h($loi) . '</p>' : '')
    . '<form method="post" class="space-y-3"><input type="hidden" name="csrf" value="' . h(Csrf::token()) . '">'
    . $o_mk('mat_khau_cu', 'Mật khẩu hiện tại', 'current-password')
    . $o_mk('mat_khau_moi', 'Mật khẩu mới (ít nhất ' . MAT_KHAU_TOI_THIEU . ' ký tự)', 'new-password')
    . $o_mk('xac_nhan', 'Nhập lại mật khẩu mới', 'new-password')
    . '<button class="w-full rounded-lg bg-blue-700 py-2 font-bold text-white hover:bg-blue-800">Đổi mật khẩu</button></form>');
