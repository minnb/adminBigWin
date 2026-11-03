<?php
declare(strict_types=1);

// Danh tính web BigWIN (P01 S1.3, kế hoạch tổng §5.3e): đăng nhập, người dùng hiện tại, đổi mật khẩu, đăng xuất.
// Phiên/CSRF qua lib/security/; người dùng được nạp lại từ DB MỖI request → khoá tài khoản có hiệu lực ngay.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/security/PhienBaoMat.php';
require_once __DIR__ . '/security/Csrf.php';
require_once __DIR__ . '/security/TieuDeBaoMat.php';

const MAT_KHAU_TOI_THIEU = 10;
const DANG_NHAP_SAI_TOI_DA = 5;
const DANG_NHAP_KHOA_PHUT = 15;
const THONG_BAO_SAI_DANG_NHAP = 'Sai tên đăng nhập hoặc mật khẩu.';
/** Hash argon2id của một chuỗi ngẫu nhiên đã bỏ — verify với nó khi username không tồn tại (cùng chi phí với user thật). */
const HASH_GIA = '$argon2id$v=19$m=65536,t=4,p=1$ZE5ZZnoxOGlzaUp1Qm44Mw$hAZmyQARGRJ7FWgQLYAbkH2fv4cNHvqJaOEyD1qD/+0';

/**
 * [thuật toán, tuỳ chọn] băm mật khẩu — nguồn DUY NHẤT cho băm + kiểm rehash. moi_truong = 'test': argon2id tham số tối
 * thiểu để bộ test không tốn ~0,8 s / lần băm (production KHÔNG BAO GIỜ đặt 'test' — P02 rủi ro ngay_gia_lap, P13 kiểm).
 */
function thuat_toan_bam(): array
{
    $thuatToan = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    $nhe = cfg('moi_truong') === 'test' && $thuatToan === PASSWORD_ARGON2ID;
    return [$thuatToan, $nhe ? ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1] : []];
}

function bam_mat_khau(string $matKhau): string
{
    return password_hash($matKhau, ...thuat_toan_bam());
}

/**
 * Đường dẫn tới tệp trong src/webapp, TƯƠNG ĐỐI với script đang chạy (site có thể nằm dưới thư mục con nhờ
 * rewrite của .htaccess gốc — như /admin dùng 'index.php'). Vd từ app/ajax/x.php: url_web('login.php') = '../../login.php'.
 */
function url_web(string $tep): string
{
    static $tienTo = null; // script cố định trong một request → tính 1 lần (khung app gọi hàng chục lần / trang)
    if ($tienTo === null) {
        $goc = realpath(__DIR__ . '/..');
        $script = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: $goc . '/index.php';
        $tienTo = str_repeat('../', substr_count(str_replace('\\', '/', substr(dirname($script), strlen($goc))), '/'));
    }
    return $tienTo . ltrim($tep, '/');
}

function chuyen_huong(string $tep): never
{
    header('Location: ' . url_web($tep));
    exit;
}

/** Người dùng của phiên đang mở (đã kiểm sổ phiên + trạng thái tài khoản), hoặc null. */
function nguoi_dung_hien_tai(): ?array
{
    $uid = PhienBaoMat::hienTai();
    if ($uid === null) {
        return null;
    }
    $st = db()->prepare('SELECT id, username, role, trang_thai, ten_hien_thi, phai_doi_mat_khau FROM users WHERE id = ?');
    $st->execute([$uid]);
    $u = $st->fetch();
    if (!$u || $u['trang_thai'] !== 'active') {
        PhienBaoMat::thuHoiTatCa($uid);
        PhienBaoMat::huy();
        return null;
    }
    $u['id'] = (int) $u['id'];
    $u['phai_doi_mat_khau'] = (int) $u['phai_doi_mat_khau'];
    return $u;
}

/** Trang HTML cần đăng nhập. Chưa đăng nhập → login.php; còn cờ buộc đổi mật khẩu → doi_mat_khau.php. */
function require_user(bool $choPhepPhaiDoiMatKhau = false): array
{
    $u = nguoi_dung_hien_tai();
    if ($u === null) {
        chuyen_huong('login.php');
    }
    if ($u['phai_doi_mat_khau'] === 1 && !$choPhepPhaiDoiMatKhau) {
        chuyen_huong('doi_mat_khau.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_user();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('Không có quyền.');
    }
    return $u;
}

/**
 * Xác thực username + mật khẩu; đúng thì mở phiên đăng nhập.
 * Trả ['ok' => bool, 'thong_diep' => lỗi cho người dùng, 'phai_doi_mat_khau' => bool].
 * Sai username và sai mật khẩu trả CÙNG một câu (THONG_BAO_SAI_DANG_NHAP), cùng chi phí băm.
 */
function dang_nhap(string $username, string $matKhau): array
{
    $pdo = db();
    $ip = client_ip();
    $username = mb_substr(trim($username), 0, 50);
    $pdo->exec('DELETE FROM user_login_fails WHERE created_at < NOW() - INTERVAL 1 DAY');
    $st = $pdo->prepare('SELECT COUNT(*) FROM user_login_fails WHERE (username = ? OR ip = ?) AND created_at > NOW() - INTERVAL ? MINUTE');
    $st->execute([$username, $ip, DANG_NHAP_KHOA_PHUT]);
    if ((int) $st->fetchColumn() >= DANG_NHAP_SAI_TOI_DA) {
        return ['ok' => false, 'thong_diep' => 'Bạn đã nhập sai quá ' . DANG_NHAP_SAI_TOI_DA . ' lần. Vui lòng thử lại sau ' . DANG_NHAP_KHOA_PHUT . ' phút.', 'phai_doi_mat_khau' => false];
    }

    $st = $pdo->prepare('SELECT id, password_hash, trang_thai, phai_doi_mat_khau FROM users WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();
    // Không có user vẫn verify một lần để thời gian phản hồi không lộ username có tồn tại hay không
    $dung = password_verify($matKhau, $u['password_hash'] ?? HASH_GIA);
    if (!$u || !$dung) {
        $pdo->prepare('INSERT INTO user_login_fails (ip, username, created_at) VALUES (?, ?, NOW())')->execute([$ip, $username]);
        return ['ok' => false, 'thong_diep' => THONG_BAO_SAI_DANG_NHAP, 'phai_doi_mat_khau' => false];
    }
    if ($u['trang_thai'] !== 'active') {
        return ['ok' => false, 'thong_diep' => 'Tài khoản đã bị khoá — liên hệ quản trị.', 'phai_doi_mat_khau' => false];
    }

    $uid = (int) $u['id'];
    $pdo->prepare('DELETE FROM user_login_fails WHERE username = ? OR ip = ?')->execute([$username, $ip]);
    if (password_needs_rehash($u['password_hash'], ...thuat_toan_bam())) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([bam_mat_khau($matKhau), $uid]);
    }
    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$uid]);
    PhienBaoMat::batDau();
    PhienBaoMat::dangNhap($uid);
    return ['ok' => true, 'thong_diep' => '', 'phai_doi_mat_khau' => (int) $u['phai_doi_mat_khau'] === 1];
}

/**
 * Đổi mật khẩu của người đang đăng nhập. Trả lỗi THEO TRƯỜNG ['mat_khau_cu' | 'mat_khau_moi' | 'xac_nhan' => thông điệp];
 * rỗng = thành công → bỏ cờ buộc đổi + thu hồi mọi phiên khác. Tài khoản admin: ghi kèm bảng `admins` (P03 QĐ3 — cửa sổ
 * rollback của admin/_auth.php cũ, bỏ sau P03).
 */
function doi_mat_khau(array $user, string $cu, string $moi, string $xacNhan): array
{
    $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$user['id']]);
    $loi = [];
    if (!password_verify($cu, (string) $st->fetchColumn())) {
        $loi['mat_khau_cu'] = 'Mật khẩu hiện tại không đúng.';
    }
    if (mb_strlen($moi) < MAT_KHAU_TOI_THIEU) {
        $loi['mat_khau_moi'] = 'Mật khẩu mới phải có ít nhất ' . MAT_KHAU_TOI_THIEU . ' ký tự.';
    } elseif ($moi === $cu) {
        $loi['mat_khau_moi'] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    }
    if ($moi !== $xacNhan) {
        $loi['xac_nhan'] = 'Xác nhận mật khẩu không khớp.';
    }
    if ($loi !== []) {
        return $loi;
    }
    $hash = bam_mat_khau($moi);
    trong_giao_dich(static function (PDO $pdo) use ($user, $hash): void {
        $pdo->prepare('UPDATE users SET password_hash = ?, phai_doi_mat_khau = 0 WHERE id = ?')->execute([$hash, $user['id']]);
        if ($user['role'] === 'admin') {
            dong_bo_admins($user['username'], $hash);
        }
    });
    PhienBaoMat::thuHoiTatCa($user['id'], true);
    return [];
}

function dang_xuat(): void
{
    PhienBaoMat::huy();
}

/** Form POST của request hiện tại hợp lệ (nguồn + token `csrf`) — điểm kiểm DUY NHẤT cho form HTML; tự mở phiên. */
function form_hop_le(): bool
{
    PhienBaoMat::batDau(); // token nằm trong phiên (P03-B06)
    return Csrf::formHopLe($_SERVER, $_POST['csrf'] ?? null);
}

/** Ô ẩn CSRF cho form POST. */
function truong_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . h(Csrf::token()) . '">';
}

/** Xử lý form đăng xuất (POST + CSRF + nguồn → thu hồi phiên → trang đăng nhập); không hợp lệ → $tepKhac. */
function dang_xuat_tu_form(string $tepKhac): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && form_hop_le()) {
        dang_xuat();
        chuyen_huong('login.php');
    }
    chuyen_huong($tepKhac);
}

/** Tên hiển thị của người dùng (trống → username). */
function ten_hien(array $u): string
{
    return $u['ten_hien_thi'] !== '' ? $u['ten_hien_thi'] : $u['username'];
}

/**
 * Ghi kép bảng `admins` cho cửa sổ rollback admin/_auth.php cũ (P03 QĐ3 — bỏ sau P03): admin còn hiệu lực ($hash khác
 * null) → upsert mật khẩu mới nhất; bị khoá / hạ quyền ($hash null) → gỡ khỏi `admins` để đăng nhập cũ cũng mất quyền.
 */
function dong_bo_admins(string $username, ?string $hash): void
{
    if ($hash === null) {
        db()->prepare('DELETE FROM admins WHERE username = ?')->execute([$username]);
        return;
    }
    db()->prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)')->execute([$username, $hash]);
}

/** Trang HTML tối giản dùng chung cho login/đổi mật khẩu (P03 thay bằng layout app). */
function trang_don_gian(string $tieuDe, string $noiDung): void
{
    TieuDeBaoMat::gui();
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">',
        '<meta name="csrf-token" content="', h(Csrf::token()), '">',
        '<title>', h($tieuDe), ' — BigWIN</title><script src="https://cdn.tailwindcss.com/3.4.16"></script></head>',
        '<body class="min-h-screen bg-slate-100 text-slate-900 flex items-center justify-center p-4">',
        '<main class="w-full max-w-sm bg-white rounded-2xl shadow p-6"><h1 class="text-xl font-bold mb-4">', h($tieuDe), '</h1>',
        $noiDung, '</main></body></html>';
}
