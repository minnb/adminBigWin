<?php
declare(strict_types=1);

// Nhật ký "Tổng hợp kết quả" người dùng đã xem trên form Telegram — bản MỚI NHẤT theo (máy, ngày, khách).
// Đây là bảng người dùng ĐÃ XEM (tính từ lưới), không phải sổ sách đã lưu (R18).

require __DIR__ . '/_auth.php';
require_login();

$pdo = db();
const SO_DONG_TOI_DA = 300;

/** 'Y-m-d' có thật (checkdate — regex để lọt 2026-13-45) → giữ; sai/thiếu → $macDinh. */
function ngay_hop_le($giaTri, string $macDinh): string
{
    if (!is_string($giaTri) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $giaTri, $m)) {
        return $macDinh;
    }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $giaTri : $macDinh;
}

$tuMacDinh = date('Y-m-d', strtotime('-6 days'));
$denMacDinh = date('Y-m-d');
$tu = ngay_hop_le($_GET['tu'] ?? null, $tuMacDinh);
$den = ngay_hop_le($_GET['den'] ?? null, $denMacDinh);
if ($tu > $den) {
    [$tu, $den] = [$den, $tu];  // nhập ngược khoảng → tự đảo, không trả danh sách rỗng câm
}
$q = trim((string)($_GET['q'] ?? ''));

$where = 'u.ngay BETWEEN ? AND ?';
$args = [$tu, $den];
if ($q !== '') {
    $where .= ' AND (m.hostname LIKE ? OR m.customer_name LIKE ? OR u.ten_khach LIKE ?)';
    $like = mau_like($q);
    array_push($args, $like, $like, $like);
}
$joinMay = 'LEFT JOIN machines m ON m.machine_id = u.machine_id';
$from = "FROM usage_summaries u $joinMay WHERE $where";

// Số liệu tổng tính trên TOÀN BỘ khoảng lọc — cộng trên danh sách đã LIMIT là sai tiền câm lặng.
// JOIN machines (unique machine_id) không đổi COUNT/SUM — chỉ cần khi có ô tìm kiếm.
$st = $pdo->prepare("SELECT COUNT(*) AS so_bang, COALESCE(SUM(u.tong_thanh_tien), 0) AS tong,
                            COUNT(DISTINCT u.machine_id) AS so_may, COALESCE(SUM(u.so_lan_xem), 0) AS luot_xem
                     FROM usage_summaries u " . ($q !== '' ? $joinMay : '') . " WHERE $where");
$st->execute($args);
$tongHop = $st->fetch();
$soBang = (int)$tongHop['so_bang'];
$tong = (int)$tongHop['tong'];
$soMayPhatSinh = (int)$tongHop['so_may'];
$tongLuotXem = (int)$tongHop['luot_xem'];

$st = $pdo->prepare("SELECT u.*, m.hostname, m.customer_name $from
                     ORDER BY u.ngay DESC, u.xem_cuoi DESC LIMIT " . SO_DONG_TOI_DA);
$st->execute($args);
$rows = $st->fetchAll();

function tien(int $v): string
{
    return number_format($v, 0, ',', '.');
}

page_head('Nhật ký sử dụng — BigWIN Admin');
admin_header('usage.php');
?>

<div class="space-y-6">
    <!-- Breadcrumb & Title -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">
                <a href="index.php" class="hover:text-pos-blue transition-colors">Dashboard</a>
                <span>/</span>
                <span class="text-slate-700 dark:text-slate-300">Nhật ký sử dụng</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                    <i class="fa-solid fa-receipt text-sm"></i>
                </span>
                Nhật ký Tổng hợp Cược Telegram
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Thống kê các bảng người dùng đã mở xem trên form Telegram (tính tức thời từ lưới tin nhắn).
            </p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="p-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                    Từ ngày
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                        <i class="fa-regular fa-calendar"></i>
                    </span>
                    <input type="date" name="tu" value="<?= h($tu) ?>"
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-pos-blue outline-none text-slate-800 dark:text-slate-200">
                </div>
            </div>

            <div class="md:col-span-3">
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                    Đến ngày
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                        <i class="fa-regular fa-calendar"></i>
                    </span>
                    <input type="date" name="den" value="<?= h($den) ?>"
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-pos-blue outline-none text-slate-800 dark:text-slate-200">
                </div>
            </div>

            <div class="md:col-span-4">
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                    Tìm kiếm nhanh
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>
                    <input type="text" name="q" value="<?= h($q) ?>" placeholder="Tên máy, khách hàng, khách cược..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-pos-blue outline-none text-slate-800 dark:text-slate-200">
                </div>
            </div>

            <div class="md:col-span-2 flex items-center gap-2">
                <button type="submit"
                        class="w-full py-2 px-4 text-sm font-semibold text-white bg-pos-blue hover:bg-pos-hover rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
                    <i class="fa-solid fa-filter text-xs"></i>
                    Lọc
                </button>
                <?php if ($q !== '' || $tu !== $tuMacDinh || $den !== $denMacDinh): ?>
                <a href="usage.php" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl border border-slate-200 dark:border-slate-700 transition-colors" title="Đặt lại bộ lọc">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Số bảng xem trong kỳ</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white mt-0.5"><?= number_format($soBang) ?></p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/40 text-pos-blue flex items-center justify-center text-lg">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
        </div>

        <div class="p-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Tổng thành tiền</p>
                <p class="text-xl font-bold <?= $tong < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' ?> mt-0.5">
                    <?= tien($tong) ?> đ
                </p>
            </div>
            <div class="w-10 h-10 rounded-xl <?= $tong < 0 ? 'bg-rose-50 dark:bg-rose-900/40 text-rose-600' : 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600' ?> flex items-center justify-center text-lg">
                <i class="fa-solid fa-coins"></i>
            </div>
        </div>

        <div class="p-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Máy phát sinh xem</p>
                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-0.5"><?= number_format($soMayPhatSinh) ?></p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-desktop"></i>
            </div>
        </div>

        <div class="p-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Tổng lượt xem</p>
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-0.5"><?= number_format($tongLuotXem) ?></p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/40 text-amber-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-eye"></i>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-4 w-28">Ngày cược</th>
                        <th class="py-3 px-4 min-w-[160px]">Điểm bán / Máy</th>
                        <th class="py-3 px-4 min-w-[140px]">Khách cược</th>
                        <th class="py-3 px-4 min-w-[160px] text-right">Thành tiền</th>
                        <th class="py-3 px-4 w-24 text-center">Lượt xem</th>
                        <th class="py-3 px-4 w-36">Xem lần cuối</th>
                        <th class="py-3 px-4 min-w-[280px]">Bảng chi tiết & Số trúng</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400 dark:text-slate-500">
                            <i class="fa-regular fa-calendar-xmark text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                            Không tìm thấy dữ liệu nhật ký nào trong khoảng thời gian đã chọn.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r):
                        $pl = json_decode($r['payload_json'], true) ?: [];
                        $tt = (int)$r['tong_thanh_tien'];
                    ?>
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-750 transition-colors">
                        <td class="py-3 px-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                <i class="fa-regular fa-calendar text-[10px]"></i>
                                <?= h(date('d/m/Y', strtotime($r['ngay']))) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <strong class="font-semibold text-slate-900 dark:text-white text-xs block">
                                <?= h($r['hostname'] ?? 'Khách') ?>
                            </strong>
                            <span class="text-xs text-slate-400 block truncate max-w-[150px]">
                                <?= h($r['customer_name'] ?? '—') ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-regular fa-circle-user text-slate-400 text-xs"></i>
                                <span class="font-medium text-slate-800 dark:text-slate-200 text-xs">
                                    <?= h($r['ten_khach']) ?>
                                </span>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-right whitespace-nowrap">
                            <span class="font-bold text-sm <?= $tt < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' ?>">
                                <?= ($tt > 0 ? '+' : '') . tien($tt) ?> đ
                            </span>
                            <span class="block text-[11px] <?= $tt < 0 ? 'text-rose-500' : 'text-emerald-500' ?>">
                                <?= $tt < 0 ? 'Phải trả khách' : 'Phải thu khách' ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center font-mono text-xs text-slate-600 dark:text-slate-300">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                <?= (int)$r['so_lan_xem'] ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            <i class="fa-regular fa-clock mr-1 text-[11px]"></i>
                            <?= h(date('d/m H:i', strtotime($r['xem_cuoi']))) ?>
                        </td>
                        <td class="py-3 px-4">
                            <details class="group bg-slate-50 dark:bg-slate-900/60 rounded-xl border border-slate-200 dark:border-slate-700/80 p-2 text-xs">
                                <summary class="cursor-pointer font-semibold text-pos-blue hover:underline flex items-center justify-between select-none list-none">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fa-solid fa-list-check text-xs"></i>
                                        Chi tiết <?= count($pl['rows'] ?? []) ?> loại cược
                                    </span>
                                    <i class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform group-open:rotate-180"></i>
                                </summary>
                                <div class="mt-2.5 pt-2 border-t border-slate-200 dark:border-slate-700/80 space-y-2">
                                    <table class="w-full text-left text-[11px] border-collapse">
                                        <thead>
                                            <tr class="text-slate-400 border-b border-slate-200 dark:border-slate-750 font-medium">
                                                <th class="pb-1">Loại</th>
                                                <th class="pb-1">Đánh</th>
                                                <th class="pb-1 text-right">Tiền</th>
                                                <th class="pb-1 text-right">Trúng</th>
                                                <th class="pb-1 text-right">CK</th>
                                                <th class="pb-1 text-right">Thành tiền</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                        <?php foreach (($pl['rows'] ?? []) as $d): ?>
                                            <tr>
                                                <td class="py-1 font-semibold text-slate-700 dark:text-slate-300"><?= h((string)($d['loai'] ?? '')) ?></td>
                                                <td class="py-1 text-slate-500 dark:text-slate-400 max-w-[100px] truncate" title="<?= h((string)($d['danh'] ?? '')) ?>"><?= h((string)($d['danh'] ?? '')) ?></td>
                                                <td class="py-1 text-right font-mono"><?= tien((int)($d['tien'] ?? 0)) ?></td>
                                                <td class="py-1 text-right font-mono text-emerald-600 dark:text-emerald-400"><?= tien((int)($d['trung'] ?? 0)) ?></td>
                                                <td class="py-1 text-right font-mono text-slate-400"><?= tien((int)($d['ck'] ?? 0)) ?></td>
                                                <td class="py-1 text-right font-mono font-semibold"><?= tien((int)($d['thanh_tien'] ?? 0)) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>

                                    <?php if (!empty($pl['so_trung'])): ?>
                                    <div class="mt-2 p-2 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-[11px]">
                                        <div class="font-bold text-amber-800 dark:text-amber-300 flex items-center gap-1 mb-1">
                                            <i class="fa-solid fa-trophy text-[10px] text-amber-600"></i>
                                            Số trúng thưởng:
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                        <?php foreach ($pl['so_trung'] as $s): ?>
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200 font-mono font-semibold text-[10px]">
                                                <?= h((string)($s['loai'] ?? '')) ?> <?= h((string)($s['so'] ?? '')) ?>
                                            </span>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($soBang > count($rows)): ?>
        <p class="px-4 py-3 text-xs font-semibold text-amber-700 dark:text-amber-300 border-t border-slate-100 dark:border-slate-700">
            Đang hiển thị <?= count($rows) ?>/<?= number_format($soBang) ?> bảng mới nhất — số liệu tổng ở trên tính trên đủ <?= number_format($soBang) ?> bảng. Thu hẹp khoảng ngày để xem hết.
        </p>
        <?php endif; ?>
    </div>
</div>

<?php
admin_footer();
