<?php
declare(strict_types=1);

// Quản lý chủ máy — gộp nhiều máy (cũ + mới) của 1 khách hàng để phục vụ khôi phục backup.

require __DIR__ . '/_auth.php';
require_login();

$pdo = db();
$loi = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $ten = post_text('ten', 150);
    $ghiChu = post_text('ghi_chu', 500);

    if ($ten === '') {
        $loi = 'Tên chủ máy không được để trống';
    } else {
        if ($id > 0) {
            $pdo->prepare('UPDATE owners SET ten = ?, ghi_chu = ? WHERE id = ?')->execute([$ten, $ghiChu, $id]);
        } else {
            // created_at NOT NULL, không có DEFAULT → BẮT BUỘC ghi đích danh (thiếu = lỗi 1364, đo 2026-10-20).
            $pdo->prepare('INSERT INTO owners (ten, ghi_chu, created_at) VALUES (?, ?, NOW())')->execute([$ten, $ghiChu]);
        }
        header('Location: owners.php');
        exit;
    }
}

$rows = $pdo->query(
    'SELECT o.id, o.ten, o.ghi_chu, o.created_at,
            COUNT(m.id) AS so_may,
            GROUP_CONCAT(COALESCE(NULLIF(m.hostname, ""), SUBSTR(m.machine_id, 1, 8)) SEPARATOR ", ") AS cac_may
     FROM owners o LEFT JOIN machines m ON m.owner_id = o.id
     GROUP BY o.id ORDER BY o.ten'
)->fetchAll();

$tongChuMay = count($rows);
$tongMayLienKet = array_sum(array_column($rows, 'so_may'));

page_head('Quản lý Chủ máy — BigWIN Admin');
admin_header('owners.php');
?>

<div class="space-y-6" x-data="{ q: '', showAddForm: false }">
    <!-- Breadcrumb & Top Title -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">
                <a href="index.php" class="hover:text-pos-blue transition-colors">Dashboard</a>
                <span>/</span>
                <span class="text-slate-700 dark:text-slate-300">Quản lý Chủ máy</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-pos-blue/10 text-pos-blue dark:bg-pos-blue/20 dark:text-pos-light">
                    <i class="fa-solid fa-users-gear text-sm"></i>
                </span>
                Quản lý Chủ máy
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Gộp các thiết bị (máy cũ, máy mới) theo từng khách hàng để liên kết và đồng bộ sao lưu.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button @click="showAddForm = !showAddForm"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-pos-blue hover:bg-pos-hover rounded-xl shadow-sm hover:shadow transition-all duration-150">
                <i class="fa-solid" :class="showAddForm ? 'fa-xmark' : 'fa-plus'"></i>
                <span x-text="showAddForm ? 'Đóng form' : 'Thêm chủ máy mới'"></span>
            </button>
        </div>
    </div>

    <!-- Error Alert -->
    <?php if ($loi): ?>
    <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200 text-sm flex items-start gap-3">
        <i class="fa-solid fa-triangle-exclamation text-rose-500 mt-0.5 text-base"></i>
        <div>
            <span class="font-semibold">Lỗi thao tác:</span> <?= h($loi) ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Workflow Guide Banner -->
    <div class="p-4 rounded-2xl bg-gradient-to-r from-blue-50/80 via-indigo-50/50 to-slate-50/80 dark:from-slate-800/80 dark:via-slate-800/50 dark:to-slate-800/80 border border-blue-100/80 dark:border-slate-700/80 text-sm text-slate-600 dark:text-slate-300">
        <div class="flex items-start gap-3">
            <div class="w-8 h-8 rounded-xl bg-blue-600/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                <i class="fa-regular fa-lightbulb text-base"></i>
            </div>
            <div class="leading-relaxed">
                <strong class="font-semibold text-slate-900 dark:text-white">Quy trình chuyển đổi hoặc khôi phục máy khi mất:</strong>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-medium">
                        <span class="w-4 h-4 rounded-full bg-blue-100 dark:bg-blue-900/60 text-blue-600 dark:text-blue-400 text-[10px] flex items-center justify-center font-bold">1</span>
                        Gán máy MỚI vào cùng Chủ với máy cũ
                    </span>
                    <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-medium">
                        <span class="w-4 h-4 rounded-full bg-blue-100 dark:bg-blue-900/60 text-blue-600 dark:text-blue-400 text-[10px] flex items-center justify-center font-bold">2</span>
                        Duyệt máy mới (Trạng thái Active)
                    </span>
                    <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-medium">
                        <span class="w-4 h-4 rounded-full bg-blue-100 dark:bg-blue-900/60 text-blue-600 dark:text-blue-400 text-[10px] flex items-center justify-center font-bold">3</span>
                        Trên máy mới: <em>Hệ thống → Khôi phục từ máy chủ</em>
                    </span>
                    <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-medium text-rose-600 dark:text-rose-400">
                        <span class="w-4 h-4 rounded-full bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-400 text-[10px] flex items-center justify-center font-bold">4</span>
                        Thu hồi máy cũ
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Add Form Card -->
    <div x-show="showAddForm" x-collapse x-cloak class="p-6 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm transition-all">
        <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-user-plus text-pos-blue"></i>
            Thêm Chủ Máy Mới
        </h2>
        <form method="post" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
            <?= csrf_field() ?>
            <div class="md:col-span-5">
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                    Tên chủ máy / Đại lý <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 text-xs">
                        <i class="fa-solid fa-user"></i>
                    </span>
                    <input type="text" name="ten" placeholder="Vd: Anh Ba — Quận 5" required
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-pos-blue focus:border-pos-blue outline-none text-slate-800 dark:text-slate-200">
                </div>
            </div>
            <div class="md:col-span-5">
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                    Ghi chú / Số điện thoại
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 text-xs">
                        <i class="fa-solid fa-tag"></i>
                    </span>
                    <input type="text" name="ghi_chu" placeholder="Số điện thoại, địa chỉ, kênh liên hệ..."
                           class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-pos-blue focus:border-pos-blue outline-none text-slate-800 dark:text-slate-200">
                </div>
            </div>
            <div class="md:col-span-2">
                <button type="submit"
                        class="w-full py-2 px-4 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
                    <i class="fa-solid fa-check"></i>
                    Lưu mới
                </button>
            </div>
        </form>
    </div>

    <!-- Stats & Fast Filter Bar -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Tổng số chủ máy</p>
                <p class="text-2xl font-bold text-slate-900 dark:text-white mt-0.5"><?= number_format($tongChuMay) ?></p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/40 text-pos-blue flex items-center justify-center text-lg">
                <i class="fa-solid fa-user-group"></i>
            </div>
        </div>
        <div class="p-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Máy đã liên kết chủ</p>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-0.5"><?= number_format($tongMayLienKet) ?></p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg">
                <i class="fa-solid fa-desktop"></i>
            </div>
        </div>
        <div class="p-4 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center">
            <div class="relative w-full">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input type="text" x-model="q" placeholder="Lọc nhanh danh sách chủ..."
                       class="w-full pl-9 pr-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-pos-blue outline-none text-slate-800 dark:text-slate-200">
            </div>
        </div>
    </div>

    <!-- Data Table Card -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-4 w-12 text-center">ID</th>
                        <th class="py-3 px-4 min-w-[280px]">Chủ máy & Ghi chú (Chỉnh sửa nhanh)</th>
                        <th class="py-3 px-4 min-w-[220px]">Máy đã liên kết</th>
                        <th class="py-3 px-4 w-36">Ngày tạo</th>
                        <th class="py-3 px-4 w-28 text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="5" class="py-12 text-center text-slate-400 dark:text-slate-500">
                            <i class="fa-solid fa-users-slash text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                            Chưa có chủ máy nào trong hệ thống.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr x-show="q === '' || '<?= strtolower(addslashes($r['ten'] . ' ' . ($r['ghi_chu'] ?? '') . ' ' . ($r['cac_may'] ?? ''))) ?>'.includes(q.toLowerCase())"
                        class="hover:bg-slate-50/70 dark:hover:bg-slate-750 transition-colors">
                        <td class="py-3 px-4 text-center font-mono text-xs text-slate-400">
                            #<?= (int)$r['id'] ?>
                        </td>
                        <td class="py-3 px-4">
                            <form method="post" id="form-owner-<?= (int)$r['id'] ?>" class="space-y-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <div>
                                    <input type="text" name="ten" value="<?= h($r['ten']) ?>" required
                                           class="w-full px-2.5 py-1 text-sm font-semibold bg-transparent hover:bg-slate-100 dark:hover:bg-slate-900 focus:bg-white dark:focus:bg-slate-900 border border-transparent hover:border-slate-300 dark:hover:border-slate-600 focus:border-pos-blue rounded-lg outline-none text-slate-900 dark:text-white transition-all">
                                </div>
                                <div>
                                    <input type="text" name="ghi_chu" value="<?= h($r['ghi_chu'] ?? '') ?>" placeholder="Thêm ghi chú..."
                                           class="w-full px-2.5 py-1 text-xs text-slate-500 dark:text-slate-400 bg-transparent hover:bg-slate-100 dark:hover:bg-slate-900 focus:bg-white dark:focus:bg-slate-900 border border-transparent hover:border-slate-300 dark:hover:border-slate-600 focus:border-pos-blue rounded-lg outline-none transition-all">
                                </div>
                            </form>
                        </td>
                        <td class="py-3 px-4 align-top">
                            <div class="flex items-center gap-2 mb-1.5">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= (int)$r['so_may'] > 0 ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400' ?>">
                                    <i class="fa-solid fa-desktop text-[10px]"></i>
                                    <?= (int)$r['so_may'] ?> máy
                                </span>
                                <?php if ((int)$r['so_may'] > 0): ?>
                                <a href="index.php?q=<?= urlencode($r['ten']) ?>" class="text-[11px] text-pos-blue hover:underline">
                                    Xem trên lưới <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($r['cac_may'])): ?>
                                <p class="text-xs text-slate-500 dark:text-slate-400 font-mono leading-relaxed line-clamp-2" title="<?= h($r['cac_may']) ?>">
                                    <?= h($r['cac_may']) ?>
                                </p>
                            <?php else: ?>
                                <span class="text-xs text-slate-400 italic">Chưa gán máy nào</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            <i class="fa-regular fa-clock mr-1 text-[11px] text-slate-400"></i>
                            <?= h(date('d/m/Y H:i', strtotime($r['created_at']))) ?>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <button type="submit" form="form-owner-<?= (int)$r['id'] ?>"
                                    title="Lưu thay đổi thông tin chủ máy"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-pos-blue bg-pos-blue/10 hover:bg-pos-blue hover:text-white dark:bg-pos-blue/20 dark:hover:bg-pos-blue dark:text-pos-light rounded-lg transition-colors">
                                <i class="fa-solid fa-floppy-disk text-xs"></i>
                                Lưu
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
admin_footer();
