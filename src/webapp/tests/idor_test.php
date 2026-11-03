<?php
declare(strict_types=1);

// P02 S1.4 — P02-T17: khung IDOR. Liệt kê MỌI lớp `extends TenantRepo` (lib/bigwin/repo/ + repo demo), gọi MỌI method
// public dưới ngữ cảnh tenant A với id dữ liệu của tenant B → không được trả dữ liệu của B, dữ liệu B không đổi.
// Repo thật từ P07+ tự động vào vòng quét. Kiểu tham số không sinh được → FAIL (buộc bổ sung cách gọi), không bỏ qua.

require_once __DIR__ . '/_tenant.php';

foreach (glob(BW_WEBAPP_DIR . '/lib/bigwin/repo/*.php') ?: [] as $tep) {
    require_once $tep;
}

/** Lớp repo tenant cụ thể (không abstract) đang được nạp. */
function bw_idor_lop_repo(): array
{
    $lop = array_values(array_filter(get_declared_classes(), static fn (string $c): bool =>
        is_subclass_of($c, TenantRepo::class) && !(new ReflectionClass($c))->isAbstract() && !(new ReflectionClass($c))->isAnonymous()));
    sort($lop, SORT_STRING);
    return $lop;
}

/** Dấu vân tay toàn bộ dữ liệu của tenant $uid trên mọi bảng tenant. */
function bw_idor_van_tay(int $uid): string
{
    $tat = [];
    foreach (array_keys(TenantRepo::DANH_SACH_BANG_TENANT) as $bang) {
        $st = db_test()->prepare("SELECT * FROM `$bang` WHERE user_id = ? ORDER BY id");
        $st->execute([$uid]);
        $tat[$bang] = $st->fetchAll();
    }
    return hash('sha256', json_encode($tat));
}

/** Tham số cho method: int → $idB, string → 'idor', array → ['ten' => 'idor'], bool → false. Không sinh được → null. */
function bw_idor_tham_so(ReflectionMethod $m, int $idB): ?array
{
    $ts = [];
    foreach ($m->getParameters() as $p) {
        $kieu = $p->getType();
        $ten = $kieu instanceof ReflectionNamedType ? $kieu->getName() : null;
        $ts[] = match ($ten) {
            'int' => $idB,
            'string' => 'idor',
            'array' => ['ten' => 'idor'],
            'bool' => false,
            default => null,
        };
        if (end($ts) === null) {
            return null;
        }
    }
    return $ts;
}

/** Giá trị trả về có chứa dữ liệu của B không (user_id = B, hoặc id thuộc danh sách id của B). */
function bw_idor_lo(mixed $kq, int $uidB, array $idB): bool
{
    if (!is_array($kq)) {
        return false;
    }
    if ((isset($kq['user_id']) && (int) $kq['user_id'] === $uidB) || (isset($kq['id']) && in_array((int) $kq['id'], $idB, true))) {
        return true;
    }
    foreach ($kq as $v) {
        if (bw_idor_lo($v, $uidB, $idB)) {
            return true;
        }
    }
    return false;
}

function test_p02_t17_moi_method_public_cua_moi_repo_khong_lo_du_lieu_tenant_khac(): void
{
    $id = bw_tenant_hai_dai_ly();
    $vanTay = bw_idor_van_tay($id['b']);
    $lop = bw_idor_lop_repo();
    assert_true(in_array(DemoChaRepo::class, $lop, true) && in_array(DemoConRepo::class, $lop, true), 'Phải tìm thấy repo demo: ' . implode(', ', $lop));
    $daGoi = 0;
    foreach ($lop as $c) {
        $bang = (new ReflectionClassConstant($c, 'BANG'))->getValue();
        $st = db_test()->prepare("SELECT id FROM `$bang` WHERE user_id = ?");
        $st->execute([$id['b']]);
        $idB = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        assert_true($idB !== [], "Thiếu dữ liệu của B trong $bang để thử $c");
        $repo = new $c(bw_ctx($id['a']));
        foreach ((new ReflectionClass($c))->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isConstructor() || $m->isStatic() || $m->getDeclaringClass()->getName() === TenantRepo::class) {
                continue;
            }
            foreach ($idB as $mot) {
                $ts = bw_idor_tham_so($m, $mot);
                assert_true($ts !== null, "$c::{$m->getName()}: kiểu tham số chưa hỗ trợ — bổ sung bw_idor_tham_so()");
                try {
                    $kq = $m->invokeArgs($repo, $ts);
                } catch (Throwable) {
                    $kq = null; // bị chặn bằng exception = không lộ dữ liệu
                }
                $daGoi++;
                assert_true(!bw_idor_lo($kq, $id['b'], $idB), "$c::{$m->getName()}(" . json_encode($ts) . ') trả dữ liệu của tenant B: ' . json_encode($kq));
            }
        }
    }
    assert_true($daGoi >= 10, "Gọi quá ít method ($daGoi)");
    assert_same($vanTay, bw_idor_van_tay($id['b']), 'Dữ liệu của tenant B bị thay đổi');
}
