<?php
declare(strict_types=1);

// Tệp cấu hình KHI CHẠY TEST (P01 S1.0, Q-P01-1): tools/test.php đặt BIGWIN_CONFIG_FILE trỏ vào đây → cfg() của
// runner và của `php -S` nạp tệp này thay config.php. Lấy cấu hình gốc, thay db_* bằng db_test_*; thiếu khoá hoặc
// DB không kết thúc "_test" → dừng ngay: test KHÔNG BAO GIỜ chạm DB thật, không rải bản sao config ra thư mục tạm.
// Cấu hình gốc = config.php, hoặc tệp tools/test.php nhận qua BIGWIN_CONFIG_FILE (CI / máy không có config thật).

require_once __DIR__ . '/_cau_hinh_ham.php';

$goc = getenv('BIGWIN_TEST_CONFIG_GOC');
return bw_cau_hinh_test(require (is_string($goc) && $goc !== '' ? $goc : __DIR__ . '/../config.php'));
