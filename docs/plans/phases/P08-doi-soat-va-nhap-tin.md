# P08 — Đối soát · Page Nhập tin

| Thuộc tính | Giá trị |
|---|---|
| Slice | S4.3 (`DoiSoatService` + `TinNhanRepo` + `DoiSoatRepo`), S4.4 (page Nhập tin) |
| Phụ thuộc | **P07 ĐẠT** |
| Mở khoá | P09, P11 |
| Quyết định áp dụng | N2, N3, N7 (Ghi đè/Huỷ), N8, N10 (chế độ sửa), O1, O2, N17 |
| Thiết kế chuẩn | Preview `#mau1`…`#mau4` · luồng §8.1–§8.4 kế hoạch tổng · Phụ lục A-NT |
| Cỡ | L + L |

## 1. Mục tiêu
**Giá trị cốt lõi của sản phẩm:** đại lý nhập tin → thấy tô lỗi tức thời → đối soát & lưu → thấy kết quả phiên; tiền khớp Desktop từng đồng; mọi thay đổi dữ liệu tiền là xoá mềm + snapshot bất biến.

## 2. Điều kiện bắt đầu
- [ ] P07 ĐẠT.
- [ ] Bảng dàn số & tự học **chưa** có DB (P09) → `ParserService` dùng bảng 115 dàn mặc định + quy tắc tự học rỗng; ghi rõ trong code là tạm.

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Ghi chú |
|---|---|---|
| S4.3 | `lib/bigwin/DoiSoatService.php`, `lib/bigwin/repo/TinNhanRepo.php`, `lib/bigwin/repo/DoiSoatRepo.php` | `luu`, `timTrung`, `ghiDe`, `xoaPhien`, `suaPhien`; snapshot + `he_so_ap_dung_json`; `thay_the_boi_msg_id`; đếm `su_dung_ngay` trong transaction; đọc lại xác minh sau commit |
| S4.4 | `app/nhap_tin.php`, `app/ajax/phan_tich.php`, `app/ajax/doi_soat.php`, `app/ajax/phien_xoa.php`, `app/assets/editor.js` | Editor mirror tô đỏ/vàng theo offset ký tự, debounce 300 ms, `AbortController`, chẩn đoán + "nhảy tới", chọn khách (`/`, ↑↓, Enter), KPI, bảng tổng hợp (`bang_tong_hop.js`) + chi tiết từng cược, modal trùng (Ghi đè/Huỷ), **3 chế độ**: nhập mới / đã lưu (mã phiên, đã ghi đè N, trúng/trượt, Xoá phiên) / đang sửa phiên (`?sua=<msg_id>`) |

## 4. Thứ tự thực hiện
1. `TinNhanRepo`, `DoiSoatRepo` (cột bất biến) → test DB.
2. `DoiSoatService::luu` → E2E `pipeline.json` qua DB.
3. Trùng bộ cược + ghi đè → xoá phiên → sửa phiên.
4. `phan_tich.php` + đo p95.
5. Page + editor; 3 chế độ.

## 5. Kế hoạch test
### 5.1 Tự động — Service & DB
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P08-T01 | **E2E parity:** mọi ca `pipeline.json` → `luu()` → đọc lại DB → tổng hợp | Tiền ghi/CK/trúng/thành tiền `===` fixture |
| P08-T02 | Chặn theo thứ tự: chưa KQ → chưa khách → tin trống → còn lỗi → trùng | Đúng mã/thông điệp từng bước |
| P08-T03 | Trùng bộ cược: cùng ngày + khách + nguồn; bộ `loai,so,tien_vnd` khác thứ tự dòng | Phát hiện; trả **mọi** phiên trùng |
| P08-T04 | Cùng bộ cược nhưng khác nguồn (Telegram vs Zalo) / khác ngày | **Không** coi là trùng |
| P08-T05 | Ghi đè | Phiên cũ xoá mềm (`ly_do_xoa='ghi_de'`), `thay_the_boi_msg_id` trỏ phiên mới |
| P08-T06 | Không có lựa chọn "lưu thêm" khi trùng (endpoint từ chối nếu không gửi `ghi_de`) | 409 |
| P08-T07 | Xoá phiên | Xoá mềm cả `tin_nhan_log` và mọi dòng `lich_su_doi_soat` của phiên |
| P08-T08 | Sửa phiên | Bản cũ xoá mềm `sua_phien`, bản mới tính theo giá **hiện tại**, liên kết chuỗi |
| P08-T09 | **Bất biến:** lưu phiên → đổi giá khách → đọc lại phiên | Tiền phiên **không đổi** |
| P08-T10 | `he_so_ap_dung_json` | Chứa đúng hệ số đã dùng |
| P08-T11 | Lỗi giữa transaction (giả lập ném lỗi sau khi ghi `tin_nhan_log`) | Rollback sạch, `su_dung_ngay` không tăng |
| P08-T12 | Hạn mức tin/ngày | Vượt → 402, không ghi gì |
| P08-T13 | Khách của tenant khác trong `khach_id` | 404 |
| P08-T14 | `idor_test` cho `TinNhanRepo`, `DoiSoatRepo` | Xanh |
| P08-T15 | Quét mã: không `DELETE` trên `tin_nhan_log`/`lich_su_doi_soat`; không UPDATE cột tiền | 0 vi phạm |

### 5.2 Tự động — HTTP
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P08-T20 | `phan_tich.php` trả `marks` offset **ký tự** đúng với tin có dấu/emoji | Khớp `rule_parser.json` |
| P08-T21 | `phan_tich.php` p95 (cache trúng) trên 200 tin | < 80 ms |
| P08-T22 | `doi_soat.php` thành công trả dữ liệu chế độ "Đã lưu" | Có `msg_id`, `da_ghi_de`, danh sách trúng/trượt, `tong_hop` |
| P08-T23 | `phien_xoa.php` phiên của tenant khác | 404 |
| P08-T24 | Thuê bao hết hạn: `phan_tich.php`, `doi_soat.php` | 402 |
| P08-T25 | Hồi quy P00–P07 | Xanh |

### 5.3 Thủ công (Phụ lục A-NT — tick từng dòng)
- [ ] P08-M01: Gõ 4 tin mẫu của preview → tô đỏ/vàng đúng chỗ, "nhảy tới" chọn đúng đoạn.
- [ ] P08-M02: Ctrl+Enter lưu → ô nhập **xoá trắng**, hiện chế độ "Đã lưu"; Ctrl+Enter lần 2 không tạo phiên thứ hai.
- [ ] P08-M03: Lưu lại cùng tin → modal trùng chỉ có Ghi đè/Huỷ, liệt kê mã phiên/giờ/số dòng.
- [ ] P08-M04: Chưa chọn khách → mở chọn khách; gõ tên mới → tạo khách.
- [ ] P08-M05: Chưa có KQXS → bảng tổng hợp khung vàng "tạm tính".
- [ ] P08-M06: 17:00 → ngày mặc định hôm qua.
- [ ] P08-M07: **So song song Desktop:** 20 tin thật (ẩn danh) nhập cả Desktop và Web cùng KQ, cùng giá → tổng từng khách khớp 0 đồng.
- [ ] P08-M08: Gõ liên tục 1 phút không giật; mạng chậm (DevTools throttling 3G) vẫn gõ được.

## 6. Cổng nghiệm thu
README §2 **và**: P08-T01..T25 xanh; P08-M07 lệch **0 đồng**; Phụ lục A-NT (trừ mục chế độ sửa từ Lịch sử — hoàn tất ở P10) tick đủ.

## 7. Rủi ro & xử lý
| Rủi ro | Xử lý |
|---|---|
| Lệch tiền chỉ lộ khi qua DB (kiểu cột, làm tròn khi đọc `DOUBLE`) | P08-T01 chạy qua DB thật, không chỉ hàm thuần |
| Offset tô màu lệch với tin chứa emoji (UTF-16 surrogate ở JS) | P08-T20 + M01 với emoji; chuyển offset code point → UTF-16 ở `editor.js` |
| Hiệu năng gõ phím | Cache P05 + đo P08-T21; phương án hạ cấp §8.1d |

## 8. Ngoài phạm vi
Bảng dàn/tự học theo user (P09); học quy tắc khi sửa phiên (P09 nối vào `suaPhien`); mở chế độ sửa từ Lịch sử (P10).

## 9. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
