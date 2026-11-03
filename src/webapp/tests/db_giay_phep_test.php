<?php
declare(strict_types=1);

// P02 S1.5 — ThueBaoRepo trên DB test: dữ liệu khởi tạo, đếm hạn mức nguyên tử, lịch sử append-only, cron, giấy phép nền tảng.

require_once __DIR__ . '/_thue_bao.php';

function test_p02_s15_du_lieu_khoi_tao_cua_migration(): void
{
    bw_gp_lam_sach();
    $goi = db_test()->query("SELECT ma, so_tin_ngay_toi_da, so_khach_toi_da, so_ngay_an_han FROM goi_dich_vu ORDER BY ma")->fetchAll();
    assert_same([
        ['ma' => 'dung_thu', 'so_tin_ngay_toi_da' => null, 'so_khach_toi_da' => null, 'so_ngay_an_han' => 3],
        ['ma' => 'unlimited', 'so_tin_ngay_toi_da' => null, 'so_khach_toi_da' => null, 'so_ngay_an_han' => 3],
    ], $goi, 'Q-P02-3: seed gói unlimited + dung_thu');
    assert_same([['id' => 1, 'so_dai_ly_toi_da' => null]], db_test()->query('SELECT id, so_dai_ly_toi_da FROM giay_phep_he_thong')->fetchAll());
    assert_true(bw_test_bat_loi(static fn () => db_test()->exec("INSERT INTO giay_phep_he_thong (id, cap_nhat_luc) VALUES (2, NOW())")) !== null,
        'Giấy phép nền tảng chỉ có đúng 1 dòng (CHECK id = 1)');
}

function test_p02_s15_cua_toi_chi_doc_thue_bao_cua_chinh_minh(): void
{
    bw_gp_lam_sach();
    $a = bw_gp_dai_ly('tb_a', bw_gp_ngay(5));
    $b = bw_gp_dai_ly('tb_b', bw_gp_ngay(90), 'dung_thu');
    $cua = (new ThueBaoRepo(bw_ctx($a)))->cuaToi();
    assert_same([$a, bw_gp_ngay(5), 'unlimited'], [(int) $cua['user_id'], $cua['het_han'], $cua['goi_ma']]);
    assert_same('dung_thu', (new ThueBaoRepo(bw_ctx($b)))->cuaToi()['goi_ma']);
    assert_same(null, (new ThueBaoRepo(bw_ctx(bw_gp_dai_ly('tb_c', null))))->cuaToi());
}

function test_p02_t25_vuot_han_muc_ngay_bi_tu_choi(): void
{
    bw_gp_lam_sach();
    bw_gp_goi('goi_3', ['so_tin_ngay_toi_da' => 3]);
    $uid = bw_gp_dai_ly('hm25', bw_gp_ngay(10), 'goi_3');
    $repo = new ThueBaoRepo(bw_ctx($uid));
    $homNay = today_vn();
    $kq = [];
    for ($i = 0; $i < 4; $i++) {
        $kq[] = $repo->tangDem('so_tin_doi_soat', $homNay);
    }
    assert_same([true, true, true, false], $kq);
    assert_same(3, bw_gp_da_dung($uid, 'so_tin_doi_soat', bw_gp_ngay()));
    assert_same(true, $repo->tangDem('so_tin_doi_soat', $homNay->modify('+1 day')), 'Hạn mức tính theo từng ngày');
    for ($i = 0; $i < 5; $i++) {
        assert_same(true, $repo->tangDem('so_lan_phan_tich', $homNay), 'so_lan_phan_tich không có hạn mức trong gói');
    }
    assert_true(bw_test_bat_loi(static fn () => $repo->tangDem('so_khach', $homNay)) !== null, 'Cột đếm lạ phải lỗi');
}

function test_p02_t25_chua_co_thue_bao_khong_dem_duoc_admin_khong_gioi_han(): void
{
    bw_gp_lam_sach();
    $uid = bw_gp_dai_ly('hm25b', null);
    assert_same(false, (new ThueBaoRepo(bw_ctx($uid)))->tangDem('so_tin_doi_soat', today_vn()), 'Fail-closed: chưa có thuê bao');
    $ad = bw_auth_tao_user('ad25', ['role' => 'admin']);
    $repo = new ThueBaoRepo(bw_ctx($ad, 'admin'));
    for ($i = 0; $i < 5; $i++) {
        assert_same(true, $repo->tangDem('so_tin_doi_soat', today_vn()));
    }
}

function test_p02_t26_hai_tien_trinh_dong_thoi_chi_mot_thanh_cong(): void
{
    bw_gp_lam_sach();
    bw_gp_goi('goi_3', ['so_tin_ngay_toi_da' => 3]);
    $uid = bw_gp_dai_ly('dt26', bw_gp_ngay(10), 'goi_3');
    $ngay = bw_gp_ngay();
    db_test()->prepare('INSERT INTO su_dung_ngay (user_id, ngay, so_tin_doi_soat) VALUES (?, ?, 2)')->execute([$uid, $ngay]); // còn đúng 1 chỗ
    $con = __DIR__ . '/_tang_dem_dong_thoi.php';
    $a = proc_open([PHP_BINARY, $con, (string) $uid, 'giu', $ngay], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $ongA);
    $dongA = trim((string) fgets($ongA[1])); // A đã giữ khoá dòng, chờ lệnh commit
    $b = proc_open([PHP_BINARY, $con, (string) $uid, 'thuong', $ngay], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $ongB);
    // Chờ tới khi B bị khoá chờ A (tối đa 30 s; B kết thúc mà chưa từng chờ = mất khoá → dừng, test đỏ). Nhịp đọc PHẢI > 0,1 s:
    // InnoDB chỉ làm mới bộ đệm INNODB_TRX khi bảng này không bị đọc ≥ 0,1 s — đọc dày hơn thì thấy mãi ảnh cũ.
    $thayCho = false;
    for ($i = 0; $i < 150 && !$thayCho && proc_get_status($b)['running']; $i++) {
        usleep(200_000);
        $thayCho = (int) db_test()->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state = 'LOCK WAIT'")->fetchColumn() > 0;
    }
    fwrite($ongA[0], "\n");
    fclose($ongA[0]);
    $loiA = stream_get_contents($ongA[1]) . stream_get_contents($ongA[2]);
    $dongB = trim((string) stream_get_contents($ongB[1]));
    $loiB = stream_get_contents($ongB[2]);
    proc_close($a);
    proc_close($b);
    assert_true($thayCho, 'Tiến trình B phải bị khoá chờ A (chứng minh 2 giao dịch chồng nhau). A: ' . $dongA . $loiA . ' B: ' . $dongB . $loiB);
    assert_same(['OK', 'TU_CHOI'], [$dongA, $dongB], "Đúng 1 tiến trình thành công. Lỗi A: $loiA · Lỗi B: $loiB");
    assert_same(3, bw_gp_da_dung($uid, 'so_tin_doi_soat', $ngay));
}

function test_p02_t27_moi_thao_tac_quan_tri_sinh_dung_mot_dong_lich_su(): void
{
    bw_gp_lam_sach();
    bw_gp_goi('vip');
    $ad = bw_auth_tao_user('ad27', ['role' => 'admin']);
    $uid = bw_gp_dai_ly('dl27', null);
    $repo = new ThueBaoRepo(bw_ctx($ad, 'admin'));
    $repo->tao($uid, 'dung_thu', today_vn(), today_vn()->modify('+7 day'));
    $repo->giaHan($uid, today_vn()->modify('+37 day'));
    $repo->doiGoi($uid, 'vip');
    $repo->tamKhoa($uid, 'Nợ phí');
    $repo->moKhoa($uid);
    $ls = bw_gp_lich_su($uid);
    assert_same(['tao', 'gia_han', 'doi_goi', 'tam_khoa', 'mo_khoa'], array_column($ls, 'hanh_dong'));
    assert_same([$ad], array_values(array_unique(array_column($ls, 'boi'))), 'boi = admin thao tác');
    assert_same(null, $ls[0]['truoc']);
    assert_same(['dung_thu', bw_gp_ngay(7), 'dung_thu'], [$ls[0]['sau']['goi_ma'], $ls[0]['sau']['het_han'], $ls[0]['sau']['trang_thai']]);
    assert_same([bw_gp_ngay(7), bw_gp_ngay(37)], [$ls[1]['truoc']['het_han'], $ls[1]['sau']['het_han']]);
    assert_same(['dung_thu', 'vip', 'hieu_luc'], [$ls[2]['truoc']['goi_ma'], $ls[2]['sau']['goi_ma'], $ls[2]['sau']['trang_thai']]);
    assert_same([0, 1, 'Nợ phí', 'tam_khoa'], [$ls[3]['truoc']['tam_khoa'], $ls[3]['sau']['tam_khoa'], $ls[3]['sau']['ly_do_khoa'], $ls[3]['sau']['trang_thai']]);
    assert_same([1, 0, null], [$ls[4]['truoc']['tam_khoa'], $ls[4]['sau']['tam_khoa'], $ls[4]['sau']['ly_do_khoa']]);
    assert_same($ls[4]['sau'], array_intersect_key($repo->lichSu($uid)[4]['sau'] ?? [], $ls[4]['sau']), 'lichSu() đọc đúng dữ liệu đã ghi');
}

function test_p02_t27_mat_quan_tri_tu_choi_ngu_canh_dai_ly_va_du_lieu_sai(): void
{
    bw_gp_lam_sach();
    $ad = bw_auth_tao_user('ad27b', ['role' => 'admin']);
    $a = bw_gp_dai_ly('dl27a', bw_gp_ngay(5));
    $b = bw_gp_dai_ly('dl27b', bw_gp_ngay(5));
    $repoDaiLy = new ThueBaoRepo(bw_ctx($a));
    foreach ([
        'giaHan' => static fn () => $repoDaiLy->giaHan($b, today_vn()->modify('+999 day')),
        'tamKhoa' => static fn () => $repoDaiLy->tamKhoa($b, 'x'),
        'lichSu' => static fn () => $repoDaiLy->lichSu($b),
        'tao' => static fn () => $repoDaiLy->tao($b, 'unlimited', today_vn(), today_vn()),
    ] as $ten => $fn) {
        assert_same(LoiCoLapTenant::class, bw_test_loi_lop($fn), "Đại lý không được gọi $ten (mặt quản trị)");
    }
    assert_same(bw_gp_ngay(5), (new ThueBaoRepo(bw_ctx($b)))->cuaToi()['het_han'], 'Thuê bao của B không đổi');
    $repo = new ThueBaoRepo(bw_ctx($ad, 'admin'));
    assert_true(bw_test_loi_lop(static fn () => $repo->tao($a, 'unlimited', today_vn(), today_vn())) !== null, 'Đã có thuê bao → không tạo lần 2');
    assert_true(bw_test_loi_lop(static fn () => $repo->tao($ad, 'unlimited', today_vn(), today_vn())) !== null, 'Không cấp thuê bao cho admin');
    assert_true(bw_test_loi_lop(static fn () => $repo->doiGoi($a, 'khong_co')) !== null, 'Gói không tồn tại');
    assert_true(bw_test_loi_lop(static fn () => $repo->giaHan(bw_gp_dai_ly('dl27c', null), today_vn())) !== null, 'Chưa có thuê bao → không gia hạn');
    assert_true(bw_test_loi_lop(static fn () => $repo->tamKhoa($a, '  ')) !== null, 'Tạm khoá bắt buộc lý do');
    assert_true(bw_test_loi_lop(static fn () => $repo->tao(bw_gp_dai_ly('dl27d', null), 'unlimited', today_vn(), today_vn()->modify('-1 day'))) !== null, 'Hết hạn trước bắt đầu');
    assert_same([], bw_gp_lich_su($a), 'Thao tác bị từ chối không ghi lịch sử');
}

function test_p03_s17_gia_han_them_cong_tu_het_han_hoac_tu_hom_nay(): void
{
    bw_gp_lam_sach();
    $ad = bw_auth_tao_user('ad_gh', ['role' => 'admin']);
    $repo = new ThueBaoRepo(bw_ctx($ad, 'admin'));
    $conHan = bw_gp_dai_ly('gh_con', bw_gp_ngay(5));
    $quaHan = bw_gp_dai_ly('gh_qua', bw_gp_ngay(-10));
    $repo->giaHanThem($conHan, 30);
    $repo->giaHanThem($quaHan, 30);
    $hh = static fn (int $id): string => (string) db_test()->query("SELECT het_han FROM thue_bao WHERE user_id = $id")->fetchColumn();
    assert_same([bw_gp_ngay(35), bw_gp_ngay(30)], [$hh($conHan), $hh($quaHan)], 'Còn hạn cộng tiếp; quá hạn tính từ hôm nay');
    assert_same(['gia_han'], array_column(bw_gp_lich_su($conHan), 'hanh_dong'));
    foreach ([0, ThueBaoRepo::GIA_HAN_TOI_DA_NGAY + 1] as $sai) {
        assert_true(bw_test_loi_lop(static fn () => $repo->giaHanThem($conHan, $sai)) !== null, "Số ngày $sai phải bị từ chối");
    }
    assert_true(bw_test_loi_lop(static fn () => $repo->giaHanThem(bw_gp_dai_ly('gh_chua', null), 30)) !== null, 'Chưa có thuê bao → không gia hạn');
}

function test_p02_t28_lich_su_chi_ghi_them(): void
{
    $cong = array_map(static fn (ReflectionMethod $m): string => $m->getName(),
        array_filter((new ReflectionClass(ThueBaoRepo::class))->getMethods(ReflectionMethod::IS_PUBLIC), static fn (ReflectionMethod $m): bool => !$m->isConstructor()));
    sort($cong, SORT_STRING);
    assert_same(['coTheThemDaiLy', 'cuaToi', 'doiGoi', 'ghiChuyenTrangThaiTuDong', 'giaHan', 'giaHanThem', 'lichSu', 'moKhoa', 'sapHetHan', 'soDaiLyHoatDong', 'tamKhoa', 'tangDem', 'tao'],
        $cong, 'Method public của ThueBaoRepo — không có method sửa/xoá lịch sử');
    $nguon = file_get_contents(BW_WEBAPP_DIR . '/lib/bigwin/repo/ThueBaoRepo.php');
    assert_true(!preg_match('/\bUPDATE\s+`?thue_bao_lich_su\b/i', $nguon) && !preg_match('/\bDELETE\b/i', $nguon), 'ThueBaoRepo không được có UPDATE lịch sử hay DELETE');
}

function test_p02_t29_cron_chay_hai_lan_khong_ghi_trung(): void
{
    bw_gp_lam_sach();
    $ad = bw_auth_tao_user('ad29', ['role' => 'admin']);
    $repo = new ThueBaoRepo(bw_ctx($ad, 'admin'));
    $uid = bw_gp_dai_ly('dl29', null);
    $repo->tao($uid, 'unlimited', today_vn()->modify('-40 day'), today_vn()->modify('+5 day')); // lịch sử 'tao' ghi trạng thái hieu_luc
    db_test()->prepare('UPDATE thue_bao SET het_han = ? WHERE user_id = ?')->execute([bw_gp_ngay(-10), $uid]); // thời gian trôi: đã quá ân hạn
    $chay = static fn (): array => bw_test_chay([PHP_BINARY, BW_WEBAPP_DIR . '/tools/cap_nhat_thue_bao.php']);
    [$ma1, $ra1, $loi1] = $chay();
    [$ma2, $ra2, $loi2] = $chay();
    assert_same([0, 0], [$ma1, $ma2], "cron phải thoát 0. Lần 1: $ra1 $loi1 · Lần 2: $ra2 $loi2");
    $ls = bw_gp_lich_su($uid);
    assert_same(['tao', 'tu_dong'], array_column($ls, 'hanh_dong'), 'Lần 2 cùng ngày không ghi trùng');
    assert_same(['hieu_luc', 'het_han', null], [$ls[1]['truoc']['trang_thai'], $ls[1]['sau']['trang_thai'], $ls[1]['boi']]);
    assert_true(str_contains($ra1, 'ghi 1') && str_contains($ra2, 'ghi 0'), "Báo số dòng đã ghi. Lần 1: $ra1 · Lần 2: $ra2");
}

function test_p02_t30_giay_phep_nen_tang_dem_dai_ly_hoat_dong(): void
{
    bw_gp_lam_sach();
    $ad = bw_auth_tao_user('ad30', ['role' => 'admin']);
    bw_auth_tao_user('ad30b', ['role' => 'admin']);
    bw_gp_dai_ly('dl30a', bw_gp_ngay(5));
    bw_gp_dai_ly('dl30b', null);
    bw_auth_tao_user('dl30_khoa', ['trang_thai' => 'locked']);
    $repo = new ThueBaoRepo(bw_ctx($ad, 'admin'));
    assert_same(2, $repo->soDaiLyHoatDong(), 'Chỉ đếm role=user đang active (admin, tài khoản khoá không tính)');
    assert_same(true, $repo->coTheThemDaiLy(), 'NULL = không giới hạn');
    db_test()->exec('UPDATE giay_phep_he_thong SET so_dai_ly_toi_da = 2');
    assert_same(false, $repo->coTheThemDaiLy());
    db_test()->exec('UPDATE giay_phep_he_thong SET so_dai_ly_toi_da = 3');
    assert_same(true, $repo->coTheThemDaiLy());
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => (new ThueBaoRepo(bw_ctx(bw_gp_dai_ly('dl30c', null))))->coTheThemDaiLy()), 'Chỉ admin/hệ thống');
}

function test_p02_s15_sap_het_han_cho_nhac_gia_han(): void
{
    bw_gp_lam_sach();
    $ad = bw_auth_tao_user('ad31', ['role' => 'admin']);
    $sap = bw_gp_dai_ly('sap', bw_gp_ngay(2));
    bw_gp_dai_ly('xa', bw_gp_ngay(30));
    bw_gp_dai_ly('qua', bw_gp_ngay(-1));
    $ds = (new ThueBaoRepo(bw_ctx($ad, 'admin')))->sapHetHan(today_vn(), 3);
    assert_same([[$sap, 'sap', bw_gp_ngay(2)]], array_map(static fn (array $d): array => [(int) $d['user_id'], $d['username'], $d['het_han']], $ds));
}
