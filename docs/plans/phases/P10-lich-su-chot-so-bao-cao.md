# P10 — Lịch sử · Chốt sổ · In báo cáo

| Thuộc tính | Giá trị |
|---|---|
| Slice | S6.1 (tab Lịch sử tin), S6.2 (tab Chốt sổ), S6.3 (in Tổng hợp / Chi tiết) |
| Phụ thuộc | **P09 ĐẠT** |
| Mở khoá | P12 (cùng P11) |
| Quyết định áp dụng | N2, N3, N10, O1 (khách đã ẩn vẫn có số), O2 (chỉ cộng snapshot), §13 L1–L3 (không port lỗi Desktop) |
| Thiết kế chuẩn | Preview `#lichsu` · Phụ lục A-LS |
| Cỡ | M + M + M |

## 1. Mục tiêu
Đại lý tra cứu, sửa, xoá phiên đã lưu; chốt sổ cuối ngày theo khách (phiếu chốt copy gửi khách); in báo cáo ngày Tổng hợp / Chi tiết — **mọi con số lấy từ snapshot**, một quy ước dấu.

## 2. Điều kiện bắt đầu
- [ ] P09 ĐẠT.

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Ghi chú |
|---|---|---|
| S6.1 | `app/lich_su.php` (tab Lịch sử tin), `app/ajax/lich_su.php` (GET) | Lọc ngày (topbar) + khách (có "Tất cả", gồm khách đã ẩn có nhãn) + trạng thái OK/Cảnh báo/Lỗi; cột STT/khách/giờ/nguồn/tin/trạng thái/tiền; nút Sửa → `nhap_tin.php?sua=<msg_id>`; Xoá (xoá mềm, xác nhận); xem chuỗi phiên cũ → mới; phân trang server-side; giới hạn tra cứu theo `so_ngay_tra_cuu_lich_su` của gói |
| S6.2 | `app/ajax/chot_so.php` (GET), `app/assets/lich_su.js` | KPI ngày; bảng theo khách (Thành tiền Thu/Trả); mở rộng = bảng tổng hợp (`bang_tong_hop.js`) + phiếu chốt (copy) + danh sách phiên |
| S6.3 | `app/in_bao_cao.php`, `app/assets/print.css` | `@media print`; mẫu Tổng hợp (khách × loại) và Chi tiết (từng tin); nút "In tổng hợp" / "In chi tiết" ở tab Chốt sổ |

## 4. Kế hoạch test
### 4.1 Tự động
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P10-T01 | Tổng chốt sổ theo khách/ngày trên dữ liệu `pipeline.json` đã lưu | `===` fixture |
| P10-T02 | Đổi giá sau khi lưu → chốt sổ | **Không đổi** (chỉ snapshot) |
| P10-T03 | Phiên đã xoá mềm | Không vào tổng; xem được khi bật "hiện cả đã xoá" (nếu có) |
| P10-T04 | Khách đã ẩn có phiên trong ngày | Vẫn vào tổng, nhãn "đã xoá" |
| P10-T05 | Lọc "Tất cả" khách | Tiền dùng snapshot từng dòng (không lặp lỗi L3 của Desktop) |
| P10-T06 | Phiếu chốt: tách Xiên 2/3/4, nhãn theo N2 | Đúng mẫu |
| P10-T07 | `lich_su.php` vượt `so_ngay_tra_cuu_lich_su` | 402 hoặc cắt theo gói (thông điệp rõ) |
| P10-T08 | `lich_su.php`/`chot_so.php` với tài khoản **hết hạn** | 200 (chế độ `doc`) |
| P10-T09 | `idor_test` cho truy vấn lịch sử / chốt sổ (khách_id của tenant khác) | Rỗng / 404 |
| P10-T10 | Quét mã: `chot_so.php`, `in_bao_cao.php` không gọi `TinhTien` | 0 vi phạm |
| P10-T11 | Hồi quy P00–P09 | Xanh |

### 4.2 Thủ công (Phụ lục A-LS)
- [ ] P10-M01: Sửa phiên từ Lịch sử → Nhập tin chế độ sửa → lưu → Lịch sử hiển thị phiên mới, phiên cũ là "đã thay".
- [ ] P10-M02: Copy phiếu chốt → dán vào Zalo hiển thị đúng định dạng.
- [ ] P10-M03: In tổng hợp & chi tiết ra PDF (Chrome) — đúng 1 quy ước dấu, không vỡ trang giữa dòng bảng.
- [ ] P10-M04: **So Desktop:** cùng dữ liệu 1 ngày → tổng từng khách khớp; khác biệt chỉ ở các lỗi Desktop đã liệt kê (§13 L1–L3) và phải ghi rõ.

## 5. Cổng nghiệm thu
README §2 **và**: P10-T01..T11 xanh; Phụ lục A-LS tick đủ; mục "chế độ sửa từ Lịch sử" của A-NT tick.

## 6. Rủi ro & xử lý
| Rủi ro | Xử lý |
|---|---|
| Báo cáo in chậm với ngày nhiều tin | Truy vấn tổng hợp bằng SQL (`SUM` trên snapshot) qua `truyVan()`; phân trang danh sách |

## 7. Ngoài phạm vi
Thống kê toàn hệ thống (P12).

## 8. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
