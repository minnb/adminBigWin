<?php
declare(strict_types=1);

// Ngày làm việc (quyết định N8, `ui/app.py:649-653` của Desktop): trước 18:30 → hôm qua (KQXS chưa quay xong),
// từ 18:30 → hôm nay. THUẦN — thời điểm nhận qua tham số; hienTai() / homNayLich() là chỗ DUY NHẤT đọc đồng hồ
// (now_vn / today_vn) và ngày giả lập cfg('ngay_gia_lap') (chỉ khi cfg('moi_truong') === 'test').

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/helpers.php';

final class NgayLamViec
{
    public const GIO_CHOT = '18:30:00';

    /** Ngày làm việc mặc định tại thời điểm $luc (00:00, múi giờ VN). */
    public static function macDinh(DateTimeImmutable $luc): DateTimeImmutable
    {
        $luc = $luc->setTimezone(new DateTimeZone(BW_TZ));
        $ngay = $luc->setTime(0, 0);
        return $luc->format('H:i:s') < self::GIO_CHOT ? $ngay->modify('-1 day') : $ngay;
    }

    public static function laMacDinh(string $ngay, DateTimeImmutable $luc): bool
    {
        return $ngay === self::macDinh($luc)->format('Y-m-d');
    }

    /** 'Y-m-d' hợp lệ thì trả lại chính nó, ngược lại null (dùng cho ?ngay= do người dùng chọn). */
    public static function chuanHoa(mixed $ngay): ?string
    {
        if (!is_string($ngay)) {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ngay, new DateTimeZone(BW_TZ));
        return $d !== false && $d->format('Y-m-d') === $ngay ? $ngay : null;
    }

    /** Ngày lịch hôm nay — hoặc cfg('ngay_gia_lap') khi moi_truong = test (P02-M02). */
    public static function homNayLich(): DateTimeImmutable
    {
        $giaLap = cfg('ngay_gia_lap');
        if (cfg('moi_truong') !== 'test' || $giaLap === null || $giaLap === '') {
            return today_vn();
        }
        $ngay = self::chuanHoa($giaLap);
        if ($ngay === null) {
            throw new LoiCauHinh('ngay_gia_lap', 'Cấu hình ngay_gia_lap phải là ngày hợp lệ dạng YYYY-MM-DD.');
        }
        return new DateTimeImmutable($ngay, new DateTimeZone(BW_TZ));
    }

    /** Ngày làm việc mặc định lúc này (giờ thật, ngày lịch có thể là ngày giả lập). */
    public static function hienTai(): DateTimeImmutable
    {
        return self::macDinh(self::homNayLich()->modify(now_vn()->format('H:i:s')));
    }
}
