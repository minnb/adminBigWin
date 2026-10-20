<?php
declare(strict_types=1);

// Các phiên bản backup của 1 máy (giữ 30 bản mới nhất + bản cuối mỗi tháng).

require __DIR__ . '/_auth.php';
require_login();

$pdo = db();
$mid = (string)($_GET['machine'] ?? '');
if (!preg_match('/^[0-9a-f]{64}$/', $mid)) {
    http_response_code(400);
    exit('Mã machine không hợp lệ (yêu cầu chuỗi hex 64 ký tự)');
}

$st = $pdo->prepare('SELECT m.*, o.ten AS ten_chu FROM machines m LEFT JOIN owners o ON o.id = m.owner_id WHERE m.machine_id = ?');
$st->execute([$mid]);
$may = $st->fetch();

$st = $pdo->prepare('SELECT v.id, v.bo, v.hash, v.so_dong_json, v.kich_thuoc, v.app_version, v.created_at,
                            (SELECT COUNT(*) FROM backup_access_logs a WHERE a.version_id = v.id) AS so_lan_tai
                     FROM backup_versions v WHERE v.machine_id = ? ORDER BY v.id DESC');
$st->execute([$mid]);
$rows = $st->fetchAll();
$tenBo = [
    'cu_phap'    => 'Cú pháp cược',
    'khach_hang' => 'Khách hàng & hệ số'
];

$tongBanSaoLuu = count($rows);
$tongLuotTai = array_sum(array_column($rows, 'so_lan_tai'));
$banMoiNhat = $rows ? $rows[0]['created_at'] : null;

page_head('Sao lưu máy ' . ($may['hostname'] ?? 'Khách') . ' — BigWIN Admin');
admin_header('index.php');
?>

<div class="space-y-6">
    <!-- Breadcrumb & Top Bar -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">
                <a href="index.php" class="hover:text-pos-blue transition-colors">Dashboard</a>
                <span>/</span>
                <a href="index.php?q=<?= h(substr($mid, 0, 16)) ?>" class="hover:text-pos-blue transition-colors">Danh sách máy</a>
                <span>/</span>
                <span class="text-slate-700 dark:text-slate-300">Lịch sử sao lưu</span>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2.5">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                    <i class="fa-solid fa-cloud-arrow-down text-sm"></i>
                </span>
                Sao lưu của máy: <span class="text-pos-blue"><?= h($may['hostname'] ?? 'Chưa đặt tên máy') ?></span>
            </h1>
        </div>

        <div>
            <a href="index.php?q=<?= h(substr($mid, 0, 16)) ?>"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-750 shadow-sm transition-colors">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                Quay lại danh sách máy
            </a>
        </div>
    </div>

    <!-- Machine Overview Banner -->
    <div class="p-5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-center">
            <div class="md:col-span-6 space-y-2">
                <div class="flex items-center gap-3">
                    <span class="font-bold text-lg text-slate-900 dark:text-white">
                        <?= h($may['customer_name'] ?? 'Chưa ghi tên khách') ?>
                    </span>
                    <?php
                    $stt = $may['status'] ?? 'pending';
                    $badgeCls = match($stt) {
                        'active' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800',
                        'revoked' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border-rose-200 dark:border-rose-800',
                        default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800'
                    };
                    $sttTen = match($stt) {
                        'active' => 'Đang dùng',
                        'revoked' => 'Thu hồi',
                        default => 'Chờ duyệt'
                    };
                    ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border <?= $badgeCls ?>">
                        <?= $sttTen ?>
                    </span>
                </div>

                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <i class="fa-solid fa-user-tie"></i>
                    <span>Chủ máy:</span>
                    <strong class="text-slate-700 dark:text-slate-300"><?= h($may['ten_chu'] ?? '— Chưa gán chủ —') ?></strong>
                    <?php if (!empty($may['owner_id'])): ?>
                    <a href="owners.php" class="text-pos-blue hover:underline text-[11px]">(Đổi chủ)</a>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2" x-data="{ copied: false }">
                    <span class="text-xs text-slate-400 font-mono">MID:</span>
                    <code class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-xs font-mono select-all">
                        <?= h($mid) ?>
                    </code>
                    <button @click="navigator.clipboard.writeText('<?= $mid ?>'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="text-xs text-slate-400 hover:text-pos-blue transition-colors"
                            title="Sao chép Machine ID">
                        <i class="fa-regular" :class="copied ? 'fa-circle-check text-emerald-500' : 'fa-copy'"></i>
                    </button>
                </div>
            </div>

            <!-- Stats in banner -->
            <div class="md:col-span-6 grid grid-cols-3 gap-3 pt-3 md:pt-0 border-t md:border-t-0 md:border-l border-slate-100 dark:border-slate-700 md:pl-5">
                <div class="text-center md:text-left">
                    <p class="text-xs text-slate-400">Tổng bản sao lưu</p>
                    <p class="text-xl font-bold text-slate-900 dark:text-white mt-0.5"><?= number_format($tongBanSaoLuu) ?></p>
                </div>
                <div class="text-center md:text-left">
                    <p class="text-xs text-slate-400">Lượt tải về</p>
                    <p class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-0.5"><?= number_format($tongLuotTai) ?></p>
                </div>
                <div class="text-center md:text-left">
                    <p class="text-xs text-slate-400">Bản mới nhất</p>
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 mt-1.5">
                        <?= $banMoiNhat ? h(date('d/m/Y H:i', strtotime($banMoiNhat))) : '—' ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Backup Versions Table -->
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-700/80 flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-clock-rotate-left text-pos-blue"></i>
                Danh sách các phiên bản sao lưu (Tối đa 30 bản mới nhất + bản cuối mỗi tháng)
            </h2>
            <span class="text-xs text-slate-400">
                <?= count($rows) ?> phiên bản
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-4 w-12 text-center">#</th>
                        <th class="py-3 px-4 min-w-[160px]">Bộ dữ liệu</th>
                        <th class="py-3 px-4 min-w-[150px]">Thời gian sao lưu</th>
                        <th class="py-3 px-4 min-w-[200px]">Chi tiết nội dung</th>
                        <th class="py-3 px-4 w-28 text-right">Dung lượng</th>
                        <th class="py-3 px-4 w-28 text-center">Bản App</th>
                        <th class="py-3 px-4 w-28 text-center">Lượt tải</th>
                        <th class="py-3 px-4 w-32 text-center">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-sm">
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="8" class="py-12 text-center text-slate-400 dark:text-slate-500">
                            <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                            Máy này chưa có phiên bản sao lưu nào được tải lên máy chủ.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r):
                        $sd = json_decode($r['so_dong_json'], true) ?: [];
                        $boKey = $r['bo'];
                        $boTen = $tenBo[$boKey] ?? $boKey;
                        $boBadge = $boKey === 'cu_phap'
                            ? 'bg-purple-50 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300 border-purple-200 dark:border-purple-800'
                            : 'bg-blue-50 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 border-blue-200 dark:border-blue-800';
                        $boIcon = $boKey === 'cu_phap' ? 'fa-code' : 'fa-address-book';
                    ?>
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-750 transition-colors">
                        <td class="py-3 px-4 text-center font-mono text-xs text-slate-400">
                            <?= (int)$r['id'] ?>
                        </td>
                        <td class="py-3 px-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold border <?= $boBadge ?>">
                                <i class="fa-solid <?= $boIcon ?> text-[10px]"></i>
                                <?= h($boTen) ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap">
                            <i class="fa-regular fa-clock mr-1 text-slate-400"></i>
                            <?= h(date('d/m/Y H:i:s', strtotime($r['created_at']))) ?>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex flex-wrap gap-1.5">
                            <?php if ($sd): ?>
                                <?php foreach ($sd as $k => $v): ?>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-mono">
                                    <span class="text-slate-400"><?= h((string)$k) ?>:</span>
                                    <strong class="font-bold"><?= (int)$v ?></strong>
                                </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-xs text-slate-400 italic">Không có metadata</span>
                            <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-right font-mono text-xs text-slate-700 dark:text-slate-300 whitespace-nowrap">
                            <?= number_format((int)$r['kich_thuoc'] / 1024, 1) ?> KB
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                <?= h($r['app_version'] ?: 'v1.0') ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= (int)$r['so_lan_tai'] > 0 ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'text-slate-400' ?>">
                                <i class="fa-solid fa-download text-[10px]"></i>
                                <?= (int)$r['so_lan_tai'] ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <a href="backup_download.php?id=<?= (int)$r['id'] ?>"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-pos-blue hover:bg-pos-hover rounded-lg shadow-sm transition-colors">
                                <i class="fa-solid fa-file-arrow-down text-xs"></i>
                                Tải JSON
                            </a>
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
