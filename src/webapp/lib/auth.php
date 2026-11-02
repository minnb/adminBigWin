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

function bam_mat_khau(string $matKhau): string
{
    return password_hash($matKhau, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
}

/**
 * Đường dẫn tới tệp trong src/webapp, TƯƠNG ĐỐI với script đang chạy (site có thể nằm dưới thư mục con nhờ
 * rewrite của .htaccess gốc — như /admin dùng 'index.php'). Vd từ app/ajax/x.php: url_web('login.php') = '../../login.php'.
 */
function url_web(string $tep): string
{
    $goc = realpath(__DIR__ . '/..');
    $script = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: $goc . '/index.php';
    $doSau = substr_count(str_replace('\\', '/', substr(dirname($script), strlen($goc))), '/');
    return str_repeat('../', $doSau) . ltrim($tep, '/');
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
    if (password_needs_rehash($u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([bam_mat_khau($matKhau), $uid]);
    }
    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$uid]);
    PhienBaoMat::batDau();
    PhienBaoMat::dangNhap($uid);
    return ['ok' => true, 'thong_diep' => '', 'phai_doi_mat_khau' => (int) $u['phai_doi_mat_khau'] === 1];
}

/** Đổi mật khẩu của người đang đăng nhập; null = thành công. Thành công → bỏ cờ buộc đổi + thu hồi mọi phiên khác. */
function doi_mat_khau(array $user, string $cu, string $moi, string $xacNhan): ?string
{
    $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $st->execute([$user['id']]);
    if (!password_verify($cu, (string) $st->fetchColumn())) {
        return 'Mật khẩu hiện tại không đúng.';
    }
    if (mb_strlen($moi) < MAT_KHAU_TOI_THIEU) {
        return 'Mật khẩu mới phải có ít nhất ' . MAT_KHAU_TOI_THIEU . ' ký tự.';
    }
    if ($moi !== $xacNhan) {
        return 'Xác nhận mật khẩu không khớp.';
    }
    if ($moi === $cu) {
        return 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    }
    db()->prepare('UPDATE users SET password_hash = ?, phai_doi_mat_khau = 0 WHERE id = ?')->execute([bam_mat_khau($moi), $user['id']]);
    PhienBaoMat::thuHoiTatCa($user['id'], true);
    return null;
}

function dang_xuat(): void
{
    PhienBaoMat::huy();
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
