# P04 — Lõi cú pháp 1: SequenceMatcher · HeuristicCleaner · Đối chiếu nghi ngờ

| Thuộc tính | Giá trị |
|---|---|
| Slice | S2.1 (SequenceMatcher), S2.2 (HeuristicCleaner — 2 chế độ), S2.6 (DiffAnalyzer + DoiChieuTin) |
| Phụ thuộc | **P00 ĐẠT** (fixture từ `port-baseline`). Không phụ thuộc P01–P03 (PURE, không DB) |
| Mở khoá | P05 |
| Quy tắc bắt buộc | `.claude/rules/bigwin-core.md` (PURE, `bw_re`, `bw_finditer`, `mb_*`, `PHP_ROUND_HALF_EVEN`, `SORT_STRING`) · kỹ thuật regex v1 §4.1 |
| Cỡ | M + L + M |

## 1. Mục tiêu
Port 3 khối PURE nền móng của pipeline phân tích tin, **khớp 100% golden fixture**:
`SequenceMatcher` (difflib, không autojunk) → `HeuristicCleaner` (cả `bungDanKep = true` lẫn `false`) → `DiffAnalyzer` + `DoiChieuTin::danhDauNghiNgo`.

## 2. Điều kiện bắt đầu
- [ ] P00 ĐẠT; fixture `seqmatch`, `cleaner`, `cleaner_moi`, `learning`, `doi_chieu` có `meta.bigwin_commit` = tag.
- [ ] Nguồn Python đọc từ **tag `port-baseline`**, không đọc working tree.

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Nguồn Python |
|---|---|---|
| S2.1 | `lib/bigwin/SequenceMatcher.php`, `tests/seqmatch_test.php` | `difflib.SequenceMatcher` (`find_longest_match`, `get_matching_blocks`, `get_opcodes`, `ratio`) trên **mảng phần tử** |
| S2.2 | `lib/bigwin/HeuristicCleaner.php`, `tests/cleaner_test.php` | `core/heuristic_cleaner.py` (1169 dòng) — giữ **nguyên thứ tự** bảng luật và pipeline |
| S2.6 | `lib/bigwin/DiffAnalyzer.php`, `lib/bigwin/DoiChieuTin.php`, `tests/doi_chieu_test.php` | `utils/diff_analyzer.py`, `core/doi_chieu_tin.py` |

## 4. Thứ tự thực hiện
1. S2.1 → chạy `seqmatch.json` tới 100%.
2. S2.2 theo từng khối của pipeline Python: chuẩn hoá ký tự ẩn → tiếng lóng tiền → từ khoá có dấu → quy tắc tự học → bung dàn kép (chỉ khi `bungDanKep`) → các luật còn lại. Sau mỗi khối chạy fixture để khoanh vùng lệch.
3. S2.6 dùng `SequenceMatcher` của S2.1.
4. Đo thời gian chạy cả corpus (ghi số liệu vào nhật ký làm mốc cho P05 S2.7).

## 5. Kế hoạch test
### 5.1 Tự động
| Mã | Tệp | Kiểm tra | Kỳ vọng |
|---|---|---|---|
| P04-T01 | `seqmatch_test.php` | 100% ca `seqmatch.json` (cấp ký tự + cấp token): `opcodes`, `ratio` | `===` |
| P04-T02 | `seqmatch_test.php` | Ca biên: mảng rỗng, 1 phần tử, trùng hoàn toàn, khác hoàn toàn | `===` với fixture |
| P04-T03 | `cleaner_test.php` | 100% `cleaner.json` (`bungDanKep = true`) | `===` |
| P04-T04 | `cleaner_test.php` | 100% `cleaner_moi.json` (`bungDanKep = false`) | `===` |
| P04-T05 | `cleaner_test.php` | `chuan_hoa_ky_tu_an` giữ nguyên độ dài (`mb_strlen`) trên toàn corpus | Đúng |
| P04-T06 | `cleaner_test.php` | Tin NFD (tổ hợp rời) và NFC cho cùng kết quả như Python | `===` |
| P04-T07 | `cleaner_test.php` | Làm tròn tiếng lóng `x.5` theo banker's rounding | Khớp fixture |
| P04-T08 | `doi_chieu_test.php` | 100% `doi_chieu.json`: đoạn nghi ngờ + offset **ký tự** | `===` |
| P04-T09 | `doi_chieu_test.php` | `trich_xuat_tat_ca_khac_biet` trên cặp của `learning.json` | `===` |
| P04-T10 | `core_pure_scan_test.php` (mới) | `lib/bigwin/` (trừ `repo/`, `*Service.php`) không gọi `db()`, PDO, `curl_`, `session_`, `echo`, `date(`, `time(`, `new DateTime(` không múi giờ, `strlen(`/`substr(` trên văn bản, regex trần `'/…/'` không qua `bw_re` | 0 vi phạm |
| P04-T11 | Hồi quy | P00–P03 | Xanh |

### 5.2 Thủ công
- [ ] P04-M01: Chọn 10 ca lệch từng gặp trong quá trình port (ghi trong nhật ký), giải thích nguyên nhân + cách sửa — để tránh lặp ở P05.
- [ ] P04-M02: Ghi thời gian chạy cả corpus (cleaner 2 chế độ) vào nhật ký.

## 6. Cổng nghiệm thu
README §2 **và**: P04-T01..T11 xanh — **không có ca nào bị bỏ qua/đánh dấu "biết lệch"**. Fixture lệch = bug PHP (không sửa fixture).

## 7. Rủi ro & xử lý
| Rủi ro | Xử lý |
|---|---|
| `\d`/`\w`/`\b` Unicode khác Python | Luôn `bw_re()` (`/u` + `(*UCP)`); fixture có ký tự đặc biệt sẽ bắt lệch |
| Offset byte vs ký tự | Chỉ dùng `bw_finditer()` |
| `array_unique`/khoá mảng biến `"05"` thành số | Cấm theo rule; dedup bằng `$daCo["k:$x"]` |
| Cleaner quá chậm | Chưa tối ưu ở phase này; ghi số liệu, xử lý bằng cache ở P05 S2.7 |

## 8. Ngoài phạm vi
MessageParser, RuleParser, dàn số (P05). Không có UI, không DB.

## 9. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
