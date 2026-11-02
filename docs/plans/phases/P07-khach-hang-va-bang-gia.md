# P07 — Khách hàng & Bảng giá

| Thuộc tính | Giá trị |
|---|---|
| Slice | S4.1 (migration tenant + `KhachHangRepo` + `HeSoRepo`), S4.2 (page Khách hàng & Bảng giá) |
| Phụ thuộc | **P06 ĐẠT** |
| Mở khoá | P08 |
| Quyết định áp dụng | N5 (21 khoá, 3 tầng), N6 + O1 (xoá mềm), O2, N15 (TenantScope) |
| Thiết kế chuẩn | Preview `#khach`, `#hoso`, `#khachmoi`, `#xoakhach` · Phụ lục A-KH |
| Cỡ | M + L |

## 1. Mục tiêu
**Repo nghiệp vụ thật đầu tiên** đứng trên `TenantRepo`; page Khách hàng & Bảng giá đầy đủ (lưới 21 cột, drawer hồ sơ + giá riêng, thêm, ẩn khách) với hệ số 3 tầng resolve đúng.

## 2. Điều kiện bắt đầu
- [ ] P06 ĐẠT.
- [ ] Xác nhận phiên bản MySQL/MariaDB production hỗ trợ **cột sinh STORED + UNIQUE** và **trigger** (ghi kết quả vào nhật ký).

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Ghi chú |
|---|---|---|
| S4.1 | `migrations/2026-11-xx_nghiep_vu_tenant.sql`, `schema.sql`, `lib/bigwin/repo/KhachHangRepo.php`, `lib/bigwin/repo/HeSoRepo.php` | Tạo **cả 4 bảng** `khach_hang`, `he_so_user`, `he_so_khach_hang` (có `user_id`, FK kép), `tin_nhan_log`, `lich_su_doi_soat` (+ trigger chặn UPDATE cột tiền, `ON DELETE RESTRICT`) — để P08 chỉ viết repo; `cau_hinh_mac_dinh` seed 21 khoá; cột sinh `ten_khach_hoat_dong`, `id_telegram_hoat_dong` |
| S4.2 | `app/khach_hang.php`, `app/ajax/khach_luu.php`, `app/ajax/khach_xoa.php`, `app/ajax/he_so_luu.php` | Lưới (bật/tắt nhóm cột, cột tên ghim), dòng Hệ thống (chỉ xem) / Đại lý / khách; drawer; modal Thêm; modal Ẩn khách hàng ("Ẩn khách hàng, bảo toàn lịch sử đối soát cũ") |

## 4. Thứ tự thực hiện
1. Migration + `schema.sql` (`/db-migration`) → P01-T02 (schema = migration) vẫn xanh.
2. Đăng ký bảng vào `DANH_SACH_BANG_TENANT`.
3. `KhachHangRepo`, `HeSoRepo` → test DB + IDOR.
4. Endpoint → test HTTP.
5. Page theo preview.

## 5. Kế hoạch test
### 5.1 Tự động
| Mã | Tệp | Kiểm tra | Kỳ vọng |
|---|---|---|---|
| P07-T01 | `db_khach_hang_test.php` | Thêm khách: tên bắt buộc, gộp khoảng trắng | Đúng |
| P07-T02 | | Trùng tên không phân biệt hoa thường (cùng tenant) | 409 |
| P07-T03 | | Trùng tên **khác tenant** | Được |
| P07-T04 | | Ẩn khách rồi tạo lại cùng tên | Được (cột sinh) |
| P07-T05 | | Telegram ID: không phải số / trùng khách đang hoạt động | 422 / 409 |
| P07-T06 | | Ẩn khách: `deleted_at`, `deleted_by`, `ly_do_xoa`; bản ghi còn; không còn trong `tim()` | Đúng |
| P07-T07 | | Không có đường `DELETE` cứng (quét mã `KhachHangRepo`) | 0 |
| P07-T08 | | Hạn mức `so_khach_toi_da` | Vượt → 402 |
| P07-T09 | `db_he_so_test.php` | Resolve 3 tầng cho 21 khoá: khách → đại lý → hệ thống | Đúng thứ tự ưu tiên |
| P07-T10 | | Bỏ trống ô (xoá giá riêng) | Kế thừa tầng trên |
| P07-T11 | | Giá sai: chữ, âm, CK > 100 | 422, giá cũ giữ nguyên |
| P07-T12 | | Lưu nhiều giá một lần có 1 ô lỗi | Không ghi ô nào (transaction) — khắc phục lỗi Desktop L4 |
| P07-T13 | | Định dạng số kiểu Việt: `78.000` → 78000, `23,5` → 23.5 | Đúng |
| P07-T14 | | Tăng `he_so.phien_ban` khi sửa giá (khoá cache) | Đúng |
| P07-T15 | `idor_test.php` | Mọi method public của `KhachHangRepo`, `HeSoRepo` dưới tenant khác | Rỗng / 404 / không đổi dữ liệu |
| P07-T16 | `db_rang_buoc_test.php` | FK kép: gắn `he_so_khach_hang` của A vào khách của B (SQL trực tiếp) | DB từ chối |
| P07-T17 | | Trigger: UPDATE `lich_su_doi_soat.tien_ghi` (SQL trực tiếp) | DB từ chối |
| P07-T18 | `http_khach_hang_test.php` | 3 endpoint: thiếu CSRF 403 · hết hạn 402 · id của tenant khác 404 · dữ liệu sai 422 | Đúng mã |
| P07-T19 | | User thường sửa dòng "Hệ thống" | 403 |
| P07-T20 | Hồi quy | P00–P06 | Xanh |

### 5.2 Thủ công (đối chiếu Phụ lục A-KH và preview)
- [ ] P07-M01: Lưới 21 cột; bật/tắt 3 nhóm; cột tên ghim khi cuộn ngang; badge số giá riêng.
- [ ] P07-M02: Sửa ô trên lưới và trong drawer cho cùng kết quả; ô riêng tô vàng, ô kế thừa hiện giá mờ.
- [ ] P07-M03: Thêm khách từ page và từ nút "+" ở Nhập tin (P08 sẽ dùng lại modal).
- [ ] P07-M04: Ẩn khách: modal đúng câu chữ, phải gõ đúng tên, khách biến khỏi lưới.
- [ ] P07-M05: 375 px không cuộn ngang trang (lưới cuộn trong khung).

## 6. Cổng nghiệm thu
README §2 **và**: P07-T01..T20 xanh; `idor_test` + `tenant_scan_test` xanh; Phụ lục A-KH tick đủ.

## 7. Rủi ro & xử lý
| Rủi ro | Xử lý |
|---|---|
| DB production không hỗ trợ cột sinh/trigger | Phát hiện ở §2; phương án thay: UNIQUE trên `(user_id, ten_khach_chuan, xoa_tag)` với `xoa_tag` = 0 khi hoạt động, = id khi xoá; chặn UPDATE tiền chỉ ở repo + test |
| Tạo cả 4 bảng sớm nhưng chưa có repo tiền | Chấp nhận; P08 viết repo, không đổi schema trừ khi cần (khi đó migration mới) |

## 8. Ngoài phạm vi
Lưu phiên đối soát (P08); import giá từ `xoso.db` (P12).

## 9. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
