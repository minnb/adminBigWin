<?php
declare(strict_types=1);

// Phần dùng chung của trang quản trị MỚI (P03 S1.7) — dựng trên khung app (QĐ1), KHÔNG dùng admin/_auth.php cũ.
// Mỗi trang: $ctx = khung_bat_dau('quan_tri', true); $kq = quan_tri_xu_ly_post(fn …); khung_ve($ctx, …).

require_once __DIR__ . '/../app/_layout.php';
require_once __DIR__ . '/../lib/bigwin/repo/UserRepo.php';
require_once __DIR__ . '/../lib/bigwin/repo/GoiDichVuRepo.php';
require_once __DIR__ . '/../lib/bigwin/repo/GiayPhepNenTangRepo.php';

/** Số ngày thuê bao mặc định khi tạo đại lý / cấp thuê bao. */
const QT_NGAY_THUE_BAO_MAC_DINH = 30;

/** Ô form POST dạng chuỗi (thiếu / sai kiểu → ''). */
function o_post(string $ten): string
{
    return is_string($_POST[$ten] ?? null) ? trim($_POST[$ten]) : '';
}

/** Số nguyên dương từ chuỗi (id trong form / URL), không hợp lệ → null. */
function so_id(mixed $v): ?int
{
    return is_string($v) && preg_match('/^[1-9]\d{0,9}$/', $v) === 1 ? (int) $v : null;
}

/** id từ ô form — sai → lỗi hiển thị cho admin. */
function o_post_id(): int
{
    return so_id(o_post('id')) ?? throw new InvalidArgumentException('Thiếu tài khoản / gói cần thao tác.');
}

/** Ngày từ ô form 'Y-m-d' (sai → lỗi hiển thị). */
function o_post_ngay(string $ten): DateTimeImmutable
{
    $ngay = NgayLamViec::chuanHoa(o_post($ten)) ?? throw new InvalidArgumentException('Ngày không hợp lệ.');
    return new DateTimeImmutable($ngay, new DateTimeZone(BW_TZ));
}

/** Ngày hết hạn mặc định cho ô ngày của form (hôm nay + QT_NGAY_THUE_BAO_MAC_DINH). */
function het_han_mac_dinh(): string
{
    return GiayPhepGuard::homNay()->modify('+' . QT_NGAY_THUE_BAO_MAC_DINH . ' day')->format('Y-m-d');
}

/**
 * GET → thông báo của thao tác vừa làm (nếu có, hiện 1 lần). POST → kiểm CSRF + nguồn (sai → 403, dừng), chạy
 * $xuLy(o_post('hanh_dong')) trả ['loai' => 'ok', 'thong_diep', …]; vi phạm luật / dữ liệu sai → ['loai' => 'loi', …].
 * Kết quả KHÔNG có mật khẩu tạm → lưu thông báo vào phiên rồi chuyển hướng (PRG — tải lại trang không gửi lại form);
 * có mật khẩu tạm → trả thẳng để hiện đúng 1 lần trên phản hồi này, không lưu ở đâu (QĐ6). Lỗi cô lập tenant không bị nuốt.
 */
function quan_tri_xu_ly_post(callable $xuLy): ?array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        $kq = $_SESSION['quan_tri_kq'] ?? null;
        unset($_SESSION['quan_tri_kq']);
        return is_array($kq) ? $kq : null;
    }
    khung_kiem_post();
    try {
        $kq = $xuLy(o_post('hanh_dong'));
    } catch (LoiCoLapTenant $e) {
        throw $e;
    } catch (LoiQuanTri | InvalidArgumentException | LogicException $e) {
        $kq = ['loai' => 'loi', 'thong_diep' => $e->getMessage()];
    }
    if (isset($kq['mat_khau_tam'])) {
        return $kq;
    }
    $_SESSION['quan_tri_kq'] = ['loai' => $kq['loai'], 'thong_diep' => $kq['thong_diep']];
    $truyVan = (string) ($_SERVER['QUERY_STRING'] ?? '');
    header('Location: ' . url_web('admin/' . basename((string) $_SERVER['SCRIPT_FILENAME'])) . ($truyVan !== '' ? "?$truyVan" : ''), true, 303);
    exit;
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

/** Thẻ mở form POST thao tác: CSRF + hanh_dong (+ id, + ô ẩn khác); $xacNhan khác rỗng → hỏi lại trước khi gửi. */
function form_mo(string $hanhDong, ?int $id = null, string $lop = 'inline', string $xacNhan = '', array $an = []): string
{
    $s = '<form method="post" class="' . h($lop) . '"'
        . ($xacNhan !== '' ? ' @submit="confirm(' . h(json_encode($xacNhan, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) . ') || $event.preventDefault()"' : '')
        . '>' . truong_csrf() . '<input type="hidden" name="hanh_dong" value="' . h($hanhDong) . '">';
    foreach (($id === null ? [] : ['id' => (string) $id]) + $an as $ten => $giaTri) {
        $s .= '<input type="hidden" name="' . h($ten) . '" value="' . h($giaTri) . '">';
    }
    return $s;
}

/** Nút nhỏ trong bảng. */
function nut_nho(string $nhan, string $lop = 'btn-ghost'): string
{
    return '<button class="btn ' . h($lop) . ' !px-2.5 !py-1 !text-[13px]">' . h($nhan) . '</button>';
}

/** Form 1 nút (thao tác trên một id). */
function nut_form(string $hanhDong, int $id, string $nhan, string $lop = 'btn-ghost', string $xacNhan = '', array $an = []): string
{
    return form_mo($hanhDong, $id, 'inline', $xacNhan, $an) . nut_nho($nhan, $lop) . '</form>';
}
