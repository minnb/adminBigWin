# P12 — Thống kê admin · Quyền admin trên page · Import `xoso.db`

| Thuộc tính | Giá trị |
|---|---|
| Slice | S8.1 (Thống kê), S8.2 (quyền admin trên page 4 và 6), S8.3 (import — theo O7) |
| Phụ thuộc | **P10 ĐẠT** + **P11 ĐẠT** (hoặc P11 được hoãn có biên bản) |
| Mở khoá | P13 |
| Quyết định áp dụng | Q5 (admin chỉ thấy số tổng hợp), O6, O7, §13 L5 |
| Cỡ | M + S + M |

## 1. Mục tiêu
Admin theo dõi được toàn hệ thống **không thấy nội dung tin**; sửa giá hệ thống và bộ dàn gốc ngay trên page có sẵn; (tuỳ O7) chuyển dữ liệu `xoso.db` của Desktop vào một tài khoản đại lý.

## 2. Điều kiện bắt đầu
- [ ] P10 ĐẠT; P11 ĐẠT hoặc có biên bản hoãn.
- [ ] O7 đã chốt (có import hay không, cho tài khoản nào). Nếu "không" → bỏ S8.3, ghi nhật ký.

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Ghi chú |
|---|---|---|
| S8.1 | `admin/thong_ke.php`, `lib/bigwin/repo/ThongKeRepo.php` | Theo ngày/đại lý: số tin, tổng ghi, thành tiền (từ snapshot), mức dùng hạn mức, ngày hết hạn; **truy vấn chéo tenant duy nhất được phép** |
| S8.2 | sửa `app/khach_hang.php`, `app/cu_phap.php`, `app/ajax/he_so_he_thong_luu.php`, `app/ajax/dan_so_goc_luu.php` | Dòng "Hệ thống" (page 4) và bộ dàn gốc (page 6) mở sửa khi role admin; ghi qua `SharedRepo` |
| S8.3 | `tools/import_sqlite.php` | `--dry-run`; gán `user_id`; `dd-mm-yyyy` → `DATE`; làm sạch FK mồ côi; bỏ hệ số trùng mặc định (L5); dữ liệu tiền nhập **nguyên snapshot** của Desktop |

## 4. Kế hoạch test
### 4.1 Tự động
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P12-T01 | Quét mã: `ThongKeRepo` không SELECT cột `noi_dung`, `noi_dung_kiem_tra`, `text` | 0 |
| P12-T02 | `tenant_scan_test`: chỉ `ThongKeRepo` được truy vấn chéo tenant | Đúng |
| P12-T03 | Số liệu thống kê = tổng snapshot từng tenant | `===` |
| P12-T04 | User thường gọi `he_so_he_thong_luu.php` / `dan_so_goc_luu.php` | 403 |
| P12-T05 | Admin sửa giá hệ thống → khách không có giá riêng ở mọi tenant nhận giá mới cho **tin mới**; phiên cũ không đổi | Đúng |
| P12-T06 | Admin sửa dàn gốc → user đã có **không** đổi; "Khôi phục dàn gốc" của user nhận bản mới | Đúng (O6) |
| P12-T07 | Import `--dry-run` trên `xoso.db` mẫu ẩn danh | Báo cáo số dòng/bảng, FK mồ côi, không ghi DB |
| P12-T08 | Import thật | Số dòng + tổng tiền/ngày trước = sau |
| P12-T09 | Import chạy 2 lần | Không nhân đôi (khoá tự nhiên `msg_id`) |
| P12-T10 | Hồi quy P00–P11 | Xanh |

### 4.2 Thủ công
- [ ] P12-M01: Trang Thống kê: lọc theo ngày/đại lý; không có chỗ nào hiện nội dung tin.
- [ ] P12-M02: Đăng nhập admin → page Khách hàng thấy dòng Hệ thống sửa được; đăng nhập đại lý → chỉ xem.
- [ ] P12-M03: Import `xoso.db` thật (nếu O7 = có) vào tài khoản chỉ định → so 3 ngày bất kỳ với Desktop.

## 5. Cổng nghiệm thu
README §2 **và**: P12-T01..T10 xanh (T07–T09 bỏ nếu O7 = không).

## 6. Rủi ro & xử lý
| Rủi ro | Xử lý |
|---|---|
| Dữ liệu `xoso.db` có FK mồ côi / ngày sai định dạng | `--dry-run` liệt kê trước; quy tắc làm sạch ghi trong báo cáo import |
| Thống kê làm rò dữ liệu | Chỉ số tổng hợp; quét mã P12-T01 |

## 7. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
