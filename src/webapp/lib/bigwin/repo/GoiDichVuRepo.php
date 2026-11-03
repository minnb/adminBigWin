<?php
declare(strict_types=1);

// Gói dịch vụ (bảng dùng chung goi_dich_vu — admin/goi_thue_bao.php, P03 S1.7). QĐ7: chỉ THÊM / SỬA / NGỪNG BÁN — không có
// đường xoá gói (thuê bao + lịch sử tham chiếu, FK RESTRICT). Ghi chỉ admin / CLI (SharedRepo); `ma` không sửa (mã cố định
// được dùng trong mã, vd `dung_thu`).

require_once __DIR__ . '/SharedRepo.php';
require_once __DIR__ . '/LoiQuanTri.php';

final class GoiDichVuRepo extends SharedRepo
{
    protected const BANG = 'goi_dich_vu';
    /** Cột hạn mức: '' / null = không giới hạn. */
    private const COT_HAN_MUC = ['so_khach_toi_da', 'so_tin_ngay_toi_da', 'so_ngay_tra_cuu_lich_su'];

    public function danhSach(): array
    {
        return $this->doc('1=1', [], 'ORDER BY dang_ban DESC, ma');
    }

    /** Gói đang bán (chọn khi tạo / cấp / đổi gói). */
    public function dangBan(): array
    {
        return $this->doc('dang_ban = 1', [], 'ORDER BY ma');
    }

    /** $goi: ma, ten, gia_thang, so_khach_toi_da, so_tin_ngay_toi_da, so_ngay_tra_cuu_lich_su, so_ngay_an_han, cho_phep_telegram. */
    public function themGoi(array $goi): int
    {
        $ma = is_string($goi['ma'] ?? null) ? trim($goi['ma']) : '';
        if (preg_match('/^[a-z0-9_]{2,30}$/', $ma) !== 1) {
            throw new InvalidArgumentException('Mã gói 2–30 ký tự: chữ thường không dấu, số, gạch dưới.');
        }
        if ($this->doc('ma = ?', [$ma]) !== []) {
            throw new LoiQuanTri("Mã gói '$ma' đã tồn tại.");
        }
        return $this->them(['ma' => $ma] + self::chuanHoa($goi));
    }

    public function suaGoi(int $id, array $goi): void
    {
        $this->sua($id, self::chuanHoa($goi));
    }

    /** Ngừng bán (false) / bán lại (true) — gói đang dùng vẫn giữ cho thuê bao hiện có. */
    public function datDangBan(int $id, bool $dangBan): void
    {
        $this->sua($id, ['dang_ban' => $dangBan ? 1 : 0]);
    }

    private static function chuanHoa(array $goi): array
    {
        $ten = is_string($goi['ten'] ?? null) ? trim($goi['ten']) : '';
        if ($ten === '' || mb_strlen($ten) > 100) {
            throw new InvalidArgumentException('Tên gói bắt buộc, tối đa 100 ký tự.');
        }
        $kq = [
            'ten' => $ten,
            'gia_thang' => self::soNguyen($goi['gia_thang'] ?? '0', 'Giá / tháng') ?? 0,
            'so_ngay_an_han' => self::soNguyen($goi['so_ngay_an_han'] ?? '3', 'Số ngày ân hạn') ?? 0,
            'cho_phep_telegram' => !empty($goi['cho_phep_telegram']) ? 1 : 0,
        ];
        foreach (self::COT_HAN_MUC as $cot) {
            $kq[$cot] = self::soNguyen($goi[$cot] ?? null, $cot);
        }
        return $kq;
    }

    /** Số nguyên ≥ 0 từ ô nhập; rỗng → null. */
    private static function soNguyen(mixed $v, string $nhan): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = is_int($v) ? (string) $v : (is_string($v) ? trim($v) : '');
        if (preg_match('/^\d{1,9}$/', $s) !== 1) {
            throw new InvalidArgumentException("$nhan phải là số nguyên không âm.");
        }
        return (int) $s;
    }
}
