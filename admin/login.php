<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';

// Nếu đã đăng nhập thành công từ trước, tự động chuyển hướng vào Dashboard
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

const SO_LAN_SAI_TOI_DA = 5;
const KHOA_PHUT = 15;

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $pdo = db();
    $ip = client_ip();

    $pdo->exec('DELETE FROM admin_login_fails WHERE created_at < NOW() - INTERVAL 1 DAY');
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM admin_login_fails WHERE ip = ? AND created_at > NOW() - INTERVAL ? MINUTE'
    );
    $st->execute([$ip, KHOA_PHUT]);

    if ((int)$st->fetchColumn() >= SO_LAN_SAI_TOI_DA) {
        $error = 'Bạn đã nhập sai quá ' . SO_LAN_SAI_TOI_DA . ' lần liên tiếp. Vui lòng thử lại sau ' . KHOA_PHUT . ' phút.';
    } else {
        $user = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $pass = is_string($_POST['password'] ?? null) ? (string)$_POST['password'] : '';
        $st = $pdo->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
        $st->execute([$user]);
        $row = $st->fetch();
        if ($row && password_verify($pass, $row['password_hash'])) {
            $pdo->prepare('DELETE FROM admin_login_fails WHERE ip = ?')->execute([$ip]);
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$row['id'];
            $_SESSION['admin_user'] = $user;
            session_write_close();
            header('Location: index.php');
            exit;
        }
        $pdo->prepare('INSERT INTO admin_login_fails (ip, username, created_at) VALUES (?, ?, NOW())')
            ->execute([$ip, mb_substr($user, 0, 50)]);
        sleep(1);
        $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
    }
}

page_head('Đăng nhập — BigWIN License');
?>
<div class="min-h-screen w-full flex items-center justify-center p-4 relative overflow-hidden bg-gradient-to-br from-slate-100 via-primary-50/30 to-slate-200 dark:from-slate-950 dark:via-slate-900 dark:to-primary-950/40">
    <!-- Nút đổi theme góc trên -->
    <div class="absolute top-5 right-5 z-20">
        <button 
            type="button"
            @click="toggleTheme()" 
            onclick="document.documentElement.classList.toggle('dark'); var isDark = document.documentElement.classList.contains('dark'); localStorage.setItem('color-theme', isDark ? 'dark' : 'light'); this.querySelector('i').className = isDark ? 'fa-solid fa-sun text-amber-400' : 'fa-solid fa-moon text-slate-600';"
            class="p-2.5 rounded-2xl bg-white/80 dark:bg-slate-900/80 backdrop-blur-md shadow-sm border border-slate-200/80 dark:border-slate-800 text-slate-600 dark:text-slate-300 hover:scale-105 transition-all focus:outline-none"
            :title="darkMode ? 'Chuyển sang Giao diện Sáng' : 'Chuyển sang Giao diện Tối'"
        >
            <i class="fa-solid" :class="darkMode ? 'fa-sun text-amber-400' : 'fa-moon text-slate-600'"></i>
        </button>
    </div>

    <!-- Login Card -->
    <div class="w-full max-w-md bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl rounded-3xl shadow-2xl border border-slate-200/80 dark:border-slate-800 p-8 sm:p-10 relative z-10 transition-all">
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-primary-600 to-indigo-600 text-white flex items-center justify-center mx-auto mb-4 shadow-lg shadow-primary-500/25">
                <i class="fa-solid fa-gem text-2xl"></i>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                BigWIN License
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 font-medium">
                Cổng quản trị máy chủ bản quyền & sao lưu
            </p>
        </div>

        <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 dark:bg-rose-950/40 dark:border-rose-900/60 dark:text-rose-300 text-xs flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation text-base flex-shrink-0 text-rose-500"></i>
            <span><?= h($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Form Đăng Nhập -->
        <form method="post" class="space-y-5">
            <?= csrf_field() ?>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                    Tên đăng nhập
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-regular fa-user text-sm"></i>
                    </span>
                    <input 
                        name="username" 
                        autocomplete="username" 
                        required 
                        autofocus
                        placeholder="Nhập tài khoản admin..."
                        class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 rounded-2xl focus:bg-white dark:focus:bg-slate-900 focus:border-primary-500 focus:ring-4 focus:ring-primary-500/15 text-slate-900 dark:text-white placeholder-slate-400 outline-none transition"
                    >
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                    Mật khẩu bảo mật
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-lock text-sm"></i>
                    </span>
                    <input 
                        name="password" 
                        type="password" 
                        autocomplete="current-password" 
                        required
                        placeholder="••••••••"
                        class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80 rounded-2xl focus:bg-white dark:focus:bg-slate-900 focus:border-primary-500 focus:ring-4 focus:ring-primary-500/15 text-slate-900 dark:text-white placeholder-slate-400 outline-none transition"
                    >
                </div>
            </div>

            <button 
                type="submit" 
                class="w-full py-3.5 px-4 rounded-2xl bg-gradient-to-r from-primary-600 to-indigo-600 hover:from-primary-700 hover:to-indigo-700 text-white font-bold text-sm shadow-lg shadow-primary-500/25 hover:shadow-primary-500/35 hover:-translate-y-0.5 active:translate-y-0 transition-all flex items-center justify-center gap-2"
            >
                <span>Đăng nhập hệ thống</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800 text-center">
            <p class="text-[11px] text-slate-400">
                Bảo vệ xác thực đa tầng Ed25519 & Rate-limiting IP
            </p>
        </div>
    </div>
</div>
</div>
</body>
</html>