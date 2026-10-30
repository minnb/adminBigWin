---
description: Rà soát git diff của API/crypto xem có phá hợp đồng với app BigWIN không
argument-hint: "[git ref để so sánh, mặc định HEAD]"
---
Chạy `git diff ${ARGUMENTS:-HEAD} -- src/webapp/api/ src/webapp/lib/api.php src/webapp/lib/crypto.php src/webapp/lib/bootstrap.php` (và `git diff --cached` nếu có).

Đối chiếu với `.claude/rules/api-contract.md`, đánh giá từng thay đổi:
- Tệp/URL endpoint bị đổi tên/xoá?
- Trường JSON request/response bị xoá, đổi tên, đổi kiểu, đổi giá trị enum? Trường request mới có bắt buộc không?
- HTTP status / mã lỗi `fail()` thay đổi?
- Cách tạo `payload`/`sig` Ed25519, khoá ký, hay mã hoá secretbox backup thay đổi (app cũ có còn verify/giải mã được)?

Kết luận: AN TOÀN / CÓ RỦI RO / PHÁ VỠ, kèm `file:line` và đề xuất cách giữ tương thích ngược. Không tự sửa code.
