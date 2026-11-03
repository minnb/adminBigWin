---
paths:
  - "src/webapp/app/**"
  - "src/webapp/lib/bigwin/repo/**"
  - "src/webapp/lib/bigwin/*Service.php"
---
# Cô lập dữ liệu theo tenant (multi-tenant) — TenantScope tự động, chống IDOR

Thiết kế đầy đủ: `docs/plans/2026-11-02-ke-hoach-chuyen-doi-python-php.md` §5.4, §7.1. Tenant = 1 tài khoản đại lý (`users.id`).

## Nguồn `user_id`
- `user_id` CHỈ đến từ phiên đăng nhập, đóng gói trong `TenantContext` (bất biến). Web: do `ajax_khoi_dong()` / `app/_layout.php` tạo bằng `TenantContext::tuPhien()`. CLI/worker: `TenantContext::choCli($id, $lyDo)` (bắt buộc ghi lý do).
- CẤM nhận `user_id` từ `$_GET`/`$_POST`/JSON/cookie. CẤM tự tạo `TenantContext` ở nơi khác.
- Admin dùng app như một tenant bình thường với `user_id` của chính mình; KHÔNG có "chế độ xem hộ" trong `app/`.

## Repository — bắt buộc qua lớp nền
- Repo cho bảng tenant **phải** `extends TenantRepo`, nhận `TenantContext` qua constructor. KHÔNG có tham số `int $userId` ở method; KHÔNG viết tay `user_id = ?`.
- Dùng `tim()` / `timMot()` / `them()` / `sua()` / `xoaMem()` của lớp nền — `user_id` và `deleted_at IS NULL` được tự chèn. `them()`/`sua()` CẤM có khoá `user_id` trong mảng cột.
- SQL tự viết (JOIN, tổng hợp) chỉ qua `truyVan()` (chỉ một câu `SELECT`); mỗi bảng tenant viết **thay tên bảng** sau `FROM`/`JOIN`: `{tenant:ten_bang alias}` (hoặc `{tenant_ca_xoa:ten_bang alias}` khi cần cả bản đã xoá mềm) — alias bắt buộc, không trùng tên bảng tenant. Lớp nền thay bằng bảng lọc sẵn `(SELECT * FROM ten_bang WHERE user_id = <id> AND deleted_at IS NULL) alias` → `OR` / `LEFT JOIN` ở câu ngoài không vô hiệu được (Q-P02-8, `BoQuetSqlTenant`). Tên bảng tenant xuất hiện "trần" ở bất cứ đâu, chú thích SQL, `;`, `UNION`, `WITH`, `INTO` → `LoiCoLapTenant` (fail-closed). Không bắt lỗi này để "chạy tiếp".
- CẤM gọi `db()`, `->prepare(`, `->query(`, `->exec(`, `new PDO` trong `app/**`, `lib/bigwin/**`, `*Service.php` — trừ lớp nền `TenantRepo`, `SharedRepo`, `BoQuetSqlTenant`, `ThueBaoRepo`, `UserRepo` (P03 QĐ4 — quản trị tài khoản, chỉ ngữ cảnh admin / hệ thống), `ThongKeRepo`. Chuỗi SQL nhắc tên bảng tenant / bảng thuê bao chỉ được nằm trong `lib/bigwin/repo/` (Q-P02-6; `admin/`, `api/`, `tools/` cũng không ngoại lệ — dùng repo).
- `TenantContext::tuPhien` chỉ ở `lib/security/AjaxGateway.php` và `app/_layout.php`; `choCli` / `heThong` chỉ trong `tools/` (và chỉ chạy được khi `PHP_SAPI = cli`).
- Bảng tenant mới: thêm cột `user_id NOT NULL` (kể cả bảng con), `UNIQUE(id, user_id)` ở bảng cha + FK kép `(cha_id, user_id)` ở bảng con; khai báo vào `TenantRepo::DANH_SACH_BANG_TENANT` (`bang => có xoá mềm`; `demo_cha`/`demo_con` chỉ có trong DB test — Q-P02-5).
- Tham chiếu chéo (vd `khach_hang_id` khi ghi phiên) phải được lấy qua `timMot()` của repo tương ứng TRƯỚC khi ghi; không thấy → 404 (không tiết lộ là của tenant khác). FK kép ở DB là lớp chặn thứ hai.
- PDO prepared 100%; tên bảng/cột không bao giờ đến từ input.

## Thuê bao — `ThueBaoRepo` (Q-P02-1)
- `thue_bao`, `thue_bao_lich_su`, `su_dung_ngay` có `user_id` nhưng KHÔNG nằm trong `DANH_SACH_BANG_TENANT`: chỉ truy cập qua `ThueBaoRepo` (không kế thừa `TenantRepo`).
- Mặt đại lý (`cuaToi`, `tangDem`) luôn theo id của `TenantContext`. Mặt quản trị (`tao`, `giaHan`, `doiGoi`, `tamKhoa`, `moKhoa`, `lichSu`, …) chỉ ngữ cảnh admin (web) hoặc hệ thống (CLI); id đại lý đích chỉ do trang `admin/` đã `require_admin()` truyền vào.
- `thue_bao_lich_su` append-only: mọi thay đổi ghi 1 dòng cùng transaction; không có đường sửa/gỡ dòng lịch sử.

## Dữ liệu tiền — xoá mềm & bất biến
- CẤM `DELETE` trên `khach_hang`, `tin_nhan_log`, `lich_su_doi_soat` — chỉ `xoaMem()` (`deleted_at`, `deleted_by`, `ly_do_xoa`). Ghi đè / sửa phiên / đồng bộ lại = xoá mềm bản cũ + ghi bản mới + `thay_the_boi_msg_id`.
- Cột tiền snapshot của `lich_su_doi_soat` (`tien_vnd`, `tien_ghi`, `tien_trung`, `tien_chiet_khau`, `so_lan_trung`, `giai_trung`, `he_so_ap_dung_json`) KHÔNG được UPDATE (khai báo `COT_BAT_BIEN`; DB có trigger chặn). Báo cáo / chốt sổ / thống kê chỉ cộng snapshot, không tính lại tiền.
- FK trên dữ liệu tiền dùng `ON DELETE RESTRICT`, không `CASCADE`.

## Dữ liệu dùng chung
- `ket_qua_xo_so`, `cau_hinh_mac_dinh`, `parser_dan_so_goc*`, `scraper_log`, `goi_dich_vu`, `giay_phep_he_thong`: KHÔNG có `user_id`; truy cập qua `SharedRepo` — tenant chỉ ĐỌC; ghi chỉ khi ngữ cảnh admin/CLI.
- Không JOIN bảng dùng chung theo cách làm rò dữ liệu tenant khác (vd thống kê "số đại lý đã đánh số X").
- Truy vấn chéo tenant duy nhất được phép: `ThongKeRepo` (admin), chỉ trả số tổng hợp, không có cột nội dung tin.

## Hệ số (3 tầng)
`he_so_khach_hang` (có `user_id`) → `he_so_user` → `cau_hinh_mac_dinh`. Khách phải thuộc tenant (qua `timMot()`) trước khi đọc tầng 1.

## Cache
Khoá cache chứa dữ liệu tenant luôn có `user_id` (vd `rp:{user_id}:{phien_ban}`) — không bao giờ dùng chung khoá giữa tenant.

## Trang `app/` & endpoint
- Mọi `app/ajax/*.php`: dòng đầu `ajax_khoi_dong(CheDo)` (session, CSRF, `require_user`, `TenantContext`, `GiayPhepGuard`). Trang HTML: `app/_layout.php`. Output escape `h()`.
- "Không có quyền" và "không tồn tại" trả cùng mã 404.

## Kiểm chứng (bắt buộc mỗi slice đụng repo)
- `tests/tenant_scan_test.php` (quét mã) xanh.
- `tests/idor_test.php`: dữ liệu của tenant B, gọi **mọi** method public của repo dưới ngữ cảnh tenant A → rỗng / 404 / không đổi dữ liệu.
