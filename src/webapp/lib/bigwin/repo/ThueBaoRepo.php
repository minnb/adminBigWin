<?php
declare(strict_types=1);

// Truy cập DUY NHẤT tới bảng thuê bao (thue_bao, thue_bao_lich_su, su_dung_ngay) + đọc goi_dich_vu / giay_phep_he_thong.
// Q-P02-1: KHÔNG kế thừa TenantRepo — admin phải thao tác trên thuê bao của đại lý khác. Hai mặt:
//   • Đại lý: cuaToi(), tangDem() — luôn theo id của TenantContext, không nhận id từ ngoài.
//   • Quản trị: tao / giaHan / doiGoi / tamKhoa / moKhoa / lichSu / … — chỉ ngữ cảnh admin (web) hoặc hệ thống (CLI); id đại
//     lý đích do trang admin (đã qua require_admin()) truyền vào. Mỗi thay đổi ghi 1 dòng thue_bao_lich_su CÙNG transaction.
//     Lịch sử chỉ được INSERT — lớp này không có câu lệnh nào sửa hay gỡ dòng lịch sử (P02-T28).

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../TenantContext.php';
require_once __DIR__ . '/../GiayPhepGuard.php';

final class ThueBaoRepo
{
    public const BANG_THUE_BAO = ['thue_bao', 'thue_bao_lich_su', 'su_dung_ngay'];
    /** Cột đếm của su_dung_ngay => cột hạn mức tương ứng trong gói (null = không có hạn mức). */
    public const COT_DEM = ['so_tin_doi_soat' => 'so_tin_ngay_toi_da', 'so_lan_phan_tich' => null];

    private const COT = 'tb.user_id, g.ma AS goi_ma, tb.bat_dau, tb.het_han, tb.tam_khoa, tb.ly_do_khoa,
            g.so_ngay_an_han, g.so_khach_toi_da, g.so_tin_ngay_toi_da, g.cho_phep_telegram, g.so_ngay_tra_cuu_lich_su';
    private const TU = ' FROM thue_bao tb JOIN goi_dich_vu g ON g.id = tb.goi_id';

    public function __construct(private readonly TenantContext $ctx)
    {
    }

    // ---- Mặt đại lý ----

    /** Thuê bao của chính ngữ cảnh (kèm hạn mức gói), null = chưa có. */
    public function cuaToi(): ?array
    {
        return $this->doc($this->idCuaToi());
    }

    /**
     * Tăng 1 lần sử dụng trong ngày nếu còn hạn mức; false = đã hết (không tăng). Gọi trong transaction của thao tác ghi
     * thì đếm và ghi cùng commit; không có transaction ngoài thì tự mở/đóng.
     */
    public function tangDem(string $cot, DateTimeImmutable $ngay): bool
    {
        if (!array_key_exists($cot, self::COT_DEM)) {
            throw new InvalidArgumentException("ThueBaoRepo::tangDem(): cột đếm `$cot` không hợp lệ");
        }
        $id = $this->idCuaToi();
        $ngayStr = $ngay->format('Y-m-d');
        return trong_giao_dich(function (PDO $pdo) use ($cot, $id, $ngayStr): bool {
            // Tạo dòng (nếu chưa có) và giữ khoá X ngay: FOR UPDATE trên dòng CHƯA tồn tại chỉ khoá khoảng → 2 giao dịch cùng lọt
            $pdo->prepare('INSERT INTO su_dung_ngay (user_id, ngay) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = user_id')->execute([$id, $ngayStr]);
            $st = $pdo->prepare("SELECT $cot FROM su_dung_ngay WHERE user_id = ? AND ngay = ? FOR UPDATE");
            $st->execute([$id, $ngayStr]);
            $daDung = (int) $st->fetchColumn();
            $cotHan = self::COT_DEM[$cot];
            $toiDa = $cotHan === null || $this->ctx->laAdmin() ? null : GiayPhepGuard::hanMuc($this->ctx->vaiTro, $this->doc($id))[$cotHan];
            if ($toiDa !== null && $daDung >= $toiDa) {
                return false;
            }
            $pdo->prepare("UPDATE su_dung_ngay SET $cot = $cot + 1 WHERE user_id = ? AND ngay = ?")->execute([$id, $ngayStr]);
            return true;
        });
    }

    // ---- Mặt quản trị ----

    public function tao(int $daiLy, string $maGoi, DateTimeImmutable $batDau, DateTimeImmutable $hetHan): void
    {
        $this->thayDoi($daiLy, 'tao', function (?array $truoc, ?int $boi) use ($daiLy, $maGoi, $batDau, $hetHan): void {
            if ($truoc !== null) {
                throw new LogicException("ThueBaoRepo::tao(): đại lý #$daiLy đã có thuê bao — dùng giaHan/doiGoi");
            }
            $st = db()->prepare('SELECT role FROM users WHERE id = ?');
            $st->execute([$daiLy]);
            if ($st->fetchColumn() !== 'user') {
                throw new InvalidArgumentException("ThueBaoRepo::tao(): #$daiLy không phải tài khoản đại lý");
            }
            if ($hetHan < $batDau) {
                throw new InvalidArgumentException('ThueBaoRepo::tao(): ngày hết hạn trước ngày bắt đầu');
            }
            db()->prepare('INSERT INTO thue_bao (user_id, goi_id, bat_dau, het_han, cap_nhat_boi, cap_nhat_luc) VALUES (?, ?, ?, ?, ?, NOW())')
                ->execute([$daiLy, $this->idGoi($maGoi), $batDau->format('Y-m-d'), $hetHan->format('Y-m-d'), $boi]);
        });
    }

    public function giaHan(int $daiLy, DateTimeImmutable $hetHanMoi): void
    {
        $hetHan = $hetHanMoi->format('Y-m-d');
        $this->capNhat($daiLy, 'gia_han', 'het_han = ?', [$hetHan], static function (array $truoc) use ($hetHan): void {
            if ($hetHan < $truoc['bat_dau']) {
                throw new InvalidArgumentException('ThueBaoRepo::giaHan(): ngày hết hạn trước ngày bắt đầu');
            }
        });
    }

    public function doiGoi(int $daiLy, string $maGoi): void
    {
        $this->capNhat($daiLy, 'doi_goi', 'goi_id = ?', [$this->idGoi($maGoi)]);
    }

    public function tamKhoa(int $daiLy, string $lyDo): void
    {
        $lyDo = trim($lyDo);
        if ($lyDo === '') {
            throw new InvalidArgumentException('ThueBaoRepo::tamKhoa(): bắt buộc ghi lý do');
        }
        $this->capNhat($daiLy, 'tam_khoa', 'tam_khoa = 1, ly_do_khoa = ?', [mb_substr($lyDo, 0, 255, 'UTF-8')]);
    }

    public function moKhoa(int $daiLy): void
    {
        $this->capNhat($daiLy, 'mo_khoa', 'tam_khoa = 0, ly_do_khoa = NULL', []);
    }

    /** Lịch sử thuê bao của đại lý, theo thứ tự ghi; truoc/sau đã giải mã JSON. */
    public function lichSu(int $daiLy): array
    {
        $this->batBuocQuanTri();
        $st = db()->prepare('SELECT id, hanh_dong, truoc_json, sau_json, boi, luc FROM thue_bao_lich_su WHERE user_id = ? ORDER BY id');
        $st->execute([$daiLy]);
        return array_map(static fn (array $d): array => [
            'id' => (int) $d['id'],
            'hanh_dong' => $d['hanh_dong'],
            'truoc' => $d['truoc_json'] === null ? null : json_decode($d['truoc_json'], true),
            'sau' => json_decode($d['sau_json'], true),
            'boi' => $d['boi'] === null ? null : (int) $d['boi'],
            'luc' => $d['luc'],
        ], $st->fetchAll());
    }

    /** Số đại lý (role=user) đang hoạt động — admin và tài khoản bị khoá không tính (O8). */
    public function soDaiLyHoatDong(): int
    {
        $this->batBuocQuanTri();
        return (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND trang_thai = 'active'")->fetchColumn();
    }

    /** Giấy phép nền tảng còn cho tạo / mở khoá thêm 1 đại lý không (gọi trước khi tạo / mở khoá tài khoản — P03). */
    public function coTheThemDaiLy(): bool
    {
        $this->batBuocQuanTri();
        $dong = db()->query('SELECT so_dai_ly_toi_da FROM giay_phep_he_thong WHERE id = 1')->fetch();
        if ($dong === false) {
            return false; // thiếu giấy phép nền tảng → không cấp thêm (fail-closed)
        }
        return GiayPhepGuard::coTheThemDaiLy($dong['so_dai_ly_toi_da'] === null ? null : (int) $dong['so_dai_ly_toi_da'], $this->soDaiLyHoatDong());
    }

    /** Thuê bao đang hoạt động hết hạn trong [hôm nay, hôm nay + $soNgay] — để nhắc gia hạn. */
    public function sapHetHan(DateTimeImmutable $homNay, int $soNgay): array
    {
        $this->batBuocQuanTri();
        $st = db()->prepare("SELECT tb.user_id, u.username, tb.het_han FROM thue_bao tb JOIN users u ON u.id = tb.user_id
            WHERE u.role = 'user' AND u.trang_thai = 'active' AND tb.tam_khoa = 0 AND tb.het_han BETWEEN ? AND ?
            ORDER BY tb.het_han, tb.user_id");
        $st->execute([$homNay->format('Y-m-d'), $homNay->modify("+$soNgay day")->format('Y-m-d')]);
        return $st->fetchAll();
    }

    /**
     * Cron: với mỗi thuê bao, trạng thái tính tại $homNay khác trạng thái ghi ở dòng lịch sử gần nhất → ghi 1 dòng tu_dong.
     * Đọc tất cả bằng 1 câu; chỉ đại lý có đổi mới mở transaction (khoá dòng, kiểm lại, ghi). Chạy lại cùng ngày không ghi
     * trùng (P02-T29). Trả số dòng đã ghi.
     */
    public function ghiChuyenTrangThaiTuDong(DateTimeImmutable $homNay): int
    {
        $boi = $this->batBuocQuanTri();
        $tatCa = db()->query('SELECT ' . self::COT . ', (SELECT l.sau_json FROM thue_bao_lich_su l WHERE l.user_id = tb.user_id
            ORDER BY l.id DESC LIMIT 1) AS sau_cuoi' . self::TU . " JOIN users u ON u.id = tb.user_id WHERE u.role = 'user'")->fetchAll();
        $daGhi = 0;
        foreach ($tatCa as $dong) {
            $id = (int) $dong['user_id'];
            $cuoi = $dong['sau_cuoi'] === null ? null : json_decode($dong['sau_cuoi'], true);
            if (($cuoi['trang_thai'] ?? null) === GiayPhepGuard::trangThai('user', $dong, $homNay)) {
                continue;
            }
            $daGhi += trong_giao_dich(function () use ($id, $homNay, $boi): int {
                $sau = $this->anh($this->doc($id, true), $homNay);
                $truoc = $this->lichSuCuoi($id);
                if (($truoc['trang_thai'] ?? null) === $sau['trang_thai']) {
                    return 0; // tiến trình khác vừa ghi
                }
                $this->ghiLichSu($id, 'tu_dong', $truoc, $sau, $boi);
                return 1;
            });
        }
        return $daGhi;
    }

    // ---- Nội bộ ----

    private function idCuaToi(): int
    {
        if ($this->ctx->userId <= 0) {
            throw new LoiCoLapTenant('ThueBaoRepo: ngữ cảnh không thuộc đại lý nào');
        }
        return $this->ctx->userId;
    }

    /** id người thao tác (admin) để ghi `boi`; null = hệ thống (CLI). Ngữ cảnh đại lý → LoiCoLapTenant. */
    private function batBuocQuanTri(): ?int
    {
        if ($this->ctx->laAdmin()) {
            return $this->ctx->userId;
        }
        if ($this->ctx->laHeThong()) {
            return null;
        }
        throw new LoiCoLapTenant('ThueBaoRepo: thao tác quản trị thuê bao chỉ dành cho admin hoặc hệ thống');
    }

    private function doc(int $id, bool $khoa = false): ?array
    {
        $st = db()->prepare('SELECT ' . self::COT . self::TU . ' WHERE tb.user_id = ?' . ($khoa ? ' FOR UPDATE' : ''));
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** sau_json của dòng lịch sử gần nhất (đã giải mã), null nếu chưa có. */
    private function lichSuCuoi(int $id): ?array
    {
        $st = db()->prepare('SELECT sau_json FROM thue_bao_lich_su WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$id]);
        $json = $st->fetchColumn();
        return $json === false ? null : json_decode($json, true);
    }

    private function idGoi(string $ma): int
    {
        $st = db()->prepare('SELECT id FROM goi_dich_vu WHERE ma = ?');
        $st->execute([$ma]);
        $id = $st->fetchColumn();
        if ($id === false) {
            throw new InvalidArgumentException("ThueBaoRepo: gói `$ma` không tồn tại");
        }
        return (int) $id;
    }

    /** Thao tác quản trị trên thuê bao ĐÃ CÓ: UPDATE `$gan` (sau khi $kiem(dòng hiện tại) nếu có) + lịch sử. */
    private function capNhat(int $daiLy, string $hanhDong, string $gan, array $thamSo, ?callable $kiem = null): void
    {
        $this->thayDoi($daiLy, $hanhDong, static function (?array $truoc, ?int $boi) use ($daiLy, $hanhDong, $gan, $thamSo, $kiem): void {
            if ($truoc === null) {
                throw new LogicException("ThueBaoRepo::$hanhDong(): đại lý #$daiLy chưa có thuê bao");
            }
            if ($kiem !== null) {
                $kiem($truoc);
            }
            db()->prepare("UPDATE thue_bao SET $gan, cap_nhat_boi = ?, cap_nhat_luc = NOW() WHERE user_id = ?")->execute([...$thamSo, $boi, $daiLy]);
        });
    }

    /** Một thao tác quản trị: khoá dòng, $ap(dòng trước, người thao tác), ghi lịch sử trước–sau — tất cả trong 1 transaction. */
    private function thayDoi(int $daiLy, string $hanhDong, callable $ap): void
    {
        $boi = $this->batBuocQuanTri();
        trong_giao_dich(function () use ($daiLy, $hanhDong, $ap, $boi): void {
            $truoc = $this->doc($daiLy, true);
            $ap($truoc, $boi);
            $homNay = GiayPhepGuard::homNay();
            $this->ghiLichSu($daiLy, $hanhDong, $truoc === null ? null : $this->anh($truoc, $homNay), $this->anh($this->doc($daiLy), $homNay), $boi);
        });
    }

    /** Ảnh chụp thuê bao để ghi lịch sử (kèm trạng thái tính tại $homNay). */
    private function anh(array $dong, DateTimeImmutable $homNay): array
    {
        return [
            'goi_ma' => $dong['goi_ma'],
            'bat_dau' => $dong['bat_dau'],
            'het_han' => $dong['het_han'],
            'tam_khoa' => (int) $dong['tam_khoa'],
            'ly_do_khoa' => $dong['ly_do_khoa'],
            'trang_thai' => GiayPhepGuard::trangThai('user', $dong, $homNay),
        ];
    }

    private function ghiLichSu(int $daiLy, string $hanhDong, ?array $truoc, array $sau, ?int $boi): void
    {
        $json = static fn (?array $a): ?string => $a === null ? null : json_encode($a, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        db()->prepare('INSERT INTO thue_bao_lich_su (user_id, hanh_dong, truoc_json, sau_json, boi, luc) VALUES (?, ?, ?, ?, ?, NOW())')
            ->execute([$daiLy, $hanhDong, $json($truoc), $json($sau), $boi]);
    }
}
