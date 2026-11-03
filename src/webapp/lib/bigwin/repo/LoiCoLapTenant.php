<?php
declare(strict_types=1);

/**
 * Vi phạm cô lập tenant (kế hoạch tổng §5.4): SQL thiếu {tenant:...}, truyền user_id vào them()/sua(), repo trỏ bảng
 * chưa khai báo, ngữ cảnh không hợp lệ… Fail-closed: KHÔNG bắt lỗi này để "chạy tiếp" — sửa câu lệnh / mã gọi.
 */
final class LoiCoLapTenant extends LogicException
{
}
