<?php
declare(strict_types=1);

// Rate limit cửa sổ cố định (kế hoạch tổng §5.3c): APCu nếu có, không thì bảng gioi_han_tan_suat
// (giờ theo NOW() của DB, time_zone +07:00). Khoá dạng "<loại>:<định danh>", vd "dang_nhap:1.2.3.4".

require_once __DIR__ . '/../bootstrap.php';

final class GioiHanTanSuat
{
    /** Ghi nhận 1 lần; true = còn trong hạn mức $toiDa lần / $cuaSoGiay giây. */
    public static function cho(string $khoa, int $toiDa, int $cuaSoGiay): bool
    {
        $khoa = self::chuanKhoa($khoa);
        if (self::coApcu()) {
            apcu_add($khoa, 0, $cuaSoGiay);
            return apcu_inc($khoa) <= $toiDa;
        }
        $pdo = db();
        // Hết cửa sổ → đếm lại từ 1 (MySQL gán từ trái sang phải: điều kiện het_luc ở cả 2 vế đều đọc giá trị cũ)
        $pdo->prepare(
            'INSERT INTO gioi_han_tan_suat (khoa, so_lan, het_luc) VALUES (?, 1, NOW() + INTERVAL ? SECOND)
             ON DUPLICATE KEY UPDATE so_lan = IF(het_luc <= NOW(), 1, so_lan + 1),
                                     het_luc = IF(het_luc <= NOW(), VALUES(het_luc), het_luc)'
        )->execute([$khoa, $cuaSoGiay]);
        $st = $pdo->prepare('SELECT so_lan FROM gioi_han_tan_suat WHERE khoa = ?');
        $st->execute([$khoa]);
        if (random_int(1, 100) === 1) {
            $pdo->exec('DELETE FROM gioi_han_tan_suat WHERE het_luc < NOW() - INTERVAL 1 DAY');
        }
        return (int) $st->fetchColumn() <= $toiDa;
    }

    /** Xoá bộ đếm (vd đăng nhập đúng thì bỏ đếm sai). */
    public static function datLai(string $khoa): void
    {
        $khoa = self::chuanKhoa($khoa);
        if (self::coApcu()) {
            apcu_delete($khoa);
            return;
        }
        db()->prepare('DELETE FROM gioi_han_tan_suat WHERE khoa = ?')->execute([$khoa]);
    }

    /** Khoá vừa cột VARCHAR(120): khoá dài được thay bằng hash. */
    private static function chuanKhoa(string $khoa): string
    {
        return strlen($khoa) <= 120 ? $khoa : 'h:' . hash('sha256', $khoa);
    }

    private static function coApcu(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }
}
