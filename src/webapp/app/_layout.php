<?php
declare(strict_types=1);

// Khung app BigWIN (P03 S1.6, bố cục theo docs/ui-preview/index.html — N4): topbar (ngày làm việc N8, trạng thái KQ,
// đồng hồ, menu tài khoản), băng thuê bao, sidebar 6 page (+ Quản trị nếu admin), tab mobile, phím tắt, modal Đổi mật
// khẩu. Trang dùng: $ctx = khung_bat_dau('nhap_tin'); khung_ve($ctx, 'Tiêu đề', function (array $ctx): void { … });
// Trang quản trị mới (QĐ1) cũng dùng khung này với khung_bat_dau('quan_tri', true).

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/bigwin/NgayLamViec.php';
require_once __DIR__ . '/../lib/bigwin/repo/ThueBaoRepo.php';

/** Mục điều hướng theo thứ tự phím Alt+1…: mã => [nhãn, nhãn ngắn (tab mobile), icon Font Awesome, tệp]. */
const KHUNG_TRANG = [
    'nhap_tin' => ['Nhập tin nhanh', 'Nhập tin', 'fa-solid fa-keyboard', 'app/nhap_tin.php'],
    'telegram' => ['Telegram', 'Telegram', 'fa-brands fa-telegram', 'app/telegram.php'],
    'kqxs' => ['Kết quả XSMB', 'KQXS', 'fa-solid fa-trophy', 'app/kqxs.php'],
    'khach_hang' => ['Khách hàng & Giá', 'Khách & Giá', 'fa-solid fa-users', 'app/khach_hang.php'],
    'lich_su' => ['Lịch sử & Chốt sổ', 'Chốt sổ', 'fa-solid fa-receipt', 'app/lich_su.php'],
    'cu_phap' => ['Cú pháp cược', 'Cú pháp', 'fa-solid fa-puzzle-piece', 'app/cu_phap.php'],
];
const KHUNG_TRANG_QUAN_TRI = ['quan_tri' => ['Quản trị hệ thống', 'Quản trị', 'fa-solid fa-shield-halved', 'admin/users.php']];
const KHUNG_SAP_HET_HAN_NGAY = 7;

/**
 * Xác thực + ngữ cảnh trang (chưa xuất gì). $chiAdmin: user thường → 403. Trả ['ma', 'user', 'tenant', 'giay_phep'
 * (GiayPhepGuard chế độ 'doc'), 'ngay' (ngày đang chọn, ?ngay=), 'ngay_mac_dinh' (N8)].
 */
function khung_bat_dau(string $ma, bool $chiAdmin = false): array
{
    $user = $chiAdmin ? require_admin() : require_user();
    $tenant = TenantContext::tuPhien($user);
    $thueBao = $tenant->laAdmin() ? null : (new ThueBaoRepo($tenant))->cuaToi();
    $ngayMacDinh = NgayLamViec::hienTai()->format('Y-m-d');
    return [
        'ma' => $ma,
        'user' => $user,
        'tenant' => $tenant,
        'giay_phep' => GiayPhepGuard::kiemTra($tenant->vaiTro, $thueBao, 'doc', GiayPhepGuard::homNay()),
        'ngay' => NgayLamViec::chuanHoa($_GET['ngay'] ?? null) ?? $ngayMacDinh,
        'ngay_mac_dinh' => $ngayMacDinh,
    ];
}

/** Form POST của trang: bắt buộc nguồn hợp lệ + token CSRF (field `csrf`), sai → 403 và dừng. */
function khung_kiem_post(): void
{
    if (!form_hop_le()) {
        http_response_code(403);
        trang_don_gian('Phiên làm việc đã hết hạn', '<p>Vui lòng tải lại trang rồi thử lại.</p>');
        exit;
    }
}

/** Gói thuê bao cho phép chế độ $cheDo không (admin luôn được). */
function khung_duoc_phep(array $ctx, string $cheDo): bool
{
    return GiayPhepGuard::choPhep($ctx['giay_phep']['trang_thai'], $cheDo);
}

/** Nút hành động gắn chế độ bản quyền — KHÔNG render khi thuê bao không cho (P03-T11; endpoint vẫn tự chặn 402). */
function nut_hanh_dong(array $ctx, string $cheDo, string $nhan, string $icon, string $lop = 'btn-primary'): string
{
    if (!khung_duoc_phep($ctx, $cheDo)) {
        return '';
    }
    return '<button type="button" class="btn ' . h($lop) . '" data-che-do="' . h($cheDo) . '"><i class="' . h($icon) . '"></i>' . h($nhan) . '</button>';
}

/** URL tệp trong webapp, giữ ngày đang chọn khi khác mặc định. */
function khung_url(array $ctx, string $tep): string
{
    return url_web($tep) . ($ctx['ngay'] !== $ctx['ngay_mac_dinh'] ? '?ngay=' . rawurlencode($ctx['ngay']) : '');
}

/** [mã băng, lớp pill, thông điệp] của băng thuê bao, hoặc null (P03 S1.6d — M04). */
function khung_bang_thue_bao(array $gp): ?array
{
    $hetHan = $gp['het_han'] === null ? '' : (new DateTimeImmutable($gp['het_han']))->format('d/m/Y');
    return match (true) {
        $gp['trang_thai'] === 'het_han' => ['het_han', 'pill-err', "Thuê bao đã hết hạn ngày $hetHan — chỉ còn xem được dữ liệu. Liên hệ quản trị để gia hạn."],
        $gp['trang_thai'] === 'an_han' => ['an_han', 'pill-err', "Thuê bao đã hết hạn ngày $hetHan — đang trong thời gian ân hạn, gia hạn sớm để không bị gián đoạn."],
        $gp['trang_thai'] === 'tam_khoa', $gp['trang_thai'] === 'chua_co_thue_bao' => [$gp['trang_thai'], 'pill-err', $gp['thong_diep']],
        $gp['so_ngay_con'] !== null && $gp['so_ngay_con'] <= KHUNG_SAP_HET_HAN_NGAY => ['sap_het_han', 'pill-warn', "Thuê bao còn {$gp['so_ngay_con']} ngày (hết hạn $hetHan)."],
        default => null,
    };
}

/** Xuất trang HTML đầy đủ. $noiDung(array $ctx) in phần thân page; bị bỏ qua khi thuê bao chặn cả chế độ xem. */
function khung_ve(array $ctx, string $tieuDe, callable $noiDung): void
{
    $u = $ctx['user'];
    $laAdmin = $ctx['tenant']->laAdmin();
    $muc = $laAdmin ? KHUNG_TRANG + KHUNG_TRANG_QUAN_TRI : KHUNG_TRANG;
    $tenHien = ten_hien($u);
    $chuTat = mb_strtoupper(mb_substr($tenHien, 0, 2, 'UTF-8'), 'UTF-8');
    $bang = khung_bang_thue_bao($ctx['giay_phep']);
    $ngayKhac = $ctx['ngay'] !== $ctx['ngay_mac_dinh'];
    $khung = json_encode([
        'trang' => array_values(array_map(static fn (array $m): string => khung_url($ctx, $m[3]), $muc)),
        'urlDoiMatKhau' => url_web('app/ajax/doi_mat_khau.php'),
        'urlDangNhap' => url_web('login.php'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $phienBan = static fn (string $tep): string => url_web($tep) . '?v=' . (int) @filemtime(__DIR__ . '/../' . $tep);

    TieuDeBaoMat::gui();
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= h(Csrf::token()) ?>">
<title><?= h($tieuDe) ?> — BigWIN</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script>
  tailwind.config = {
    theme: { extend: {
      fontFamily: { sans: ['"Inter"', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'], mono: ['"JetBrains Mono"', 'Consolas', 'monospace'] },
      colors: { pos: { blue: '#1B6EC2', ink: '#0F172A', title: '#0A2540', muted: '#334E68', ok: '#107C41', err: '#CC0000', warn: '#B86200', learn: '#6F42C1' } },
    } },
  };
</script>
<link rel="stylesheet" href="<?= h($phienBan('app/assets/app.css')) ?>">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>
</head>
<body x-data="bwKhung()" data-khung="<?= h($khung) ?>" class="h-screen flex flex-col overflow-hidden text-[15.5px]">

<header class="bg-[#0A2540] text-white border-b border-[#071B2F] shrink-0 min-w-0 shadow-sm">
  <div class="flex items-center gap-2 sm:gap-3 px-3 sm:px-4 h-16 min-w-0">
    <div class="flex items-center gap-2.5 pr-3 lg:w-56 shrink-0">
      <div class="w-10 h-10 rounded-xl bg-pos-blue text-white grid place-items-center font-extrabold text-lg shadow-md ring-2 ring-blue-400/30">B</div>
      <div class="leading-tight hidden sm:block">
        <div class="font-extrabold text-white text-lg tracking-tight">BigWIN</div>
        <div class="text-[11.5px] text-sky-200 font-semibold">Đối soát XSMB</div>
      </div>
    </div>

    <form method="get" class="flex items-center gap-2 min-w-0">
      <label class="flex items-center gap-2 rounded-xl border px-3 h-11 transition shadow-inner <?= $ngayKhac ? 'border-amber-400 bg-amber-500/20 text-amber-200' : 'border-sky-800 bg-[#133E68] text-white' ?>"<?= $ngayKhac ? ' data-ngay-khac' : '' ?> title="Ngày làm việc — trước 18:30 mặc định là hôm qua">
        <i class="fa-regular fa-calendar <?= $ngayKhac ? 'text-amber-300' : 'text-sky-300' ?>"></i>
        <input type="date" name="ngay" value="<?= h($ctx['ngay']) ?>" @change="$el.form.submit()" class="bg-transparent mono font-bold text-white outline-none w-[132px]">
      </label>
      <?php if ($ngayKhac): ?>
      <span class="pill pill-warn hidden md:inline-flex" title="Bạn đang làm việc trên ngày khác ngày mặc định">
        <i class="fa-solid fa-clock-rotate-left"></i> Ngày khác mặc định
        <a class="underline ml-1" href="<?= h(url_web($muc[$ctx['ma']][3])) ?>">Về hôm nay</a>
      </span>
      <?php endif; ?>
    </form>

    <span class="pill pill-info hidden sm:inline-flex" title="Trạng thái kết quả xổ số của ngày đang chọn (có từ P06)">
      <i class="fa-solid fa-circle-question"></i> KQXS: chưa có dữ liệu
    </span>

    <div class="ml-auto flex items-center gap-3">
      <span class="mono font-bold text-sky-200 text-lg hidden md:inline" x-text="gio"></span>
      <div class="relative" @click.outside="menu = false">
        <button type="button" class="flex items-center gap-2" @click="menu = !menu" :aria-expanded="menu" aria-haspopup="menu">
          <div class="w-10 h-10 rounded-full grid place-items-center font-bold text-white shadow-md ring-2 ring-white/20 <?= $laAdmin ? 'bg-indigo-600' : 'bg-emerald-600' ?>"><?= h($chuTat) ?></div>
          <div class="leading-tight hidden lg:block text-left">
            <div class="font-bold text-sm text-white"><?= h($tenHien) ?></div>
            <div class="text-xs text-sky-200"><?= $laAdmin ? 'admin · toàn quyền' : 'user · dữ liệu riêng' ?></div>
          </div>
          <i class="fa-solid fa-chevron-down text-xs text-sky-200"></i>
        </button>
        <div x-show="menu" x-cloak class="absolute right-0 mt-2 w-56 card shadow-2xl p-1 z-40 text-pos-ink" role="menu">
          <button type="button" class="nav-item !text-sm" role="menuitem" @click="moMk()"><i class="fa-solid fa-key w-5"></i>Đổi mật khẩu</button>
          <form method="post" action="<?= h(url_web('logout.php')) ?>">
            <?= truong_csrf() ?>
            <button class="nav-item !text-sm" role="menuitem"><i class="fa-solid fa-right-from-bracket w-5"></i>Đăng xuất</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>

<?php if ($bang !== null): ?>
<div class="shrink-0 px-3 sm:px-4 py-2 border-b <?= $bang[1] === 'pill-warn' ? 'bg-[#FFF9DB] border-amber-200' : 'bg-[#FDE8E8] border-red-200' ?>" data-bang-thue-bao="<?= h($bang[0]) ?>" role="status">
  <span class="pill <?= h($bang[1]) ?>"><i class="fa-solid <?= $bang[1] === 'pill-warn' ? 'fa-hourglass-half' : 'fa-triangle-exclamation' ?>"></i><?= h($bang[2]) ?></span>
</div>
<?php endif; ?>

<div class="flex flex-1 min-h-0 min-w-0">
<nav class="hidden lg:flex flex-col w-64 shrink-0 bg-white border-r border-slate-200 p-3 gap-1" aria-label="Điều hướng chính">
  <?php $stt = 0; foreach ($muc as $ma => [$nhan, , $icon, $tep]): $stt++; ?>
  <?php if ($ma === 'quan_tri'): ?><div class="mt-3 pt-3 border-t border-slate-200"><div class="px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-pos-muted">Quản trị</div><?php endif; ?>
  <a class="nav-item<?= $ma === $ctx['ma'] ? ' on' : '' ?>" href="<?= h(khung_url($ctx, $tep)) ?>" data-trang="<?= h($ma) ?>"<?= $ma === $ctx['ma'] ? ' aria-current="page"' : '' ?>><i class="<?= h($icon) ?> w-5"></i><?= h($nhan) ?><span class="kbd">Alt <?= $stt ?></span></a>
  <?php if ($ma === 'quan_tri'): ?></div><?php endif; ?>
  <?php endforeach; ?>
  <div class="mt-auto">
    <button type="button" class="w-full nav-item !text-sm" @click="help = true"><i class="fa-regular fa-keyboard w-5"></i>Phím tắt<span class="kbd">?</span></button>
  </div>
</nav>

<main class="flex-1 min-w-0 flex flex-col">
  <div class="lg:hidden flex gap-1 overflow-x-auto bg-white border-b border-slate-200 px-2 py-2 shrink-0">
    <?php foreach ($muc as $ma => [, $ngan, , $tep]): ?>
    <a class="px-3.5 py-2 rounded-lg text-sm font-bold whitespace-nowrap <?= $ma === $ctx['ma'] ? 'bg-pos-blue text-white' : 'text-pos-muted' ?>" href="<?= h(khung_url($ctx, $tep)) ?>"><?= h($ngan) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="flex-1 min-h-0 overflow-auto p-3 md:p-4">
    <?php if ($ctx['giay_phep']['cho_phep']): ?>
      <?php $noiDung($ctx); ?>
    <?php else: ?>
    <section class="card p-6 max-w-xl">
      <div class="font-extrabold text-lg text-pos-title mb-2"><i class="fa-solid fa-lock mr-2"></i>Chưa dùng được hệ thống</div>
      <p><?= h($ctx['giay_phep']['thong_diep']) ?></p>
      <p class="mt-2 text-sm text-pos-muted">Bạn vẫn có thể đổi mật khẩu từ menu tài khoản ở góc phải.</p>
    </section>
    <?php endif; ?>
  </div>
</main>
</div>

<div x-show="help" x-cloak class="fixed inset-0 z-50 backdrop grid place-items-center p-4" @click.self="help = false">
  <div class="card w-full max-w-md shadow-2xl p-6" role="dialog" aria-label="Phím tắt">
    <div class="font-extrabold text-lg text-pos-title mb-3"><i class="fa-regular fa-keyboard mr-2"></i>Phím tắt</div>
    <table class="w-full text-[15px]">
      <?php foreach ([['Alt + 1…' . count($muc), 'Chuyển màn hình'], ['Esc', 'Đóng hộp thoại'], ['?', 'Mở bảng này']] as [$phim, $viec]): ?>
      <tr class="border-b border-slate-100"><td class="py-2"><span class="kbd !text-[12px] text-pos-title"><?= h($phim) ?></span></td><td><?= h($viec) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <div class="text-right mt-4"><button type="button" class="btn btn-primary" @click="help = false">Đã hiểu <span class="kbd">Esc</span></button></div>
  </div>
</div>

<div x-show="modalMk" x-cloak class="fixed inset-0 z-50 backdrop grid place-items-center p-4" @click.self="dongMk()">
  <form class="card w-full max-w-md shadow-2xl p-6 space-y-3" role="dialog" aria-label="Đổi mật khẩu" @submit.prevent="doiMatKhau()">
    <div class="font-extrabold text-lg text-pos-title"><i class="fa-solid fa-key mr-2"></i>Đổi mật khẩu</div>
    <?php foreach ([['mat_khau_cu', 'Mật khẩu hiện tại', 'current-password'], ['mat_khau_moi', 'Mật khẩu mới (ít nhất ' . MAT_KHAU_TOI_THIEU . ' ký tự)', 'new-password'], ['xac_nhan', 'Nhập lại mật khẩu mới', 'new-password']] as [$truong, $nhan, $ac]): ?>
    <label class="block text-sm font-semibold"><?= h($nhan) ?>
      <input type="password" class="ctl mt-1" autocomplete="<?= h($ac) ?>" x-model="mk.<?= h($truong) ?>"<?= $truong === 'mat_khau_cu' ? ' x-ref="mkCu"' : '' ?> :class="loiMk.<?= h($truong) ?> && '!border-red-400'">
      <span class="text-sm text-red-700" x-show="loiMk.<?= h($truong) ?>" x-text="loiMk.<?= h($truong) ?>"></span>
    </label>
    <?php endforeach; ?>
    <p class="text-sm text-red-700" x-show="loiMk.chung" x-text="loiMk.chung"></p>
    <p class="pill pill-ok" x-show="thongBaoMk" x-text="thongBaoMk"></p>
    <div class="flex justify-end gap-2 pt-1">
      <button type="button" class="btn btn-ghost" @click="dongMk()">Đóng</button>
      <button class="btn btn-primary" :disabled="dangGuiMk"><i class="fa-solid fa-check"></i>Đổi mật khẩu</button>
    </div>
  </form>
</div>

<script src="<?= h($phienBan('app/assets/app.js')) ?>"></script>
</body>
</html>
<?php
}

/** Thân page rỗng (P03): tiêu đề, phase sẽ làm nghiệp vụ, các nút hành động mẫu gắn chế độ bản quyền. */
function trang_rong(array $ctx, string $tieuDe, string $moTa, string $phase, array $nut): void
{
    ?>
<section class="card p-6 max-w-3xl">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <h1 class="font-extrabold text-xl text-pos-title"><?= h($tieuDe) ?></h1>
    <div class="flex gap-2"><?php foreach ($nut as [$cheDo, $nhan, $icon]) { echo nut_hanh_dong($ctx, $cheDo, $nhan, $icon); } ?></div>
  </div>
  <p class="mt-3 text-pos-muted"><?= h($moTa) ?></p>
  <p class="mt-4 pill pill-info"><i class="fa-solid fa-person-digging"></i>Nghiệp vụ của trang này có ở <?= h($phase) ?> — ngày làm việc đang chọn: <span class="mono"><?= h($ctx['ngay']) ?></span></p>
</section>
<?php
}
