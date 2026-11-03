<?php
declare(strict_types=1);

// Repo demo cho test TenantScope (P02 S1.4) — đứng trên bảng demo_* chỉ có trong DB test (tests/_tenant_demo.sql).
// Viết đúng như một repo nghiệp vụ thật: chỉ gọi method của lớp nền, không SQL trực tiếp, không user_id.

require_once __DIR__ . '/../lib/bigwin/repo/TenantRepo.php';
require_once __DIR__ . '/../lib/bigwin/repo/SharedRepo.php';

final class DemoChaRepo extends TenantRepo
{
    protected const BANG = 'demo_cha';
    protected const COT_BAT_BIEN = ['so_tien'];

    public function lay(int $id): ?array
    {
        return $this->timMot($id);
    }

    public function danhSach(): array
    {
        return $this->tim('1=1', [], 'ORDER BY id');
    }

    public function tao(array $cot): int
    {
        return $this->them($cot);
    }

    public function capNhat(int $id, array $cot): bool
    {
        return $this->sua($id, $cot);
    }

    public function an(int $id, string $lyDo): bool
    {
        return $this->xoaMem($id, $lyDo);
    }

    /** LEFT JOIN: cha chưa có con vẫn phải hiện (điều kiện tenant không được biến LEFT thành INNER). */
    public function chaVaSoCon(): array
    {
        return $this->truyVan('SELECT c.id, COUNT(d.id) AS so_con FROM {tenant:demo_cha c}
            LEFT JOIN {tenant:demo_con d} ON d.cha_id = c.id GROUP BY c.id ORDER BY c.id')->fetchAll();
    }

    public function caDaXoa(): array
    {
        return $this->truyVan('SELECT c.id, c.deleted_at FROM {tenant_ca_xoa:demo_cha c} ORDER BY c.id')->fetchAll();
    }

    /** "OR 1 = 1" cố ý: bảng lọc sẵn nên OR không mở được dữ liệu tenant khác. */
    public function timTheoTen(string $ten): array
    {
        return $this->truyVan('SELECT c.id FROM {tenant:demo_cha c} WHERE c.ten = ? OR 1 = 1 ORDER BY c.id', [$ten])->fetchAll();
    }

    /** SQL tuỳ ý qua truyVan() — CHỈ để test bộ quét. */
    public function chay(string $sql, array $thamSo = []): array
    {
        return $this->truyVan($sql, $thamSo)->fetchAll();
    }
}

final class DemoConRepo extends TenantRepo
{
    protected const BANG = 'demo_con';

    public function lay(int $id): ?array
    {
        return $this->timMot($id);
    }

    public function tao(array $cot): int
    {
        return $this->them($cot);
    }
}

final class DemoDungChungRepo extends SharedRepo
{
    protected const BANG = 'demo_dung_chung';

    public function danhSach(string $dieuKien = '1=1'): array
    {
        return $this->doc($dieuKien, [], 'ORDER BY id');
    }

    public function tao(array $cot): int
    {
        return $this->them($cot);
    }
}
