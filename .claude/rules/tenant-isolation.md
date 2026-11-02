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
- SQL tự viết (JOIN, tổng hợp) chỉ qua `truyVan()`; mỗi bảng tenant trong `FROM`/`JOIN` phải có placeholder `{tenant:alias}` (hoặc `{tenant_ca_xoa:alias}` khi cần cả bản đã xoá mềm). Thiếu → `LoiCoLapTenant` (fail-closed). Không bắt lỗi này để "chạy tiếp".
- CẤM gọi `db()`, `->prepare(`, `->query(`, `->exec(` ngoài `TenantRepo`, `SharedRepo`, `ThongKeRepo`, migration, `tools/`. `app/**` và `*Service.php` không chứa SQL.
- Bảng tenant mới: thêm cột `user_id NOT NULL` (kể cả bảng con), `UNIQUE(id, user_id)` ở bảng cha + FK kép `(cha_id, user_id)` ở bảng con; khai báo vào `TenantRepo::DANH_SACH_BANG_TENANT`.
- Tham chiếu chéo (vd `khach_hang_id` khi ghi phiên) phải được lấy qua `timMot()` của repo tương ứng TRƯỚC khi ghi; không thấy → 404 (không tiết lộ là của tenant khác). FK kép ở DB là lớp chặn thứ hai.
- PDO prepared 100%; tên bảng/cột không bao giờ đến từ input.

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
