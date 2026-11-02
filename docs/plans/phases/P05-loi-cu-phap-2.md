# P05 — Lõi cú pháp 2: MessageParser · RuleParser · Dàn số · Cache

| Thuộc tính | Giá trị |
|---|---|
| Slice | S2.3 (MessageParser — lõi nền), S2.4 (DanSoMacDinh + RuleParser + ParserService), S2.5 (QuanLyDanSo), S2.7 (BoNhoDem + đo hiệu năng) |
| Phụ thuộc | **P04 ĐẠT** |
| Mở khoá | P06 (cùng P03) |
| Quyết định áp dụng | N1 (chỉ engine Mới), N17 (parser chỉ ở server + cache nhiều tầng) |
| Cỡ | L + L + M + M |

## 1. Mục tiêu
Có **engine phân tích duy nhất** của web: `ParserService` = cleaner (`bungDanKep=false`) → `RuleParser` (bảng 115 dàn) → đối chiếu nghi ngờ, **khớp 100% `rule_parser.json`**; quy tắc kiểm dàn số khớp `dan_so.json`; có cache artefact đạt mục tiêu hiệu năng §8.1.

## 2. Điều kiện bắt đầu
- [ ] P04 ĐẠT.
- [ ] Đọc `core/rule_parser.py` tại tag: các móc `_phan_tich`, `_dung_pattern` (điểm neo xuất hiện đúng 2 lần), `_bo_dau`, `_duoc_bo_dau`.

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Ghi chú |
|---|---|---|
| S2.3 | `lib/bigwin/MessageParser.php`, `tests/parser_test.php` | Port **đầy đủ** để dùng `parser.json` làm lưới an toàn cho đường chạy chung; thiết kế `phanTich(tin, pattern, cheCum, demTokenSo, xuLyBet, canhBaoGap)` có móc giống Python. Không lộ ra như một engine cho người dùng |
| S2.4 | `lib/bigwin/DanSoMacDinh.php`, `lib/bigwin/RuleParser.php`, `lib/bigwin/ParserService.php`, `tests/rule_parser_test.php` | `DanSoMacDinh` sinh 115 dàn đúng thứ tự số; `RuleParser` dùng lại móc S2.3; `ParserService` 1 engine, nhận bảng dàn + quy tắc tự học làm tham số (chưa đọc DB — DB ở P09) |
| S2.5 | `lib/bigwin/QuanLyDanSo.php`, `tests/dan_so_test.php` | `tachDanhSachSo`, `tachKhoa` (phẩy **hoặc** xuống dòng), `kiemTraDan` (đủ luật Phụ lục A-CP), `anhHuong`, `thayDan`, `boDan` |
| S2.7 | `lib/bigwin/BoNhoDem.php`, tách `RuleParser::bienDich()` / `chay()`, `tools/do_hieu_nang_parser.php` | Driver L0 static · L1 APCu · L2 Redis/Memcached · L3 tệp PHP + OPcache; tự chọn theo môi trường |

## 4. Thứ tự thực hiện
1. S2.3: port pattern nguyên chuỗi → `_parse_tien` → vòng `_phan_tich` → markup/span/lỗi → chạy `parser.json`.
2. Test điểm neo pattern (R2) trước khi viết RuleParser.
3. S2.4: `DanSoMacDinh` → so 115 dàn với `rule_parser.json`/Python → `RuleParser` → `ParserService`.
4. S2.5.
5. S2.7: tách biên dịch/chạy → `BoNhoDem` → đo.

## 5. Kế hoạch test
### 5.1 Tự động
| Mã | Tệp | Kiểm tra | Kỳ vọng |
|---|---|---|---|
| P05-T01 | `parser_test.php` | 100% `parser.json`: `items`, `spans`, `errors` (mã + offset), `error_summary`, `can_save` | `===` |
| P05-T02 | `parser_test.php` | Pattern MessageParser chứa điểm neo `(?:ch[aăẵ][̀-ͯ]*n|` đúng 2 lần (như `rule_parser.py:62-66`) | Đúng |
| P05-T03 | `rule_parser_test.php` | `DanSoMacDinh::tao()` | 115 dàn; số lượng theo nhóm: 15 bộ · 11 tổng · 50 chia · 10 chạm · 10 đầu · 10 đít · 9 dàn; thứ tự số từng dàn khớp Python |
| P05-T04 | `rule_parser_test.php` | 100% `rule_parser.json` | `===` |
| P05-T05 | `rule_parser_test.php` | `_bo_dau` giữ nguyên độ dài trên toàn corpus | Đúng |
| P05-T06 | `rule_parser_test.php` | Khoá ≥ 2 từ & mỗi từ ≥ 3 ký tự khớp khi gõ thiếu dấu; khoá 1 từ (`le`) **không** khớp `lẻ` | Như Python |
| P05-T07 | `rule_parser_test.php` | Bảng dàn rỗng / dàn tắt hết | Không ném lỗi; tin chỉ có số thường vẫn parse |
| P05-T08 | `dan_so_test.php` | 100% `dan_so.json` (`tach_*`, `kiem_tra_dan` ≥ 200 ca, `anh_huong` ≥ 30 ca) | `===` |
| P05-T09 | `dan_so_test.php` | `anhHuong` có tin thử tự sinh `de|lo <khoá> x10k` cho mọi dàn thay đổi | Khớp fixture |
| P05-T10 | `pipeline_parse_test.php` | `ParserService::phanTich` (cleaner mới → RuleParser) trên phần parse của `pipeline.json` | `===` |
| P05-T11 | `cache_test.php` | Chạy `rule_parser.json` ở 3 chế độ: tắt cache / cache trượt / cache trúng (L3 tệp) | Kết quả giống hệt nhau |
| P05-T12 | `cache_test.php` | Đổi `phien_ban` bảng dàn → khoá cache mới; khoá cũ không được dùng | Đúng |
| P05-T13 | `cache_test.php` | Ghi tệp cache nguyên tử (`rename`) khi 2 tiến trình ghi cùng khoá | Tệp hợp lệ, không hỏng |
| P05-T14 | `cache_test.php` | Môi trường không có APCu/Redis | Tự dùng L3, không lỗi |
| P05-T15 | Hồi quy | P00–P04 | Xanh |

### 5.2 Đo hiệu năng (`tools/do_hieu_nang_parser.php`, 1.300 tin)
| Mã | Chỉ số | Mục tiêu |
|---|---|---|
| P05-H01 | p95 `ParserService::phanTich` khi cache trúng | < 30 ms |
| P05-H02 | p95 khi cache trượt (biên dịch lại artefact) | < 150 ms |
| P05-H03 | Thời gian biên dịch artefact 115 dàn | Ghi số liệu |

### 5.3 Thủ công
- [ ] P05-M01: So 20 tin đặc trưng (kép, sát kép, bộ, tổng, chạm, đầu/đít, xiên quay, tiếng lóng) giữa Python và PHP bằng mắt trên markup.
- [ ] P05-M02: Ghi kết quả đo P05-H01..H03 vào nhật ký (kèm cấu hình máy, có/không OPcache).

## 6. Cổng nghiệm thu
README §2 **và**: P05-T01..T15 xanh; P05-H01, H02 đạt trên máy dev **có bật OPcache** (nếu không đạt → áp phương án hạ cấp §8.1d và ghi lại).

## 7. Rủi ro & xử lý
| Rủi ro | Xử lý |
|---|---|
| R2 — RuleParser phụ thuộc chi tiết `_PATTERN` | Port nguyên chuỗi; P05-T02 bảo vệ |
| Cache trả kết quả khác không cache | P05-T11 bắt buộc; artefact chỉ chứa dữ liệu thuần |
| Thư mục cache trong docroot | `var/cache/` ngoài docroot hoặc `.htaccess` chặn — kiểm ở P13 |

## 8. Ngoài phạm vi
Đọc/ghi bảng dàn số và quy tắc tự học từ DB (P09); tính tiền (P06).

## 9. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
