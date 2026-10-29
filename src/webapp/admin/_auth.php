<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/bootstrap.php';

if (!function_exists('trang_bao_loi')) {
    /** Trang lỗi ĐỘC LẬP (CSS inline, không CDN/layout) — lỗi có thể nổ giữa lúc đang render trang. */
    function trang_bao_loi(int $http, string $tieuDe, string $goiY, string $chiTiet = ''): never
    {
        if (!headers_sent()) {
            http_response_code($http);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($tieuDe) . ' — BigWIN Admin</title></head>'
            . '<body style="margin:0;padding:16px;font-family:system-ui,\'Segoe UI\',sans-serif;background:#f1f5f9;color:#0f172a">'
            . '<div style="max-width:680px;margin:40px auto;padding:24px;background:#fff;border:1px solid #fecaca;border-left:6px solid #e11d48;border-radius:12px">'
            . '<h1 style="margin:0 0 12px;font-size:20px;color:#be123c">' . h($tieuDe) . '</h1>'
            . '<p style="margin:0 0 12px;line-height:1.6">' . h($goiY) . '</p>'
            . ($chiTiet === '' ? '' : '<pre style="margin:0;white-space:pre-wrap;word-break:break-all;background:#f8fafc;padding:12px;border-radius:8px;font-size:12px">' . h($chiTiet) . '</pre>')
            . '<p style="margin:16px 0 0;font-size:13px"><a href="index.php">← Về trang quản trị</a></p></div></body></html>';
        exit;
    }
}

// Mọi lỗi chưa bắt (DB, cấu hình, code) → trang báo lỗi có hướng dẫn sửa thay vì "HTTP ERROR 500" trắng.
// Nguyên văn lỗi CHỈ hiện cho admin đã đăng nhập hoặc khi config bật 'debug' (trang đăng nhập là công khai).
set_exception_handler(function (Throwable $e): void {
    $ref = ghi_loi($e);
    $cd = chan_doan_loi($e);
    $chiTiet = "Mã lỗi: {$cd['ma']} · Mã tra cứu trong error_log: $ref";
    if (che_do_debug() || !empty($_SESSION['admin_id'])) {
        $chiTiet .= "\n" . get_class($e) . ': ' . $e->getMessage() . "\n@ " . $e->getFile() . ':' . $e->getLine();
    }
    trang_bao_loi(500, 'Máy chủ gặp lỗi', $cd['goi_y'], $chiTiet);
});

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', '604800');
    session_set_cookie_params([
        'lifetime' => 604800,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('bigwin_admin');
    session_start();
}

if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check(): void
    {
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
            trang_bao_loi(403, 'Phiên làm việc đã hết hạn', 'Mã bảo vệ form (CSRF) không khớp — thường do mở trang quá lâu hoặc đã đăng xuất ở tab khác. Tải lại trang rồi thao tác lại.');
        }
    }
}

if (!function_exists('bao')) {
    /** Thông báo kết quả thao tác, hiện 1 lần ở trang kế tiếp (sau redirect). $loai: 'ok' | 'loi'. */
    function bao(string $loai, string $noiDung): void
    {
        $_SESSION['bao'][] = [$loai, $noiDung];
    }
}

if (!function_exists('kiem_tra_he_thong')) {
    /**
     * Cấu hình server còn thiếu gì → danh sách việc admin cần làm (rỗng = ổn). Hiện ở đầu mọi trang
     * admin để lỗi thiếu bảng / khoá được thấy NGAY, không đợi tới lúc app gọi API mới 500.
     *
     * @return string[]
     */
    function kiem_tra_he_thong(PDO $pdo): array
    {
        $viec = [];
        if (!function_exists('sodium_crypto_sign_detached')) {
            $viec[] = 'PHP thiếu extension sodium — cPanel → Select PHP Version → Extensions, bật "sodium".';
        }
        $bangCan = ['owners', 'machines', 'check_logs', 'admin_login_fails', 'admins',
                    'backup_versions', 'usage_summaries', 'backup_access_logs'];
        $bangCo = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')
            ->fetchAll(PDO::FETCH_COLUMN);
        $thieuBang = array_diff($bangCan, $bangCo);
        if ($thieuBang) {
            $viec[] = 'Thiếu bảng: ' . implode(', ', $thieuBang) . ' — import schema.sql (bảng đã có không bị ảnh hưởng).';
        }
        $cotCo = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'machines'")
            ->fetchAll(PDO::FETCH_COLUMN);
        $thieuCot = $cotCo ? array_diff(['owner_id', 'device_secret_hash', 'secret_mismatch_at'], $cotCo) : [];
        if ($thieuCot) {
            $viec[] = 'Bảng machines thiếu cột: ' . implode(', ', $thieuCot) . ' — chạy các file migrations/*.sql theo thứ tự ngày.';
        }
        $sk = @file_get_contents((string)cfg('secret_key_file'));
        if ($sk === false || strlen($sk) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            $viec[] = 'Không đọc được file khoá ký (secret_key_file trong config.php) — kiểm tra đường dẫn + quyền đọc. Dùng ĐÚNG file license_secret.key cũ.';
        } elseif (cfg('app_public_key') && base64_encode(sodium_crypto_sign_publickey_from_secretkey($sk)) !== cfg('app_public_key')) {
            $viec[] = 'Khoá ký trên server KHÔNG khớp khoá công khai nhúng trong app (app_public_key) — mọi app sẽ từ chối server. Upload lại đúng file license_secret.key cũ.';
        }
        if ($sk !== false) {
            sodium_memzero($sk);
        }
        $kb = @file_get_contents((string)cfg('backup_key_file'));
        if ($kb === false || strlen($kb) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $viec[] = 'Không đọc được file khoá backup (backup_key_file trong config.php) — sao lưu cloud sẽ lỗi. Dùng ĐÚNG file backup_secretbox.key cũ.';
        }
        return $viec;
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        if (empty($_SESSION['admin_id'])) {
            header('Location: login.php');
            exit;
        }
    }
}

if (!function_exists('page_head')) {
    function page_head(string $title): void
    {
?>
<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>

    <!-- Google Fonts: Be Vietnam Pro & Inter (Chuẩn Unicode Tiếng Việt) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome 6 Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <!-- Tailwind CSS v3 -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Be Vietnam Pro"', 'Inter', 'system-ui', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eef6ff', 100: '#d9eaff', 200: '#bcdbff', 300: '#8ec5ff',
                            400: '#59a3ff', 500: '#1b6ec2', 600: '#145cb5', 700: '#124891',
                            800: '#133e77', 900: '#153564', 950: '#0c2142',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js v3 -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js"></script>

    <!-- Chart.js v4 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Anti-FOUC Dark Mode Script -->
    <script>
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.4); border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(100, 116, 139, 0.7); }
    </style>
</head>
<body 
    x-data="{ 
        sidebarOpen: window.innerWidth >= 1024, 
        sidebarCollapsed: false,
        mobileMenuOpen: false,
        darkMode: document.documentElement.classList.contains('dark'),
        toggleTheme() {
            this.darkMode = !this.darkMode;
            if (this.darkMode) {
                document.documentElement.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
            }
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { isDark: this.darkMode } }));
        }
    }"
    class="h-full bg-slate-50 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-100 transition-colors duration-200"
>
<div class="min-h-full flex flex-col lg:flex-row">
<?php
    }
}

/**
 * Render Header, Sidebar và Topbar chung cho các trang đã đăng nhập
 */
if (!function_exists('admin_header')) {
    function admin_header(string $trangHienTai): void
    {
        $menu = [
            'index.php'  => ['nhan' => 'Quản lý máy & Bản quyền', 'icon' => 'fa-solid fa-laptop-code'],
            'owners.php' => ['nhan' => 'Chủ sở hữu máy',         'icon' => 'fa-solid fa-users-gear'],
            'usage.php'  => ['nhan' => 'Nhật ký đối soát',        'icon' => 'fa-solid fa-chart-line'],
        ];
?>
<!-- Backdrop Overlay cho Mobile -->
<div 
    x-show="mobileMenuOpen" 
    x-transition:enter="transition-opacity ease-linear duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-linear duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="mobileMenuOpen = false"
    class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"
    aria-hidden="true"
    style="display: none;"
></div>

<!-- Sidebar Navigation -->
<aside 
    :class="{
        'translate-x-0': mobileMenuOpen,
        '-translate-x-full lg:translate-x-0': !mobileMenuOpen,
        'lg:w-72': !sidebarCollapsed,
        'lg:w-20': sidebarCollapsed
    }"
    class="fixed inset-y-0 left-0 z-50 flex flex-col flex-shrink-0 bg-white border-r border-slate-200/80 transition-all duration-300 ease-in-out dark:bg-slate-900 dark:border-slate-800/80 shadow-sm"
>
    <!-- Brand Logo -->
    <div class="h-16 flex items-center justify-between px-5 border-b border-slate-200/80 dark:border-slate-800/80">
        <a href="index.php" class="flex items-center gap-3 overflow-hidden focus:outline-none">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-primary-600 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-primary-500/20 flex-shrink-0">
                <i class="fa-solid fa-gem text-lg"></i>
            </div>
            <div x-show="!sidebarCollapsed" class="transition-opacity duration-200 whitespace-nowrap">
                <span class="font-extrabold text-lg tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent dark:from-white dark:to-slate-300">
                    BigWIN
                </span>
                <span class="text-[10px] uppercase font-bold tracking-widest px-1.5 py-0.5 rounded bg-primary-100 text-primary-700 dark:bg-primary-950 dark:text-primary-300 ml-1">
                    License
                </span>
            </div>
        </a>
        <button 
            @click="mobileMenuOpen = false" 
            class="lg:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
            aria-label="Đóng menu"
        >
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>

    <!-- Navigation Items -->
    <nav class="flex-1 overflow-y-auto px-3.5 py-4 space-y-1.5 text-sm" role="navigation">
        <div x-show="!sidebarCollapsed" class="px-3 pt-2 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            Danh mục chính
        </div>

        <?php foreach ($menu as $file => $item): 
            $isActive = ($file === $trangHienTai);
            $cls = $isActive 
                ? 'bg-primary-50 text-primary-600 dark:bg-primary-950/50 dark:text-primary-400 font-semibold shadow-sm' 
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800/60 dark:hover:text-slate-200 font-medium';
        ?>
        <a href="<?= h($file) ?>" class="flex items-center gap-3.5 px-3 py-2.5 rounded-xl transition-all duration-150 <?= $cls ?>">
            <i class="<?= $item['icon'] ?> text-base w-5 text-center flex-shrink-0"></i>
            <span x-show="!sidebarCollapsed" class="whitespace-nowrap"><?= h($item['nhan']) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar Bottom Collapse Button -->
    <div class="p-3 border-t border-slate-200/80 dark:border-slate-800/80 hidden lg:flex justify-end">
        <button 
            @click="sidebarCollapsed = !sidebarCollapsed" 
            class="w-full flex items-center justify-center gap-2 p-2 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors"
            :title="sidebarCollapsed ? 'Mở rộng menu' : 'Thu gọn menu'"
        >
            <i class="fa-solid" :class="sidebarCollapsed ? 'fa-angles-right' : 'fa-angles-left'"></i>
            <span x-show="!sidebarCollapsed" class="text-xs font-semibold">Thu gọn sidebar</span>
        </button>
    </div>
</aside>

<!-- Main Wrapper -->
<div 
    :class="{ 'lg:pl-72': !sidebarCollapsed, 'lg:pl-20': sidebarCollapsed }"
    class="flex-1 flex flex-col transition-all duration-300 min-w-0"
>
    <!-- Topbar -->
    <header class="h-16 bg-white/80 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 dark:bg-slate-900/80 dark:border-slate-800/80 transition-colors">
        <div class="flex items-center gap-3 flex-1">
            <button 
                @click="mobileMenuOpen = true" 
                class="lg:hidden p-2 rounded-xl text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 focus:outline-none"
                aria-label="Mở danh mục điều hướng"
            >
                <i class="fa-solid fa-bars-staggered text-lg"></i>
            </button>
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 hidden sm:inline-block">
                <i class="fa-solid fa-shield-halved text-emerald-500 mr-1.5"></i> Cổng quản trị máy chủ bản quyền BigWIN
            </span>
        </div>

        <div class="flex items-center gap-2 sm:gap-3">
            <!-- Nút đổi Light / Dark mode -->
            <button 
                type="button"
                @click="toggleTheme()"
                onclick="document.documentElement.classList.toggle('dark'); var isDark = document.documentElement.classList.contains('dark'); localStorage.setItem('color-theme', isDark ? 'dark' : 'light');"
                class="p-2.5 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200 transition-colors focus:outline-none"
                :title="darkMode ? 'Chuyển sang Giao diện Sáng' : 'Chuyển sang Giao diện Tối'"
                aria-label="Đổi giao diện"
            >
                <i class="fa-solid text-base" :class="darkMode ? 'fa-sun text-amber-400' : 'fa-moon text-slate-600'"></i>
            </button>

            <div class="h-6 w-px bg-slate-200 dark:bg-slate-800"></div>

            <!-- Profile & Logout Dropdown -->
            <div class="relative" x-data="{ openProfile: false }">
                <button 
                    @click="openProfile = !openProfile" 
                    class="flex items-center gap-2.5 p-1.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors focus:outline-none"
                >
                    <img 
                        src="https://ui-avatars.com/api/?name=Admin+Bigwin&background=1b6ec2&color=fff&bold=true" 
                        alt="Avatar" 
                        class="w-8 h-8 rounded-full ring-2 ring-primary-500/30 object-cover"
                    >
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 hidden md:inline">
                        <?= h($_SESSION['admin_user'] ?? 'Admin') ?>
                    </span>
                    <i class="fa-solid fa-angle-down text-slate-400 text-xs hidden md:inline"></i>
                </button>

                <div 
                    x-show="openProfile" 
                    @click.away="openProfile = false"
                    x-transition
                    class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-slate-200/80 p-1.5 dark:bg-slate-900 dark:border-slate-800 text-xs z-50"
                    style="display: none;"
                >
                    <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-800">
                        <div class="font-bold text-slate-800 dark:text-white"><?= h($_SESSION['admin_user'] ?? 'Admin') ?></div>
                        <div class="text-[10px] text-slate-400">Quản trị viên hệ thống</div>
                    </div>
                    <div class="pt-1">
                        <form method="post" action="logout.php" class="m-0">
                            <?= csrf_field() ?>
                            <button type="submit" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-left font-medium transition">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i> Đăng xuất
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6">
<?php
        // Đang render dở (header đã gửi) → lỗi ở đây KHÔNG được ném ra, chỉ thành 1 dòng cảnh báo.
        try {
            $dsViec = kiem_tra_he_thong(db());
        } catch (Throwable $e) {
            $dsViec = ['Không kiểm tra được cấu hình (mã lỗi: ' . chan_doan_loi($e)['ma'] . ', tra error_log: ' . ghi_loi($e) . ').'];
        }
        foreach ($dsViec as $viec): ?>
        <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 dark:bg-amber-950/40 dark:border-amber-900/60 dark:text-amber-300 text-sm flex items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 flex-shrink-0"></i>
            <span><span class="font-semibold">Cấu hình server chưa đủ:</span> <?= h($viec) ?></span>
        </div>
<?php   endforeach;
        foreach ($_SESSION['bao'] ?? [] as [$loai, $noiDung]):
            $mau = $loai === 'ok'
                ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-900/60 dark:text-emerald-300'
                : 'bg-rose-50 border-rose-200 text-rose-700 dark:bg-rose-950/40 dark:border-rose-900/60 dark:text-rose-300'; ?>
        <div class="p-4 rounded-2xl border text-sm flex items-center gap-3 <?= $mau ?>">
            <i class="fa-solid <?= $loai === 'ok' ? 'fa-circle-check' : 'fa-circle-exclamation' ?> flex-shrink-0"></i>
            <span><?= h($noiDung) ?></span>
        </div>
<?php   endforeach;
        unset($_SESSION['bao']);
    }
}

/**
 * Render Footer chung cho các trang
 */
if (!function_exists('admin_footer')) {
    function admin_footer(): void
    {
?>
    </main>

    <!-- Footer -->
    <footer class="mt-auto py-5 px-4 sm:px-6 bg-white border-t border-slate-200/80 dark:bg-slate-900 dark:border-slate-800/80 text-xs text-slate-500 dark:text-slate-400 transition-colors">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div>
                © <?= date('Y') ?> <span class="font-bold text-slate-700 dark:text-slate-300">BigWIN License Server</span>. All rights reserved.
            </div>
            <div class="flex items-center gap-3">
                <span>Xác thực Ed25519 & Cloud Backup an toàn</span>
                <span>•</span>
                <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 font-mono text-[10px]">v3.2</span>
            </div>
        </div>
    </footer>
</div>
</div>
</body>
</html>
<?php
    }
}