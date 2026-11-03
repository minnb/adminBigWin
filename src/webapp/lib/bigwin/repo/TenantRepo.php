<?php
declare(strict_types=1);

// Lớp nền BẮT BUỘC cho repo bảng tenant (kế hoạch tổng §5.4, rule tenant-isolation.md). Lớp con chỉ khai báo BANG
// (+ COT_BAT_BIEN) và viết method nghiệp vụ trên tim/timMot/them/sua/xoaMem/truyVan — user_id và deleted_at IS NULL do
// lớp nền tự chèn từ TenantContext; lớp con không bao giờ thấy, nhận hay viết user_id. Không có xoá cứng (O1).

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../TenantContext.php';
require_once __DIR__ . '/BoQuetSqlTenant.php';
require_once __DIR__ . '/ThueBaoRepo.php';

abstract class TenantRepo
{
    /** Bảng có user_id nhưng chỉ truy cập qua repo riêng (Q-P02-1) — truyVan / SharedRepo / quét mã luôn từ chối. */
    public const BANG_REPO_RIENG = ThueBaoRepo::BANG_THUE_BAO;
    /** Bảng xác thực / phiên (lib/auth.php, lib/security/) — repo nghiệp vụ không bao giờ được đọc (hash mật khẩu, phiên). */
    public const BANG_XAC_THUC = ['users', 'admins', 'phien_dang_nhap', 'user_login_fails', 'admin_login_fails', 'gioi_han_tan_suat'];

    /**
     * Bảng tenant — nguồn DUY NHẤT (bảng => có xoá mềm). Bảng tenant mới: user_id NOT NULL, UNIQUE(id, user_id) ở bảng
     * cha + FK kép ở bảng con (§7.1), rồi khai báo ở đây (P02-T19 đối chiếu schema.sql).
     * demo_cha / demo_con: chỉ tồn tại trong DB test (tests/_tenant_demo.sql, Q-P02-5) — vô hại ở production.
     */
    public const DANH_SACH_BANG_TENANT = [
        'demo_cha' => true,
        'demo_con' => true,
    ];

    /** Bảng chính của repo — lớp con bắt buộc khai báo, phải nằm trong DANH_SACH_BANG_TENANT. */
    protected const BANG = '';
    /** Cột không được sửa sau khi ghi (vd tiền snapshot — O2). */
    protected const COT_BAT_BIEN = [];
    /** Cột do lớp nền quản lý — them()/sua() không nhận. */
    private const COT_NEN = ['id', 'deleted_at', 'deleted_by', 'ly_do_xoa'];

    public function __construct(private readonly TenantContext $ctx)
    {
        if (!array_key_exists(static::BANG, self::DANH_SACH_BANG_TENANT)) {
            throw new LoiCoLapTenant(static::class . ': BANG `' . static::BANG . '` chưa khai báo trong TenantRepo::DANH_SACH_BANG_TENANT');
        }
        if ($ctx->userId <= 0) {
            throw new LoiCoLapTenant(static::class . ': ngữ cảnh không thuộc đại lý nào');
        }
    }

    /** Các dòng của tenant (bỏ bản đã xoá mềm). $dieuKien/$sau viết theo alias `t`; đi qua bộ quét như truyVan(). */
    final protected function tim(string $dieuKien = '1=1', array $thamSo = [], string $sau = ''): array
    {
        return $this->truyVan('SELECT t.* FROM {tenant:' . static::BANG . " t} WHERE ($dieuKien) $sau", $thamSo)->fetchAll();
    }

    final protected function timMot(int $id): ?array
    {
        return $this->tim('t.id = ?', [$id], 'LIMIT 1')[0] ?? null;
    }

    /** Thêm dòng cho tenant hiện tại; trả id mới. */
    final protected function them(array $cot): int
    {
        $this->kiemCot($cot, 'them');
        $ten = array_keys($cot);
        $sql = 'INSERT INTO `' . static::BANG . '` (`' . implode('`, `', $ten) . '`, `user_id`) VALUES (' . str_repeat('?, ', count($ten)) . '?)';
        db()->prepare($sql)->execute([...array_values($cot), $this->ctx->userId]);
        return (int) db()->lastInsertId();
    }

    /** Sửa dòng của tenant hiện tại (chưa xoá mềm). false = không có dòng đó trong tenant này. */
    final protected function sua(int $id, array $cot): bool
    {
        $this->kiemCot($cot, 'sua');
        $batBien = array_values(array_intersect(array_keys($cot), static::COT_BAT_BIEN));
        if ($batBien !== []) {
            throw new LogicException(static::class . '::sua(): cột bất biến `' . implode('`, `', $batBien) . '` không được sửa');
        }
        $gan = implode(', ', array_map(static fn (string $c): string => "`$c` = ?", array_keys($cot)));
        $st = db()->prepare('UPDATE `' . static::BANG . "` SET $gan WHERE id = ? AND user_id = ?" . ($this->coXoaMem() ? ' AND deleted_at IS NULL' : ''));
        $st->execute([...array_values($cot), $id, $this->ctx->userId]);
        // rowCount() của MySQL chỉ đếm dòng THAY ĐỔI: 0 = không có dòng HOẶC ghi lại giá trị cũ → mới kiểm tồn tại
        return $st->rowCount() > 0 || $this->timMot($id) !== null;
    }

    /** Xoá mềm (O1): giữ dòng, ghi deleted_at / deleted_by / ly_do_xoa. false = không có hoặc đã xoá. */
    final protected function xoaMem(int $id, string $lyDo): bool
    {
        if (!$this->coXoaMem()) {
            throw new LogicException(static::class . ': bảng `' . static::BANG . '` không có xoá mềm');
        }
        $lyDo = trim($lyDo);
        if ($lyDo === '') {
            throw new InvalidArgumentException(static::class . '::xoaMem(): bắt buộc ghi lý do');
        }
        $st = db()->prepare('UPDATE `' . static::BANG . '` SET deleted_at = NOW(), deleted_by = ?, ly_do_xoa = ?
            WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
        $st->execute([$this->ctx->userId, mb_substr($lyDo, 0, 255, 'UTF-8'), $id, $this->ctx->userId]);
        return $st->rowCount() === 1;
    }

    /** SQL tự viết (JOIN, tổng hợp): mỗi bảng tenant viết {tenant:ten_bang alias} — xem BoQuetSqlTenant. */
    final protected function truyVan(string $sql, array $thamSo = []): PDOStatement
    {
        $st = db()->prepare(BoQuetSqlTenant::anToanHoa($sql, $this->ctx->userId, self::DANH_SACH_BANG_TENANT, self::bangCam()));
        $st->execute($thamSo);
        return $st;
    }

    /** Bảng không bao giờ được nhắc trong truyVan / SharedRepo::doc (bảng repo riêng + bảng xác thực). */
    public static function bangCam(): array
    {
        return [...self::BANG_REPO_RIENG, ...self::BANG_XAC_THUC];
    }

    private function coXoaMem(): bool
    {
        return self::DANH_SACH_BANG_TENANT[static::BANG];
    }

    /** Tên cột của mảng $cot (khác rỗng, đều là định danh an toàn để ghép vào SQL). Dùng chung cho TenantRepo / SharedRepo. */
    public static function tenCot(array $cot, string $noi): array
    {
        if ($cot === []) {
            throw new InvalidArgumentException("$noi: không có cột nào");
        }
        foreach (array_keys($cot) as $c) {
            if (!is_string($c) || !preg_match('/^[a-z_][a-z0-9_]*$/', $c)) {
                throw new InvalidArgumentException("$noi: tên cột không hợp lệ");
            }
        }
        return array_keys($cot);
    }

    private function kiemCot(array $cot, string $thaoTac): void
    {
        foreach (self::tenCot($cot, static::class . "::$thaoTac()") as $c) {
            if ($c === 'user_id') {
                throw new LoiCoLapTenant(static::class . "::$thaoTac(): CẤM truyền user_id — lớp nền tự gắn theo TenantContext");
            }
            if (in_array($c, self::COT_NEN, true)) {
                throw new LogicException(static::class . "::$thaoTac(): cột `$c` do lớp nền quản lý");
            }
        }
    }
}
