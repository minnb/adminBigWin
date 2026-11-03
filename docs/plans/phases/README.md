# Lộ trình theo Phase — BigWIN Web (PHP)

> **Điều phối:** [`EXECUTION_PIPELINE.md`](EXECUTION_PIPELINE.md) (vòng lặp chuẩn, bản đồ phụ thuộc, Action Plan P00–P09, gatekeeping) · **Nhật ký:** [`TASK_LOG.md`](TASK_LOG.md)

Tài liệu thực thi chia nhỏ từ kế hoạch tổng [`../2026-11-02-ke-hoach-chuyen-doi-python-php.md`](../2026-11-02-ke-hoach-chuyen-doi-python-php.md) (v2.1). Kế hoạch tổng giữ **thiết kế & quyết định**; các tệp phase giữ **thứ tự làm, test case, cổng nghiệm thu**. Khi hai bên khác nhau → kế hoạch tổng là chuẩn, sửa tệp phase cho khớp.

## 1. Bản đồ phase

| Phase | Tên | Slice (kế hoạch tổng) | Phụ thuộc | Cỡ | Trạng thái |
|---|---|---|---|---|---|
| [P00](P00-dong-bang-va-fixture.md) | Đóng băng BigWIN & fixture engine Mới | S0.4, S0.5 | Chủ repo BigWIN làm R1 | S+M | ⏳ Chờ chủ repo |
| [P01](P01-danh-tinh-va-bao-mat.md) | Hạ tầng test DB/HTTP · Danh tính · Core bảo mật · Đăng nhập | S1.0*, S1.1, S1.2, S1.3 | — | M+M+M | ✅ ĐẠT (2026-11-03) |
| [P02](P02-tenant-scope-va-ban-quyen.md) | TenantScope · Bản quyền SaaS | S1.4, S1.5 | P01 | M+M | ✅ ĐẠT (2026-11-03) |
| [P03](P03-khung-app-va-quan-tri.md) | Khung app 6 page · Quản trị tài khoản & thuê bao | S1.6, S1.7 | P02 | M+L | |
| [P04](P04-loi-cu-phap-1.md) | Lõi cú pháp 1: SequenceMatcher · Cleaner · Đối chiếu nghi ngờ | S2.1, S2.2, S2.6 | P00 | M+L+M | |
| [P05](P05-loi-cu-phap-2.md) | Lõi cú pháp 2: MessageParser · RuleParser · Dàn số · Cache | S2.3, S2.4, S2.5, S2.7 | P04 | L+L+M+M | |
| [P06](P06-tien-va-kqxs.md) | Tiền · Dò trúng · Tổng hợp · KQXS | S3.1, S3.2, S3.3 | P03, P05 | M+M+M | |
| [P07](P07-khach-hang-va-bang-gia.md) | Khách hàng & Bảng giá | S4.1, S4.2 | P06 | M+L | |
| [P08](P08-doi-soat-va-nhap-tin.md) | Đối soát · Page Nhập tin | S4.3, S4.4 | P07 | L+L | |
| [P09](P09-cu-phap-va-tu-hoc.md) | Cú pháp (dàn số) · Bộ nhớ tự học | S5.1–S5.4 | P08 | L+M+L+M | |
| [P10](P10-lich-su-chot-so-bao-cao.md) | Lịch sử · Chốt sổ · In báo cáo | S6.1–S6.3 | P09 | M+M+M | |
| [P11](P11-telegram.md) | Telegram (daemon systemd) | S7.1–S7.4 | P08 + VPS sẵn sàng | L+M+L+M | |
| [P12](P12-thong-ke-va-import.md) | Thống kê admin · Quyền admin trên page · Import `xoso.db` | S8.1–S8.3 | P10, P11 | M+S+M | |
| [P13](P13-go-live.md) | Go-live | S9.1–S9.4 | P12 | — | |

\* S1.0 = hạ tầng test DB/HTTP — slice mới, thêm khi tách phase (cần cho mọi test tích hợp từ P01 trở đi).

```
P00 ─────────────► P04 ──► P05 ─┐
P01 ──► P02 ──► P03 ────────────┴─► P06 ──► P07 ──► P08 ──► P09 ──► P10 ─┐
                                                      └──► P11 ──────────┴─► P12 ──► P13
```
P00 (chờ chủ repo BigWIN) và P01→P03 chạy **song song**. P11 chỉ cần P08 và hạ tầng VPS; có thể chạy song song P09–P10.

## 2. Quy tắc cổng nghiệm thu (áp cho MỌI phase)

Một phase chỉ được đánh dấu **ĐẠT** khi đủ cả 6 điều kiện:

1. **Test tự động mới của phase** xanh 100% (`php src/webapp/tools/test.php`).
2. **Hồi quy:** toàn bộ test của các phase trước vẫn xanh — chạy cả bộ, không lọc.
3. **Test thủ công** trong mục "Checklist thủ công" của phase được tick đủ, có ghi người test + ngày.
4. `/healthcheck` xanh; nếu phase đụng `api/`, `lib/api.php`, `lib/crypto.php` → `/audit-api` báo AN TOÀN.
5. DoD chung §10 của kế hoạch tổng được tuân thủ (TenantScope, Core bảo mật, bất biến tiền… khi áp dụng).
6. **Người duyệt (chủ dự án) ký duyệt** ở mục "Nhật ký" của tệp phase.

Không đạt bất kỳ điều kiện nào → **không mở phase sau** phụ thuộc vào nó. Lỗi phát hiện ở phase sau mà gốc nằm ở phase trước → mở lại phase trước, sửa, chạy lại cổng của nó.

## 3. Quy ước test

| Loại | Tiền tố tệp | Chạy ở đâu | Ghi chú |
|---|---|---|---|
| Đơn vị / parity (PURE) | `tests/<module>_test.php` | Không cần DB | So `===` với `tests/fixtures/*.json`; fixture lệch = bug PHP |
| Tích hợp DB | `tests/db_<chủ đề>_test.php` | DB test `bigwin_test` (dựng lại sạch mỗi lần chạy từ `schema.sql`) | Hạ tầng: `tests/_db.php` (P01) |
| HTTP / endpoint | `tests/http_<chủ đề>_test.php` | `php -S 127.0.0.1:8765 -t src/webapp` do test tự bật | Hạ tầng: `tests/_http.php` (P01) — cookie jar, CSRF, Origin |
| Quét mã | `tests/*_scan_test.php` | Không cần DB | Quét `src/webapp/**` tìm vi phạm quy tắc |

- **Mã test case:** `Pxx-Tnn` (tự động), `Pxx-Mnn` (thủ công). Ghi mã vào tên hàm test: `test_p01_t03_cookie_co_du_co()` (runner liệt kê hàm theo chữ thường).
- **Dữ liệu test** chỉ sinh trong test; không dùng dữ liệu thật; không commit `tests/fixtures/local/`.
- **Lệnh chuẩn:** `php src/webapp/tools/test.php` (cả bộ) · `php src/webapp/tools/test.php db_auth` (lọc theo **chuỗi con của tên tệp** — runner `tools/test.php` chỉ lọc theo tên tệp).
- DB test và HTTP test **bắt buộc chạy** ở cổng nghiệm thu; khi thiếu cấu hình `db_test` thì runner báo **FAIL** (không được tự bỏ qua).

## 4. Theo dõi trạng thái
Cập nhật cột "Trạng thái" ở bảng §1: `⏳ Chờ` · `▶ Đang làm` · `🧪 Đang test` · `✅ ĐẠT (ngày)` · `⛔ Mở lại`. Chi tiết kết quả ghi ở mục "Nhật ký" cuối mỗi tệp phase và một dòng vào §14 kế hoạch tổng.
