<?php
declare(strict_types=1);

// Phiên đăng nhập web BigWIN (kế hoạch tổng §5.3a). Cookie riêng `bigwin_web` (admin cũ dùng `bigwin_admin`).
// Sổ phiên `phien_dang_nhap` là nguồn DUY NHẤT về thời điểm tạo / hoạt động cuối / User-Agent / thu hồi:
// kiểm hết hạn bằng kiemTra() (hàm thuần), thu hồi có hiệu lực ngay ở request kế.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/Csrf.php';

final class PhienBaoMat
{
    public const TEN = 'bigwin_web';
    public const NHAN_ROI_GIAY = 30 * 60;
    public const TUYET_DOI_GIAY = 12 * 3600;

    /** ini phiên bắt buộc: chỉ nhận ID do server cấp, chỉ qua cookie, ID dài, cookie hết khi đóng trình duyệt. */
    public static function iniBatBuoc(): array
    {
        return [
            'session.use_strict_mode' => '1',
            'session.use_only_cookies' => '1',
            'session.use_trans_sid' => '0',
            'session.cookie_lifetime' => '0',
            'session.sid_length' => '48',
            'session.sid_bits_per_character' => '6',
        ];
    }

    public static function tuyChonCookie(bool $secure): array
    {
        return ['lifetime' => 0, 'path' => '/', 'domain' => '', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'];
    }

    /** Mở phiên với cấu hình an toàn (idempotent). Secure khi HTTPS hoặc cfg('cookie_secure') = true. */
    public static function batDau(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        foreach (self::iniBatBuoc() as $k => $v) {
            ini_set($k, $v);
        }
        session_name(self::TEN);
        session_set_cookie_params(self::tuyChonCookie(la_https() || cfg('cookie_secure') === true));
        session_start();
    }

    public static function bamId(string $sid): string
    {
        return hash('sha256', $sid);
    }

    public static function bamUa(string $ua): string
    {
        return hash('sha256', $ua);
    }

    /** Sau khi xác thực mật khẩu: đổi session ID (chống fixation), xoay CSRF, ghi sổ phiên. Phiên phải đang mở. */
    public static function dangNhap(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION = ['uid' => $userId];
        Csrf::xoay();
        db()->prepare('INSERT INTO phien_dang_nhap (id_hash, user_id, ip, ua_hash, tao_luc, hoat_dong_luc) VALUES (?, ?, ?, ?, NOW(), NOW())')
            ->execute([self::bamId(session_id()), $userId, client_ip(), self::bamUa((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))]);
    }

    /** user_id của phiên đang mở nếu còn hợp lệ trong sổ phiên; không hợp lệ → huỷ phiên, trả null. */
    public static function hienTai(): ?int
    {
        self::batDau();
        $uid = $_SESSION['uid'] ?? null;
        if (!is_int($uid)) {
            return null;
        }
        $st = db()->prepare('SELECT tao_luc, hoat_dong_luc, ua_hash, thu_hoi_luc FROM phien_dang_nhap WHERE id_hash = ? AND user_id = ?');
        $st->execute([self::bamId(session_id()), $uid]);
        $dong = $st->fetch() ?: null;
        if (self::kiemTra($dong, time(), self::bamUa((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) !== 'hop_le') {
            self::huy();
            return null;
        }
        if (time() - strtotime($dong['hoat_dong_luc']) >= 60) { // ghi mốc hoạt động tối đa 1 lần/phút
            db()->prepare('UPDATE phien_dang_nhap SET hoat_dong_luc = NOW() WHERE id_hash = ?')->execute([self::bamId(session_id())]);
        }
        return $uid;
    }

    /** Kết thúc phiên đang mở: thu hồi trong sổ phiên, xoá dữ liệu phiên và cookie. */
    public static function huy(): void
    {
        self::batDau();
        db()->prepare('UPDATE phien_dang_nhap SET thu_hoi_luc = NOW() WHERE id_hash = ? AND thu_hoi_luc IS NULL')
            ->execute([self::bamId(session_id())]);
        $_SESSION = [];
        session_destroy();
        $tuyChon = self::tuyChonCookie(la_https() || cfg('cookie_secure') === true);
        unset($tuyChon['lifetime']); // khoá của session_set_cookie_params, setcookie() không nhận
        setcookie(self::TEN, '', ['expires' => 1] + $tuyChon);
    }

    /** Thu hồi mọi phiên của user (khoá tài khoản, đổi mật khẩu, "đăng xuất mọi nơi"); $giuPhienNay = trừ phiên đang mở. */
    public static function thuHoiTatCa(int $userId, bool $giuPhienNay = false): void
    {
        $sql = 'UPDATE phien_dang_nhap SET thu_hoi_luc = NOW() WHERE user_id = ? AND thu_hoi_luc IS NULL';
        $thamSo = [$userId];
        if ($giuPhienNay) {
            $sql .= ' AND id_hash <> ?';
            $thamSo[] = self::bamId(session_id());
        }
        db()->prepare($sql)->execute($thamSo);
    }

    /**
     * Trạng thái một dòng sổ phiên tại thời điểm $bayGio: 'hop_le' | 'khong_co' | 'thu_hoi' | 'het_tuyet_doi'
     * | 'het_nhan_roi' | 'doi_ua'. Mọi trạng thái khác 'hop_le' = phải đăng nhập lại.
     */
    public static function kiemTra(?array $dong, int $bayGio, string $uaHash): string
    {
        if ($dong === null) {
            return 'khong_co';
        }
        if ($dong['thu_hoi_luc'] !== null) {
            return 'thu_hoi';
        }
        if ($bayGio - strtotime($dong['tao_luc']) > self::TUYET_DOI_GIAY) {
            return 'het_tuyet_doi';
        }
        if ($bayGio - strtotime($dong['hoat_dong_luc']) > self::NHAN_ROI_GIAY) {
            return 'het_nhan_roi';
        }
        return hash_equals($dong['ua_hash'], $uaHash) ? 'hop_le' : 'doi_ua';
    }
}
