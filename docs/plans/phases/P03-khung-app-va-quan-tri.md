# P03 — Khung app 6 page · Quản trị tài khoản & thuê bao

| Thuộc tính | Giá trị |
|---|---|
| Slice | S1.6 (khung app), S1.7 (admin tài khoản + Gói & Thuê bao) |
| Phụ thuộc | **P02 ĐẠT** |
| Mở khoá | P06 (cùng P05) |
| Quyết định áp dụng | N4 (bố cục), N8 (ngày 18:30), N13, O5, O8 |
| Thiết kế chuẩn | `docs/ui-preview/` — topbar, sidebar, phím tắt, modal Phím tắt |
| Cỡ | M + L |

## 1. Mục tiêu
Đại lý đăng nhập thấy **khung app hoàn chỉnh** (topbar, sidebar 6 page, phím tắt, đổi mật khẩu, băng thuê bao) với các page còn rỗng nghiệp vụ; admin **tạo/quản lý tài khoản đại lý và thuê bao** từ giao diện. Kết thúc P03 có thể bàn giao tài khoản dùng thử khung.

## 2. Điều kiện bắt đầu
- [ ] P02 ĐẠT.
- [ ] Token màu/khoảng cách lấy từ `docs/ui-preview/app.css` (không vẽ lại).

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Ghi chú |
|---|---|---|
| S1.6 | `app/_layout.php`, `app/assets/app.css`, `app/assets/app.js` (`goiApi()` duy nhất), `lib/bigwin/NgayLamViec.php`, 6 page rỗng `app/{nhap_tin,telegram,kqxs,khach_hang,lich_su,cu_phap}.php`, `app/ajax/doi_mat_khau.php`; xoá `app/ajax/_ping*.php` | Topbar: ô ngày (N8, đổi màu khi không phải ngày làm việc mặc định), đồng hồ, trạng thái KQ (placeholder "chưa có dữ liệu" tới P06), avatar menu (Đổi mật khẩu, Đăng xuất), **băng thuê bao** (còn N ngày ≤ 7 / ân hạn / hết hạn). Sidebar + tab mobile; Alt+1…6 (Alt+7 Quản trị nếu admin); `?` mở bảng phím tắt; khi hết hạn: ẩn nút hành động theo `CheDo` |
| S1.7 | `admin/users.php`, `admin/thue_bao.php`, `lib/bigwin/repo/UserRepo.php`; sửa `admin/_auth.php` dùng `users` + `lib/security` (bỏ `bigwin_admin`) | Tab Tài khoản: tạo (kiểm giấy phép nền tảng, buộc đổi mật khẩu), khoá/mở, đặt lại mật khẩu, đăng xuất mọi nơi, đổi role (≥ 1 admin, không tự hạ/khoá mình). Tab Gói & Thuê bao: CRUD gói, gia hạn, đổi gói, tạm khoá/mở, lịch sử, "Đã dùng X/Y đại lý" |

## 4. Thứ tự thực hiện
1. `NgayLamViec` (hàm thuần) + test.
2. `_layout.php` + `app.css` + `app.js` + 6 page rỗng; so trực quan với preview.
3. Đổi mật khẩu (modal + endpoint).
4. Băng thuê bao + ẩn hành động theo trạng thái.
5. Chuyển `admin/_auth.php` sang `users`; kiểm toàn bộ trang admin cũ còn chạy.
6. `admin/users.php`, `admin/thue_bao.php`.

## 5. Kế hoạch test
### 5.1 Tự động
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P03-T01 | `NgayLamViec::macDinh()` lúc 18:29:59 | Hôm qua |
| P03-T02 | Lúc 18:30:00 | Hôm nay |
| P03-T03 | Lúc 00:05 | Hôm qua (của ngày mới) |
| P03-T04 | HTTP: user chưa đăng nhập GET `app/nhap_tin.php` | 302 → login |
| P03-T05 | HTTP: user role `user` GET `admin/users.php` | 403 |
| P03-T06 | HTTP: admin GET mọi trang `app/*` và `admin/*` | 200 |
| P03-T07 | HTTP: 6 page có `<meta name="csrf-token">`, sidebar đủ 6 mục, user thường không thấy mục Quản trị | Đúng |
| P03-T08 | `app.js`: quét mã — không có `fetch(` ngoài `goiApi()` | 0 vi phạm |
| P03-T09 | Đổi mật khẩu: sai mật khẩu cũ / mới < 10 ký tự / xác nhận lệch | 422 với thông điệp từng trường |
| P03-T10 | Đổi mật khẩu khi thuê bao **hết hạn** | Vẫn được (chế độ `doc`) |
| P03-T11 | Tài khoản hết hạn tải page | HTML có băng "hết hạn", không render nút có `data-che-do="phan_tich"`/`ghi` |
| P03-T12 | Admin tạo đại lý khi đã đạt `so_dai_ly_toi_da` | Bị chặn, thông báo rõ |
| P03-T13 | Admin tạo đại lý | `phai_doi_mat_khau = 1`, có bản ghi `thue_bao` theo gói chọn + 1 dòng lịch sử `tao` |
| P03-T14 | Admin tự khoá mình / hạ quyền admin cuối cùng | Bị chặn |
| P03-T15 | Admin "đăng xuất mọi nơi" cho user X | Mọi `phien_dang_nhap` của X bị thu hồi; request kế của X → login |
| P03-T16 | Form admin POST thiếu CSRF | 403 |
| P03-T17 | Mở khoá đại lý khi vượt giấy phép | Bị chặn |
| P03-T18 | Hồi quy P00–P02; các trang admin cũ (`index`, `owners`, `backups`, `usage`) | 200 với admin |

### 5.2 Thủ công (đối chiếu preview)
- [ ] P03-M01: Topbar, sidebar, tab mobile khớp `docs/ui-preview/index.html` ở 1440 px và 375 px (không cuộn ngang).
- [ ] P03-M02: Alt+1…6 chuyển page; `?` mở bảng phím tắt; Esc đóng.
- [ ] P03-M03: Đăng nhập 17:00 → ô ngày = hôm qua; đổi ngày → ô đổi màu, nút "Về hôm nay".
- [ ] P03-M04: Tài khoản còn 5 ngày → băng vàng; ân hạn → băng đỏ; hết hạn → page vẫn mở được, nút hành động ẩn.
- [ ] P03-M05: Admin tạo đại lý → đăng nhập bằng đại lý đó → bị buộc đổi mật khẩu → vào được app.
- [ ] P03-M06: Trang Gói & Thuê bao: gia hạn 30 ngày → băng của đại lý biến mất ở lần tải sau.
- [ ] P03-M07: Mọi trang admin cũ vẫn hoạt động sau khi bỏ `bigwin_admin`.

## 6. Cổng nghiệm thu
README §2 **và**: P03-T01..T18 xanh; P03-M01..M07 tick; không còn tệp `_ping*.php`.

## 7. Rủi ro & rollback
| Rủi ro | Xử lý |
|---|---|
| Chuyển `admin/_auth.php` làm vỡ trang admin cũ | Làm cuối phase; P03-T18 + P03-M07 bắt buộc; rollback = khôi phục `_auth.php` cũ (bảng `admins` vẫn còn) |
| Tailwind CDN chậm/khác bản preview | Khoá đúng phiên bản như preview (`3.4.16`) |

## 8. Ngoài phạm vi
Nội dung nghiệp vụ của 6 page; thống kê admin (P12).

## 9. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
