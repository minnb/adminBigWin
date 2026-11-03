# P02 — TenantScope · Bản quyền SaaS

| Thuộc tính | Giá trị |
|---|---|
| Slice | S1.4 (TenantScope — §5.4), S1.5 (Bản quyền — §1.4) |
| Phụ thuộc | **P01 ĐẠT** (cần `_db.php`, `_http.php`, gateway, `users`) |
| Mở khoá | P03 |
| Quyết định áp dụng | N14, N15, O1 (xoá mềm), O2 (bất biến tiền), O8 (1 User = 1 Đại lý) |
| Cỡ | M + M |

## 1. Mục tiêu
1. **Không repo nghiệp vụ nào có thể quên `user_id`:** `TenantContext` + `TenantRepo` tự chèn `user_id` và `deleted_at IS NULL`, SQL tự viết thiếu đánh dấu bị chặn (fail-closed).
2. **Bản quyền SaaS chạy thật trong gateway:** trạng thái thuê bao tính từ ngày, chặn đúng ma trận §1.4, đếm hạn mức nguyên tử.

Phase này **chưa có bảng nghiệp vụ thật** → TenantScope được kiểm bằng bảng demo chỉ tồn tại trong DB test.

## 2. Điều kiện bắt đầu
- [ ] P01 ĐẠT.
- [ ] Đọc lại `.claude/rules/tenant-isolation.md` (đã cập nhật v2.1).

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Ghi chú |
|---|---|---|
| S1.4 | `lib/bigwin/TenantContext.php`, `lib/bigwin/repo/TenantRepo.php`, `lib/bigwin/repo/SharedRepo.php`, `tests/_tenant_demo.sql` (2 bảng demo cha/con có `user_id`, `deleted_at`, FK kép — **chỉ nạp vào DB test**), `tests/tenant_scan_test.php`, `tests/db_tenant_repo_test.php`, khung `tests/idor_test.php` | `DANH_SACH_BANG_TENANT` khai báo một chỗ; `truyVan()` thay `{tenant:x}` / `{tenant_ca_xoa:x}`; quét `FROM/JOIN` → `LoiCoLapTenant` |
| S1.5 | `migrations/2026-11-xx_thue_bao.sql`, `schema.sql`, `lib/bigwin/GiayPhepGuard.php`, `lib/bigwin/repo/ThueBaoRepo.php`, `tools/cap_nhat_thue_bao.php`, nối `GiayPhepGuard` vào `AjaxGateway` | Bảng `giay_phep_he_thong`, `goi_dich_vu`, `thue_bao`, `thue_bao_lich_su`, `su_dung_ngay`; trạng thái **tính ra** từ `today_vn()` |

## 4. Thứ tự thực hiện
1. `TenantContext` (constructor private, `tuPhien`, `choCli`) → test.
2. `TenantRepo` CRUD (`tim`, `timMot`, `them`, `sua`, `xoaMem`) trên bảng demo → test.
3. `truyVan()` + bộ quét SQL → test các dạng JOIN, alias, subquery.
4. `tenant_scan_test.php` (quét mã toàn `src/webapp`).
5. Migration thuê bao → `GiayPhepGuard` (hàm thuần nhận ngày + bản ghi thuê bao) → test ma trận.
6. Nối guard vào gateway; endpoint `_ping.php` khai báo chế độ `doc`, thêm `_ping_ghi.php` (`ghi`) và `_ping_phan_tich.php` (`phan_tich`) để test (xoá ở P03).
7. Đếm hạn mức `su_dung_ngay` (`FOR UPDATE`) → test đồng thời.

## 5. Kế hoạch test
### 5.1 Tự động — TenantScope
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P02-T01 | `new TenantContext(...)` từ ngoài | Không được (constructor private) |
| P02-T02 | `TenantContext::choCli()` không lý do | Lỗi |
| P02-T03 | `them()` với mảng có khoá `user_id` | `LoiCoLapTenant` |
| P02-T04 | `them()` bình thường | Bản ghi có `user_id` = ngữ cảnh |
| P02-T05 | Tenant A `timMot(id của B)` | `null` |
| P02-T06 | Tenant A `sua(id của B)` | `false`, dữ liệu B không đổi |
| P02-T07 | Tenant A `xoaMem(id của B)` | `false`, B không bị đánh dấu xoá |
| P02-T08 | `xoaMem()` của chính mình | `deleted_at` có giá trị, `deleted_by`, `ly_do_xoa` đúng; bản ghi còn trong bảng |
| P02-T09 | `tim()` sau `xoaMem()` | Không trả bản đã xoá |
| P02-T10 | `truyVan()` có `{tenant_ca_xoa:x}` | Trả cả bản đã xoá (chỉ của tenant mình) |
| P02-T11 | `truyVan("SELECT … FROM demo_cha c JOIN demo_con d …")` thiếu placeholder cho `d` | `LoiCoLapTenant` trước khi chạm DB |
| P02-T12 | `truyVan()` alias kiểu `AS`, không `AS`, xuống dòng, chữ hoa/thường | Quét đúng cả 4 dạng |
| P02-T13 | Subquery `IN (SELECT … FROM demo_con)` thiếu placeholder | `LoiCoLapTenant` |
| P02-T14 | FK kép: gắn con của A vào cha của B (bỏ qua repo, chèn SQL trực tiếp trong test) | DB từ chối |
| P02-T15 | `sua()` đụng cột trong `COT_BAT_BIEN` của repo demo | Lỗi |
| P02-T16 | `tenant_scan_test`: tệp mẫu vi phạm (đặt trong `tests/_vi_pham_mau/`) gọi `db()` trong `app/` | Quét phát hiện; mã thật hiện tại: 0 vi phạm |
| P02-T17 | `idor_test` khung: liệt kê mọi class `extends TenantRepo`, gọi mọi method public dưới tenant khác | Chạy được với repo demo; sẵn sàng cho repo thật ở P07+ |

### 5.2 Tự động — Bản quyền
| Mã | Kiểm tra (ngày giả lập) | Kỳ vọng |
|---|---|---|
| P02-T20 | `het_han` = hôm nay | `hieu_luc`; `doc/ghi/phan_tich` đều qua |
| P02-T21 | `het_han` = hôm qua, ân hạn 3 ngày | `an_han`; vẫn qua, kèm cờ cảnh báo |
| P02-T22 | `het_han` = 4 ngày trước | `het_han`; `doc` qua, `ghi`/`phan_tich` → 402 |
| P02-T23 | `tam_khoa = 1` dù còn hạn | `doc`/`ghi`/`phan_tich` → 402; đăng nhập vẫn được |
| P02-T24 | Admin (role admin) không có thuê bao | Không bị chặn (admin dùng app như tenant, có gói nội bộ mặc định — ghi rõ trong code) |
| P02-T25 | Hạn mức `so_tin_ngay_toi_da = 3`, gọi tăng đếm 4 lần | Lần 4 → 402; `su_dung_ngay.so_tin_doi_soat` = 3 |
| P02-T26 | 2 tiến trình tăng đếm đồng thời khi còn 1 chỗ | Đúng 1 thành công (khoá `FOR UPDATE`) |
| P02-T27 | Gia hạn / đổi gói / tạm khoá qua `ThueBaoRepo` | Mỗi thao tác sinh 1 dòng `thue_bao_lich_su` với `truoc_json`/`sau_json` |
| P02-T28 | UPDATE/DELETE trực tiếp `thue_bao_lich_su` qua repo | Không có method; append-only |
| P02-T29 | `cap_nhat_thue_bao.php` chạy 2 lần cùng ngày | Không ghi lịch sử trùng |
| P02-T30 | Giấy phép nền tảng `so_dai_ly_toi_da = 2`, đang có 2 đại lý hoạt động | `GiayPhepGuard::coTheThemDaiLy()` = false; admin không bị tính |
| P02-T31 | HTTP: `_ping_phan_tich.php` với tài khoản hết hạn | 402 JSON `{ma:402}` |
| P02-T32 | Hồi quy toàn bộ P00–P01 | Xanh |

### 5.3 Thủ công
- [ ] P02-M01: Đọc lại `TenantRepo.php` cùng người duyệt — không có đường nào chạy SQL trên bảng tenant mà bỏ qua lớp nền.
- [ ] P02-M02: Đổi ngày hệ thống giả lập (cấu hình `ngay_gia_lap` chỉ bật ở môi trường test) → gọi `_ping_*` thấy đúng ma trận.

## 6. Cổng nghiệm thu
README §2 **và**: P02-T01..T32 xanh; `tenant_scan_test` = 0 vi phạm trên mã thật; bảng demo **không** có trong `schema.sql`.

## 7. Rủi ro & rollback
| Rủi ro | Xử lý |
|---|---|
| Bộ quét SQL bỏ sót dạng câu (CTE, UNION) | Mặc định fail-closed: gặp cú pháp không hiểu → ném lỗi, buộc viết lại câu đơn giản hơn; thêm test khi gặp dạng mới |
| `ngay_gia_lap` lọt lên production | Chỉ đọc khi `cfg('moi_truong') === 'test'`; P13 kiểm lại |

## 8. Ngoài phạm vi
Giao diện băng thuê bao & trang Gói/Thuê bao (P03); bảng nghiệp vụ thật (P06+).

## 9. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| 2026-11-03 | Claude (thực thi; ký theo uỷ quyền chủ dự án) | S1.4 TenantScope (bảng lọc sẵn `{tenant:ten_bang alias}`, `TenantRepo`, `SharedRepo`, quét mã, khung IDOR) + S1.5 bản quyền (`GiayPhepGuard`, `ThueBaoRepo`, cron, gateway 402). Hồi quy 138/138 ×3, `/healthcheck` OK, `/audit-api` AN TOÀN, M01/M02 đạt, migration P01+P02 đã chạy lên DB thật. Chi tiết + B01–B10: `TASK_LOG.md` mục P02 | ✅ **PASS** — mở khoá P03 |
