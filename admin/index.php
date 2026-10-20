<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
require_login();

$pdo = db();
$statuses = [
    'pending' => 'Chờ duyệt',
    'active'  => 'Đang dùng',
    'revoked' => 'Thu hồi'
];

const KHOA_BO_LOC = ['status' => 1, 'q' => 1, 'tg_multi' => 1];  // tham số GET được giữ qua redirect / phân trang

// ── Hành động POST: 1 phần tử = 1 hành động (thêm hành động mới = thêm 1 dòng, không sửa luồng) ──
// Mỗi hàm nhận ($pdo, id máy) và tự kiểm input riêng của nó; input sai = không làm gì.
$HANH_DONG = [
    'set_status' => function (PDO $pdo, int $id) use ($statuses): void {
        $status = (string)($_POST['status'] ?? '');
        if (!isset($statuses[$status])) {
            return;
        }
        $pdo->prepare('UPDATE machines SET status = ? WHERE id = ?')->execute([$status, $id]);
    },
    'delete' => function (PDO $pdo, int $id): void {
        // Xoá máy rác (spam API). Máy THẬT bị xoá sẽ tự đăng ký lại + dùng thử lại → máy thật dùng Thu hồi.
        $st = $pdo->prepare('SELECT machine_id FROM machines WHERE id = ?');
        $st->execute([$id]);
        $mid = $st->fetchColumn();
        if ($mid === false) {
            return;
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM check_logs WHERE machine_id = ?')->execute([$mid]);
        $pdo->prepare('DELETE FROM machines WHERE id = ?')->execute([$id]);
        $pdo->commit();
    },
    'set_owner' => function (PDO $pdo, int $id): void {
        // owner_id quyết định máy nào xem được backup của nhau (lib/api.php::dieu_kien_quyen_xem):
        // CẤM gán chủ không tồn tại — 2 máy cùng "chủ ma" sẽ thấy backup của nhau.
        $ownerId = (int)($_POST['owner_id'] ?? 0);
        if ($ownerId > 0) {
            $st = $pdo->prepare('SELECT 1 FROM owners WHERE id = ?');
            $st->execute([$ownerId]);
            if (!$st->fetchColumn()) {
                return;
            }
        }
        $pdo->prepare('UPDATE machines SET owner_id = ? WHERE id = ?')->execute([$ownerId > 0 ? $ownerId : null, $id]);
    },
    'reset_secret' => function (PDO $pdo, int $id): void {
        $pdo->prepare('UPDATE machines SET device_secret_hash = NULL, secret_mismatch_at = NULL WHERE id = ?')
            ->execute([$id]);
    },
    'save_info' => function (PDO $pdo, int $id): void {
        $pdo->prepare('UPDATE machines SET customer_name = ?, note = ? WHERE id = ?')->execute([
            post_text('customer_name', 150),
            post_text('note', 500),
            $id,
        ]);
    },
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $hanhDong = $HANH_DONG[(string)($_POST['action'] ?? '')] ?? null;
    if ($hanhDong !== null) {
        $hanhDong($pdo, (int)($_POST['id'] ?? 0));
    }
    header('Location: index.php?' . http_build_query(array_intersect_key($_GET, KHOA_BO_LOC + ['page' => 1])));
    exit;
}

// ── Bộ lọc ──
$fStatus = isset($statuses[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$q = trim((string)($_GET['q'] ?? ''));
$tgMulti = !empty($_GET['tg_multi']);

$where = [];
$args = [];
if ($fStatus !== '') {
    $where[] = 'm.status = ?';
    $args[] = $fStatus;
}
if ($q !== '') {
    $where[] = '(m.hostname LIKE ? OR m.customer_name LIKE ? OR m.tg_username LIKE ? OR m.tg_name LIKE ? OR m.machine_id LIKE ?)';
    $like = mau_like($q);
    array_push($args, $like, $like, $like, $like, $like);
}
if ($tgMulti) {
    $where[] = 'tgc.so_may > 1';
}

$dieuKien = $where ? ' WHERE ' . implode(' AND ', $where) : '';
// Bảng gom nhóm theo tg_id quét TOÀN BỘ machines — câu COUNT chỉ cần nó khi đang lọc "Telegram >1 máy".
$joinTg = ' LEFT JOIN (SELECT tg_id, COUNT(*) AS so_may FROM machines WHERE tg_id IS NOT NULL GROUP BY tg_id) tgc
               ON tgc.tg_id = m.tg_id';
$from = ' FROM machines m' . $joinTg . ' LEFT JOIN owners o ON o.id = m.owner_id' . $dieuKien;

// ── Phân trang ──
const SO_DONG_MOI_TRANG = 50;
$st = $pdo->prepare('SELECT COUNT(*) FROM machines m' . ($tgMulti ? $joinTg : '') . $dieuKien);
$st->execute($args);
$tongDong = (int)$st->fetchColumn();
$soTrang = max(1, (int)ceil($tongDong / SO_DONG_MOI_TRANG));

$page = is_string($_GET['page'] ?? null) ? (int)$_GET['page'] : 1;
$page = min(max(1, $page), $soTrang);

$st = $pdo->prepare(
    'SELECT m.*, COALESCE(tgc.so_may, 0) AS tg_so_may,
            UNIX_TIMESTAMP(m.first_seen) AS first_seen_ts,
            o.ten AS ten_chu,
            (SELECT MAX(b.created_at) FROM backup_versions b WHERE b.machine_id = m.machine_id) AS backup_cuoi'
    . $from
    . " ORDER BY FIELD(m.status, 'pending', 'active', 'revoked'), m.last_seen DESC LIMIT ? OFFSET ?"
);
$i = 1;
foreach ($args as $a) {
    $st->bindValue($i++, $a, PDO::PARAM_STR);
}
$st->bindValue($i++, SO_DONG_MOI_TRANG, PDO::PARAM_INT);
$st->bindValue($i, ($page - 1) * SO_DONG_MOI_TRANG, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

function link_trang(int $p): string
{
    $q = array_intersect_key($_GET, KHOA_BO_LOC);
    $q['page'] = $p;
    return '?' . http_build_query($q);
}

$counts = $pdo->query('SELECT status, COUNT(*) c FROM machines GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$owners = $pdo->query('SELECT id, ten FROM owners ORDER BY ten')->fetchAll(PDO::FETCH_KEY_PAIR);

$totalAll = array_sum($counts);
$countActive = (int)($counts['active'] ?? 0);
$countPending = (int)($counts['pending'] ?? 0);
$countRevoked = (int)($counts['revoked'] ?? 0);

page_head('Quản lý máy & Bản quyền — BigWIN License');
admin_header('index.php');
?>

<!-- 1. Header & Breadcrumb -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <nav class="flex text-xs text-slate-400 mb-1.5" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 sm:space-x-2">
                <li><a href="index.php" class="hover:text-primary-600 dark:hover:text-primary-400">Trang chủ</a></li>
                <li><i class="fa-solid fa-chevron-right text-[10px] mx-1"></i></li>
                <li class="font-semibold text-slate-700 dark:text-slate-300">Quản lý máy & Bản quyền</li>
            </ol>
        </nav>
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Danh Sách Thiết Bị Cài Đặt
        </h1>
    </div>
</div>

<!-- 2. Stats Cards (4 Thẻ thống kê máy) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
    <!-- Thẻ 1: Tổng số máy -->
    <a href="index.php" class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm hover:shadow-md transition group">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Tổng số máy</span>
            <span class="w-10 h-10 rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400 flex items-center justify-center text-base group-hover:scale-110 transition">
                <i class="fa-solid fa-laptop"></i>
            </span>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold text-slate-900 dark:text-white"><?= number_format($totalAll) ?></span>
            <span class="text-xs text-slate-400 font-medium">thiết bị</span>
        </div>
        <div class="mt-3 text-xs text-slate-400">
            Tất cả máy từng kết nối tới API
        </div>
    </a>

    <!-- Thẻ 2: Đang dùng (Active) -->
    <a href="index.php?status=active" class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm hover:shadow-md transition group">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Đang dùng (Active)</span>
            <span class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400 flex items-center justify-center text-base group-hover:scale-110 transition">
                <i class="fa-solid fa-circle-check"></i>
            </span>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400"><?= number_format($countActive) ?></span>
            <span class="text-xs text-slate-400 font-medium">máy duyệt</span>
        </div>
        <div class="mt-3 flex items-center text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
            <i class="fa-solid fa-shield text-xs mr-1.5"></i> Bản quyền chính thức
        </div>
    </a>

    <!-- Thẻ 3: Chờ duyệt / Dùng thử (Pending) -->
    <a href="index.php?status=pending" class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm hover:shadow-md transition group">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Chờ duyệt / Thử</span>
            <span class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400 flex items-center justify-center text-base group-hover:scale-110 transition">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </span>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold text-amber-600 dark:text-amber-400"><?= number_format($countPending) ?></span>
            <span class="text-xs text-slate-400 font-medium">chờ xử lý</span>
        </div>
        <div class="mt-3 flex items-center text-xs text-amber-600 dark:text-amber-400 font-semibold">
            <i class="fa-solid fa-hourglass-half mr-1.5"></i> 7 ngày dùng thử
        </div>
    </a>

    <!-- Thẻ 4: Đã thu hồi (Revoked) -->
    <a href="index.php?status=revoked" class="bg-white dark:bg-slate-900 p-5 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm hover:shadow-md transition group">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Bị thu hồi (Revoked)</span>
            <span class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400 flex items-center justify-center text-base group-hover:scale-110 transition">
                <i class="fa-solid fa-ban"></i>
            </span>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold text-rose-600 dark:text-rose-400"><?= number_format($countRevoked) ?></span>
            <span class="text-xs text-slate-400 font-medium">đã khóa</span>
        </div>
        <div class="mt-3 flex items-center text-xs text-rose-600 dark:text-rose-400 font-semibold">
            <i class="fa-solid fa-lock text-xs mr-1.5"></i> Khóa truy cập app
        </div>
    </a>
</div>

<!-- 3. Bảng Dữ Liệu Máy Khách -->
<div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden">
    <!-- Toolbar: Tabs + Search Form -->
    <div class="p-5 border-b border-slate-100 dark:border-slate-800/80 flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
        <!-- Status Tabs (Pills) -->
        <div class="flex flex-wrap items-center gap-2">
            <a href="index.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $fStatus === '' && !$tgMulti ? 'bg-primary-600 text-white shadow-sm shadow-primary-500/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' ?>">
                Tất cả (<?= $totalAll ?>)
            </a>
            <?php foreach ($statuses as $k => $label): 
                $cnt = (int)($counts[$k] ?? 0);
                $isOn = ($fStatus === $k && !$tgMulti);
            ?>
            <a href="index.php?status=<?= $k ?>" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $isOn ? 'bg-primary-600 text-white shadow-sm shadow-primary-500/20' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' ?>">
                <?= $label ?> (<?= $cnt ?>)
            </a>
            <?php endforeach; ?>
            <a href="index.php?tg_multi=1" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $tgMulti ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300' ?>">
                <i class="fa-brands fa-telegram mr-1"></i> Telegram > 1 máy
            </a>
        </div>

        <!-- Search Input Form -->
        <form method="get" class="flex items-center gap-2">
            <?php if ($fStatus): ?><input type="hidden" name="status" value="<?= h($fStatus) ?>"><?php endif; ?>
            <?php if ($tgMulti): ?><input type="hidden" name="tg_multi" value="1"><?php endif; ?>
            <div class="relative w-full sm:w-80">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i>
                <input 
                    name="q" 
                    value="<?= h($q) ?>" 
                    placeholder="Tìm tên máy, khách, Telegram, mã..." 
                    class="w-full pl-9 pr-3.5 py-2 text-xs rounded-2xl bg-slate-50 border border-slate-200 dark:bg-slate-800 dark:border-slate-700/80 text-slate-800 dark:text-white placeholder-slate-400 outline-none focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 transition"
                >
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-900 text-white dark:bg-primary-600 dark:hover:bg-primary-700 rounded-2xl text-xs font-bold transition">
                Tìm
            </button>
            <?php if ($q !== '' || $fStatus !== '' || $tgMulti): ?>
            <a href="index.php" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-xs font-semibold" title="Bỏ lọc">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table Responsive -->
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/75 dark:bg-slate-800/40 text-slate-400 uppercase font-bold text-[11px] tracking-wider">
                    <th class="py-3.5 px-4">Trạng thái</th>
                    <th class="py-3.5 px-4">Thiết bị & Mã máy</th>
                    <th class="py-3.5 px-4">Khách hàng & Ghi chú</th>
                    <th class="py-3.5 px-4">Telegram khách</th>
                    <th class="py-3.5 px-4">Phiên bản & IP</th>
                    <th class="py-3.5 px-4">Lần cuối online</th>
                    <th class="py-3.5 px-4 text-right">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if (!$rows): ?>
                <tr>
                    <td colspan="7" class="py-12 text-center text-slate-400">
                        <i class="fa-solid fa-inbox text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                        Không tìm thấy máy nào phù hợp với bộ lọc.
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($rows as $r): 
                    $st = $r['status'];
                    $stColor = match($st) {
                        'active'  => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-500/30',
                        'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-400 dark:ring-amber-500/30',
                        'revoked' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-400 dark:ring-rose-500/30',
                        default   => 'bg-slate-100 text-slate-700'
                    };
                    $dotColor = match($st) {
                        'active'  => 'bg-emerald-500',
                        'pending' => 'bg-amber-500 animate-pulse',
                        'revoked' => 'bg-rose-500',
                        default   => 'bg-slate-400'
                    };
                ?>
                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40 transition-colors">
                    <!-- 1. Trạng thái -->
                    <td class="py-3.5 px-4 align-top">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-bold text-[11px] ring-1 <?= $stColor ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $dotColor ?>"></span>
                            <?= h($statuses[$st] ?? $st) ?>
                        </span>
                        <?php if ($st === 'pending'): ?>
                        <div class="mt-1 text-[10px] font-semibold text-slate-400">
                            <?php $tsDau = (int)$r['first_seen_ts']; if (trang_thai_hieu_luc($st, $tsDau, time()) === 'trial'): ?>
                                <span class="text-amber-600 dark:text-amber-400 font-bold">
                                    <i class="fa-regular fa-clock"></i> Còn <?= (int)ceil((het_han_dung_thu($tsDau) - time()) / 86400) ?> ngày thử
                                </span>
                            <?php else: ?>
                                <span class="text-rose-500 font-bold">
                                    <i class="fa-solid fa-triangle-exclamation"></i> Hết hạn (Đã chặn)
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- 2. Thiết bị & Mã máy -->
                    <td class="py-3.5 px-4 align-top space-y-1">
                        <div class="font-extrabold text-slate-900 dark:text-white flex items-center gap-1.5">
                            <i class="fa-solid fa-display text-slate-400"></i>
                            <span><?= h($r['hostname']) ?></span>
                        </div>
                        
                        <!-- Machine ID + Copy -->
                        <div class="flex items-center gap-1.5">
                            <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[10px] font-mono text-slate-600 dark:text-slate-300" title="<?= h($r['machine_id']) ?>">
                                <?= h(substr($r['machine_id'], 0, 16)) ?>…
                            </code>
                            <button 
                                onclick="navigator.clipboard.writeText('<?= h($r['machine_id']) ?>'); alert('Đã sao chép mã máy!')" 
                                class="text-slate-400 hover:text-primary-600 text-[11px]" 
                                title="Sao chép toàn bộ mã máy"
                            >
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                        
                        <div class="text-[10px] text-slate-400">
                            Gặp lần đầu: <?= h(substr((string)$r['first_seen'], 0, 10)) ?>
                        </div>

                        <!-- Gán chủ máy -->
                        <form method="post" class="pt-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_owner">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <select 
                                name="owner_id" 
                                onchange="this.form.submit()" 
                                class="text-[11px] py-1 px-2 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 outline-none max-w-[180px]"
                                title="Gán chủ sở hữu để phục hồi bản sao lưu giữa các máy"
                            >
                                <option value="0">— Chọn chủ máy —</option>
                                <?php foreach ($owners as $oid => $oten): ?>
                                <option value="<?= (int)$oid ?>" <?= (int)$r['owner_id'] === (int)$oid ? 'selected' : '' ?>>
                                    <?= h($oten) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>

                        <!-- Cloud Backup Status -->
                        <div class="text-[10px] pt-0.5">
                            <?php if ($r['backup_cuoi']): ?>
                                <a href="backups.php?machine=<?= h($r['machine_id']) ?>" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                                    <i class="fa-solid fa-cloud-arrow-up text-xs mr-1"></i> Backup: <?= h($r['backup_cuoi']) ?>
                                </a>
                                <?php if ($r['status'] === 'active' && strtotime($r['backup_cuoi']) < time() - 7 * 86400): ?>
                                    <span class="text-rose-500 font-bold block">⚠️ Quá 7 ngày chưa backup</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-slate-400"><i class="fa-solid fa-cloud text-xs mr-1"></i> Chưa có backup</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($r['secret_mismatch_at']): ?>
                        <div class="text-[10px] text-rose-600 dark:text-rose-400 font-bold pt-1">
                            ⚠️ Khóa máy lệch từ: <?= h($r['secret_mismatch_at']) ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- 3. Khách hàng & Ghi chú -->
                    <td class="py-3.5 px-4 align-top">
                        <form method="post" class="space-y-1.5 w-48 sm:w-56">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save_info">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input 
                                name="customer_name" 
                                value="<?= h($r['customer_name'] ?? '') ?>" 
                                placeholder="Tên khách hàng..." 
                                class="w-full px-2.5 py-1 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 text-slate-800 dark:text-white placeholder-slate-400 outline-none focus:border-primary-500 transition"
                            >
                            <div class="flex items-center gap-1.5">
                                <input 
                                    name="note" 
                                    value="<?= h($r['note'] ?? '') ?>" 
                                    placeholder="Ghi chú (sđt, vị trí...)" 
                                    class="flex-1 px-2.5 py-1 text-xs rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 text-slate-800 dark:text-white placeholder-slate-400 outline-none focus:border-primary-500 transition"
                                >
                                <button type="submit" class="p-1.5 text-primary-600 hover:text-primary-700 bg-primary-50 dark:bg-primary-950/60 rounded-xl" title="Lưu thông tin">
                                    <i class="fa-solid fa-floppy-disk text-xs"></i>
                                </button>
                            </div>
                        </form>
                    </td>

                    <!-- 4. Telegram -->
                    <td class="py-3.5 px-4 align-top space-y-1">
                        <?php if ($r['tg_username'] || $r['tg_name'] || $r['tg_id']): ?>
                            <?php if ($r['tg_username']): ?>
                                <a href="https://t.me/<?= h($r['tg_username']) ?>" target="_blank" class="inline-flex items-center gap-1 text-primary-600 dark:text-primary-400 font-bold hover:underline">
                                    <i class="fa-brands fa-telegram text-sm text-[#0088cc]"></i> @<?= h($r['tg_username']) ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($r['tg_name']): ?>
                                <div class="font-medium text-slate-700 dark:text-slate-300"><?= h($r['tg_name']) ?></div>
                            <?php endif; ?>
                            <?php if ($r['tg_id']): ?>
                                <div class="text-[10px] font-mono text-slate-400">ID: <?= h($r['tg_id']) ?></div>
                            <?php endif; ?>
                            <?php if ((int)$r['tg_so_may'] > 1): ?>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400 font-bold text-[10px]">
                                    ⚠️ Trùng <?= (int)$r['tg_so_may'] ?> máy
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-slate-400 italic">Chưa đăng nhập TG</span>
                        <?php endif; ?>
                    </td>

                    <!-- 5. Phiên bản & IP -->
                    <td class="py-3.5 px-4 align-top space-y-1">
                        <span class="inline-block px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 font-mono font-bold text-[10px] text-slate-600 dark:text-slate-300">
                            v<?= h($r['app_version'] ?: '2.0.0') ?>
                        </span>
                        <div class="font-mono text-[11px] text-slate-500"><?= h($r['last_ip'] ?: '—') ?></div>
                    </td>

                    <!-- 6. Lần cuối online -->
                    <td class="py-3.5 px-4 align-top text-slate-600 dark:text-slate-300 font-medium whitespace-nowrap">
                        <?= h($r['last_seen'] ?: '—') ?>
                    </td>

                    <!-- 7. Thao tác -->
                    <td class="py-3.5 px-4 align-top text-right space-y-1.5 whitespace-nowrap">
                        <!-- Nút đổi Status -->
                        <div class="flex items-center justify-end gap-1.5">
                            <?php if ($st !== 'active'): ?>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="status" value="active">
                                <button type="submit" class="px-2.5 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] transition shadow-sm">
                                    Duyệt
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if ($st !== 'revoked'): ?>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="status" value="revoked">
                                <button type="submit" class="px-2.5 py-1 rounded-xl bg-rose-50 text-rose-700 hover:bg-rose-100 dark:bg-rose-950/50 dark:text-rose-300 font-bold text-[11px] transition">
                                    Thu hồi
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if ($st !== 'pending'): ?>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_status">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="status" value="pending">
                                <button type="submit" class="px-2.5 py-1 rounded-xl bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-950/50 dark:text-amber-300 font-bold text-[11px] transition">
                                    Về chờ
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>

                        <!-- Nút Reset Khóa & Xóa -->
                        <div class="flex items-center justify-end gap-1.5 pt-1">
                            <?php if ($r['device_secret_hash'] !== null): /* có khoá là cho đặt lại — khách cài lại Windows có thể chưa kịp sinh cờ lệch */ ?>
                            <form method="post" class="inline" onsubmit="return confirm('Cấp lại khóa máy này?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="reset_secret">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="p-1.5 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950 rounded-lg text-xs" title="Đặt lại khóa máy">
                                    <i class="fa-solid fa-key"></i> Reset khóa
                                </button>
                            </form>
                            <?php endif; ?>

                            <form method="post" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa thiết bị này? Máy thật sẽ đăng ký lại như máy mới.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg text-xs transition" title="Xóa máy">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Phân trang (Pagination) -->
    <?php if ($soTrang > 1): ?>
    <div class="p-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
        <span>Hiển thị trang <strong><?= $page ?></strong> / <strong><?= $soTrang ?></strong> (Tổng số <strong><?= number_format($tongDong) ?></strong> máy)</span>
        <div class="flex items-center gap-1.5">
            <?php if ($page > 1): ?>
                <a href="<?= h(link_trang($page - 1)) ?>" class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                    <i class="fa-solid fa-angle-left mr-1"></i> Trước
                </a>
            <?php endif; ?>

            <span class="px-3 py-1.5 rounded-xl bg-primary-600 text-white font-bold">
                <?= $page ?>
            </span>

            <?php if ($page < $soTrang): ?>
                <a href="<?= h(link_trang($page + 1)) ?>" class="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                    Sau <i class="fa-solid fa-angle-right ml-1"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- 4. Ghi Chú Nghiệp Vụ Bản Quyền -->
<div class="p-4 rounded-3xl bg-primary-50/50 dark:bg-primary-950/20 border border-primary-200/50 dark:border-primary-900/40 text-xs text-slate-600 dark:text-slate-400 flex items-start gap-3">
    <i class="fa-solid fa-circle-info text-primary-600 dark:text-primary-400 text-base mt-0.5 flex-shrink-0"></i>
    <div class="space-y-1">
        <p><strong>Cơ chế bản quyền BigWIN:</strong> Máy mới cài đặt được cấp dùng thử 7 ngày tự động tính từ lần đầu kết nối. Quá hạn 7 ngày nếu chưa được Admin duyệt thì ứng dụng sẽ tự động khóa.</p>
        <p>Máy bị thu hồi (Revoked) sẽ bị khóa ngay ở lần kiểm tra tiếp theo (mỗi khi mở app hoặc định kỳ 6 giờ/lần). Khi mất mạng, máy đã duyệt vẫn có thể sử dụng offline an toàn tối đa 7 ngày.</p>
    </div>
</div>

<?php admin_footer(); ?>