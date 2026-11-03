<?php
declare(strict_types=1);

// Quản trị tài khoản đăng nhập (bảng users) cho admin/users.php (P03 S1.7). QĐ4: lớp quản trị ĐỘC LẬP gọi DB trực tiếp
// như mặt quản trị của ThueBaoRepo — chỉ ngữ cảnh admin (web) hoặc hệ thống (CLI). Luật:
//   • Giấy phép nền tảng khi tạo / mở khoá đại lý / hạ admin thành đại lý — khoá dòng giay_phep_he_thong trong cùng
//     transaction → 2 admin thao tác cùng lúc không vượt giới hạn.
//   • Luôn còn ≥ 1 admin hoạt động; không tự khoá / tự hạ quyền / tự đặt lại mật khẩu (dùng Đổi mật khẩu).
//   • Mật khẩu tạm 12 ký tự ngẫu nhiên, trả về MỘT lần, không lưu / ghi log bản rõ; luôn buộc đổi lần đầu (QĐ6).
//   • Tài khoản admin đồng bộ bảng `admins` qua dong_bo_admins() (QĐ3 — cửa sổ rollback admin/_auth.php cũ, bỏ sau P03):
//     admin hoạt động có mặt với mật khẩu mới nhất; bị khoá / hạ quyền thì gỡ.

require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/ThueBaoRepo.php';
require_once __DIR__ . '/GiayPhepNenTangRepo.php';
require_once __DIR__ . '/LoiQuanTri.php';

final class UserRepo
{
    public const DO_DAI_MAT_KHAU_TAM = 12;
    private const VAI_TRO = ['user', 'admin'];
    /** Chữ + số, bỏ 0 O 1 l I (đọc / gõ lại không nhầm khi admin chuyển cho đại lý). */
    private const KY_TU_MAT_KHAU = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    public function __construct(private readonly TenantContext $ctx)
    {
        if (!$ctx->laAdmin() && !$ctx->laHeThong()) {
            throw new LoiCoLapTenant('UserRepo: quản trị tài khoản chỉ dành cho admin hoặc hệ thống');
        }
    }

    /** Tài khoản (mọi vai trò, hoặc chỉ $vaiTro) kèm thuê bao nếu có — cho bảng quản trị. */
    public function danhSach(?string $vaiTro = null): array
    {
        $st = db()->prepare("SELECT u.id, u.username, u.role, u.trang_thai, u.ten_hien_thi, u.phai_doi_mat_khau, u.created_at,
                u.last_login_at, g.ma AS goi_ma, tb.het_han, tb.tam_khoa
            FROM users u LEFT JOIN thue_bao tb ON tb.user_id = u.id LEFT JOIN goi_dich_vu g ON g.id = tb.goi_id
            WHERE (? IS NULL OR u.role = ?) ORDER BY u.role = 'admin' DESC, u.username");
        $st->execute([$vaiTro, $vaiTro]);
        return $st->fetchAll();
    }

    /**
     * Tạo tài khoản; đại lý kèm thuê bao gói $maGoi tới $hetHan (+ 1 dòng lịch sử `tao`). Trả ['id', 'mat_khau_tam'] —
     * mật khẩu tạm chỉ có ở kết quả này.
     */
    public function tao(string $username, string $vaiTro, string $tenHienThi = '', ?string $maGoi = null, ?DateTimeImmutable $hetHan = null): array
    {
        $username = trim($username);
        if (preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username) !== 1) {
            throw new InvalidArgumentException('Tên đăng nhập 3–50 ký tự: chữ không dấu, số, dấu chấm, gạch dưới, gạch ngang.');
        }
        self::kiemVaiTro($vaiTro);
        if ($vaiTro === 'user' && ($maGoi === null || $hetHan === null)) {
            throw new InvalidArgumentException('Tài khoản đại lý cần chọn gói và ngày hết hạn thuê bao.');
        }
        $this->batBuocChuaCo($username); // trước khi băm (~0,8 s) — kiểm lại trong transaction
        $matKhau = self::sinhMatKhau();
        $hash = bam_mat_khau($matKhau);
        $id = trong_giao_dich(function (PDO $pdo) use ($username, $vaiTro, $tenHienThi, $maGoi, $hetHan, $hash): int {
            if ($vaiTro === 'user') {
                $this->batBuocConGiayPhep();
            }
            $this->batBuocChuaCo($username);
            $pdo->prepare("INSERT INTO users (username, password_hash, role, trang_thai, ten_hien_thi, phai_doi_mat_khau, created_at, created_by)
                VALUES (?, ?, ?, 'active', ?, 1, NOW(), ?)")
                ->execute([$username, $hash, $vaiTro, mb_substr(trim($tenHienThi), 0, 100, 'UTF-8'), $this->boi()]);
            $id = (int) $pdo->lastInsertId();
            if ($vaiTro === 'admin') {
                dong_bo_admins($username, $hash);
            } else {
                (new ThueBaoRepo($this->ctx))->tao($id, (string) $maGoi, GiayPhepGuard::homNay(), $hetHan);
            }
            return $id;
        });
        return ['id' => $id, 'mat_khau_tam' => $matKhau];
    }

    /** Khoá tài khoản + thu hồi mọi phiên (có hiệu lực ngay ở request kế). */
    public function khoa(int $id): void
    {
        trong_giao_dich(function () use ($id): void {
            $u = $this->docKhoa($id);
            $this->khongPhaiMinh($id, 'khoá');
            if ($u['role'] === 'admin' && $u['trang_thai'] === 'active') {
                $this->conAdminKhac($id);
            }
            db()->prepare("UPDATE users SET trang_thai = 'locked' WHERE id = ?")->execute([$id]);
            if ($u['role'] === 'admin') {
                dong_bo_admins($u['username'], null);
            }
        });
        PhienBaoMat::thuHoiTatCa($id);
    }

    public function moKhoa(int $id): void
    {
        trong_giao_dich(function () use ($id): void {
            $u = $this->docKhoa($id);
            if ($u['trang_thai'] === 'active') {
                return;
            }
            if ($u['role'] === 'user') {
                $this->batBuocConGiayPhep();
            }
            db()->prepare("UPDATE users SET trang_thai = 'active' WHERE id = ?")->execute([$id]);
            if ($u['role'] === 'admin') {
                dong_bo_admins($u['username'], $u['password_hash']);
            }
        });
    }

    /** Đặt lại mật khẩu: trả mật khẩu tạm MỘT lần, buộc đổi lần đăng nhập kế, thu hồi mọi phiên. */
    public function datLaiMatKhau(int $id): string
    {
        $matKhau = self::sinhMatKhau();
        $hash = bam_mat_khau($matKhau);
        trong_giao_dich(function (PDO $pdo) use ($id, $hash): void {
            $u = $this->docKhoa($id);
            $this->khongPhaiMinh($id, 'đặt lại mật khẩu (dùng "Đổi mật khẩu")');
            $pdo->prepare('UPDATE users SET password_hash = ?, phai_doi_mat_khau = 1 WHERE id = ?')->execute([$hash, $id]);
            if ($u['role'] === 'admin' && $u['trang_thai'] === 'active') {
                dong_bo_admins($u['username'], $hash);
            }
        });
        PhienBaoMat::thuHoiTatCa($id);
        return $matKhau;
    }

    /** "Đăng xuất mọi nơi": thu hồi mọi phiên của tài khoản. */
    public function dangXuatMoiNoi(int $id): void
    {
        $this->docKhoa($id);
        PhienBaoMat::thuHoiTatCa($id);
    }

    public function doiVaiTro(int $id, string $vaiTro): void
    {
        self::kiemVaiTro($vaiTro);
        trong_giao_dich(function () use ($id, $vaiTro): void {
            $u = $this->docKhoa($id);
            if ($u['role'] === $vaiTro) {
                return;
            }
            $this->khongPhaiMinh($id, 'đổi quyền');
            if ($vaiTro === 'user' && $u['trang_thai'] === 'active') {
                $this->conAdminKhac($id);
                $this->batBuocConGiayPhep();
            }
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$vaiTro, $id]);
            dong_bo_admins($u['username'], $vaiTro === 'admin' && $u['trang_thai'] === 'active' ? $u['password_hash'] : null);
        });
        PhienBaoMat::thuHoiTatCa($id); // quyền đổi → đăng nhập lại để menu / quyền mới có hiệu lực
    }

    // ---- Nội bộ ----

    private static function kiemVaiTro(string $vaiTro): void
    {
        if (!in_array($vaiTro, self::VAI_TRO, true)) {
            throw new InvalidArgumentException("Vai trò '$vaiTro' không hợp lệ.");
        }
    }

    private static function sinhMatKhau(): string
    {
        $mk = '';
        for ($i = 0; $i < self::DO_DAI_MAT_KHAU_TAM; $i++) {
            $mk .= self::KY_TU_MAT_KHAU[random_int(0, strlen(self::KY_TU_MAT_KHAU) - 1)];
        }
        return $mk;
    }

    /** Người thao tác (ghi created_by): admin web; null = hệ thống. */
    private function boi(): ?int
    {
        return $this->ctx->laAdmin() ? $this->ctx->userId : null;
    }

    private function docKhoa(int $id): array
    {
        $st = db()->prepare('SELECT id, username, role, trang_thai, password_hash FROM users WHERE id = ? FOR UPDATE');
        $st->execute([$id]);
        return $st->fetch() ?: throw new LoiQuanTri("Không tìm thấy tài khoản #$id.");
    }

    private function khongPhaiMinh(int $id, string $viec): void
    {
        if ($this->ctx->laAdmin() && $this->ctx->userId === $id) {
            throw new LoiQuanTri("Không thể tự $viec cho chính tài khoản đang đăng nhập.");
        }
    }

    private function conAdminKhac(int $id): void
    {
        $st = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND trang_thai = 'active' AND id <> ? FOR UPDATE");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() === 0) {
            throw new LoiQuanTri('Phải luôn còn ít nhất 1 quản trị viên đang hoạt động.');
        }
    }

    /** Còn chỗ trong giấy phép nền tảng cho thêm 1 đại lý hoạt động (khoá dòng giấy phép). Gọi TRONG transaction. */
    private function batBuocConGiayPhep(): void
    {
        $thueBao = new ThueBaoRepo($this->ctx);
        (new GiayPhepNenTangRepo($this->ctx))->batBuocConCho(static fn (): int => $thueBao->soDaiLyHoatDong());
    }

    private function batBuocChuaCo(string $username): void
    {
        $st = db()->prepare('SELECT 1 FROM users WHERE username = ?');
        $st->execute([$username]);
        if ($st->fetchColumn() !== false) {
            throw new LoiQuanTri("Tên đăng nhập '$username' đã tồn tại.");
        }
    }
}
