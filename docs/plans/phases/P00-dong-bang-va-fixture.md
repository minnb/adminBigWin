# P00 — Đóng băng BigWIN & fixture engine Mới

| Thuộc tính | Giá trị |
|---|---|
| Slice | S0.4 (đóng băng), S0.5 (fixture engine Mới) — kế hoạch tổng §9 G0b |
| Phụ thuộc | Không có phase trước. **Bước 1–3 do chủ repo BigWIN thực hiện** (R1, O4) |
| Song song với | P01 → P03 |
| Mở khoá | P04 |
| Cỡ | S + M |

## 1. Mục tiêu
Có một **mốc mã nguồn Python bất biến** (`port-baseline`) và **bộ golden fixture đầy đủ cho engine Mới** sinh từ đúng mốc đó — nền để mọi phase lõi (P04, P05, P06, P08) so khớp parity.

## 2. Điều kiện bắt đầu
- [ ] Chủ repo BigWIN đã đọc R1 (§12 kế hoạch tổng).
- [ ] Quyền ở BigWIN: Claude **chỉ** được sửa `scripts/export_golden.py` (Q7).

## 3. Thứ tự thực hiện
| Bước | Ai | Việc | Kết quả |
|---|---|---|---|
| 1 | Chủ repo | Rà soát, dọn dẹp, **commit toàn bộ ~1.300 dòng đang treo** trong `core/`, `utils/` (không stash bỏ dở) | `git status` sạch |
| 2 | Chủ repo | Chạy test Python của BigWIN (`pytest`) | Xanh |
| 3 | Chủ repo | `git tag -a port-baseline -m "Mốc mã nguồn cho migration PHP"` | `git describe` = `port-baseline` |
| 4 | Claude | S0.4: checkout tag, chạy lại `scripts/export_golden.py` → ghi đè 6 fixture hiện có | `meta.bigwin_commit` = hash tag, không `-dirty` |
| 5 | Claude | Chạy `php src/webapp/tools/test.php` | 19 test hiện có vẫn xanh (nếu đỏ → fixture cũ phản ánh code chưa commit; ghi lại khác biệt vào nhật ký) |
| 6 | Claude | S0.5: mở rộng `export_golden.py` thêm 6 hàm xuất (bảng §4) | 6 tệp mới trong `src/webapp/tests/fixtures/` |
| 7 | Claude | Chạy exporter 2 lần, so SHA-256 | Trùng 100% |

## 4. Fixture mới (S0.5)
| Tệp | Nguồn Python | Nội dung mỗi ca | Số ca tối thiểu |
|---|---|---|---|
| `cleaner_moi.json` | `HeuristicCleaner(quy_tac, bung_dan_kep=False).clean()` | input → output | toàn bộ corpus (≥ 1.300) |
| `rule_parser.json` | `RuleParser(tao_dan_so_mac_dinh()).parse_detailed()` trên output cleaner chế độ mới | input → `items`, `spans`, `errors`, `markup`, `can_save` | toàn bộ corpus |
| `dan_so.json` | `quan_ly_dan_so`: `tach_danh_sach_so`, `tach_khoa`, `kiem_tra_dan`, `anh_huong` | đầu vào → kết quả/danh sách lỗi | `kiem_tra_dan` ≥ 200 ca (hợp lệ + từng loại lỗi: tên trùng, nhóm sai, Bộ/Tổng/Chia áp LÔ, khoá hệ thống, khoá trùng dàn khác **kể cả dàn tắt**, sai tiền tố họ, trùng tham số `bo 2`≡`bo 02`, từ cấm trong cụm, dạng tiền, khoá toàn số, số trùng, số > 2 chữ số); `anh_huong` ≥ 30 ca |
| `tong_hop.json` | `Database.tong_hop_doi_soat_bang_bieu` | `items_detail` → `rows`, `tong`, `so_trung` | ≥ 100 ca (đủ 6 loại, có/không trúng, Lô nhiều nháy) |
| `doi_chieu.json` | `doi_chieu_tin.danh_dau_nghi_ngo` (+ `diff_analyzer.trich_xuat_tat_ca_khac_biet` trên cặp tin gốc/đã sạch) | tin gốc + kết quả parse → các đoạn nghi ngờ (offset ký tự) | toàn bộ corpus |
| `pipeline.json` | cleaner mới → RuleParser → `tinh_tien_*` → `tim_trung*` → `tong_hop_doi_soat_bang_bieu` | tin + KQ ngày + bộ hệ số → bảng tổng hợp cuối | 3 bộ hệ số (mặc định, khách 7 hệ số lẻ, khách CK cao) × ≥ 300 tin |

## 5. Kế hoạch test
### 5.1 Tự động
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P00-T01 | `tests/fixture_meta_test.php`: mọi fixture có `meta.bigwin_commit` | Giống nhau, khác rỗng, **không** chứa `dirty` |
| P00-T02 | Cùng tệp: số ca từng fixture ≥ bảng §4 | Đạt |
| P00-T03 | Cùng tệp: không ca nào là `{"error": …}` do exporter ném lỗi (trừ ca cố ý) | 0 ca lỗi ngoài danh sách cho phép |
| P00-T04 | Hồi quy: `helpers_test.php`, `smoke_test.php` | Xanh |

### 5.2 Thủ công
- [ ] P00-M01: Trong repo BigWIN `git describe --exact-match` tại commit dùng xuất fixture = `port-baseline`.
- [ ] P00-M02: Chạy exporter 2 lần → `sha256sum tests/fixtures/*.json` trùng.
- [ ] P00-M03: Mở ngẫu nhiên 5 ca trong `rule_parser.json`, đối chiếu tay với chạy `RuleParser` bên Python.

## 6. Cổng nghiệm thu
Đủ 6 điều kiện README §2 **và**: tag tồn tại; 12 fixture có cùng hash commit; P00-T01..T04 xanh.

## 7. Rủi ro & xử lý
| Rủi ro | Xử lý |
|---|---|
| Sau khi commit, 19 test PHP đỏ (fixture cũ khác code đã commit) | Bình thường nếu chủ repo đã sửa logic khi dọn; ghi danh sách khác biệt vào nhật ký, cập nhật test PHP P00 cho khớp fixture mới |
| Exporter không chạy được trên tag (thiếu module) | Sửa chỉ trong `export_golden.py`; không sửa code khác của BigWIN |

## 8. Ngoài phạm vi
Không port module nào sang PHP trong phase này.

## 9. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
