<?php
declare(strict_types=1);

// Ngữ cảnh tenant của request (kế hoạch tổng §5.4, rule tenant-isolation.md). 1 User = 1 Đại lý (O8): khoá cô lập là
// users.id, CHỈ đến từ phiên đã xác thực (tuPhien — AjaxGateway / app/_layout.php) hoặc tiến trình CLI có ghi lý do.
// Bất biến; constructor private → không nơi nào dựng được ngữ cảnh với id tuỳ ý (P02-T01; tests/tenant_scan_test.php).

require_once __DIR__ . '/repo/LoiCoLapTenant.php';

final class TenantContext
{
    /** @param string $nguon 'phien' (web) | 'cli' (worker cho một đại lý) | 'he_thong' (việc nền tảng, không thuộc đại lý nào) */
    private function __construct(
        public readonly int $userId,
        public readonly string $vaiTro,
        public readonly string $nguon,
    ) {
    }

    /** Từ người dùng đã xác thực (nguoi_dung_hien_tai()). Dữ liệu sai kiểu → lỗi, không đoán. */
    public static function tuPhien(array $user): self
    {
        $id = $user['id'] ?? null;
        $vaiTro = $user['role'] ?? null;
        if (!is_int($id) || $id <= 0 || !in_array($vaiTro, ['user', 'admin'], true)) {
            throw new LoiCoLapTenant('TenantContext::tuPhien: người dùng không hợp lệ');
        }
        return new self($id, $vaiTro, 'phien');
    }

    /** Tiến trình CLI làm việc trên dữ liệu của MỘT đại lý (worker, công cụ). Bắt buộc lý do — ghi log. */
    public static function choCli(int $id, string $lyDo): self
    {
        if ($id <= 0) {
            throw new LoiCoLapTenant('TenantContext::choCli: id đại lý không hợp lệ');
        }
        self::ghiNhatKyCli("choCli id=$id", $lyDo);
        return new self($id, 'user', 'cli');
    }

    /** Việc nền tảng chạy CLI (cron thuê bao, lấy KQXS): không thuộc đại lý nào → TenantRepo từ chối. */
    public static function heThong(string $lyDo): self
    {
        self::ghiNhatKyCli('heThong', $lyDo);
        return new self(0, 'he_thong', 'he_thong');
    }

    public function laAdmin(): bool
    {
        return $this->vaiTro === 'admin' && $this->nguon === 'phien';
    }

    public function laHeThong(): bool
    {
        return $this->nguon === 'he_thong';
    }

    /** Ngữ cảnh tiến trình CLI (worker của một đại lý hoặc việc hệ thống). */
    public function laNoiBo(): bool
    {
        return $this->nguon !== 'phien';
    }

    private static function ghiNhatKyCli(string $loai, string $lyDo): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new LoiCoLapTenant("TenantContext::$loai chỉ dùng trong CLI"); // web không bao giờ tự cấp ngữ cảnh
        }
        $lyDo = trim($lyDo);
        if ($lyDo === '') {
            throw new LoiCoLapTenant("TenantContext::$loai: bắt buộc ghi lý do");
        }
        error_log("BigWIN ngữ cảnh CLI $loai: $lyDo");
    }
}
