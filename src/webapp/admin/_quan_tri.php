<?php
declare(strict_types=1);

// Phần dùng chung của trang quản trị MỚI (P03 S1.7) — dựng trên khung app (QĐ1), KHÔNG dùng admin/_auth.php cũ.
// Mỗi trang: $ctx = khung_bat_dau('quan_tri', true); $kq = quan_tri_xu_ly_post(fn …); khung_ve($ctx, …).

require_once __DIR__ . '/../app/_layout.php';
require_once __DIR__ . '/../lib/bigwin/repo/UserRepo.php';
require_once __DIR__ . '/../lib/bigwin/repo/GoiDichVuRepo.php';
require_once __DIR__ . '/../lib/bigwin/repo/GiayPhepNenTangRepo.php';

/** Ô form POST dạng chuỗi (thiếu / sai kiểu → ''). */
function o_post(string $ten): string
{
    return is_string($_POST[$ten] ?? null) ? trim($_POST[$ten]) : '';
}

/** id từ ô form (số nguyên dương) — sai → lỗi hiển thị cho admin. */
function o_post_id(): int
{
    $id = o_post('id');
    if (preg_match('/^[1-9]\d{0,9}$/', $id) !== 1) {
        throw new InvalidArgumentException('Thiếu tài khoản / gói cần thao tác.');
    }
    return (int) $id;
}

/** Ngày từ ô form 'Y-m-d' (sai → lỗi hiển thị). */
function o_post_ngay(string $ten): DateTimeImmutable
{
    $ngay = NgayLamViec::chuanHoa(o_post($ten)) ?? throw new InvalidArgumentException('Ngày không hợp lệ.');
    return new DateTimeImmutable($ngay, new DateTimeZone(BW_TZ));
}

/**
 * GET → null. POST → kiểm CSRF + nguồn (sai → 403, dừng), chạy $xuLy(o_post('hanh_dong')) trả ['loai' => 'ok', …];
 * vi phạm luật / dữ liệu sai → ['loai' => 'loi', 'thong_diep']. Lỗi cô lập tenant (lỗi lập trình) KHÔNG bị nuốt.
 */
function quan_tri_xu_ly_post(callable $xuLy): ?array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return null;
    }
    khung_kiem_post();
    try {
        return $xuLy(o_post('hanh_dong'));
    } catch (LoiCoLapTenant $e) {
        throw $e;
    } catch (LoiQuanTri | InvalidArgumentException | LogicException $e) {
        return ['loai' => 'loi', 'thong_diep' => $e->getMessage()];
    }
}

/** Thanh tab quản trị + khung thông báo kết quả thao tác. */
function quan_tri_dau(string $tabHienTai, ?array $kq): void
{
    $tab = ['users' => ['Tài khoản', 'fa-solid fa-user-gear', 'admin/users.php'], 'goi_thue_bao' => ['Gói & Thuê bao', 'fa-solid fa-box', 'admin/goi_thue_bao.php'],
        'cu' => ['Hệ thống (giao diện cũ)', 'fa-solid fa-server', 'admin/index.php']];
    echo '<div class="flex gap-2 flex-wrap mb-4">';
    foreach ($tab as $ma => [$nhan, $icon, $tep]) {
        echo '<a class="btn ', $ma === $tabHienTai ? 'btn-primary' : 'btn-ghost', '" href="', h(url_web($tep)), '"><i class="', h($icon), '"></i>', h($nhan), '</a>';
    }
    echo '</div>';
    if ($kq !== null) {
        echo '<div class="mb-4 pill ', $kq['loai'] === 'ok' ? 'pill-ok' : 'pill-err', ' !whitespace-normal" role="status">', h($kq['thong_diep']), '</div>';
    }
}

/** Input ẩn CSRF cho form POST. */
function o_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . h(Csrf::token()) . '">';
}

/** Form 1 nút (thao tác trên một id). */
function nut_form(string $hanhDong, int $id, string $nhan, string $lop = 'btn-ghost', string $xacNhan = ''): string
{
    return '<form method="post" class="inline"' . ($xacNhan !== '' ? ' @submit="confirm(' . h(json_encode($xacNhan, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) . ') || $event.preventDefault()"' : '') . '>'
        . o_csrf() . '<input type="hidden" name="hanh_dong" value="' . h($hanhDong) . '"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn ' . h($lop) . ' !px-2.5 !py-1 !text-[13px]">' . h($nhan) . '</button></form>';
}
