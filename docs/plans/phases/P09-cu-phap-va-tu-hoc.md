# P09 — Cú pháp (bảng dàn số) · Bộ nhớ tự học

| Thuộc tính | Giá trị |
|---|---|
| Slice | S5.1 (LearningService + BoNhoRepo), S5.2 (bảng dàn số theo user), S5.3 (tab Dàn số), S5.4 (tab Bộ nhớ tự học + Hướng dẫn) |
| Phụ thuộc | **P08 ĐẠT** |
| Mở khoá | P10 |
| Quyết định áp dụng | N1, N11 (hàng rào tự học đầy đủ), O6 (dàn gốc không tự lan) |
| Thiết kế chuẩn | Preview `#dan`, `#quytac`, `#anhhuong`, `#khoiphuc` · Phụ lục A-CP, A-TH |
| Cỡ | L + M + L + M |

## 1. Mục tiêu
Mỗi đại lý có **bảng dàn số riêng** (bản sao 115 dàn gốc) và **bộ nhớ tự học riêng**; sửa dàn / thêm quy tắc qua đầy đủ hàng rào an toàn, có đo ảnh hưởng; thay đổi áp dụng **ngay ở lần phân tích kế** (vô hiệu cache). `ParserService` chuyển từ bảng mặc định tạm (P08) sang dữ liệu của user.

## 2. Điều kiện bắt đầu
- [ ] P08 ĐẠT; fixture `learning`, `dan_so` từ tag.

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Ghi chú |
|---|---|---|
| S5.1 | `lib/bigwin/LearningService.php`, `lib/bigwin/repo/BoNhoRepo.php`, `tests/learning_test.php` | Cổng ghi **duy nhất** `bo_nho_sua_tin`; 5 guard + shadow test 16 tin chuẩn trên bảng dàn của user; giới hạn theo user; `hocTuLanSuaTin`, `themQuyTacThuCong`, `ghiNhanLuotApDung`; nối vào `DoiSoatService::suaPhien` và `luu` |
| S5.2 | `migrations/2026-11-xx_dan_so_tu_hoc.sql`, `schema.sql`, `lib/bigwin/repo/DanSoRepo.php`, `tools/seed_dan_so.php` | `parser_dan_so_goc*` (dùng chung), `parser_dan_so*` (có `user_id`, `phien_ban`), `bo_nho_sua_tin`; copy bộ gốc khi tạo user (nối vào P03 `admin/users.php`); khôi phục kéo bộ gốc **hiện tại** |
| S5.3 | `app/cu_phap.php` (tab Dàn số), `app/ajax/dan_so_kiem_tra.php`, `dan_so_luu.php`, `dan_so_xoa.php`, `dan_so_khoi_phuc.php` | Lọc nhóm, bảng 6 cột, form 50-50, chọn nhanh 00–99, modal đo ảnh hưởng (tin chuẩn + tin thử tự sinh + N tin thật gần nhất), khôi phục phải gõ `KHOI PHUC` |
| S5.4 | `app/cu_phap.php` (tab Tự học, tab Hướng dẫn & Quy tắc), `app/ajax/tu_hoc_them.php`, `tu_hoc_xoa.php` | Bảng quy tắc, thêm tay qua `LearningService`, xoá nhiều có xác nhận, trạng thái rỗng, lý do từ chối theo từng hàng rào; tab Hướng dẫn chỉ đọc. **Chuyển tab Tự học ra khỏi page Lịch sử** (bố cục đã duyệt) |

## 4. Thứ tự thực hiện
1. S5.2 migration + `DanSoRepo` → `ParserService` đọc bảng của user qua cache (`rp:{user}:{phien_ban}`).
2. S5.1 `LearningService` → `learning.json` → nối vào luồng lưu/sửa phiên.
3. S5.3 → S5.4 UI.

## 5. Kế hoạch test
### 5.1 Tự động
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P09-T01 | 100% `learning.json` (`du_sach`, `cac_cap`, `quy_tac_hop_le`) | `===` |
| P09-T02 | Hàng rào số: so danh sách `\d+` **đúng thứ tự** (`12 3` ≠ `1 23`); cụm gốc phải có chữ | Từ chối đúng lý do |
| P09-T03 | 16 từ khoá cấm; độ dài gốc 2–60, thay ≤ 60; hai vế giống nhau; đệ quy tự bọc | Từ chối đúng lý do |
| P09-T04 | Shadow test: quy tắc làm đổi kết quả 1 trong 16 tin chuẩn | Từ chối |
| P09-T05 | Quét mã: chỉ `LearningService` gọi ghi `BoNhoRepo`; không nơi nào khác INSERT/UPDATE `bo_nho_sua_tin` | 0 vi phạm |
| P09-T06 | Thêm quy tắc → `phan_tich.php` lần kế áp dụng ngay | Đúng (cache `th:` đổi phiên bản) |
| P09-T07 | User mới có đủ 115 dàn, `nguon='goc'` | Đúng |
| P09-T08 | Sửa dàn gốc → `nguon='sua'`; thêm dàn → `nguon='user'`; `phien_ban` tăng | Đúng |
| P09-T09 | `dan_so_kiem_tra.php` với các ca lỗi của Phụ lục A-CP | Đủ lỗi như `kiem_tra_dan` |
| P09-T10 | Số trùng trong dàn | Là **lỗi** (không tự lọc khi lưu); thứ tự số giữ như nhập |
| P09-T11 | Khoá trùng dàn khác **đang tắt** | Lỗi |
| P09-T12 | Đo ảnh hưởng gồm tin thật gần nhất của **chính user** (không lẫn tenant khác) | Đúng |
| P09-T13 | Khôi phục dàn gốc: xoá dàn `user`/`sua`, nạp bộ gốc hiện tại; admin sửa gốc sau đó **không** lan sang user cũ | Đúng (O6) |
| P09-T14 | `idor_test` cho `DanSoRepo`, `BoNhoRepo` | Xanh |
| P09-T15 | Sửa phiên (P08) giờ gọi `hocTuLanSuaTin` và trả số quy tắc đã nhớ | Đúng |
| P09-T16 | Toàn bộ `pipeline.json` vẫn khớp khi `ParserService` đọc bảng từ DB | `===` |
| P09-T17 | Hồi quy P00–P08 | Xanh |

### 5.2 Thủ công
- [ ] P09-M01: Tab Dàn số khớp preview (form 50-50, chọn nhanh 00–99, modal ảnh hưởng hiển thị trước → sau, số thêm/bớt).
- [ ] P09-M02: Tạo dàn "Dàn đẹp" khoá `dan dep` → nhập `de dan dep x10k` ở Nhập tin → bung đúng số.
- [ ] P09-M03: Tab Tự học: thêm `xjen → xien` được; thêm `12 3 → 1 23` bị từ chối với lý do rõ; xoá nhiều có xác nhận.
- [ ] P09-M04: Sửa một phiên ở Nhập tin (chế độ sửa) → thông báo "Đã ghi nhớ N quy tắc" → quy tắc xuất hiện ở tab Tự học.
- [ ] P09-M05: Page Lịch sử không còn tab Tự học; page Cú pháp có 3 tab.

## 6. Cổng nghiệm thu
README §2 **và**: P09-T01..T17 xanh; Phụ lục A-CP và A-TH tick đủ.

## 7. Rủi ro & xử lý
| Rủi ro | Xử lý |
|---|---|
| Quy tắc tự học xấu lọt qua làm đổi tiền | 5 guard + shadow test; P09-T02..T04 |
| Cache dàn cũ sau khi sửa | `phien_ban` tăng trong cùng transaction ghi; P09-T06 |

## 8. Ngoài phạm vi
Admin sửa bộ dàn gốc qua UI (P12 S8.2 — trong phase này chỉ có seed/CLI).

## 9. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
