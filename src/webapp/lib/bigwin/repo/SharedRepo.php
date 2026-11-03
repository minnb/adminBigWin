<?php
declare(strict_types=1);

// Lớp nền cho bảng DÙNG CHUNG (không có user_id — rule tenant-isolation.md "Dữ liệu dùng chung"): tenant chỉ ĐỌC;
// ghi chỉ khi ngữ cảnh admin (web) hoặc CLI. Không có SQL tự do → không JOIN được sang bảng tenant.

require_once __DIR__ . '/TenantRepo.php';

abstract class SharedRepo
{
    /** Bảng dùng chung được phép. demo_dung_chung: chỉ có trong DB test. Bảng mới (ket_qua_xo_so, …) khai báo thêm ở đây. */
    public const DANH_SACH_BANG_DUNG_CHUNG = ['demo_dung_chung', 'goi_dich_vu', 'giay_phep_he_thong'];

    protected const BANG = '';

    public function __construct(private readonly TenantContext $ctx)
    {
        if (!in_array(static::BANG, self::DANH_SACH_BANG_DUNG_CHUNG, true)) {
            throw new LoiCoLapTenant(static::class . ': BANG `' . static::BANG . '` chưa khai báo trong SharedRepo::DANH_SACH_BANG_DUNG_CHUNG');
        }
    }

    /** Đọc bảng dùng chung. $dieuKien/$sau không được nhắc bảng tenant, bảng repo riêng hay bảng xác thực. */
    final protected function doc(string $dieuKien = '1=1', array $thamSo = [], string $sau = ''): array
    {
        $bang = BoQuetSqlTenant::nhacBang("$dieuKien $sau", [...array_keys(TenantRepo::DANH_SACH_BANG_TENANT), ...TenantRepo::bangCam()]);
        if ($bang !== null) {
            throw new LoiCoLapTenant(static::class . "::doc(): không được chạm bảng `$bang`");
        }
        $st = db()->prepare('SELECT * FROM `' . static::BANG . "` WHERE ($dieuKien) $sau");
        $st->execute($thamSo);
        return $st->fetchAll();
    }

    final protected function them(array $cot): int
    {
        $ten = $this->cotGhi($cot);
        db()->prepare('INSERT INTO `' . static::BANG . '` (`' . implode('`, `', $ten) . '`) VALUES (' . rtrim(str_repeat('?, ', count($ten)), ', ') . ')')
            ->execute(array_values($cot));
        return (int) db()->lastInsertId();
    }

    final protected function sua(int $id, array $cot): bool
    {
        $gan = implode(', ', array_map(static fn (string $c): string => "`$c` = ?", $this->cotGhi($cot)));
        $st = db()->prepare('UPDATE `' . static::BANG . "` SET $gan WHERE id = ?");
        $st->execute([...array_values($cot), $id]);
        return $st->rowCount() > 0;
    }

    /** Kiểm quyền ghi (admin hoặc CLI) + tên cột; trả tên cột. */
    private function cotGhi(array $cot): array
    {
        if (!$this->ctx->laAdmin() && !$this->ctx->laNoiBo()) {
            throw new LoiCoLapTenant(static::class . ': chỉ admin hoặc CLI được ghi bảng dùng chung');
        }
        $ten = TenantRepo::tenCot($cot, static::class);
        if (in_array('id', $ten, true)) {
            throw new InvalidArgumentException(static::class . ': cột `id` do DB cấp');
        }
        return $ten;
    }
}
