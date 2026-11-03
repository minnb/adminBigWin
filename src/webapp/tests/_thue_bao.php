<?php
declare(strict_types=1);

// Hạ tầng test bản quyền SaaS (P02 S1.5) — CHỈ dùng trong tests/.

require_once __DIR__ . '/_tenant.php';
require_once __DIR__ . '/../lib/bigwin/GiayPhepGuard.php';
require_once __DIR__ . '/../lib/bigwin/repo/ThueBaoRepo.php';

function bw_gp_lam_sach(): void
{
    bw_tenant_lam_sach();
    db_test()->exec("DELETE FROM goi_dich_vu WHERE ma NOT IN ('unlimited', 'dung_thu')");
    db_test()->exec('UPDATE giay_phep_he_thong SET so_dai_ly_toi_da = NULL');
}

/** Ngày lệch $lech ngày so với hôm nay (giờ VN), dạng Y-m-d. */
function bw_gp_ngay(int $lech = 0): string
{
    return today_vn()->modify("$lech day")->format('Y-m-d');
}

/** Tạo gói thử; $han: so_tin_ngay_toi_da, so_ngay_an_han, … */
function bw_gp_goi(string $ma, array $han = []): int
{
    $han += ['so_tin_ngay_toi_da' => null, 'so_ngay_an_han' => 3];
    db_test()->prepare('INSERT INTO goi_dich_vu (ma, ten, so_tin_ngay_toi_da, so_ngay_an_han) VALUES (?, ?, ?, ?)')
        ->execute([$ma, "Gói $ma", $han['so_tin_ngay_toi_da'], $han['so_ngay_an_han']]);
    return (int) db_test()->lastInsertId();
}

/** Đại lý có thuê bao gói $maGoi hết hạn ngày $hetHan (null = chưa có thuê bao). */
function bw_gp_dai_ly(string $ten, ?string $hetHan, string $maGoi = 'unlimited', int $tamKhoa = 0): int
{
    return bw_auth_tao_user($ten, ['thue_bao' => $hetHan === null ? false : ['het_han' => $hetHan, 'goi' => $maGoi, 'tam_khoa' => $tamKhoa]]);
}

/** Các dòng lịch sử thuê bao của $uid (JSON đã giải mã), theo thứ tự ghi. */
function bw_gp_lich_su(int $uid): array
{
    $st = db_test()->prepare('SELECT hanh_dong, truoc_json, sau_json, boi FROM thue_bao_lich_su WHERE user_id = ? ORDER BY id');
    $st->execute([$uid]);
    return array_map(static fn (array $d): array => [
        'hanh_dong' => $d['hanh_dong'],
        'truoc' => $d['truoc_json'] === null ? null : json_decode($d['truoc_json'], true),
        'sau' => json_decode($d['sau_json'], true),
        'boi' => $d['boi'] === null ? null : (int) $d['boi'],
    ], $st->fetchAll());
}

function bw_gp_da_dung(int $uid, string $cot, string $ngay): int
{
    $st = db_test()->prepare("SELECT $cot FROM su_dung_ngay WHERE user_id = ? AND ngay = ?");
    $st->execute([$uid, $ngay]);
    return (int) $st->fetchColumn();
}
