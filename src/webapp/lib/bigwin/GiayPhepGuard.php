<?php
declare(strict_types=1);

// Bản quyền SaaS (kế hoạch tổng §1.4) — THUẦN: nhận vai trò + bản ghi thuê bao (thue_bao JOIN goi_dich_vu) + ngày, trả
// trạng thái và quyền theo chế độ endpoint. Trạng thái TÍNH RA từ ngày (không phụ thuộc cron). Gọi từ ajax_khoi_dong()
// (lib/security/AjaxGateway.php); dữ liệu đọc qua lib/bigwin/repo/ThueBaoRepo.php.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/NgayLamViec.php';

final class GiayPhepGuard
{
    public const CHE_DO = ['doc', 'ghi', 'phan_tich'];

    /** Admin dùng app như một tenant nhưng không cần thuê bao: gói nội bộ, không hạn mức, không bao giờ bị chặn (P02-T24). */
    public const GOI_NOI_BO_ADMIN = ['so_khach_toi_da' => null, 'so_tin_ngay_toi_da' => null, 'cho_phep_telegram' => 1, 'so_ngay_tra_cuu_lich_su' => null];

    /** Ma trận §1.4: doc = xem lịch sử / chốt sổ / in · ghi = sửa khách, giá, dàn, tự học · phan_tich = phân tích / đối soát / đồng bộ. */
    private const MA_TRAN = [
        'dung_thu' => ['doc' => true, 'ghi' => true, 'phan_tich' => true],
        'hieu_luc' => ['doc' => true, 'ghi' => true, 'phan_tich' => true],
        'an_han' => ['doc' => true, 'ghi' => true, 'phan_tich' => true],
        'het_han' => ['doc' => true, 'ghi' => false, 'phan_tich' => false],
        'tam_khoa' => ['doc' => false, 'ghi' => false, 'phan_tich' => false],
        'chua_co_thue_bao' => ['doc' => false, 'ghi' => false, 'phan_tich' => false], // Q-P02-2: fail-closed
    ];

    private const THONG_DIEP = [
        'an_han' => 'Thuê bao đã hết hạn, đang trong thời gian ân hạn — gia hạn sớm để không bị gián đoạn.',
        'het_han' => 'Thuê bao đã hết hạn — chỉ còn xem được dữ liệu. Liên hệ quản trị để gia hạn.',
        'tam_khoa' => 'Dịch vụ của tài khoản đang bị tạm khoá — liên hệ quản trị.',
        'chua_co_thue_bao' => 'Tài khoản chưa có thuê bao — liên hệ quản trị.',
    ];

    /** dung_thu | hieu_luc | an_han | het_han | tam_khoa | chua_co_thue_bao */
    public static function trangThai(string $vaiTro, ?array $thueBao, DateTimeImmutable $homNay): string
    {
        if ($vaiTro === 'admin') {
            return 'hieu_luc';
        }
        if ($vaiTro !== 'user') {
            throw new LogicException("GiayPhepGuard: vai trò '$vaiTro' không hợp lệ");
        }
        if ($thueBao === null) {
            return 'chua_co_thue_bao';
        }
        if ((int) $thueBao['tam_khoa'] === 1) {
            return 'tam_khoa';
        }
        $hom = $homNay->format('Y-m-d');
        $hetHan = (string) $thueBao['het_han'];
        if ($hom <= $hetHan) {
            return ($thueBao['goi_ma'] ?? '') === 'dung_thu' ? 'dung_thu' : 'hieu_luc';
        }
        $cuoiAnHan = (new DateTimeImmutable($hetHan, new DateTimeZone(BW_TZ)))->modify('+' . (int) $thueBao['so_ngay_an_han'] . ' day');
        return $hom <= $cuoiAnHan->format('Y-m-d') ? 'an_han' : 'het_han';
    }

    /** Trạng thái / chế độ lạ → lỗi (không mặc định cho qua). */
    public static function choPhep(string $trangThai, string $cheDo): bool
    {
        return self::MA_TRAN[$trangThai][$cheDo] ?? throw new LogicException("GiayPhepGuard: trạng thái '$trangThai' / chế độ '$cheDo' không hợp lệ");
    }

    /** ['trang_thai', 'cho_phep', 'canh_bao' (ân hạn — băng đỏ topbar), 'thong_diep'] */
    /**
     * ['trang_thai', 'cho_phep', 'canh_bao' (ân hạn — băng đỏ topbar), 'thong_diep', 'het_han' ('Y-m-d' | null),
     *  'so_ngay_con' (số ngày tới hạn, âm = đã quá; null khi không có thuê bao / admin)]
     */
    public static function kiemTra(string $vaiTro, ?array $thueBao, string $cheDo, DateTimeImmutable $homNay): array
    {
        $trangThai = self::trangThai($vaiTro, $thueBao, $homNay);
        $hetHan = $vaiTro === 'admin' || $thueBao === null ? null : (string) $thueBao['het_han'];
        return [
            'trang_thai' => $trangThai,
            'cho_phep' => self::choPhep($trangThai, $cheDo),
            'canh_bao' => $trangThai === 'an_han',
            'thong_diep' => self::THONG_DIEP[$trangThai] ?? '',
            'het_han' => $hetHan,
            'so_ngay_con' => $hetHan === null ? null
                : (int) $homNay->setTime(0, 0)->diff(new DateTimeImmutable($hetHan, new DateTimeZone(BW_TZ)))->format('%r%a'),
        ];
    }

    /** Hạn mức đang áp dụng (NULL = không giới hạn). Chưa có thuê bao → mọi hạn mức = 0. */
    public static function hanMuc(string $vaiTro, ?array $thueBao): array
    {
        if ($vaiTro === 'admin') {
            return self::GOI_NOI_BO_ADMIN;
        }
        if ($thueBao === null) {
            return array_fill_keys(array_keys(self::GOI_NOI_BO_ADMIN), 0);
        }
        $kq = [];
        foreach (array_keys(self::GOI_NOI_BO_ADMIN) as $k) {
            $kq[$k] = isset($thueBao[$k]) ? (int) $thueBao[$k] : null;
        }
        return $kq;
    }

    /** Giấy phép nền tảng: còn thêm / mở khoá được 1 đại lý không. $toiDa NULL = không giới hạn. */
    public static function coTheThemDaiLy(?int $toiDa, int $dangHoatDong): bool
    {
        return $toiDa === null || $dangHoatDong < $toiDa;
    }

    /** Ngày làm căn cứ: cfg('ngay_gia_lap') CHỈ khi cfg('moi_truong') === 'test' (P02-M02), ngược lại today_vn(). */
    public static function homNay(): DateTimeImmutable
    {
        return NgayLamViec::homNayLich();
    }
}
