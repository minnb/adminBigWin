<?php
declare(strict_types=1);

// Quản trị › Gói & Thuê bao (P03 S1.7): gói — thêm / sửa / ngừng bán, KHÔNG xoá (QĐ7); thuê bao đại lý — cấp mới, gia hạn,
// đổi gói, tạm khoá / mở, xem lịch sử (ThueBaoRepo, mỗi thao tác 1 dòng lịch sử). Giới hạn nền tảng chỉ HIỂN THỊ "Đã dùng
// X/Y" — sửa bằng CLI tools/giay_phep_nen_tang.php (QĐ7).

require __DIR__ . '/_quan_tri.php';

const QT_GIA_HAN_TOI_DA_NGAY = 3650;

$ctx = khung_bat_dau('quan_tri', true);
$thueBao = new ThueBaoRepo($ctx['tenant']);
$goiRepo = new GoiDichVuRepo($ctx['tenant']);
$daiLy = array_values(array_filter((new UserRepo($ctx['tenant']))->danhSach(), static fn (array $u): bool => $u['role'] === 'user'));

$kq = quan_tri_xu_ly_post(static function (string $hanhDong) use ($thueBao, $goiRepo, $daiLy): array {
    $cotGoi = ['ten', 'gia_thang', 'so_khach_toi_da', 'so_tin_ngay_toi_da', 'so_ngay_tra_cuu_lich_su', 'so_ngay_an_han', 'cho_phep_telegram'];
    $goiTuForm = static fn (): array => array_combine($cotGoi, array_map('o_post', $cotGoi));
    switch ($hanhDong) {
        case 'them_goi':
            $goiRepo->themGoi(['ma' => o_post('ma')] + $goiTuForm());
            return ['loai' => 'ok', 'thong_diep' => 'Đã thêm gói ' . o_post('ma') . '.'];
        case 'sua_goi':
            $goiRepo->suaGoi(o_post_id(), $goiTuForm());
            return ['loai' => 'ok', 'thong_diep' => 'Đã lưu gói.'];
        case 'ngung_ban':
        case 'mo_ban':
            $goiRepo->datDangBan(o_post_id(), $hanhDong === 'mo_ban');
            return ['loai' => 'ok', 'thong_diep' => $hanhDong === 'mo_ban' ? 'Gói bán lại.' : 'Gói đã ngừng bán (thuê bao đang dùng vẫn giữ).'];
        case 'cap_moi':
            $thueBao->tao(o_post_id(), o_post('goi'), GiayPhepGuard::homNay(), o_post_ngay('het_han'));
            return ['loai' => 'ok', 'thong_diep' => 'Đã cấp thuê bao.'];
        case 'gia_han':
            $id = o_post_id();
            $soNgay = (int) o_post('so_ngay');
            if ($soNgay < 1 || $soNgay > QT_GIA_HAN_TOI_DA_NGAY) {
                throw new InvalidArgumentException('Số ngày gia hạn 1–' . QT_GIA_HAN_TOI_DA_NGAY . '.');
            }
            $hienTai = array_column($daiLy, 'het_han', 'id')[$id] ?? throw new InvalidArgumentException('Đại lý chưa có thuê bao — dùng "Cấp thuê bao".');
            // Còn hạn → cộng tiếp từ ngày hết hạn; đã quá hạn → tính từ hôm nay
            $tu = max($hienTai, GiayPhepGuard::homNay()->format('Y-m-d'));
            $thueBao->giaHan($id, (new DateTimeImmutable($tu, new DateTimeZone(BW_TZ)))->modify("+$soNgay day"));
            return ['loai' => 'ok', 'thong_diep' => "Đã gia hạn $soNgay ngày."];
        case 'doi_goi':
            $thueBao->doiGoi(o_post_id(), o_post('goi'));
            return ['loai' => 'ok', 'thong_diep' => 'Đã đổi gói.'];
        case 'tam_khoa':
            $thueBao->tamKhoa(o_post_id(), o_post('ly_do'));
            return ['loai' => 'ok', 'thong_diep' => 'Đã tạm khoá dịch vụ.'];
        case 'mo_tam_khoa':
            $thueBao->moKhoa(o_post_id());
            return ['loai' => 'ok', 'thong_diep' => 'Đã mở tạm khoá dịch vụ.'];
        default:
            throw new InvalidArgumentException('Thao tác không hợp lệ.');
    }
});

if ($kq !== null) { // nạp lại sau thao tác
    $daiLy = array_values(array_filter((new UserRepo($ctx['tenant']))->danhSach(), static fn (array $u): bool => $u['role'] === 'user'));
}
$goi = $goiRepo->danhSach();
$giayPhep = (new GiayPhepNenTangRepo($ctx['tenant']))->hienTai();
$dangDung = $thueBao->soDaiLyHoatDong();
$xemLichSu = preg_match('/^[1-9]\d{0,9}$/', (string) ($_GET['lich_su'] ?? '')) === 1 ? (int) $_GET['lich_su'] : null;
$lichSu = $xemLichSu === null ? [] : $thueBao->lichSu($xemLichSu);

khung_ve($ctx, 'Quản trị — Gói & Thuê bao', static function (array $ctx) use ($kq, $goi, $daiLy, $giayPhep, $dangDung, $xemLichSu, $lichSu): void {
    quan_tri_dau('goi_thue_bao', $kq);
    $toiDa = $giayPhep['so_dai_ly_toi_da'] ?? '∞';
    $dangBan = array_filter($goi, static fn (array $g): bool => (int) $g['dang_ban'] === 1);
    $chonGoi = static function (string $ten) use ($dangBan): string {
        $s = '<select class="ctl !py-1 !w-auto" name="' . h($ten) . '">';
        foreach ($dangBan as $g) {
            $s .= '<option value="' . h($g['ma']) . '">' . h($g['ten']) . '</option>';
        }
        return $s . '</select>';
    };
    $o = static fn (string $ten, string $nhan, mixed $giaTri = '', string $kieu = 'text'): string => '<label class="text-sm font-semibold">' . h($nhan)
        . '<input class="ctl mt-1" type="' . h($kieu) . '" name="' . h($ten) . '" value="' . h((string) ($giaTri ?? '')) . '"></label>';
    ?>
<section class="card p-4 mb-4 flex items-center gap-3 flex-wrap">
  <span class="pill pill-info" data-giay-phep="<?= h($dangDung . '/' . $toiDa) ?>"><i class="fa-solid fa-id-card"></i>Giấy phép nền tảng: đã dùng <?= h($dangDung . '/' . $toiDa) ?> đại lý</span>
  <span class="text-sm text-pos-muted">Giới hạn do nhà cung cấp đặt (công cụ dòng lệnh) — không sửa tại đây.</span>
</section>

<?php if ($xemLichSu !== null): ?>
<section class="card p-4 mb-4">
  <div class="font-extrabold text-lg text-pos-title mb-2"><i class="fa-solid fa-clock-rotate-left mr-2"></i>Lịch sử thuê bao #<?= $xemLichSu ?></div>
  <table class="tbl text-[14px]"><thead><tr><th>Lúc</th><th>Hành động</th><th>Bởi</th><th>Sau thay đổi</th></tr></thead><tbody>
  <?php foreach ($lichSu as $d): ?>
    <tr><td class="mono"><?= h($d['luc']) ?></td><td><?= h($d['hanh_dong']) ?></td><td><?= $d['boi'] === null ? 'hệ thống' : '#' . (int) $d['boi'] ?></td>
      <td class="mono text-sm"><?= h($d['sau']['goi_ma'] . ' · hết hạn ' . $d['sau']['het_han'] . ' · ' . $d['sau']['trang_thai'] . ((int) $d['sau']['tam_khoa'] === 1 ? ' · tạm khoá' : '')) ?></td></tr>
  <?php endforeach; ?>
  <?php if ($lichSu === []): ?><tr><td colspan="4" class="text-pos-muted">Chưa có lịch sử.</td></tr><?php endif; ?>
  </tbody></table>
</section>
<?php endif; ?>

<section class="card overflow-x-auto mb-4">
  <div class="font-extrabold text-lg text-pos-title p-4 pb-2"><i class="fa-solid fa-users mr-2"></i>Thuê bao đại lý</div>
  <table class="tbl text-[14.5px]">
    <thead><tr><th>Đại lý</th><th>Gói</th><th>Hết hạn</th><th>Thao tác</th></tr></thead>
    <tbody>
    <?php foreach ($daiLy as $u): $id = (int) $u['id']; ?>
      <tr>
        <td><div class="font-bold mono"><?= h($u['username']) ?></div><div class="text-sm text-pos-muted"><?= h($u['ten_hien_thi']) ?><?= $u['trang_thai'] !== 'active' ? ' · tài khoản đã khoá' : '' ?></div></td>
        <?php if ($u['goi_ma'] === null): ?>
        <td colspan="2"><span class="pill pill-err">chưa có thuê bao</span></td>
        <td><form method="post" class="flex gap-2 items-center flex-wrap"><?= o_csrf() ?><input type="hidden" name="hanh_dong" value="cap_moi"><input type="hidden" name="id" value="<?= $id ?>">
          <?= $chonGoi('goi') ?><input type="date" class="ctl !py-1 !w-auto mono" name="het_han" value="<?= h(GiayPhepGuard::homNay()->modify('+30 day')->format('Y-m-d')) ?>">
          <button class="btn btn-primary !px-2.5 !py-1 !text-[13px]">Cấp thuê bao</button></form></td>
        <?php else: ?>
        <td class="mono"><?= h($u['goi_ma']) ?><?= (int) $u['tam_khoa'] === 1 ? ' <span class="pill pill-err">tạm khoá</span>' : '' ?></td>
        <td class="mono"><?= h($u['het_han']) ?></td>
        <td class="whitespace-nowrap">
          <form method="post" class="inline-flex gap-1 items-center"><?= o_csrf() ?><input type="hidden" name="hanh_dong" value="gia_han"><input type="hidden" name="id" value="<?= $id ?>">
            <input class="ctl !py-1 !w-20 mono" type="number" name="so_ngay" value="30" min="1" max="<?= QT_GIA_HAN_TOI_DA_NGAY ?>"><button class="btn btn-primary !px-2.5 !py-1 !text-[13px]">Gia hạn (ngày)</button></form>
          <form method="post" class="inline-flex gap-1 items-center"><?= o_csrf() ?><input type="hidden" name="hanh_dong" value="doi_goi"><input type="hidden" name="id" value="<?= $id ?>">
            <?= $chonGoi('goi') ?><button class="btn btn-ghost !px-2.5 !py-1 !text-[13px]">Đổi gói</button></form>
          <?php if ((int) $u['tam_khoa'] === 1): ?>
            <?= nut_form('mo_tam_khoa', $id, 'Mở tạm khoá') ?>
          <?php else: ?>
          <form method="post" class="inline-flex gap-1 items-center"><?= o_csrf() ?><input type="hidden" name="hanh_dong" value="tam_khoa"><input type="hidden" name="id" value="<?= $id ?>">
            <input class="ctl !py-1 !w-36" name="ly_do" placeholder="Lý do tạm khoá" required><button class="btn btn-ghost !px-2.5 !py-1 !text-[13px]">Tạm khoá</button></form>
          <?php endif; ?>
          <a class="btn btn-ghost !px-2.5 !py-1 !text-[13px]" href="?lich_su=<?= $id ?>">Lịch sử</a>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if ($daiLy === []): ?><tr><td colspan="4" class="text-pos-muted">Chưa có tài khoản đại lý — tạo ở tab Tài khoản.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>

<section class="card p-4">
  <div class="font-extrabold text-lg text-pos-title mb-3"><i class="fa-solid fa-box mr-2"></i>Gói dịch vụ <span class="text-sm font-semibold text-pos-muted">(hạn mức để trống = không giới hạn; gói không xoá được, chỉ ngừng bán)</span></div>
  <?php foreach ($goi as $g): $gid = (int) $g['id']; ?>
  <form method="post" class="grid gap-2 md:grid-cols-8 items-end border-b border-slate-100 py-3"><?= o_csrf() ?><input type="hidden" name="hanh_dong" value="sua_goi"><input type="hidden" name="id" value="<?= $gid ?>">
    <div class="text-sm"><div class="font-bold mono"><?= h($g['ma']) ?></div><?= (int) $g['dang_ban'] === 1 ? '<span class="pill pill-ok">đang bán</span>' : '<span class="pill pill-warn">ngừng bán</span>' ?></div>
    <?= $o('ten', 'Tên', $g['ten']) ?><?= $o('gia_thang', 'Giá/tháng', $g['gia_thang'], 'number') ?><?= $o('so_khach_toi_da', 'Khách tối đa', $g['so_khach_toi_da'], 'number') ?>
    <?= $o('so_tin_ngay_toi_da', 'Tin/ngày tối đa', $g['so_tin_ngay_toi_da'], 'number') ?><?= $o('so_ngay_tra_cuu_lich_su', 'Ngày tra cứu', $g['so_ngay_tra_cuu_lich_su'], 'number') ?>
    <?= $o('so_ngay_an_han', 'Ngày ân hạn', $g['so_ngay_an_han'], 'number') ?>
    <div class="flex gap-2 items-center"><label class="text-sm"><input type="checkbox" name="cho_phep_telegram" value="1"<?= (int) $g['cho_phep_telegram'] === 1 ? ' checked' : '' ?>> Telegram</label>
      <button class="btn btn-primary !px-2.5 !py-1 !text-[13px]">Lưu</button></div>
  </form>
  <div class="text-right -mt-1 mb-2"><?= nut_form((int) $g['dang_ban'] === 1 ? 'ngung_ban' : 'mo_ban', $gid, (int) $g['dang_ban'] === 1 ? 'Ngừng bán' : 'Bán lại') ?></div>
  <?php endforeach; ?>
  <form method="post" class="grid gap-2 md:grid-cols-8 items-end pt-3"><?= o_csrf() ?><input type="hidden" name="hanh_dong" value="them_goi">
    <?= $o('ma', 'Mã gói mới') ?><?= $o('ten', 'Tên') ?><?= $o('gia_thang', 'Giá/tháng', '0', 'number') ?><?= $o('so_khach_toi_da', 'Khách tối đa', '', 'number') ?>
    <?= $o('so_tin_ngay_toi_da', 'Tin/ngày tối đa', '', 'number') ?><?= $o('so_ngay_tra_cuu_lich_su', 'Ngày tra cứu', '', 'number') ?><?= $o('so_ngay_an_han', 'Ngày ân hạn', '3', 'number') ?>
    <div class="flex gap-2 items-center"><label class="text-sm"><input type="checkbox" name="cho_phep_telegram" value="1" checked> Telegram</label>
      <button class="btn btn-success !px-2.5 !py-1 !text-[13px]"><i class="fa-solid fa-plus"></i>Thêm gói</button></div>
  </form>
</section>
<?php
});
