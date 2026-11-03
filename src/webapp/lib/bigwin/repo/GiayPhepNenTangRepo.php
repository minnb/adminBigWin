<?php
declare(strict_types=1);

// Giấy phép nền tảng (bảng dùng chung giay_phep_he_thong, 1 dòng) — số đại lý hoạt động tối đa của bản cài đặt.
// QĐ7: CHỈ sửa qua CLI tools/giay_phep_nen_tang.php (chủ nền tảng); UI admin chỉ hiển thị "Đã dùng X/Y".

require_once __DIR__ . '/SharedRepo.php';
require_once __DIR__ . '/LoiQuanTri.php';

final class GiayPhepNenTangRepo extends SharedRepo
{
    protected const BANG = 'giay_phep_he_thong';

    /** ['so_dai_ly_toi_da' => ?int, 'ghi_chu', 'cap_nhat_luc']. Thiếu dòng → tối đa 0 (fail-closed). $khoa: FOR UPDATE. */
    public function hienTai(bool $khoa = false): array
    {
        $d = $this->doc('id = 1', [], $khoa ? 'FOR UPDATE' : '')[0] ?? ['so_dai_ly_toi_da' => 0, 'ghi_chu' => 'Thiếu giấy phép nền tảng', 'cap_nhat_luc' => null];
        $d['so_dai_ly_toi_da'] = $d['so_dai_ly_toi_da'] === null ? null : (int) $d['so_dai_ly_toi_da'];
        return $d;
    }

    /**
     * Khoá dòng giấy phép rồi kiểm còn chỗ cho thêm 1 đại lý hoạt động (gọi TRONG transaction — 2 admin cùng lúc không
     * vượt). $dem() đếm đại lý đang hoạt động SAU khi đã khoá. Hết chỗ → LoiQuanTri "X/Y".
     */
    public function batBuocConCho(callable $dem): void
    {
        $toiDa = $this->hienTai(true)['so_dai_ly_toi_da'];
        $dung = $dem();
        if (!GiayPhepGuard::coTheThemDaiLy($toiDa, $dung)) {
            throw new LoiQuanTri("Đã đạt giới hạn giấy phép nền tảng ($dung/$toiDa đại lý đang hoạt động) — liên hệ nhà cung cấp để nâng giấy phép.");
        }
    }

    /** $toiDa null = không giới hạn. */
    public function datToiDa(?int $toiDa, string $ghiChu): void
    {
        if ($toiDa !== null && $toiDa < 0) {
            throw new InvalidArgumentException('Số đại lý tối đa phải ≥ 0.');
        }
        $this->sua(1, ['so_dai_ly_toi_da' => $toiDa, 'ghi_chu' => mb_substr($ghiChu, 0, 255, 'UTF-8'), 'cap_nhat_luc' => now_vn_sql()]);
    }
}
