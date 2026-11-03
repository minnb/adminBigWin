<?php
declare(strict_types=1);

// Quản trị › Tài khoản (P03 S1.7): tạo (kiểm giấy phép nền tảng, mật khẩu tạm 1 lần — QĐ6), khoá / mở, đặt lại mật khẩu,
// đăng xuất mọi nơi, đổi quyền. Luật nằm ở UserRepo (≥ 1 admin, không tự khoá / hạ quyền).

require __DIR__ . '/_quan_tri.php';

$ctx = khung_bat_dau('quan_tri', true);
$repo = new UserRepo($ctx['tenant']);
$kq = quan_tri_xu_ly_post(static function (string $hanhDong) use ($repo): array {
    switch ($hanhDong) {
        case 'tao':
            $vaiTro = o_post('vai_tro');
            $t = $repo->tao(o_post('username'), $vaiTro, o_post('ten_hien_thi'),
                $vaiTro === 'user' ? o_post('goi') : null, $vaiTro === 'user' ? o_post_ngay('het_han') : null);
            return ['loai' => 'ok', 'thong_diep' => 'Đã tạo tài khoản ' . o_post('username') . '.', 'cho' => o_post('username'), 'mat_khau_tam' => $t['mat_khau_tam']];
        case 'dat_lai_mat_khau':
            $id = o_post_id();
            return ['loai' => 'ok', 'thong_diep' => "Đã đặt lại mật khẩu tài khoản #$id và đăng xuất mọi phiên.", 'cho' => "#$id", 'mat_khau_tam' => $repo->datLaiMatKhau($id)];
        case 'khoa':
            $repo->khoa(o_post_id());
            return ['loai' => 'ok', 'thong_diep' => 'Đã khoá tài khoản và đăng xuất mọi phiên.'];
        case 'mo_khoa':
            $repo->moKhoa(o_post_id());
            return ['loai' => 'ok', 'thong_diep' => 'Đã mở khoá tài khoản.'];
        case 'dang_xuat_moi_noi':
            $repo->dangXuatMoiNoi(o_post_id());
            return ['loai' => 'ok', 'thong_diep' => 'Đã đăng xuất tài khoản khỏi mọi nơi.'];
        case 'doi_vai_tro':
            $repo->doiVaiTro(o_post_id(), o_post('vai_tro'));
            return ['loai' => 'ok', 'thong_diep' => 'Đã đổi quyền (tài khoản cần đăng nhập lại).'];
        default:
            throw new InvalidArgumentException('Thao tác không hợp lệ.');
    }
});

$goi = (new GoiDichVuRepo($ctx['tenant']))->dangBan();
$tai = $repo->danhSach();
khung_ve($ctx, 'Quản trị — Tài khoản', static function (array $ctx) use ($kq, $goi, $tai): void {
    quan_tri_dau('users', $kq);
    ?>
<?php if (isset($kq['mat_khau_tam'])): ?>
<section class="card p-4 mb-4 border-amber-300 bg-[#FFF9DB]" data-mat-khau-tam="<?= h($kq['mat_khau_tam']) ?>">
  <div class="font-bold text-pos-title"><i class="fa-solid fa-key mr-2"></i>Mật khẩu tạm của <?= h($kq['cho']) ?></div>
  <div class="mono text-2xl font-extrabold my-2 select-all"><?= h($kq['mat_khau_tam']) ?></div>
  <p class="text-sm">Chỉ hiện <b>một lần</b> — chép gửi cho người dùng ngay. Lần đăng nhập đầu họ phải đổi mật khẩu.</p>
</section>
<?php endif; ?>

<section class="card p-4 mb-4">
  <div class="font-extrabold text-lg text-pos-title mb-3"><i class="fa-solid fa-user-plus mr-2"></i>Tạo tài khoản</div>
  <form method="post" class="grid gap-3 md:grid-cols-3 items-end" x-data="{ vaiTro: 'user' }">
    <?= truong_csrf() ?><input type="hidden" name="hanh_dong" value="tao">
    <label class="text-sm font-semibold">Tên đăng nhập<input class="ctl mt-1 mono" name="username" required pattern="[A-Za-z0-9_.\-]{3,50}" autocomplete="off"></label>
    <label class="text-sm font-semibold">Tên hiển thị<input class="ctl mt-1" name="ten_hien_thi" maxlength="100"></label>
    <label class="text-sm font-semibold">Quyền
      <select class="ctl mt-1" name="vai_tro" x-model="vaiTro"><option value="user">Đại lý (user)</option><option value="admin">Quản trị (admin)</option></select></label>
    <label class="text-sm font-semibold" x-show="vaiTro === 'user'">Gói
      <select class="ctl mt-1" name="goi"><?php foreach ($goi as $g): ?><option value="<?= h($g['ma']) ?>"><?= h($g['ten']) ?> (<?= h($g['ma']) ?>)</option><?php endforeach; ?></select></label>
    <label class="text-sm font-semibold" x-show="vaiTro === 'user'">Hết hạn thuê bao<input type="date" class="ctl mt-1 mono" name="het_han" value="<?= h(het_han_mac_dinh()) ?>"></label>
    <div><button class="btn btn-primary"><i class="fa-solid fa-plus"></i>Tạo &amp; sinh mật khẩu tạm</button></div>
  </form>
</section>

<section class="card overflow-x-auto">
  <table class="tbl text-[14.5px]">
    <thead><tr><th>Tài khoản</th><th>Quyền</th><th>Trạng thái</th><th>Thuê bao</th><th>Đăng nhập cuối</th><th class="text-right">Thao tác</th></tr></thead>
    <tbody>
    <?php foreach ($tai as $t): $id = (int) $t['id']; $laMinh = $id === $ctx['user']['id']; ?>
      <tr data-tai-khoan="<?= h($t['username']) ?>">
        <td><div class="font-bold mono"><?= h($t['username']) ?></div><div class="text-sm text-pos-muted"><?= h($t['ten_hien_thi']) ?></div></td>
        <td><span class="pill <?= $t['role'] === 'admin' ? 'pill-info' : 'pill-ok' ?>"><?= $t['role'] === 'admin' ? 'admin' : 'đại lý' ?></span></td>
        <td><?= $t['trang_thai'] === 'active' ? '<span class="pill pill-ok">hoạt động</span>' : '<span class="pill pill-err">đã khoá</span>' ?>
          <?= (int) $t['phai_doi_mat_khau'] === 1 ? '<span class="pill pill-warn">chờ đổi mật khẩu</span>' : '' ?></td>
        <td class="mono text-sm"><?= $t['role'] === 'admin' ? '—' : ($t['goi_ma'] === null ? '<span class="pill pill-err">chưa có</span>' : h($t['goi_ma']) . ' · ' . h($t['het_han']) . ((int) $t['tam_khoa'] === 1 ? ' · tạm khoá' : '')) ?></td>
        <td class="mono text-sm"><?= h((string) ($t['last_login_at'] ?? '—')) ?></td>
        <td class="text-right whitespace-nowrap">
          <?php if ($laMinh): ?><span class="text-sm text-pos-muted">(bạn)</span><?php else: ?>
          <?= $t['trang_thai'] === 'active' ? nut_form('khoa', $id, 'Khoá', 'btn-ghost', 'Khoá tài khoản ' . $t['username'] . '?') : nut_form('mo_khoa', $id, 'Mở khoá') ?>
          <?= nut_form('dat_lai_mat_khau', $id, 'Đặt lại MK', 'btn-ghost', 'Đặt lại mật khẩu ' . $t['username'] . '?') ?>
          <?= nut_form('dang_xuat_moi_noi', $id, 'Đăng xuất mọi nơi') ?>
          <?= $t['role'] === 'admin' ? nut_form('doi_vai_tro', $id, '→ đại lý', an: ['vai_tro' => 'user']) : nut_form('doi_vai_tro', $id, '→ admin', an: ['vai_tro' => 'admin']) ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php
});
