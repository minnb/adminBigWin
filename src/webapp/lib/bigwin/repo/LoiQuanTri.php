<?php
declare(strict_types=1);

/**
 * Vi phạm luật quản trị (giấy phép nền tảng, admin cuối cùng, tự khoá, trùng tên…) — thông điệp viết cho admin đọc, trang
 * quản trị hiển thị nguyên văn. Khác LoiCoLapTenant (lỗi lập trình, không bao giờ bắt để hiển thị).
 */
final class LoiQuanTri extends RuntimeException
{
}
