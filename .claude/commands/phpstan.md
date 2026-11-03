---
description: Phân tích tĩnh PHPStan (level trong phpstan.neon) cho src/webapp
---
Chạy phân tích tĩnh, KHÔNG sửa tệp nào, KHÔNG đọc `src/webapp/config.php` (đã loại trong `phpstan.neon`):

1. Chạy `php ~/.local/bin/phpstan-2.3.1.phar analyse --memory-limit=1G --no-progress --error-format=raw` ở gốc repo. Thiếu tệp `.phar` → báo cách tải (bản phát hành GitHub `phpstan/phpstan` 2.3.1, đối chiếu SHA-256 `85157dba3d95d77ca603d2b7303aa78a538f9e3b56ccad07dd794c53aa96d1ae`) rồi dừng — KHÔNG cài qua Composer.
2. Nếu có `$ARGUMENTS` (đường dẫn), chỉ phân tích các đường dẫn đó.
3. Với mỗi lỗi: đọc trang `https://phpstan.org/error-identifiers/<identifier>`, phân loại **lỗi thật** / **cố ý** (vd test chứng minh constructor private) / **báo nhầm**.
4. Báo cáo bảng: tệp:dòng · identifier · phân loại · đề xuất sửa. Không thêm `@phpstan-ignore`, baseline, ép kiểu hay nới kiểu chỉ để tắt lỗi.
