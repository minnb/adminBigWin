<?php
declare(strict_types=1);

// P02 S1.4 — TenantScope: TenantContext, TenantRepo (CRUD + truyVan "bảng lọc sẵn" — Q-P02-8), SharedRepo.
// Chạy trên bảng demo_* (tests/_tenant_demo.sql, chỉ có trong DB test).

require_once __DIR__ . '/_tenant.php';

const BW_DS_DEMO = ['demo_cha' => true, 'demo_con' => true];

/** SQL đã an toàn hoá cho user 7 (thuần — không DB). */
function bw_an_toan(string $sql): string
{
    return BoQuetSqlTenant::anToanHoa($sql, 7, BW_DS_DEMO);
}

function bw_phai_chan(string $sql, string $vi): void
{
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => bw_an_toan($sql)), "Phải chặn ($vi): $sql");
}

// ---- TenantContext ----

function test_p02_t01_tenant_context_khong_tu_dung_duoc(): void
{
    assert_true((new ReflectionMethod(TenantContext::class, '__construct'))->isPrivate(), 'Constructor phải private');
    assert_true((new ReflectionClass(TenantContext::class))->isFinal(), 'TenantContext phải final (không kế thừa để mở constructor)');
    $loi = bw_test_bat_loi(static fn () => new TenantContext(1, 'user', 'phien'));
    assert_true($loi !== null && str_contains($loi, 'private'), 'new TenantContext từ ngoài phải lỗi, nhận: ' . var_export($loi, true));
    $ctx = bw_ctx(5);
    assert_true(bw_test_bat_loi(static function () use ($ctx): void { $ctx->userId = 6; }) !== null, 'userId phải bất biến (readonly)');
    assert_same([5, 'user', 'phien'], [$ctx->userId, $ctx->vaiTro, $ctx->nguon]);
}

function test_p02_t01_tu_phien_tu_choi_user_khong_hop_le(): void
{
    foreach ([[], ['id' => '5', 'role' => 'user'], ['id' => 0, 'role' => 'user'], ['id' => 5, 'role' => 'root'], ['id' => 5]] as $u) {
        assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => TenantContext::tuPhien($u)), 'User không hợp lệ: ' . json_encode($u));
    }
    assert_true(bw_ctx(9, 'admin')->laAdmin());
    assert_true(!bw_ctx(9)->laAdmin());
}

function test_p02_t02_cho_cli_bat_buoc_ly_do(): void
{
    foreach (['', "  \t"] as $lyDo) {
        assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => TenantContext::choCli(5, $lyDo)), 'Lý do rỗng phải lỗi');
    }
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => TenantContext::choCli(0, 'x')), 'id <= 0 phải lỗi');
    [$ctx, $nhatKy] = bw_voi_nhat_ky(static fn () => TenantContext::choCli(5, 'chay thu T02'));
    assert_same([5, 'cli', false, true], [$ctx->userId, $ctx->nguon, $ctx->laAdmin(), $ctx->laNoiBo()]);
    assert_true(str_contains($nhatKy, 'chay thu T02'), 'Lý do phải được ghi log');
}

function test_p02_s14_ngu_canh_he_thong_khong_dung_duoc_cho_repo_tenant(): void
{
    [$heThong] = bw_voi_nhat_ky(static fn () => TenantContext::heThong('chay thu'));
    assert_same([0, 'he_thong', true], [$heThong->userId, $heThong->nguon, $heThong->laHeThong()]);
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => new DemoChaRepo($heThong)), 'Ngữ cảnh hệ thống không thuộc tenant nào');
}

// ---- TenantRepo: CRUD ----

function test_p02_t03_them_co_khoa_user_id_bi_chan(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => $repo->tao(['ten' => 'x', 'user_id' => $id['b']])));
    assert_same(2, (int) db_test()->query('SELECT COUNT(*) FROM demo_cha WHERE user_id = ' . $id['b'])->fetchColumn(), 'Không được ghi thêm dòng cho B');
    foreach (['id', 'deleted_at', 'deleted_by', 'ly_do_xoa'] as $cot) {
        assert_true(bw_test_loi_lop(static fn () => $repo->tao(['ten' => 'x', $cot => 1])) !== null, "them() không được nhận cột $cot");
    }
    assert_true(bw_test_loi_lop(static fn () => $repo->tao([])) !== null, 'them() rỗng phải lỗi');
    assert_true(bw_test_loi_lop(static fn () => $repo->tao(['ten; DROP TABLE x' => 1])) !== null, 'Tên cột lạ phải lỗi');
}

function test_p02_t04_them_gan_user_id_theo_ngu_canh(): void
{
    $id = bw_tenant_hai_dai_ly();
    $moi = (new DemoChaRepo(bw_ctx($id['a'])))->tao(['ten' => 'moi_a', 'so_tien' => 5]);
    $dong = bw_tenant_dong('demo_cha', $moi);
    assert_same([$id['a'], 'moi_a', 5], [(int) $dong['user_id'], $dong['ten'], (int) $dong['so_tien']]);
}

function test_p02_t05_tenant_a_khong_doc_duoc_cua_b(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    assert_same(null, $repo->lay($id['cha_b']));
    assert_same(null, (new DemoConRepo(bw_ctx($id['a'])))->lay($id['con_b']));
    assert_same('cha1_a', $repo->lay($id['cha_a'])['ten'] ?? null);
}

function test_p02_t06_tenant_a_khong_sua_duoc_cua_b(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    assert_same(false, $repo->capNhat($id['cha_b'], ['ten' => 'hack']));
    assert_same('cha1_b', bw_tenant_dong('demo_cha', $id['cha_b'])['ten']);
    assert_same(true, $repo->capNhat($id['cha_a'], ['ten' => 'doi_ten']));
    assert_same(true, $repo->capNhat($id['cha_a'], ['ten' => 'doi_ten']), 'Ghi lại giá trị cũ vẫn là sửa thành công (không dựa rowCount)');
    assert_same('doi_ten', bw_tenant_dong('demo_cha', $id['cha_a'])['ten']);
    assert_same(false, $repo->capNhat($id['cha_xoa_a'], ['ten' => 'x']), 'Không sửa bản đã xoá mềm');
}

function test_p02_t07_tenant_a_khong_xoa_duoc_cua_b(): void
{
    $id = bw_tenant_hai_dai_ly();
    assert_same(false, (new DemoChaRepo(bw_ctx($id['a'])))->an($id['cha_b'], 'thu'));
    assert_same(null, bw_tenant_dong('demo_cha', $id['cha_b'])['deleted_at']);
}

function test_p02_t08_xoa_mem_cua_minh_giu_dong_va_dau_vet(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    assert_same(true, $repo->an($id['cha_a'], '  Khách yêu cầu  '));
    $dong = bw_tenant_dong('demo_cha', $id['cha_a']);
    assert_true($dong !== null && $dong['deleted_at'] !== null, 'Dòng phải còn trong bảng và có deleted_at');
    assert_same([$id['a'], 'Khách yêu cầu'], [(int) $dong['deleted_by'], $dong['ly_do_xoa']]);
    assert_same(false, $repo->an($id['cha_a'], 'lần 2'), 'Xoá lần 2 không có tác dụng');
    assert_true(bw_test_loi_lop(static fn () => $repo->an($id['cha_xoa_a'], ' ')) !== null, 'Lý do xoá rỗng phải lỗi');
}

function test_p02_t09_tim_khong_tra_ban_da_xoa(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    assert_same([$id['cha_a']], array_map('intval', array_column($repo->danhSach(), 'id')));
    $repo->an($id['cha_a'], 'x');
    assert_same([], $repo->danhSach());
    assert_same(null, $repo->lay($id['cha_a']));
}

function test_p02_t10_tenant_ca_xoa_tra_ca_ban_da_xoa_chi_cua_minh(): void
{
    $id = bw_tenant_hai_dai_ly();
    $dong = (new DemoChaRepo(bw_ctx($id['a'])))->caDaXoa();
    assert_same([$id['cha_a'], $id['cha_xoa_a']], array_map('intval', array_column($dong, 'id')));
    assert_true($dong[1]['deleted_at'] !== null);
}

function test_p02_t14_fk_kep_chan_gan_con_vao_cha_tenant_khac(): void
{
    $id = bw_tenant_hai_dai_ly();
    foreach ([[$id['a'], $id['cha_b']], [$id['b'], $id['cha_a']]] as [$uid, $cha]) {
        $loi = bw_test_bat_loi(static fn () => db_test()->prepare('INSERT INTO demo_con (user_id, cha_id, ten) VALUES (?, ?, ?)')->execute([$uid, $cha, 'cheo']));
        assert_true($loi !== null && str_contains($loi, 'foreign key'), "DB phải từ chối con (user $uid) gắn vào cha $cha, nhận: " . var_export($loi, true));
    }
    assert_true(bw_test_loi_lop(static fn () => (new DemoConRepo(bw_ctx($id['a'])))->tao(['cha_id' => $id['cha_b'], 'ten' => 'cheo'])) !== null,
        'Qua repo cũng bị DB từ chối');
}

function test_p02_t15_sua_cot_bat_bien_bi_chan(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    assert_true(bw_test_loi_lop(static fn () => $repo->capNhat($id['cha_a'], ['ten' => 'x', 'so_tien' => 999])) !== null, 'so_tien bất biến');
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => $repo->capNhat($id['cha_a'], ['user_id' => $id['b']])));
    foreach (['id', 'deleted_at', 'deleted_by', 'ly_do_xoa'] as $cot) {
        assert_true(bw_test_loi_lop(static fn () => $repo->capNhat($id['cha_a'], [$cot => 1])) !== null, "sua() không được đụng $cot");
    }
    $dong = bw_tenant_dong('demo_cha', $id['cha_a']);
    assert_same(['cha1_a', 100, $id['a']], [$dong['ten'], (int) $dong['so_tien'], (int) $dong['user_id']]);
}

function test_p02_s14_repo_bang_ngoai_danh_sach_bi_chan(): void
{
    $loi = bw_test_loi_lop(static fn () => new class (bw_ctx(1)) extends TenantRepo {
        protected const BANG = 'users';
    });
    assert_same(LoiCoLapTenant::class, $loi, 'Repo trỏ bảng không khai báo trong DANH_SACH_BANG_TENANT phải lỗi ngay');
}

// ---- truyVan(): bộ quét SQL fail-closed + bảng lọc sẵn ----

function test_p02_t11_join_thieu_placeholder_bi_chan_truoc_khi_cham_db(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    $nen = bw_tenant_so_cau_lenh();
    $nen = bw_tenant_so_cau_lenh() - $nen; // chi phí của chính câu đếm
    $truoc = bw_tenant_so_cau_lenh();
    $loi = bw_test_loi_lop(static fn () => $repo->chay('SELECT c.id, d.ten FROM {tenant:demo_cha c} JOIN demo_con d ON d.cha_id = c.id'));
    $chenh = bw_tenant_so_cau_lenh() - $truoc;
    assert_same(LoiCoLapTenant::class, $loi);
    assert_same($nen, $chenh, 'Không được gửi câu lệnh nào tới DB trước khi chặn');
    $truoc = bw_tenant_so_cau_lenh();
    $repo->chay('SELECT c.id FROM {tenant:demo_cha c}');
    assert_true(bw_tenant_so_cau_lenh() - $truoc > $nen, 'Bộ đếm phải thấy câu lệnh thật (chứng minh phép đo có hiệu lực)');
}

function test_p02_t12_quet_dung_moi_kieu_alias_va_chu_hoa(): void
{
    foreach (["SELECT * FROM demo_cha AS c", "SELECT * FROM demo_cha c", "SELECT *\nFROM\n   demo_cha\n  c", "select * from DEMO_CHA c"] as $sql) {
        bw_phai_chan($sql, 'bảng tenant trần');
    }
    $mong = '(SELECT * FROM `demo_cha` WHERE user_id = 7 AND deleted_at IS NULL) c';
    foreach (["SELECT * FROM {tenant:demo_cha AS c}", "SELECT * FROM {tenant:demo_cha c}", "SELECT *\nFROM\n {tenant:\n demo_cha\n c }", "select * from {TENANT:Demo_Cha c}"] as $sql) {
        assert_true(str_contains(bw_an_toan($sql), $mong), "Placeholder phải thành bảng lọc sẵn: $sql → " . bw_an_toan($sql));
    }
}

function test_p02_t12_placeholder_chay_that_tren_db_chi_tra_du_lieu_cua_minh(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    foreach (["SELECT c.id FROM {tenant:demo_cha AS c}", "SELECT c.id FROM {tenant:demo_cha c}", "SELECT c.id\nFROM\n {tenant:\n demo_cha\n c }", "select c.id from {TENANT:Demo_Cha c}"] as $sql) {
        assert_same([$id['cha_a']], array_map('intval', array_column($repo->chay($sql), 'id')), $sql);
    }
}

function test_p02_t13_subquery_thieu_placeholder_bi_chan(): void
{
    bw_phai_chan('SELECT c.id FROM {tenant:demo_cha c} WHERE c.id IN (SELECT cha_id FROM demo_con)', 'subquery IN');
    bw_phai_chan('SELECT c.id FROM {tenant:demo_cha c} WHERE EXISTS (SELECT 1 FROM demo_con x WHERE x.cha_id = c.id)', 'EXISTS');
    bw_phai_chan('SELECT x.id FROM (SELECT * FROM demo_cha) x', 'bảng dẫn xuất');
    $id = bw_tenant_hai_dai_ly();
    $dong = (new DemoChaRepo(bw_ctx($id['a'])))->chay('SELECT c.id FROM {tenant:demo_cha c} WHERE c.id IN (SELECT d.cha_id FROM {tenant:demo_con d})');
    assert_same([$id['cha_a']], array_map('intval', array_column($dong, 'id')));
}

function test_p02_t13_cac_dang_cau_khac_bi_chan_fail_closed(): void
{
    $chan = [
        'SELECT * FROM {tenant:demo_cha c}, demo_con d' => 'join dấu phẩy',
        'SELECT * FROM {tenant:demo_cha c} JOIN `demo_con` d ON d.cha_id = c.id' => 'backtick',
        'SELECT * FROM bigwin_test.demo_cha c' => 'kèm tên DB',
        "SELECT * FROM {tenant:demo_cha c} -- x\n JOIN demo_con d" => 'chú thích --',
        "SELECT * FROM {tenant:demo_cha c} # x" => 'chú thích #',
        'SELECT * FROM {tenant:demo_cha c} /* x */' => 'chú thích /* */',
        "SELECT * FROM {tenant:demo_cha c} WHERE c.ten = 'it''s -- " => 'chuỗi chưa đóng',
        'SELECT c.id FROM {tenant:demo_cha c} UNION SELECT d.id FROM {tenant:demo_con d}' => 'UNION',
        'WITH x AS (SELECT * FROM {tenant:demo_cha c}) SELECT * FROM x' => 'CTE',
        'SELECT * FROM {tenant:demo_cha c}; SELECT 1' => 'nhiều câu',
        "UPDATE demo_cha SET ten = 'x'" => 'không phải SELECT',
        'DELETE FROM {tenant:demo_cha c}' => 'DELETE',
        'SELECT c.id INTO @x FROM {tenant:demo_cha c}' => 'INTO',
        'SELECT * FROM {tenant:users u}' => 'placeholder trỏ bảng không phải tenant',
        'SELECT * FROM {tenant:demo_cha demo_con}' => 'alias trùng tên bảng tenant',
        'SELECT * FROM {tenant:demo_cha}' => 'thiếu alias',
        'SELECT * FROM {tenat:demo_cha c}' => 'placeholder sai chính tả',
        'SELECT * FROM {tenant:demo_cha c' => 'placeholder chưa đóng',
        'SELECT * FROM {tenant:demo_cha c} WHERE {tenant:demo_con d}' => 'placeholder ngoài vị trí tên bảng',
        'SELECT demo_cha.id FROM {tenant:demo_cha c}' => 'tên bảng làm tiền tố cột',
    ];
    foreach ($chan as $sql => $vi) {
        bw_phai_chan($sql, $vi);
    }
}

function test_p02_t13_bang_thue_bao_khong_doc_duoc_qua_truy_van(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    foreach (['SELECT c.id, tb.het_han FROM {tenant:demo_cha c} JOIN thue_bao tb ON tb.user_id = c.user_id',
              'SELECT c.id FROM {tenant:demo_cha c} WHERE c.id IN (SELECT user_id FROM su_dung_ngay)',
              'SELECT * FROM {tenant:thue_bao_lich_su l}'] as $sql) {
        assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => $repo->chay($sql)), "Bảng thuê bao chỉ qua ThueBaoRepo: $sql");
    }
    $repoChung = new DemoDungChungRepo(bw_ctx($id['a']));
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => $repoChung->danhSach('id IN (SELECT goi_id FROM thue_bao)')));
}

function test_p02_t13_bang_xac_thuc_khong_doc_duoc_qua_truy_van(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    foreach (['SELECT u.password_hash FROM users u', 'SELECT c.id FROM {tenant:demo_cha c} JOIN phien_dang_nhap p ON p.user_id = c.user_id',
              'SELECT c.id, (SELECT password_hash FROM admins LIMIT 1) AS h FROM {tenant:demo_cha c}'] as $sql) {
        assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => $repo->chay($sql)), "Bảng xác thực không đọc được qua repo: $sql");
    }
}

function test_p02_t13_sql_mode_khop_gia_dinh_cua_bo_quet(): void
{
    // BoQuetSqlTenant coi "..." là CHUỖI và \ là ký tự thoát — đúng khi KHÔNG bật ANSI_QUOTES / NO_BACKSLASH_ESCAPES.
    // Bật một trong hai thì "demo_con" thành tên bảng bị che → bộ quét bỏ sót. db() phải giữ sql_mode này.
    $mode = (string) db_test()->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    foreach (['ANSI_QUOTES', 'NO_BACKSLASH_ESCAPES', 'ANSI'] as $c) {
        assert_true(!in_array($c, explode(',', $mode), true), "sql_mode của db() không được có $c (đang: $mode)");
    }
}

function test_p02_t13_chuoi_ky_tu_khong_bi_tinh_la_bang(): void
{
    foreach (["SELECT c.id FROM {tenant:demo_cha c} WHERE c.ten = 'FROM demo_con'",
              'SELECT c.id FROM {tenant:demo_cha c} WHERE c.ten = "JOIN demo_con d"',
              "SELECT c.id FROM {tenant:demo_cha c} WHERE c.ten = 'it\\'s demo_con' OR c.ten = 'a''b {tenant:x y}'"] as $sql) {
        assert_true(str_contains(bw_an_toan($sql), 'user_id = 7'), "Chuỗi ký tự không phải tên bảng: $sql");
    }
    $id = bw_tenant_hai_dai_ly();
    assert_same([], (new DemoChaRepo(bw_ctx($id['a'])))->chay("SELECT c.id FROM {tenant:demo_cha c} WHERE c.ten = 'FROM demo_con'"));
}

function test_p02_t13_or_khong_mo_duoc_du_lieu_tenant_khac(): void
{
    $id = bw_tenant_hai_dai_ly();
    assert_same([$id['cha_a']], array_map('intval', array_column((new DemoChaRepo(bw_ctx($id['a'])))->timTheoTen('khong_co'), 'id')));
}

function test_p02_t13_left_join_giu_nghia_va_khong_lo_tenant_khac(): void
{
    $id = bw_tenant_hai_dai_ly();
    $repo = new DemoChaRepo(bw_ctx($id['a']));
    $chaRong = $repo->tao(['ten' => 'cha_chua_co_con']);
    $dong = array_map(static fn (array $d): array => [(int) $d['id'], (int) $d['so_con']], $repo->chaVaSoCon());
    assert_same([[$id['cha_a'], 1], [$chaRong, 0]], $dong, 'Cha chưa có con phải còn (LEFT JOIN), không có dòng của B');
}

function test_p02_t13_bang_loc_san_van_dung_chi_muc(): void
{
    $id = bw_tenant_hai_dai_ly();
    $sql = BoQuetSqlTenant::anToanHoa('SELECT c.id FROM {tenant:demo_cha c} WHERE c.id = ?', $id['a'], BW_DS_DEMO);
    $st = db_test()->prepare("EXPLAIN $sql");
    $st->execute([$id['cha_a']]);
    $ke = $st->fetchAll();
    assert_same([], array_values(array_filter($ke, static fn (array $d): bool => $d['select_type'] === 'DERIVED' || $d['key'] === null)),
        'Bảng dẫn xuất phải được gộp (derived_merge) và dùng chỉ mục: ' . json_encode($ke));
}

// ---- Danh sách bảng ----

function test_p02_t18_bang_demo_khong_co_trong_schema(): void
{
    $demo = bw_test_bang_demo();
    assert_true($demo !== [], 'tests/_tenant_demo.sql phải có bảng demo');
    foreach ([BW_WEBAPP_DIR . '/schema.sql', ...(glob(BW_WEBAPP_DIR . '/migrations/*.sql') ?: [])] as $f) {
        assert_same(null, BoQuetSqlTenant::nhacBang(file_get_contents($f), $demo), basename($f) . ' không được chứa bảng demo');
    }
}

function test_p02_t19_danh_sach_bang_tenant_khop_schema(): void
{
    $schema = bw_test_bang_trong_sql(file_get_contents(BW_WEBAPP_DIR . '/schema.sql'));
    $demo = bw_test_bang_demo();
    db_test();
    foreach (TenantRepo::DANH_SACH_BANG_TENANT as $bang => $xoaMem) {
        assert_true(in_array($bang, [...$schema, ...$demo], true), "Bảng tenant $bang không có trong schema.sql hay _tenant_demo.sql");
        $st = db_test()->prepare('SELECT COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $st->execute([$bang]);
        $cot = array_column($st->fetchAll(), 'IS_NULLABLE', 'COLUMN_NAME');
        assert_same('NO', $cot['user_id'] ?? null, "$bang phải có user_id NOT NULL");
        if ($xoaMem) {
            foreach (['deleted_at', 'deleted_by', 'ly_do_xoa'] as $c) {
                assert_true(isset($cot[$c]), "$bang khai báo xoá mềm nhưng thiếu cột $c");
            }
        }
    }
    foreach (TenantRepo::BANG_REPO_RIENG as $bang) {
        assert_true(in_array($bang, $schema, true) && !array_key_exists($bang, TenantRepo::DANH_SACH_BANG_TENANT),
            "Bảng repo riêng $bang phải có trong schema.sql và KHÔNG nằm trong danh sách tenant (Q-P02-1)");
    }
}

// ---- SharedRepo ----

function test_p02_s14_shared_repo_tenant_chi_doc_admin_moi_ghi(): void
{
    bw_tenant_lam_sach();
    $u = bw_auth_tao_user('daily_s');
    $ad = bw_auth_tao_user('admin_s', ['role' => 'admin']);
    $id = (new DemoDungChungRepo(bw_ctx($ad, 'admin')))->tao(['ten' => 'goi_a']);
    assert_true($id > 0);
    $repoUser = new DemoDungChungRepo(bw_ctx($u));
    assert_same(['goi_a'], array_column($repoUser->danhSach(), 'ten'), 'Tenant đọc được bảng dùng chung');
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => $repoUser->tao(['ten' => 'x'])), 'Tenant không được ghi bảng dùng chung');
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => $repoUser->danhSach('id IN (SELECT id FROM demo_cha)')),
        'Điều kiện của SharedRepo không được chạm bảng tenant');
    assert_same(LoiCoLapTenant::class, bw_test_loi_lop(static fn () => new class (bw_ctx($u)) extends SharedRepo {
        protected const BANG = 'demo_cha';
    }), 'SharedRepo chỉ cho bảng trong DANH_SACH_BANG_DUNG_CHUNG');
}
