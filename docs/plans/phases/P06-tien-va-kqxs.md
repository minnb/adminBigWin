# P06 — Tiền · Dò trúng · Tổng hợp · KQXS

| Thuộc tính | Giá trị |
|---|---|
| Slice | S3.1 (TinhTien, DoTrung, TongHop), S3.2 (bảng KQ + Scraper + cron), S3.3 (page KQXS) |
| Phụ thuộc | **P03 ĐẠT** (khung app) + **P05 ĐẠT** (lõi parse) |
| Mở khoá | P07 |
| Quyết định áp dụng | N2 (quy ước dấu), N3 (bảng tổng hợp 1 component), N8, N9 (cron KQXS) · quy tắc ép kiểu tiền v1 §4.2 |
| Cỡ | M + M + M |

## 1. Mục tiêu
1. Tính tiền ghi / CK / trúng và dò trúng **khớp từng đồng** với Desktop; bảng tổng hợp 7 cột theo đúng `tong_hop_doi_soat_bang_bieu`.
2. Có KQXS thật trong DB (cron + nút cập nhật), page **Kết quả XSMB** hoạt động như preview.
3. Có renderer dùng chung `app/assets/bang_tong_hop.js` — **chỉ hiển thị**, số liệu do PHP tính.

## 2. Điều kiện bắt đầu
- [ ] P03, P05 ĐẠT.
- [ ] Fixture `tinh_tien`, `do_trung`, `tong_hop`, `pipeline` từ tag.

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Ghi chú |
|---|---|---|
| S3.1 | `lib/bigwin/TinhTien.php`, `DoTrung.php`, `TongHop.php`, `tests/tien_test.php`, `app/assets/bang_tong_hop.js` | `TinhTien` nhận hệ số **đã resolve** (không đọc DB); `TongHop::bang()` trả `{rows, tong, soTrung}` với `thanhTien = tien − trung − ck`; renderer port từ `veBangTongHop()` của preview, nhận JSON |
| S3.2 | `migrations/2026-11-xx_ket_qua_xo_so.sql`, `schema.sql`, `lib/bigwin/Scraper.php`, `lib/bigwin/repo/SharedKetQuaRepo.php`, `tools/scrape_xsmb.php` | `ket_qua_xo_so`, `scraper_log`; cURL + DOMXPath + 2 fallback; chỉ ghi khi đủ 27 giải; `GET_LOCK('scrape_<ngay>')`; cron `*/5 18-19 * * *` + 06:00 bù |
| S3.3 | `app/kqxs.php`, `app/ajax/kq_lay.php`, `lib/bigwin/KetQuaService.php` | Bảng 27 giải, đầu–đuôi (+ cột số nháy theo đầu), lô nhiều nháy, chỉ 2 số cuối, panel 3 ngày trước, nút cập nhật; trạng thái KQ trên topbar nối dữ liệu thật |

## 4. Thứ tự thực hiện
1. `TinhTien` → `tinh_tien.json`; `DoTrung` → `do_trung.json`; `TongHop` → `tong_hop.json`.
2. Ghép parse (P05) + tiền + dò trúng + tổng hợp → chạy phần tiền của `pipeline.json` (chưa DB).
3. `bang_tong_hop.js` + trang demo nội bộ hiển thị 1 ca `tong_hop.json` để soát giao diện.
4. Bảng KQ + Scraper (test với HTML mẫu lưu sẵn, không gọi mạng trong test) → tool CLI.
5. Page KQXS + endpoint.

## 5. Kế hoạch test
### 5.1 Tự động
| Mã | Tệp | Kiểm tra | Kỳ vọng |
|---|---|---|---|
| P06-T01 | `tien_test.php` | 100% `tinh_tien.json` (4.800 ca, gồm khách 7 hệ số lẻ) | `===` từng đồng |
| P06-T02 | `tien_test.php` | 100% `do_trung.json` | `===` |
| P06-T03 | `tien_test.php` | 100% `tong_hop.json`: thứ tự loại DE, LO, LX2, LX3, LX4, 3C; `diemTrung` của Lô; `thanhTien` từng dòng + tổng | `===` |
| P06-T04 | `pipeline_tien_test.php` | Tin → bảng tổng hợp cuối cho 3 bộ hệ số trong `pipeline.json` | `===` |
| P06-T05 | `tien_test.php` | Dấu: tiền ghi 950.000, trúng 8.000.000, CK 7.500 | `thanhTien = −7.057.500` → nhãn "PHẢI TRẢ KHÁCH" |
| P06-T06 | `scraper_test.php` | Parse 3 trang HTML mẫu (bình thường / đổi cấu trúc → fallback dòng / chỉ còn regex) | Đủ 27 giải, đúng độ dài từng giải |
| P06-T07 | `scraper_test.php` | HTML thiếu giải (đang quay) | Không ghi; trả trạng thái "chưa đủ" |
| P06-T08 | `db_ket_qua_test.php` | Ghi 2 lần cùng ngày | Không trùng (`UNIQUE` + `INSERT IGNORE`) |
| P06-T09 | `db_ket_qua_test.php` | 2 tiến trình scrape cùng ngày | Một tiến trình chờ/bỏ qua nhờ `GET_LOCK`; dữ liệu đúng |
| P06-T10 | `db_ket_qua_test.php` | `scraper_log` có bản ghi cho mỗi lần chạy (thành công/thất bại) | Đúng |
| P06-T11 | `http_kqxs_test.php` | `kq_lay.php` ngày tương lai / hôm nay trước 18:30 | 422 với thông điệp |
| P06-T12 | `http_kqxs_test.php` | Gọi dồn `kq_lay.php` | 429 sau ngưỡng |
| P06-T13 | `http_kqxs_test.php` | User thường gọi ghi KQ trực tiếp qua `SharedKetQuaRepo` | Bị từ chối (chỉ CLI/admin ghi) |
| P06-T14 | `http_kqxs_test.php` | Thuê bao hết hạn gọi `kq_lay.php` | 402 |
| P06-T15 | `core_pure_scan_test.php` | `TinhTien`, `DoTrung`, `TongHop` PURE | 0 vi phạm |
| P06-T16 | Hồi quy | P00–P05 | Xanh |

### 5.2 Thủ công
- [ ] P06-M01: Chạy `php tools/scrape_xsmb.php <3 ngày gần nhất>` → so 27 giải với kết quả trên `xoso.db` của Desktop / nguồn minhngoc.
- [ ] P06-M02: Page KQXS khớp preview (`#kqxs`): bảng giải, đầu–đuôi có cột số nháy, lô nhiều nháy, công tắc 2 số cuối, panel 3 ngày trước.
- [ ] P06-M03: Trạng thái topbar: "Chưa có KQ" → "Đủ 27 giải" sau khi cập nhật; nhãn đếm số giải thật.
- [ ] P06-M04: Trang demo `bang_tong_hop.js` hiển thị đúng 3 ca: có trúng Lô nhiều nháy, PHẢI TRẢ, chưa có KQ (khung vàng "tạm tính").

## 6. Cổng nghiệm thu
README §2 **và**: P06-T01..T16 xanh — tiền lệch **0 đồng** trên toàn bộ fixture; P06-M01..M04 tick.

## 7. Rủi ro & xử lý
| Rủi ro | Xử lý |
|---|---|
| Lệch ép kiểu float→int | Theo đúng bảng v1 §4.2 (thứ tự nhân, `(int)` cắt về 0); fixture 4.800 ca |
| Trang nguồn KQ đổi cấu trúc | 3 tầng fallback + HTML mẫu trong test; `scraper_log` để theo dõi |
| Test gọi mạng thật | Cấm; chỉ HTML mẫu trong `tests/fixtures/html/` |

## 8. Ngoài phạm vi
Lưu phiên đối soát (P08); hệ số theo khách từ DB (P07).

## 9. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
