# TASK LOG — Nhật ký thực thi theo Phase

Quy trình: [`EXECUTION_PIPELINE.md`](EXECUTION_PIPELINE.md) · Trạng thái tổng: [`README.md`](README.md) §1.
Quy ước: mỗi phase một entry, **mới nhất ở trên**; ghi trong lúc làm; chép đúng mẫu dưới đây.

---

## MẪU ENTRY (chép khối này cho mỗi phase — không sửa mẫu)

```markdown
## Pxx — <Tên phase>

| Mục | Giá trị |
|---|---|
| Trạng thái | ▶ Đang làm / 🧪 Đang test / ✅ ĐẠT / ⛔ Mở lại / 🚫 Bị chặn |
| Luồng | A (lõi PURE) / B (nền tảng web) / Hợp nhất |
| Bắt đầu | yyyy-mm-dd |
| Kết thúc | yyyy-mm-dd |
| Người thực thi | |
| Người duyệt | |
| Commit BigWIN (`port-baseline`) | <hash> |
| Commit web đầu / cuối phase | <hash> / <hash> |

### ① Tiền đề
| Điều kiện (từ tệp phase §2 + EXECUTION_PIPELINE §2) | ✔/✘ | Ghi chú |
|---|---|---|
| Phase phụ thuộc Pyy ✅ | | |
| … | | |

Hồi quy nền trước khi code: `php src/webapp/tools/test.php` → `N passed, 0 failed (x.xxs)`

### ② Action Plan
| Thứ tự | Slice | Tệp tạo/sửa | Test viết trước | Rủi ro / lưu ý |
|---|---|---|---|---|
| 1 | | | Pxx-T.. | |

Câu hỏi cần chủ dự án quyết (nếu có) → câu trả lời + ngày:
- …

### ③ Tiến độ slice
| Slice | Bắt đầu | Xanh lúc | Test của slice | Ghi chú |
|---|---|---|---|---|
| | | | | |

### ④ Lần chạy test
| Lần | Ngày giờ | Lệnh | Kết quả (dòng tổng kết) | Đỏ (mã test) |
|---|---|---|---|---|
| 1 | | `php src/webapp/tools/test.php <lọc>` | | |
| Hồi quy | | `php src/webapp/tools/test.php` (không lọc) | | |
| Healthcheck | | `/healthcheck` | | |
| Audit API | | `/audit-api` (nếu áp dụng) | | |

Đo hiệu năng (nếu phase có): | Chỉ số | Mục tiêu | Thực tế | Cấu hình máy (OPcache/APCu) |

### Checklist thủ công
| Mã | Nội dung (tóm tắt) | Người test | Ngày | ✔ |
|---|---|---|---|---|
| Pxx-M01 | | | | |

### ⑤ Khác biệt / Bug phát hiện
| Mã | Loại | Nguồn (fixture + chỉ số ca / test / màn hình) | Input | Kỳ vọng | Thực tế | Nguyên nhân gốc | Cách sửa | Trạng thái |
|---|---|---|---|---|---|---|---|---|
| Pxx-B01 | Lệch Golden Fixture / Bug / Khác preview / Lỗi Desktop đã biết / Quyết định phát sinh | | | | | | | Mở / Đã sửa / Chuyển CR |

### ⑥ Xác nhận Cổng nghiệm thu
| # | Điều kiện | ✔ | Bằng chứng |
|---|---|---|---|
| 1 | Toàn bộ Pxx-Tnn xanh, 0 bỏ qua | | Lần chạy … |
| 2 | Hồi quy toàn bộ xanh | | `N passed, 0 failed` |
| 3 | Checklist Pxx-Mnn tick đủ | | |
| 4 | `/healthcheck` xanh; `/audit-api` AN TOÀN (nếu áp dụng) | | |
| 5 | DoD §10 kế hoạch tổng (các mục áp dụng) | | |
| 6 | Chủ dự án ký PASS | | Tên — ngày |

**Kết luận:** PASS / FAIL — <lý do nếu FAIL>
**Phase được mở khoá:** …
```

---

<!-- Các entry thật bắt đầu dưới dòng này, mới nhất ở trên -->

## P00 — Đóng băng BigWIN & Golden Fixture engine Mới

| Mục | Giá trị |
|---|---|
| Trạng thái | 🚫 **BỊ CHẶN** (2026-11-03 20:11) — tiền đề R1 / O4 chưa đạt; chưa lập Action Plan, chưa chạy exporter, chưa sửa mã |
| Luồng | A (lõi PURE) |
| Bắt đầu | 2026-11-03 (đánh giá tiền đề) |
| Kết thúc | |
| Người thực thi | Claude (bước 4–7) · Chủ repo BigWIN (bước 1–3) |
| Người duyệt | Chủ dự án |
| Commit BigWIN (`port-baseline`) | **Chưa có** — HEAD `3c5fab3` (2026-10-08 20:30) |
| Commit web đầu / cuối phase | `32c7ed8` (chốt P03) / |

### ① Tiền đề
| Điều kiện (P00 §2 + EXECUTION_PIPELINE P00) | ✔/✘ | Ghi chú (kiểm 2026-11-03 20:11, chỉ đọc) |
|---|---|---|
| Tag `port-baseline` tồn tại, `git describe --exact-match` khớp | ✘ **CHẶN** | `git tag -l port-baseline` (D:\Projects\bigwin) → rỗng · `git describe --exact-match HEAD` → `fatal: No names found` · `git ls-remote --tags origin` (github.com/minnb/bigwin) → **không có** `port-baseline` (không phải do chưa fetch) |
| Chủ repo đã dọn dẹp + commit ~1.300 dòng treo trong `core/`, `utils/` — `git status` sạch | ✘ **CHẶN** | `git status --short` → **80 mục** chưa commit. Riêng `core/` + `utils/`: **11 tệp, +1.341 / −578 dòng** — `core/{database,heuristic_cleaner,learning_service,messageParser,quan_ly_dan_so,rule_parser}.py`, `utils/raw_logger.py` (sửa) + `core/{api_client,cloud_backup,license_service}.py`, `utils/machine_id.py` (chưa theo dõi). Toàn repo: 44 tệp, +3.969 / −6.226 |
| Test Python BigWIN xanh trên commit được gắn tag | ☐ Chưa kiểm được | Chưa có commit / tag để chạy; Claude không chạy `pytest` trên working tree treo (kết quả không đại diện cho mốc) |
| Claude chỉ sửa `D:\Projects\bigwin\scripts\export_golden.py` (Q7) | ✔ (cam kết) | Lượt này chỉ ĐỌC repo BigWIN (`git tag/describe/status/diff --stat/ls-remote`); không đọc `*.session`, `backups/` |
| Hạ tầng web (Luồng B) sẵn sàng | ✔ | Working tree web sạch (commit `32c7ed8`); fixture hiện có 6 tệp: `cleaner`, `do_trung`, `learning`, `parser`, `seqmatch`, `tinh_tien` |
| Hồi quy nền xanh | ✔ | Xem dưới |

Hồi quy nền trước khi làm (2026-11-03 20:11): `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` → **`186 passed, 0 failed (20.56s)`**, exit 0 ✅ · không còn `php.exe` sót.

**Kiểm lại lần 2 (2026-11-03, theo thông báo "Tag `port-baseline` đã sẵn sàng"):** `git fetch --tags` (exit 0) → `git tag -l port-baseline` vẫn rỗng; `git describe --exact-match HEAD` → `fatal: No names found`; `git ls-remote --tags origin` → **remote không có tag nào**; HEAD vẫn `3c5fab3`; `git status --short` vẫn **80 mục** (`core/`+`utils/` 11 tệp chưa commit). Không có bản sao BigWIN nào khác trên máy (`D:/Projects/*`, Desktop, Documents). → Giữ **BỊ CHẶN**; không đánh ✔ tiền đề, không chạy `export_golden.py` (xuất từ code treo = S0.4 không đạt — R1).

**Kết luận Bước ①: BỊ CHẶN** — thông báo "đã dọn code treo và đánh tag" không khớp hiện trạng repo BigWIN trên máy này lẫn trên remote. Theo EXECUTION_PIPELINE §1.1 ("Có ✘ → dừng, ghi BỊ CHẶN + lý do, không gõ code") và bẫy P00 ("xuất fixture hoặc gắn tag khi repo còn code treo = S0.4 không đạt", R1): **không lập Action Plan chi tiết, không chạy exporter** cho tới khi gỡ chặn.

**Điều kiện gỡ chặn (chủ repo BigWIN):**
1. Dọn + commit toàn bộ thay đổi treo (tối thiểu `core/`, `utils/`; khuyến nghị cả repo — tệp chưa theo dõi `core/api_client.py`, `cloud_backup.py`, `license_service.py`, `utils/machine_id.py` cần quyết định commit hay bỏ).
2. `pytest` xanh trên commit đó.
3. `git tag -a port-baseline -m "Mốc mã nguồn cho migration PHP"` (+ `git push origin port-baseline` nếu muốn lưu trên remote).
4. Báo lại → Claude chạy lại Bước ① (kiểm `git describe --exact-match` = `port-baseline`, `git status` sạch) rồi mới lập Bước ②.

### ② Action Plan
_(Chưa lập — P00 đang BỊ CHẶN ở Bước ①.)_

### ③ Tiến độ slice
| Slice | Bắt đầu | Xanh lúc | Test của slice | Ghi chú |
|---|---|---|---|---|
| S0.4 | | | P00-T01, T04 | Chờ gỡ chặn |
| S0.5 | | | P00-T01..T03 | Chờ gỡ chặn |

### ④ Lần chạy test
| Lần | Ngày giờ | Lệnh | Kết quả (dòng tổng kết) | Đỏ (mã test) |
|---|---|---|---|---|
| Hồi quy nền | 2026-11-03 20:11 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` | **`186 passed, 0 failed (20.56s)`**, exit 0 | — |

### Checklist thủ công
| Mã | Nội dung (tóm tắt) | Người test | Ngày | ✔ |
|---|---|---|---|---|
| P00-M01 | `git describe --exact-match` tại commit xuất fixture = `port-baseline` | | | |
| P00-M02 | Chạy exporter 2 lần → SHA-256 trùng | | | |
| P00-M03 | 5 ca ngẫu nhiên `rule_parser.json` đối chiếu tay với Python | | | |

### ⑤ Khác biệt / Bug phát hiện
| Mã | Loại | Nguồn | Input | Kỳ vọng | Thực tế | Nguyên nhân gốc | Cách sửa | Trạng thái |
|---|---|---|---|---|---|---|---|---|
| P00-B01 | Tiền đề không đạt | Bước ① | Thông báo của chủ dự án: "chủ repo đã dọn code treo và đánh tag `port-baseline`" | Tag tồn tại; `git status` sạch | Không có tag (local + remote); 80 mục treo, `core/`+`utils/` +1.341 / −578 dòng | Chủ repo chưa thực hiện R1 trên repo này (hoặc thực hiện ở bản sao / máy khác chưa push) | Chủ repo làm 4 bước "Điều kiện gỡ chặn" ở trên | **Mở — chặn P00** (kiểm lại lần 2 cùng ngày: vẫn chưa có tag, local lẫn remote) |

### ⑥ Xác nhận Cổng nghiệm thu
| # | Điều kiện | ✔ | Bằng chứng |
|---|---|---|---|
| 1 | Toàn bộ P00-Tnn xanh, 0 bỏ qua | | |
| 2 | Hồi quy toàn bộ xanh | | |
| 3 | Checklist P00-Mnn tick đủ | | |
| 4 | `/healthcheck` xanh | | |
| 5 | DoD §10 kế hoạch tổng (mục 1, 2 áp dụng) | | |
| 6 | Chủ dự án ký PASS | | |

**Kết luận:** _(BỊ CHẶN ở Bước ①)_
**Phase được mở khoá:** _(P04 khi PASS)_

---

## P03 — Khung app 6 page · Quản trị tài khoản & thuê bao

| Mục | Giá trị |
|---|---|
| Trạng thái | ✅ ĐẠT — PASS 03/11/2026 (Principal Architect); B09 chốt sớm (PRG); Luồng B tạm dừng chờ Luồng A |
| Luồng | B (nền tảng web) |
| Bắt đầu | 2026-11-03 |
| Kết thúc | 2026-11-03 |
| Người thực thi | Claude |
| Người duyệt | Chủ dự án |
| Commit BigWIN (`port-baseline`) | Không áp dụng (luồng B) |
| Commit web đầu / cuối phase | `c2044c0` (commit P02) / commit P03 (ghi trong `git log`) |

### ① Tiền đề
| Điều kiện (P03 §2 + EXECUTION_PIPELINE P03) | ✔/✘ | Ghi chú (kiểm 2026-11-03) |
|---|---|---|
| P02 ✅ ĐẠT | ✔ | PASS 2026-11-03, commit `c2044c0`; `README.md` §1 |
| Token giao diện lấy từ `docs/ui-preview/app.css` | ✔ | Có `app.css` (15,6 KB, token `--flat-*`: nền, thẻ, viền, chữ, primary/success/danger/warning…), `index.html` (`tailwind.config` nội tuyến dòng 13), `app.js`, `mock-data.js` |
| Tailwind khoá `3.4.16` như preview | ✔ | `cdn.tailwindcss.com/3.4.16`; Alpine `alpinejs@3.13.5` (jsdelivr); Font Awesome (cdnjs) — CSP dev của `TieuDeBaoMat` đã cho phép đủ 3 nguồn (`unsafe-eval` cho Alpine bản thường; CSP chặt ở S9.3) |
| Preview có đủ thành phần khung | ✔ | Sidebar 6 mục + "Quản trị hệ thống (Alt 7)" chỉ hiện với admin (dòng 90–91), thanh tab mobile (dòng 108), modal phím tắt, menu avatar |
| Hạ tầng P01–P02 dùng được | ✔ | `lib/auth.php` (`require_user/require_admin`, `doi_mat_khau()`), gateway + `GiayPhepGuard`, `ThueBaoRepo` (mặt quản trị có sẵn), `PhienBaoMat::thuHoiTatCa()` |
| *(bổ sung)* Hiện trạng admin cũ | ✔ (lưu ý) | `admin/_auth.php` 457 dòng: phiên riêng `bigwin_admin`, `$_SESSION['admin_id'/'admin_user'/'csrf'/'bao']`, hàm `csrf_token/csrf_field/csrf_check/require_login/page_head/admin_header/admin_footer`; trang dùng: `index`, `owners`, `backups`, `backup_download`, `usage`, `login`, `logout`. DB thật đã có `users` (admin `bigwin` chép từ `admins`) |
| *(bổ sung)* Test đang dùng `_ping*.php` | ✔ (lưu ý) | P01-T20…T26 + P02-T25-HTTP, T31 gọi 3 endpoint thử → xoá `_ping*` phải chuyển test sang chỗ khác (Q-P03-2) |
| Hồi quy nền xanh | ✔ | Xem dưới |

Hồi quy nền trước khi code (2026-11-03 18:50):
- `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` → **`138 passed, 0 failed (22.57s)`**, exit 0 ✅ · không còn `php.exe` sót · working tree sạch.
- Chạy lại khi nhận quyết định Architect (2026-11-03 18:55): **`138 passed, 0 failed (38.18s)`**, exit 0 ✅.
- `php src/webapp/tools/test.php` (config thật) → `55 passed, 83 failed (0.61s)` — cả 83 là `Thiếu khoá 'db_test_dsn'` (P01-B06, cấu hình — không phải lỗi mã).

### ② Action Plan
Nguyên tắc thứ tự (bẫy của phase): **mọi trang mới không phụ thuộc `admin/_auth.php` cũ**; việc chuyển `admin/_auth.php` sang `users` là **bước cuối** (bước 9), có rollback = khôi phục `_auth.php` (bảng `admins` còn nguyên).

| Thứ tự | Slice | Tệp tạo/sửa | Test viết trước | Rủi ro / lưu ý |
|---|---|---|---|---|
| 1 | **S1.6a** `NgayLamViec` (thuần) | `lib/bigwin/NgayLamViec.php`: `macDinh(DateTimeImmutable $luc)` (trước 18:30 → D-1, từ 18:30 → D — N8), `laMacDinh($ngay, $luc)` (cho ô ngày đổi màu), `hienTai()` = `macDinh(now_vn())` có `ngay_gia_lap` khi `moi_truong=test` | `tests/ngay_lam_viec_test.php`: **P03-T01** 18:29:59 → hôm qua · **T02** 18:30:00 → hôm nay · **T03** 00:05 → hôm qua của ngày mới · (bổ sung) biên 18:30:01, qua tháng/năm (01/01 00:05 → 31/12) | Thời gian chỉ qua tham số / `now_vn()` (rule PURE). Xử lý luôn P02-B08(1): `ngay_gia_lap` thành "ngày làm việc" dùng chung → `GiayPhepGuard::homNay()` gọi về đây (một nguồn) |
| 2 | **S1.6b** Khung app | `app/_layout.php` (`require_user()` → `TenantContext::tuPhien` → `GiayPhepGuard::kiemTra(…,'doc')`; topbar: ô ngày N8 + đổi màu + "Về hôm nay", đồng hồ, trạng thái KQ placeholder "chưa có dữ liệu", avatar menu; sidebar 6 mục + Quản trị nếu admin; tab mobile; modal phím tắt; Alt+1…6/7, `?`, Esc), `app/assets/app.css` (chép token `--flat-*` + class `card/tbl/pill/btn/hs-input/nav-item` từ preview — không vẽ lại), `app/assets/app.js` (`goiApi()` DUY NHẤT gắn `X-CSRF-Token`, xử lý 401 → login, 402 → băng, 422 → lỗi trường), 6 page rỗng `app/{nhap_tin,telegram,kqxs,khach_hang,lich_su,cu_phap}.php`, `app/index.php` → chuyển `nhap_tin.php` | `tests/http_app_test.php`: **T04** chưa đăng nhập GET `app/nhap_tin.php` → 302 login · **T07** 6 page có `<meta name="csrf-token">`, sidebar đủ 6 mục, user thường không thấy "Quản trị" · `tests/app_js_scan_test.php`: **T08** không có `fetch(`/`XMLHttpRequest`/`$.ajax` ngoài `goiApi()` (token, như P01-T27) | ① CSP dev đã cho phép CDN; KHÔNG thêm nguồn mới. ② Giá trị động in ra luôn `h()`; dữ liệu cho Alpine qua `json_encode()` với các cờ `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS`, `JSON_HEX_QUOT` trong thuộc tính. ③ `tuPhien` chỉ ở `_layout.php` (quét P02-T16). **QĐ1:** layout phải phục vụ cả trang quản trị mới (`$chiAdmin = true` → `require_admin()`, user thường 403). ④ Trang `tam_khoa`/`chua_co_thue_bao` (doc bị chặn) → layout chỉ hiện thông báo, không render nội dung page |
| 3 | **S1.6c** Đổi mật khẩu (modal + endpoint) | `app/ajax/doi_mat_khau.php` (`ajax_khoi_dong('doc', ['cho_phep_phai_doi' => true, 'bo_qua_ban_quyen' => true])`), sửa `lib/auth.php` `doi_mat_khau()` trả lỗi **theo trường** (`mat_khau_cu` / `mat_khau_moi` / `xac_nhan`) — `doi_mat_khau.php` gốc (buộc đổi lần đầu) dùng lại | **T09** sai mật khẩu cũ / mới < 10 / xác nhận lệch → 422 kèm `loi_truong` đúng từng trường · **T10** đổi mật khẩu khi thuê bao hết hạn → được · (bổ sung) đổi xong các phiên khác bị thu hồi (như P01-T16) | **QĐ5:** đổi mật khẩu ĐỘC LẬP với giấy phép — hết hạn / tạm khoá / chưa có thuê bao vẫn đổi được; tuỳ chọn gateway `bo_qua_ban_quyen` CHỈ cho endpoint này (quét mã: danh sách cho phép đúng 1 tệp) — vẫn bắt buộc phiên + CSRF + nguồn + rate limit. Test bổ sung: đổi mật khẩu khi `tam_khoa` → 200. Đổi chữ ký `doi_mat_khau()` → cập nhật P01-T15/T16 + `doi_mat_khau.php`, giữ nguyên hành vi |
| 4 | **S1.6d** Băng thuê bao + ẩn hành động | `_layout.php` (băng: còn ≤ 7 ngày vàng · ân hạn đỏ · hết hạn đỏ "chỉ xem"; helper `nut_hanh_dong($cheDo, …)` không render nút khi `GiayPhepGuard::choPhep()` = false — mỗi page rỗng có 1 nút mẫu `data-che-do`) | **T11** tài khoản hết hạn tải page → có băng "hết hạn", không có phần tử `data-che-do="phan_tich"`/`"ghi"` · (bổ sung) còn 5 ngày → băng vàng; ân hạn → băng đỏ | "Còn N ngày" cần ngày hết hạn → `GiayPhepGuard::kiemTra` trả thêm `so_ngay_con` (thuần) |
| 5 | **S1.6e** Bỏ endpoint thử | Xoá `app/ajax/_ping.php`, `_ping_ghi.php`, `_ping_phan_tich.php`; endpoint thử 3 chế độ chuyển vào `tests/_router.php` (`/__bw_test__/ajax/{doc,ghi,phan_tich}`); sửa `bw_ping()` | P01-T20…T26, P02-T25/T31 chạy trên endpoint mới, kết quả không đổi | **QĐ2:** endpoint thử 3 chế độ CHỈ nằm ở `tests/_router.php` (chỉ tồn tại khi test chạy `php -S`; `tests/` bị `tests/.htaccess` chặn trên Apache) — gọi `ajax_khoi_dong` thật nên vẫn kiểm đúng gateway; `app/ajax/` không còn tệp thử. P01-T27 (mọi `app/ajax/*.php` qua gateway) vẫn có ≥ 1 endpoint thật (`doi_mat_khau.php`) |
| 6 | **S1.7a** `UserRepo` | `lib/bigwin/repo/UserRepo.php` (chỉ ngữ cảnh admin/hệ thống, như mặt quản trị `ThueBaoRepo`): `danhSach()`, `tao($username, $vaiTro, $maGoi, $hetHan)` (trả mật khẩu tạm; `phai_doi_mat_khau=1`; cùng 1 transaction với `ThueBaoRepo::tao` → lịch sử `tao`), `khoa/moKhoa` (+ `thuHoiTatCa`), `datLaiMatKhau` (buộc đổi + thu hồi phiên), `dangXuatMoiNoi`, `doiVaiTro`; chặn tự khoá/tự hạ quyền và hạ/khoá admin cuối cùng; kiểm `ThueBaoRepo::coTheThemDaiLy()` khi tạo / mở khoá / đổi admin→user | `tests/db_user_repo_test.php`: **T12** tạo khi đã đạt `so_dai_ly_toi_da` → chặn, thông báo rõ · **T13** tạo đại lý → `phai_doi_mat_khau=1` + `thue_bao` theo gói + 1 dòng lịch sử `tao` · **T14** tự khoá / hạ admin cuối → chặn · **T15** đăng xuất mọi nơi → mọi phiên X thu hồi (HTTP: request kế của X → login) · **T17** mở khoá khi vượt giấy phép → chặn · (bổ sung) ngữ cảnh đại lý gọi `UserRepo` → `LoiCoLapTenant` | **QĐ4:** `UserRepo` là lớp quản trị độc lập gọi DB trực tiếp (như `ThueBaoRepo`) → thêm `lib/bigwin/repo/UserRepo.php` vào `BW_TSCAN_LOP_NEN` (P02-T16) + rule `tenant-isolation.md`; ngữ cảnh đại lý gọi → `LoiCoLapTenant`. **QĐ6:** mật khẩu khởi tạo / đặt lại = ngẫu nhiên 12 ký tự (`random_int`, bỏ ký tự dễ nhầm), trả về MỘT lần cho trang admin hiển thị, KHÔNG lưu / ghi log bản rõ; luôn `phai_doi_mat_khau = 1`. **QĐ3:** tạo / đặt lại mật khẩu tài khoản admin vẫn GHI KÉP `admins` (dùng chung đường ghi với `tools/create_admin.php`). Kiểm giấy phép + ghi **cùng transaction có khoá** (2 admin tạo cùng lúc không vượt hạn mức): khoá dòng `giay_phep_he_thong` `FOR UPDATE` |
| 7 | **S1.7b** Trang quản trị mới | `admin/users.php`, `admin/thue_bao.php` dùng **khung app mới** (`app/_layout.php`, chỉ admin), form POST server-side + `Csrf`; tab Tài khoản (tạo, khoá/mở, đặt lại mật khẩu, đăng xuất mọi nơi, đổi role) · tab Gói & Thuê bao (thêm/sửa gói, ngừng bán; gia hạn, đổi gói, tạm khoá/mở, lịch sử; "Đã dùng X/Y đại lý" — chỉ đọc) · `tools/giay_phep_nen_tang.php` (CLI đặt `so_dai_ly_toi_da`) | `tests/http_admin_test.php`: **T05** user `role=user` GET `admin/users.php` → 403 · **T16** form admin POST thiếu CSRF → 403 · luồng tạo đại lý qua HTTP (khớp T13) | **QĐ1:** dựng trên `app/_layout.php`, KHÔNG dùng `admin_header()` / `admin/_auth.php` cũ → làm được trước bước 9; admin đăng nhập qua `/login.php`. **QĐ7:** gói chỉ thêm / sửa / ngừng bán (`dang_ban = 0`) — KHÔNG có nút / route / method xoá gói (test: trang không có form xoá; mã không có câu xoá trên `goi_dich_vu`); `so_dai_ly_toi_da` KHÔNG có trên UI admin (chỉ hiển thị X/Y) — đổi bằng CLI `tools/giay_phep_nen_tang.php`. **QĐ6:** mật khẩu tạm hiện 1 lần trên trang kết quả (không đưa vào URL, không lưu vào `bao()` của phiên) |
| 8 | **S1.7c** Dọn P01-B08 | Câu hướng dẫn tạo admin trong `admin/login.php` → `php tools/create_admin.php` (P01-B08). `tools/create_admin.php` **giữ nguyên ghi kép** | P01-T07 giữ nguyên (vẫn kiểm ghi kép `admins`) | **QĐ3:** GIỮ GHI KÉP `users` + `admins` đến hết P03 (cửa sổ rollback của bước 9); KHÔNG xoá / sửa cấu trúc bảng `admins`, `admin_login_fails`. Bỏ ghi kép là quyết định riêng sau P03 (P01-B07) |
| 9 | **S1.7d** Chuyển `admin/_auth.php` sang `users` — **BƯỚC CUỐI** | `admin/_auth.php`: bỏ phiên `bigwin_admin` → `PhienBaoMat` + `require_admin()`; `csrf_token/csrf_field/csrf_check` → `Csrf`; `$_SESSION['admin_user']` → user hiện tại; giữ `page_head/admin_header/admin_footer`, `bao()` (lưu trong phiên mới); `admin/login.php`, `admin/logout.php` → chuyển hướng `/login.php`, `/logout.php`; thêm mục Tài khoản + Gói & Thuê bao vào menu admin cũ | **T06** admin GET mọi trang `app/*` và `admin/*` → 200 · **T18** hồi quy P00–P02 + trang admin cũ `index`, `owners`, `backups`, `usage` → 200 với admin; `backup_download` vẫn tải được · (bổ sung) không còn cookie `bigwin_admin`; `session_start` chỉ ở `PhienBaoMat` (cập nhật P01-S13 bỏ ngoại lệ `admin/_auth.php`) | **QĐ3: BƯỚC CUỐI CÙNG** — chỉ bắt đầu khi bước 1–8 xanh. **Rollback:** khôi phục `admin/_auth.php`, `login.php`, `logout.php` từ `c2044c0`; nhờ ghi kép, bảng `admins` vẫn đúng mật khẩu mới nhất → admin cũ đăng nhập lại được ngay. Kiểm thủ công M07 trên Apache thật. `/audit-api` bắt buộc (sửa `lib/auth.php`) |
| 10 | Cổng | — | T01–T18 xanh · hồi quy toàn bộ · `/healthcheck` · `/audit-api` · quét: không còn `_ping*.php` · checklist M01–M07 | — |

**Quyết định của Architect (2026-11-03) — đã đưa vào cột "Rủi ro / lưu ý" của bước tương ứng:**
| QĐ | Nội dung | Áp vào bước |
|---|---|---|
| QĐ1 (Q-P03-1) | Trang quản trị mới dựng trên `app/_layout.php`, không dùng giao diện admin cũ | 2, 7 |
| QĐ2 (Q-P03-2) | Endpoint thử 3 chế độ nằm ở `tests/_router.php` | 5 |
| QĐ3 (Q-P03-3) | GIỮ GHI KÉP `admins` đến hết P03 (cửa sổ rollback); không xoá bảng `admins`; chuyển `admin/_auth.php` là bước CUỐI | 6, 8, 9 |
| QĐ4 (Q-P03-4) | `UserRepo` là lớp quản trị độc lập gọi DB trực tiếp (như `ThueBaoRepo`); cập nhật rule + quét SQL cho ngoại lệ này | 6 |
| QĐ5 (Q-P03-5) | Đổi mật khẩu độc lập với giấy phép (hết hạn / tạm khoá vẫn đổi được) | 3 |
| QĐ6 (Q-P03-6) | Mật khẩu khởi tạo ngẫu nhiên 12 ký tự, hiện 1 lần, buộc đổi ở lần đăng nhập đầu | 6, 7 |
| QĐ7 (Q-P03-7) | Gói: chỉ thêm / sửa / ngừng bán (không xoá); `so_dai_ly_toi_da` chỉ sửa qua CLI, không lên UI admin | 7 |

### ③ Tiến độ slice
| Slice | Bắt đầu | Xanh lúc | Test của slice | Ghi chú |
|---|---|---|---|---|
| S1.6 | 2026-11-03 | 2026-11-03 — hồi quy 156/156 | `ngay_lam_viec_test` 5 (T01, T02, T03 + múi giờ, chuẩn hoá ?ngay=) · `giay_phep_test` +1 (`so_ngay_con`) · `http_app_test` 8 (T04, T07, T09, T10 — QĐ5 cả hết hạn / tạm khoá / chưa có, T11, ngày làm việc + chống phản chiếu, băng sắp hết hạn / ân hạn / tạm khoá, ghi kép admins khi admin đổi mật khẩu) · `app_js_scan_test` 4 (T08 ×2, `bo_qua_ban_quyen` chỉ ở endpoint đổi mật khẩu, không còn `_ping*`) | Test viết trước (đỏ 11/12). Tệp: `lib/bigwin/NgayLamViec.php` (N8 18:30; `homNayLich()` = nguồn duy nhất của `ngay_gia_lap` — `GiayPhepGuard::homNay()` gọi về), `GiayPhepGuard::kiemTra` (+ `het_han`, `so_ngay_con`), `app/_layout.php` (`khung_bat_dau / khung_ve / nut_hanh_dong / khung_kiem_post / trang_rong`), `app/assets/app.css` (chép NGUYÊN VĂN `docs/ui-preview/app.css` — `cmp` khớp), `app/assets/app.js` (`goiApi()` duy nhất; cấu hình qua `data-khung`, không `<script>` nội tuyến của app), 6 page rỗng + `app/index.php`, `app/ajax/doi_mat_khau.php`, `lib/auth.php` (`doi_mat_khau()` trả lỗi theo trường + ghi kép `admins`), gateway (`bo_qua_ban_quyen`), `doi_mat_khau.php`; xoá `app/ajax/_ping*.php` → `tests/_router.php` `/__bw_test__/ajax/{doc,ghi,phan_tich}` (QĐ2) |
| S1.7 | 2026-11-03 | 2026-11-03 — hồi quy 183/183 ×3 | `db_user_repo_test` 13 (T12, T13, T14, T15, T17 + mật khẩu tạm QĐ6, tạo admin ghi kép QĐ3, dữ liệu sai, khoá / đặt lại thu hồi phiên, ngữ cảnh đại lý bị chặn QĐ4, gói không xoá QĐ7, CLI giấy phép QĐ7) · `http_admin_test` 14 (T05, T06, T13 qua trang + M05 tự động, T12 qua trang, T15 qua trang, T16, T18 ×2, gia hạn mất băng — M06 tự động, cấp thuê bao + quản lý gói, UI không sửa giới hạn / không xoá gói, đăng nhập / đăng xuất admin cũ) | Test viết trước (đỏ). Tệp: `lib/bigwin/repo/{UserRepo,GoiDichVuRepo,GiayPhepNenTangRepo,LoiQuanTri}.php`, `tools/giay_phep_nen_tang.php`, `admin/_quan_tri.php`, `admin/users.php`, `admin/goi_thue_bao.php` (B02); **bước 9 cuối cùng:** `admin/_auth.php` (phiên chung + `require_admin()` + `Csrf`, bỏ `bigwin_admin`, menu thêm 2 trang mới), `admin/login.php` / `logout.php` chuyển tiếp; rule `admin-security.md`, `tenant-isolation.md` (QĐ4); quét: `UserRepo` vào lớp nền, P01-S13 bỏ ngoại lệ `admin/_auth.php` |

### ④ Lần chạy test
| Lần | Ngày giờ | Lệnh | Kết quả (dòng tổng kết) | Đỏ (mã test) |
|---|---|---|---|---|
| Hồi quy nền | 2026-11-03 18:50 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` | **`138 passed, 0 failed (22.57s)`**, exit 0 | — |
| Hồi quy nền (config thật) | 2026-11-03 18:50 | `php src/webapp/tools/test.php` | `55 passed, 83 failed (0.61s)`, exit 1 | 83 test DB/HTTP — `Thiếu khoá 'db_test_dsn'` (P01-B06) |
| 1 | 2026-11-03 | `… test.php ngay_lam_viec` / `giay_phep` (S1.6a) | `5 passed` / `21 passed` | — |
| 2 | 2026-11-03 | `… test.php http_app` / `app_js_scan` (test viết trước) | `0 passed, 8 failed` / `1 passed, 3 failed` | Đỏ đúng dự kiến (chưa có khung app) |
| 3 (hồi quy S1.6) | 2026-11-03 | `… test.php` không lọc | `154 passed, 2 failed` → **`156 passed, 0 failed`** | Lần đầu: P01-T08 (`app/` nay chuyển tới `nhap_tin.php` — B04) và 1 test mới kiểm sai ("không có `<script>`" — trang vốn có thẻ script hợp lệ; sửa thành kiểm đầu vào không bị phản chiếu) |
| 4 | 2026-11-03 | `… test.php db_user_repo` (S1.7a) | `12 passed, 1 failed` → **`13 passed`** | CLI `giay_phep_nen_tang.php` dùng `require` thay `require_once` (nạp lớp 2 lần) — sửa |
| 5 | 2026-11-03 | `… test.php http_admin` (S1.7b) | **`10 passed, 0 failed`** | — |
| 6 | 2026-11-03 | `… test.php http_admin` / `security_scan` (test bước 9 viết trước) | `10 passed, 4 failed` / `2 passed, 1 failed` | Đỏ đúng dự kiến (chưa chuyển `_auth.php`) |
| 7 | 2026-11-03 | Như lần 6 sau chuyển `_auth.php` | `12 passed, 2 failed` → **`14 passed`** | (a) `admin/logout.php` thiếu `PhienBaoMat::batDau()` trước kiểm CSRF → không đăng xuất được — **lỗi mã, test bắt được** (B06) · (b) `backups.php` vốn bắt buộc `?machine=` → 400 của chính trang (kỳ vọng test sửa) |
| 8 | 2026-11-03 | `… test.php` không lọc | `181 passed, 2 failed` → **`183 passed`** | P01-T03 (smoke S1.0) dùng `/admin/login.php` làm trang mẫu — nay chuyển hướng; đổi sang `/login.php` (B04) |
| **9 (hồi quy cuối P03)** | 2026-11-03 19:20 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` không lọc, **3 lần** | **`183 passed, 0 failed`** (54.16s / 54.49s / 54.80s), exit 0 | — · 80 P01 + 58 P02 + **45 P03** · `php -l` 91 tệp 0 lỗi · không còn `php.exe` sót |
| 9b | 2026-11-03 | `/healthcheck` (lint + `require`) | **OK** — 133 câu `require/include`, mọi đường dẫn `__DIR__` tồn tại; 7 cờ còn lại là dương tính giả đã biết (P02 lần 11) | — |
| 9c | 2026-11-03 | `/audit-api` (`git diff HEAD -- api/ lib/api.php lib/crypto.php lib/bootstrap.php`) | **AN TOÀN** | Không đổi; `api/*` không nạp `lib/auth.php` / gateway / khung app |
| 10 (sau `/simplify`) | 2026-11-03 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` không lọc, 3 lần | **`186 passed, 0 failed`** (32.96s / 17.54s / 24.06s — trước 54s), exit 0 | — · +3 test (PRG + thông báo 1 lần, gỡ `admins` khi khoá / hạ quyền, `giaHanThem`) · `php -l` 0 lỗi · `api/`, `lib/api.php`, `lib/crypto.php`, `bootstrap.php` không đổi (`/audit-api` AN TOÀN) |
| 11 | 2026-11-03 19:55 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` không lọc (kiểm lại khi nhận quyết định nợ kỹ thuật) | **`186 passed, 0 failed (15.98s)`**, exit 0 | — · không còn `php.exe` sót |

### Checklist thủ công
| Mã | Nội dung (tóm tắt) | Người test | Ngày | ✔ |
|---|---|---|---|---|
| P03-M01 | Topbar, sidebar, tab mobile khớp preview ở 1440 px và 375 px (không cuộn ngang) | Chủ dự án | 2026-11-03 | ✔ |
| P03-M02 | Alt+1…6 chuyển page; `?` mở bảng phím tắt; Esc đóng | Chủ dự án | 2026-11-03 | ✔ |
| P03-M03 | 17:00 → ô ngày = hôm qua; đổi ngày → ô đổi màu, nút "Về hôm nay" | Chủ dự án | 2026-11-03 | ✔ |
| P03-M04 | Còn 5 ngày → băng vàng; ân hạn → băng đỏ; hết hạn → page mở được, nút hành động ẩn | Chủ dự án | 2026-11-03 | ✔ |
| P03-M05 | Admin tạo đại lý → đăng nhập bằng đại lý → buộc đổi mật khẩu → vào app | Chủ dự án | 2026-11-03 | ✔ |
| P03-M06 | Gia hạn 30 ngày → băng của đại lý biến mất ở lần tải sau | Chủ dự án | 2026-11-03 | ✔ |
| P03-M07 | Mọi trang admin cũ vẫn hoạt động sau khi bỏ `bigwin_admin` (Apache thật) | Chủ dự án | 2026-11-03 | ✔ |

### ⑤ Khác biệt / Bug phát hiện
| Mã | Loại | Nguồn | Input | Kỳ vọng | Thực tế | Nguyên nhân gốc | Cách sửa | Trạng thái |
|---|---|---|---|---|---|---|---|---|
| P03-B02 | Khác tài liệu phase | Quét mã P02-T16 (`ten_bang_ngoai_repo`) | Tệp `admin/thue_bao.php` | Theo tài liệu phase | Chuỗi `'admin/thue_bao.php'` (menu, link) chứa tên bảng `thue_bao` → bộ quét chặn (đúng thiết kế Q-P02-6) | Tên tệp trùng tên bảng | Đặt tên **`admin/goi_thue_bao.php`** (khớp tab "Gói & Thuê bao"); không nới bộ quét | Áp dụng |
| P03-B03 | Khác biệt (API nội bộ) | S1.6c | `doi_mat_khau()` | Trả 1 thông điệp | T09 cần lỗi theo trường | — | Trả `['mat_khau_cu' / 'mat_khau_moi' / 'xac_nhan' => …]` (rỗng = thành công), báo đủ mọi trường sai một lượt; `doi_mat_khau.php` (buộc đổi) ghép chuỗi — P01-T15 không đổi | Áp dụng |
| P03-B04 | Khác biệt (test P01 theo thiết kế P03) | Hồi quy lần 3, 8 | P01-T08 (`GET /app/` = 200), P01-T03 smoke (`/admin/login.php` 200 + cookie `bigwin_admin`), P01-S13 (ngoại lệ `admin/_auth.php`) | — | Đỏ | `app/` nay chuyển tới page mặc định; admin dùng đăng nhập chung | T08 kiểm chuyển hướng rồi mở `nhap_tin.php`; smoke dùng `/login.php` + cookie `bigwin_web`; S13 chỉ còn `PhienBaoMat` được `session_start` | Áp dụng |
| P03-B05 | Rủi ro còn lại | Bước 9 | Trang admin cũ | Gửi `TieuDeBaoMat` (CSP…) như mọi trang | Giữ 2 tiêu đề cũ (`X-Frame-Options`, `nosniff`) | Trang cũ dùng ảnh `ui-avatars.com`, Tailwind / Chart.js chưa khoá phiên bản → CSP hiện tại sẽ chặn ảnh; đổi ở bước cuối = rủi ro hỏng giao diện | Không áp CSP cho trang cũ trong P03; khoá phiên bản CDN + bỏ ảnh ngoài rồi áp `TieuDeBaoMat` ở S9.3 (P13) | **Mở** — Architect chốt (2026-11-03): giữ nguyên trạng, dời sang P13 |
| P03-B06 | Bug (mã, bắt bởi test) | Lần 7 | POST `admin/logout.php` có CSRF | Đăng xuất | Không đăng xuất | Kiểm `Csrf::hopLe` trước khi mở phiên → `$_SESSION` rỗng | `PhienBaoMat::batDau()` trước khi kiểm | Đã sửa |
| P03-B07 | Quyết định phát sinh | S1.6a | `ngay_gia_lap` | Một nguồn (P02-B08 bỏ qua (1)) | — | — | `NgayLamViec::homNayLich()` là nguồn duy nhất; `GiayPhepGuard::homNay()` gọi về; DB `NOW()` vẫn giờ thật | Áp dụng |
| P03-B08 | Khác Action Plan | Bước 8 | Câu hướng dẫn tạo admin trong `admin/login.php` (P01-B08) | Sửa câu | `admin/login.php` thành tệp chuyển tiếp ở bước 9 | — | Gộp bước 8 vào bước 9: chú thích trỏ `php tools/create_admin.php`; `create_admin.php` GIỮ ghi kép (QĐ3) | Đóng P01-B08 |
| P03-B09 | Ghi chú | Trang quản trị mới | Tải lại trang ngay sau một thao tác POST | — | Trình duyệt hỏi gửi lại form | Không dùng redirect sau POST để mật khẩu tạm chỉ hiện trên phản hồi đó (QĐ6, không lưu phiên) | Thao tác gửi lại an toàn: tạo trùng tên → báo lỗi; khoá / mở / gia hạn thêm lần nữa là thao tác có chủ đích. Cân nhắc PRG cho thao tác không có mật khẩu tạm ở P12 | **Đã sửa (Chốt sớm ở P03 theo quyết định của Architect)** — PRG + thông báo 1 lần (B10 (5)); test `p03_s17_thao_tac_khong_bi_mat_dung_prg_thong_bao_mot_lan` |
| P01-B07 | Nợ kỹ thuật (theo dõi ở P03) | QĐ3 · `tools/create_admin.php`, `UserRepo`, `doi_mat_khau()` | Ghi kép bảng `admins` | Bỏ ghi kép sau P03 (dự kiến ban đầu) | Vẫn ghi kép — nay qua một hàm `dong_bo_admins()` (B10) | Cửa sổ rollback của `admin/_auth.php` cũ cần `admins` đúng mật khẩu mới nhất | Tiếp tục duy trì để an toàn rollback; bảng `admins`, `admin_login_fails` giữ nguyên | **Mở** — Architect chốt (2026-11-03): chờ quyết định drop bảng ở P12/P13 |
| P03-B10 | Tối ưu (`/simplify` sau PASS) | 4 góc rà: tái sử dụng · đơn giản · hiệu năng · độ sâu | — | — | — | — | **Áp dụng:** (1) kiểm CSRF form về MỘT chỗ — `Csrf::formHopLe()` + `form_hop_le()` (tự mở phiên, gốc của B06), `truong_csrf()`, `dang_xuat_tu_form()` — thay 6 bản chép ở `login.php`, `logout.php`, `admin/logout.php`, `doi_mat_khau.php`, `admin/_auth.php`, `app/_layout.php` · (2) ghi kép `admins` về `dong_bo_admins()` dùng chung (auth, `UserRepo`, `create_admin.php`) **và** gỡ khỏi `admins` khi admin bị khoá / hạ quyền (trước: vẫn đăng nhập được qua `_auth.php` cũ nếu rollback) · (3) giấy phép nền tảng: `GiayPhepNenTangRepo::batBuocConCho()` khoá + đọc 1 lần (trước 4 truy vấn, khoá nằm ở nơi gọi) · (4) luật gia hạn vào `ThueBaoRepo::giaHanThem()` trong khoá dòng (trước tính ở trang trên danh sách đọc trước transaction) · (5) PRG + thông báo 1 lần cho thao tác không có mật khẩu tạm (đóng B09) · (6) `thuat_toan_bam()`: argon2id tham số tối thiểu CHỈ khi `moi_truong = test` — bộ test 54s → 17–33s · (7) `form_mo()` / `nut_nho()` / `so_id()` / `het_han_mac_dinh()`, `UserRepo::danhSach(?vaiTro)`, `GoiDichVuRepo::dangBan()`, kiểm trùng tên trước khi băm, `url_web()` cache tiền tố, `ten_hien()`, `mkTrong()` (app.js), `NgayLamViec::hienTai()` gọn, xoá docblock thừa. **Bỏ qua (ghi nhận):** áp `TieuDeBaoMat` hồ sơ "admin cũ" (B05 — cần kiểm CSP tay trên Apache, P13) · dữ liệu mẫu `machines` cho `backups.php` trong T06 · bỏ alias `GiayPhepGuard::homNay()` (9 nơi gọi) · cache CSRF theo jar trong test · cache `kiem_tra_he_thong()` (mã cũ ngoài phạm vi) · `post_text()` ép mảng thành "Array" (mã cũ) | Áp dụng — chưa commit |
| P03-B01 | Khác tài liệu phase | `P03-…md` §4 thứ tự | Bước 5 "chuyển `admin/_auth.php`" đứng TRƯỚC bước 6 "trang admin mới" | `_auth.php` chuyển cuối phase (Bẫy cần tránh + yêu cầu chủ dự án) | Tài liệu tự mâu thuẫn | Thứ tự viết trước khi chốt bẫy | Action Plan đảo: trang admin mới trên khung app (QĐ1) → chuyển `_auth.php` là bước 9 (QĐ3) | **Đóng** — Architect duyệt 2026-11-03 |

### ⑥ Xác nhận Cổng nghiệm thu
| # | Điều kiện | ✔ | Bằng chứng |
|---|---|---|---|
| 1 | Toàn bộ P03-Tnn xanh, 0 bỏ qua | ✔ (Claude) | T01–T18 + test bổ trợ (48 test P03) xanh — lần 10–11; runner không có cơ chế bỏ qua |
| 2 | Hồi quy toàn bộ xanh | ✔ (Claude) | Lần 11: `186 passed, 0 failed (15.98s)`, exit 0; ổn định 3 lần ở lần 10 (cấu hình gốc qua `BIGWIN_CONFIG_FILE` — P01-B06) |
| 3 | Checklist P03-Mnn tick đủ | ✔ (Chủ dự án) | M01–M07 — chủ dự án test tay 2026-11-03 |
| 4 | `/healthcheck` xanh; `/audit-api` AN TOÀN (sửa `lib/auth.php`) | ✔ (Claude) | Lần 9b (OK) · lần 9c (**AN TOÀN**) |
| 5 | DoD §10 kế hoạch tổng (mục 4, 6, 7, 9, 10 áp dụng) | ✔ (Claude) | **4** `app/ajax/doi_mat_khau.php` mở đầu `ajax_khoi_dong` (P01-T27); form đổi dữ liệu = POST + CSRF + nguồn (T16, T18); JS chỉ `goiApi()` (T08); output `h()`; SQL prepared, trang không viết SQL · **6** `api/*`, `lib/api.php`, `lib/crypto.php` không đổi · **7** ngày qua `NgayLamViec` / `today_vn()`; DB `NOW()` · **9** bám `docs/ui-preview/` (CSS chép nguyên văn; M01) — khác preview ghi ở B02 · **10** `/healthcheck` xanh, §14 đã ghi |
| 6 | Chủ dự án ký PASS | ✔ | ✅ PASS (Ngày 03/11/2026 - Principal Architect) |

**Kết luận:** PASS - Hoàn tất Luồng B, chờ Luồng A (P00, P04, P05) để hợp nhất tại P06
**Phase được mở khoá:** P06 (còn chờ P05 — luồng A, phụ thuộc P00)

---

## P02 — TenantScope · Bản quyền SaaS

| Mục | Giá trị |
|---|---|
| Trạng thái | ✅ ĐẠT — PASS 2026-11-03 (Claude ký theo uỷ quyền chủ dự án; mở khoá P03) |
| Luồng | B (nền tảng web) |
| Bắt đầu | 2026-11-03 |
| Kết thúc | 2026-11-03 |
| Người thực thi | Claude |
| Người duyệt | Chủ dự án |
| Commit BigWIN (`port-baseline`) | Không áp dụng (luồng B không dùng fixture) |
| Commit web đầu / cuối phase | `a7e4a39` (commit P01, 2026-11-03 — Q-P02-7) / |

### ① Tiền đề
| Điều kiện (P02 §2 + EXECUTION_PIPELINE P02) | ✔/✘ | Ghi chú (kiểm 2026-11-03) |
|---|---|---|
| P01 ✅ ĐẠT | ✔ | Chủ dự án ký PASS 2026-11-03; `README.md` §1 đã cập nhật |
| Đọc lại `.claude/rules/tenant-isolation.md` (v2.1) | ✔ | Đã đọc cùng kế hoạch tổng §1.4, §4.1 (O1/O2/O8), §5.4, §7.1–7.3, G1 S1.4–S1.5. Điểm then chốt: `user_id` chỉ từ `TenantContext`; repo `extends TenantRepo`; SQL tự viết chỉ qua `truyVan()` với `{tenant:x}`; thiếu → `LoiCoLapTenant` (fail-closed, không bắt để chạy tiếp) |
| Bảng demo `tests/_tenant_demo.sql` chỉ nạp vào DB test | ✔ (cam kết thiết kế) | Sẽ có test tự động P02-T18 khẳng định `schema.sql` không chứa `demo_` |
| Hạ tầng P01 dùng được (`_db.php`, `_http.php`, gateway, bảng `users`) | ✔ | Có trong hồi quy nền |
| *(bổ sung)* Hàm ngày `today_vn()` | ✔ | Có ở `lib/bigwin/helpers.php` → `GiayPhepGuard` nhận ngày qua tham số, **không** sửa `helpers.php` |
| *(bổ sung)* Cấu hình PDO | ✔ (lưu ý) | `ATTR_EMULATE_PREPARES = false` → không dùng lại một tham số tên nhiều lần, không trộn `?` với `:ten` → ảnh hưởng cách `truyVan()` chèn `user_id` (xem rủi ro R2) |
| *(bổ sung)* `users.id` kiểu `INT UNSIGNED` | ✔ | FK `thue_bao.user_id` phải cùng kiểu |
| *(bổ sung)* Rule `bigwin-core.md` (PURE) | ✔ (lưu ý) | `lib/bigwin/*.php` không được gọi DB trừ `repo/**`, `*Service.php` → `TenantContext`, `GiayPhepGuard` phải thuần; truy cập DB chỉ ở `repo/` |
| Hồi quy nền xanh | ✔ | Xem dưới |

Hồi quy nền trước khi code (2026-11-03 06:21):
- `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` (cách chạy đã duyệt ở P01-B06) → **`80 passed, 0 failed (17.70s)`**, exit 0 ✅ · không còn `php.exe` sót.
- `php src/webapp/tools/test.php` với `config.php` thật → `34 passed, 46 failed (0.05s)` — **cả 46** cùng một lỗi `Thiếu khoá 'db_test_dsn' trong config.php` (đúng tình trạng P01-B06, không phải lỗi mã). Muốn lệnh không biến môi trường xanh: chủ dự án thêm 3 khoá `db_test_*` vào `src/webapp/config.php`.

### ② Action Plan
| Thứ tự | Slice | Tệp tạo/sửa | Test viết trước | Rủi ro / lưu ý |
|---|---|---|---|---|
| 1 | **S1.4a** `TenantContext` + lỗi | `lib/bigwin/TenantContext.php` (final, constructor private; `userId` readonly int, `vaiTro`, nguồn `phien`/`cli`; `tuPhien(array $user)`, `choCli(int, string $lyDo)` ghi `error_log` lý do; `laAdmin()`, `laCli()`) · `lib/bigwin/repo/LoiCoLapTenant.php` | `tests/db_tenant_repo_test.php`: **P02-T01** constructor private (Reflection + `new` ném `Error`) · **P02-T02** `choCli` lý do rỗng/chỉ khoảng trắng → lỗi | Thuần (không DB). `tuPhien` chỉ nhận user đã xác thực (`id` int > 0, `role` hợp lệ) — sai → ném lỗi, không đoán |
| 2 | **S1.4b** `TenantRepo` CRUD + bảng demo | `lib/bigwin/repo/TenantRepo.php` (`DANH_SACH_BANG_TENANT` một chỗ; hằng `BANG`, `XOA_MEM`, `COT_BAT_BIEN`; `tim`, `timMot`, `them`, `sua`, `xoaMem` — đều `final protected`) · `tests/_tenant_demo.sql` (`demo_cha`, `demo_con`: `user_id NOT NULL`, `deleted_at/deleted_by/ly_do_xoa`, `UNIQUE(id,user_id)`, FK kép `(cha_id,user_id)` `ON DELETE RESTRICT`) · `tests/_tenant_demo.php` (2 repo demo `extends TenantRepo`, có `COT_BAT_BIEN`) · `tests/_db.php` (nạp `_tenant_demo.sql` **sau** `schema.sql`, chỉ vào DB test chính) | **P02-T03** `them()` có khoá `user_id` → `LoiCoLapTenant` · **T04** bản ghi mang `user_id` ngữ cảnh · **T05** A `timMot(id B)` = `null` · **T06** A `sua(id B)` = `false`, B không đổi · **T07** A `xoaMem(id B)` = `false` · **T08** `xoaMem` của mình: `deleted_at`, `deleted_by`, `ly_do_xoa` đúng, dòng còn trong bảng · **T09** `tim()` bỏ bản đã xoá · **T14** FK kép: chèn SQL thẳng con-A vào cha-B → DB từ chối · **T15** `sua()` đụng `COT_BAT_BIEN` → lỗi · *(bổ sung)* **T18** `schema.sql` không có `demo_` · *(bổ sung)* **T19** mỗi bảng thật trong `DANH_SACH_BANG_TENANT` có trong `schema.sql` và có `user_id NOT NULL` | ① **`sua()` trả `false` khi giá trị không đổi**: MySQL `rowCount()` chỉ đếm dòng *thay đổi* → kiểm tồn tại bằng điều kiện tenant trước (không đổi cấu hình PDO dùng chung). ② Tên cột trong `them()/sua()` kiểm regex `^[a-z_][a-z0-9_]*$` và cấm `id`, `user_id`, `deleted_*` (chỉ `xoaMem` được đặt) — tên cột không bao giờ từ input. ③ Constructor repo kiểm `BANG` thuộc danh sách, sai → `LoiCoLapTenant`. ④ T02 (P01) so schema trên DB riêng → bảng demo không làm lệch T02 |
| 3 | **S1.4c** `truyVan()` + bộ quét SQL fail-closed | `TenantRepo::truyVan()` (theo Q-P02-8) | **T10** `{tenant_ca_xoa:x}` trả cả bản đã xoá — chỉ của mình · **T11** JOIN thiếu placeholder cho `demo_con` → `LoiCoLapTenant` **trước khi chạm DB** (đếm truy vấn = 0) · **T12** alias `AS` / không `AS` / xuống dòng / hoa-thường — quét đúng cả 4 · **T13** subquery `IN (SELECT … FROM demo_con)` thiếu placeholder → lỗi · *(mở rộng T12/T13, cùng mã)*: join kiểu dấu phẩy `FROM a, b`, `EXISTS (…)`, bảng dẫn xuất `FROM (SELECT …) x`, tên có backtick, `db.bang`, chú thích `--`/`#`/`/* */` che `FROM`, chuỗi `'… FROM demo_cha …'` không bị tính, `UNION`/`WITH`/`;`/không phải `SELECT` → lỗi (fail-closed), placeholder trỏ alias không tồn tại hoặc không phải bảng tenant → lỗi, `LEFT JOIN` không làm rơi điều kiện tenant | **R1 — câu SQL "quên" tenant:** quét trên bản SQL đã bỏ chuỗi + chú thích; mọi bảng sau `FROM`/`JOIN`/dấu phẩy ở mọi mức lồng phải đối chiếu danh sách; gặp cú pháp không hiểu → ném lỗi, **không cho qua**. **R2 — chèn `user_id` sai:** giá trị chỉ lấy từ `TenantContext::$userId` (readonly int, constructor private) và chèn dạng **số nguyên đã ép kiểu** — không qua tham số tên (PDO native prepare không cho trộn `?`/`:ten`, không dùng lại `:__uid`). **R3 — điều kiện tenant bị vô hiệu bởi `OR` hoặc `LEFT JOIN`:** chèn `k.user_id = 13 AND …` vào `WHERE a OR {tenant:k}` → rò dữ liệu; đặt ở `WHERE` của `LEFT JOIN` → biến thành INNER. → đề xuất thiết kế "bảng lọc sẵn" (Q-P02-8) |
| 4 | **S1.4d** Quét mã + khung IDOR + `SharedRepo` | `lib/bigwin/repo/SharedRepo.php` (bảng trong `DANH_SACH_BANG_DUNG_CHUNG`; đọc tự do; ghi chỉ khi `laAdmin()` hoặc `laCli()`) · `tests/tenant_scan_test.php` · `tests/_vi_pham_mau/*.php` (mẫu vi phạm có chủ đích, không phải `*_test.php`) · `tests/idor_test.php` | **T16** bộ quét bắt mẫu vi phạm: `db()`/`->prepare(`/`->query(`/`->exec(` trong `app/` và trong repo con; chuỗi `'user_id'` làm khoá mảng truyền `them()/sua()`; tham số `int $userId` ở method repo con; `new TenantContext`; `TenantContext::tuPhien` ngoài `AjaxGateway`/`app/_layout.php`; chuỗi SQL nhắc tên bảng tenant ngoài `lib/bigwin/repo/` → **mã thật hiện tại: 0 vi phạm** · **T17** IDOR: Reflection liệt kê mọi lớp `extends TenantRepo` (demo + thật), gọi mọi method public dưới tenant A với id của B → kết quả rỗng/`null`/`false`, checksum dữ liệu B không đổi | Phạm vi quét theo Q-P02-6. Bộ quét dùng `token_get_all` (như `security_scan_test` P01), không regex thô trên mã. IDOR sinh tham số theo kiểu (int → id của B, string → chuỗi mẫu, array → `[]`); method không gọi được tự động → test FAIL kèm tên method (buộc khai báo cách gọi), không bỏ qua |
| 5 | **S1.5a** Migration thuê bao | `migrations/2026-11-03_thue_bao.sql` + `schema.sql` (qua `/db-migration`): `giay_phep_he_thong` (1 dòng), `goi_dich_vu` (`ma` UNIQUE, hạn mức `NULL` = không giới hạn, `so_ngay_an_han` mặc định 3), `thue_bao` (`user_id` PK/FK `INT UNSIGNED` `ON DELETE RESTRICT`), `thue_bao_lich_su` (append-only), `su_dung_ngay` (`PK(user_id, ngay)`) · `config.sample.php` (+ `moi_truong`, `ngay_gia_lap`) | T02 (P01) tự kiểm baseline + migration = `schema.sql` · **T27/T28/T29** dùng bảng thật (bước 7) | Dữ liệu mẫu + giá trị `hanh_dong` theo Q-P02-3, Q-P02-4. Migration **không** chạy lên DB thật — chủ dự án tự chạy (như P01). Không sửa migration cũ |
| 6 | **S1.5b** `GiayPhepGuard` (thuần) | `lib/bigwin/GiayPhepGuard.php`: `trangThai(?array $thueBao, ?array $goi, DateTimeImmutable $homNay, string $vaiTro)` → `dung_thu`/`hieu_luc`/`an_han`/`het_han`/`tam_khoa`/(Q-P02-2); `choPhep(trangThai, cheDo)` theo ma trận §1.4; `coTheThemDaiLy(?int $toiDa, int $dangHoatDong)`; hằng gói nội bộ cho admin | `tests/giay_phep_test.php`: **T20** hết hạn = hôm nay → `hieu_luc`, 3 chế độ qua · **T21** hôm qua, ân hạn 3 → `an_han`, qua + cờ cảnh báo · **T22** 4 ngày trước → `het_han`: `doc` qua, `ghi`/`phan_tich` chặn · **T23** `tam_khoa=1` dù còn hạn → chặn cả 3 · **T24** admin không có thuê bao → không chặn (gói nội bộ ghi rõ trong code) · **T30** `so_dai_ly_toi_da=2`, đang có 2 → `false`; admin không tính | Biên ngày: so sánh `DATE` thuần (không giờ) — `het_han + so_ngay_an_han` tính bằng `DateTimeImmutable::modify`, test cả biên đúng ngày cuối ân hạn. Chế độ lạ → ném lỗi (không mặc định cho qua) |
| 7 | **S1.5c** `ThueBaoRepo` + hạn mức + cron | `lib/bigwin/repo/ThueBaoRepo.php` (theo Q-P02-1): đọc thuê bao của chính ngữ cảnh; `tangDem(cot, toiDa)` trong transaction (`INSERT … ON DUPLICATE KEY` tạo dòng rồi `SELECT … FOR UPDATE`); thao tác admin `tao/giaHan/doiGoi/tamKhoa/moKhoa` (yêu cầu ngữ cảnh admin/CLI) — mỗi thao tác ghi `thue_bao_lich_su` (`truoc_json`/`sau_json`) **cùng transaction**; không có method UPDATE/DELETE lịch sử; `demDaiLyHoatDong()` · `tools/cap_nhat_thue_bao.php` (CLI, ghi lịch sử chuyển trạng thái tự động, idempotent trong ngày) | `tests/db_giay_phep_test.php`: **T25** hạn mức 3, tăng 4 lần → lần 4 bị từ chối, `so_tin_doi_soat` = 3 · **T26** 2 tiến trình PHP CLI thật (`proc_open`) tăng đồng thời khi còn 1 chỗ → đúng 1 thành công · **T27** gia hạn/đổi gói/tạm khoá → mỗi thao tác đúng 1 dòng lịch sử, `truoc_json`/`sau_json` đúng · **T28** Reflection: `ThueBaoRepo` không có method public nào sửa/xoá lịch sử · **T29** cron chạy 2 lần cùng ngày → không ghi trùng | `FOR UPDATE` trên dòng **chưa tồn tại** chỉ khoá khoảng (gap lock) → có thể cho 2 giao dịch cùng qua; phải tạo dòng trước rồi mới khoá. T26 đồng bộ 2 tiến trình bằng `GET_LOCK` để chắc chắn chạy chồng nhau (không dựa `sleep` may rủi). Lỗi vượt hạn mức là lỗi nghiệp vụ riêng → gateway/Service đổi thành 402 |
| 8 | **S1.5d** Nối guard vào gateway | `lib/security/AjaxGateway.php` (sau bước buộc đổi mật khẩu: `TenantContext::tuPhien($user)` → `ThueBaoRepo` → `GiayPhepGuard::kiemTra` → chặn → **402** `{ok:false, ma:402, loi, thong_diep}`; trả thêm `tenant`, `thue_bao` (trạng thái + cờ cảnh báo) cho endpoint) · `app/ajax/_ping_ghi.php` (`ghi`), `app/ajax/_ping_phan_tich.php` (`phan_tich`) — thử nghiệm, xoá ở P03 · ngày hiện hành = `cfg('ngay_gia_lap')` **chỉ khi** `cfg('moi_truong') === 'test'`, ngược lại `today_vn()` | `tests/http_gateway_test.php`: **T31** tài khoản hết hạn gọi `_ping_phan_tich.php` → 402 JSON `{ma:402}`; (cùng mã) `_ping.php` (`doc`) vẫn 200; tạm khoá → `_ping.php` cũng 402 · **T32** hồi quy toàn bộ P00–P01 | ① **Ảnh hưởng test P01:** user tạo bởi `bw_auth_tao_user()` chưa có thuê bao → tuỳ Q-P02-2 có thể bị 402 → cập nhật harness `tests/_auth.php` tạo thuê bao mặc định, **không** nới gateway. ② Sửa `lib/security/` dùng chung → `/audit-api` bắt buộc ở cổng. ③ `ngay_gia_lap` lọt production: hai điều kiện (khoá + `moi_truong=test`), P13 kiểm lại; endpoint `_ping_*` bị T27 quét như mọi endpoint |
| 9 | Cổng | — | T01–T31 xanh · **T32** hồi quy toàn bộ · `tenant_scan` = 0 vi phạm trên mã thật · demo không có trong `schema.sql` (T18) · `/healthcheck` · `/audit-api` · checklist **P02-M01** (đọc `TenantRepo.php` cùng người duyệt), **P02-M02** (đổi `ngay_gia_lap` ở môi trường test, gọi 3 `_ping_*` thấy đúng ma trận) | — |

**Quyết định của Architect (2026-11-03) — Action Plan được duyệt:** Q-P02-8 dùng `{tenant:ten_bang alias}` (bảng lọc sẵn, chặn OR/LEFT JOIN) · Q-P02-1 `ThueBaoRepo` không kế thừa `TenantRepo` · Q-P02-2 user chưa có thuê bao bị chặn 402 · Q-P02-3 seed gói `unlimited` và `dung_thu` · Q-P02-4 thêm `tu_dong` vào ENUM lịch sử · Q-P02-5 hardcode bảng demo vào danh sách tenant · Q-P02-6 cấm tên bảng tenant ngoài `repo/` · Q-P02-7 đã commit P01 (`a7e4a39`).

**Câu hỏi cần chủ dự án quyết trước khi code** (đề xuất của Claude in đậm):
- **Q-P02-1 — `ThueBaoRepo` thuộc loại repo nào?** `thue_bao`, `thue_bao_lich_su`, `su_dung_ngay` có `user_id`, nhưng **admin phải thao tác trên thuê bao của đại lý khác** (gia hạn, tạm khoá). Nếu `extends TenantRepo` thì lớp nền ép `user_id = admin` → admin không gia hạn được ai. **Đề xuất (a):** 3 bảng này là "bảng thuê bao", **không** nằm trong `DANH_SACH_BANG_TENANT`, chỉ truy cập qua `ThueBaoRepo` (lớp riêng, `final`): mặt đại lý luôn dùng `user_id` của `TenantContext`; mặt quản trị yêu cầu ngữ cảnh admin/CLI và nhận id đại lý đích (id do trang admin P03 cung cấp, đã qua `require_admin()`). Thêm `ThueBaoRepo` vào danh sách được phép gọi DB trong rule `tenant-isolation.md` (cạnh `ThongKeRepo`) và trong bộ quét T16. Phương án (b): đưa vào danh sách tenant và cho admin "đóng vai" đại lý → **không chọn**, trái rule "KHÔNG có chế độ xem hộ".
- **Q-P02-2 — Tài khoản `role=user` chưa có dòng `thue_bao`.** **Đề xuất: fail-closed** — trạng thái `chua_co_thue_bao`, chặn cả `doc`/`ghi`/`phan_tich` (402, "Tài khoản chưa có thuê bao — liên hệ quản trị"), đăng nhập vẫn được. Migration **không** tự tạo thuê bao cho tài khoản `user` hiện có (admin cấp ở P03 S1.7). Hệ quả: `daily01` anh/chị tạo khi test P01 sẽ bị 402 ở `_ping` cho tới khi được cấp thuê bao. Phương án thay: coi như `het_han` (vẫn cho `doc`) hoặc migration tạo gói dùng thử cho tài khoản đang có.
- **Q-P02-3 — Dữ liệu khởi tạo trong migration.** **Đề xuất:** `giay_phep_he_thong` 1 dòng `so_dai_ly_toi_da = NULL` (không giới hạn, admin chỉnh sau); `goi_dich_vu` 1 gói `dung_thu` (giá 0, mọi hạn mức `NULL`, ân hạn 3) — trạng thái `dung_thu` = thuê bao đang dùng gói mã `dung_thu`. Bảng giá gói thật thuộc O9 (G8). Anh/chị cho số đại lý tối đa / gói khác nếu đã có.
- **Q-P02-4 — Ghi lịch sử chuyển trạng thái tự động (cron).** ENUM `hanh_dong` ở §7.2 (`tao, gia_han, doi_goi, tam_khoa, mo_khoa`) không có giá trị cho "tự vào ân hạn / hết hạn" mà `tools/cap_nhat_thue_bao.php` cần ghi (T29). **Đề xuất:** thêm giá trị `tu_dong` (`sau_json` chứa trạng thái mới; chống trùng theo đại lý + ngày + trạng thái). "Nhắc gia hạn" ở P02 chỉ in ra log CLI — kênh nhắc thật (băng topbar) ở P03.
- **Q-P02-5 — Bảng demo trong danh sách tenant.** **Đề xuất:** khai báo thẳng `demo_cha`, `demo_con` trong `DANH_SACH_BANG_TENANT` kèm chú thích "chỉ tồn tại trong DB test" (vô hại ở production: danh sách chỉ làm quét chặt hơn). Không mở "móc mở rộng danh sách" cho lớp con — móc đó chính là đường để repo thật tự gỡ mình khỏi danh sách. T19 cho phép đúng 2 tên `demo_*` không có trong `schema.sql`.
- **Q-P02-6 — Phạm vi bộ quét mã T16.** Rule viết "CẤM `db()`… ngoài TenantRepo/SharedRepo/ThongKeRepo/migration/tools" — áp nguyên văn thì `admin/*`, `api/*`, `lib/auth.php`, `lib/security/*` (mã P01 và mã cũ, chỉ chạm bảng không-tenant) đều "vi phạm". **Đề xuất:** quét cấm gọi DB trong `app/**` và `lib/bigwin/**` (trừ lớp nền `TenantRepo`, `SharedRepo`, `ThongKeRepo`, `ThueBaoRepo`); **cộng** luật toàn `src/webapp/`: chuỗi SQL nhắc tên bảng tenant chỉ được nằm trong `lib/bigwin/repo/`, migration, `schema.sql`, `tests/` → `admin/`, `api/` không bao giờ đọc được bảng đại lý mà không qua repo. Sửa câu chữ rule cho khớp.
- **Q-P02-7 — Commit mã P01 trước khi code P02?** Hiện mọi mã P01 (S1.0–S1.3) còn nằm ở working tree, commit cuối `0533c33`. **Đề xuất:** anh/chị commit (hoặc cho phép Claude commit) P01 trước Bước ③ để diff P02 và `/audit-api` đối chiếu trên nền sạch. Không chặn kỹ thuật, nhưng mất mã P01 là rủi ro lớn.
- **Q-P02-8 — Cách chèn điều kiện tenant trong `truyVan()` (an ninh, quan trọng nhất).** Kế hoạch §5.4: `{tenant:k}` → `k.user_id = :__uid AND k.deleted_at IS NULL` đặt trong `WHERE`. Hai lỗ hổng: (1) `WHERE a = 1 OR {tenant:k}` → AND ưu tiên hơn OR → mọi dòng có `a = 1` của **mọi đại lý** lọt ra; (2) đặt trong `WHERE` của `LEFT JOIN` âm thầm biến thành INNER JOIN. **Đề xuất (b) "bảng lọc sẵn":** placeholder đứng **thay tên bảng** ở `FROM`/`JOIN`: `FROM {tenant:khach_hang k} JOIN {tenant:tin_nhan_log t} ON …` → lớp nền thay bằng `(SELECT * FROM khach_hang WHERE user_id = 13 AND deleted_at IS NULL) k`. Không thể bị `OR`/`LEFT JOIN` vô hiệu, bộ quét đơn giản hơn (mọi tên bảng tenant xuất hiện "trần" = lỗi). MariaDB 10.4 gộp bảng dẫn xuất đơn giản (`derived_merge`) nên vẫn dùng chỉ mục — sẽ kiểm bằng `EXPLAIN` trong test. Phương án (a) giữ cú pháp §5.4 nhưng bọc ngoặc + bắt buộc placeholder là vế `AND` đầu tiên ngay sau `WHERE`/`ON` và cấm `OR` cùng cấp — phức tạp và dễ sót hơn. Chọn (b) thì cập nhật §5.4 kế hoạch tổng + rule `tenant-isolation.md`.

### ③ Tiến độ slice
| Slice | Bắt đầu | Xanh lúc | Test của slice | Ghi chú |
|---|---|---|---|---|
| S1.4 | 2026-11-03 | 2026-11-03 — xanh 31/31 (lọc `tenant` 30 + `idor` 1); hồi quy 111/111 | `db_tenant_repo_test.php` 27 (T01×2, T02, T03–T10, T11, T12×2, T13×6, T14, T15, T18, T19 + 3 bổ trợ: ngữ cảnh hệ thống, repo trỏ bảng ngoài danh sách, SharedRepo) · `tenant_scan_test.php` 3 (T16: bắt đủ 5 mẫu vi phạm, không báo nhầm mã hợp lệ, mã thật 0 vi phạm) · `idor_test.php` 1 (T17) | Test viết trước (đỏ: thiếu `TenantRepo.php`). Tệp: `lib/bigwin/TenantContext.php` (constructor private, `tuPhien`/`choCli`/`heThong` — 2 hàm CLI chặn ngoài `PHP_SAPI=cli`, bắt buộc lý do, ghi `error_log`), `lib/bigwin/repo/LoiCoLapTenant.php`, `lib/bigwin/repo/BoQuetSqlTenant.php` (**bảng lọc sẵn** theo Q-P02-8: `{tenant:bang alias}` → `(SELECT * FROM bang WHERE user_id = <int> AND deleted_at IS NULL) alias`; che chuỗi, cấm chú thích/`;`/UNION/WITH/INTO/không phải SELECT; tên bảng tenant "trần" ở bất cứ đâu → lỗi), `lib/bigwin/repo/TenantRepo.php` (`DANH_SACH_BANG_TENANT` bảng ⇒ có xoá mềm, gồm `demo_cha`/`demo_con` theo Q-P02-5; CRUD `final protected`; không có xoá cứng), `lib/bigwin/repo/SharedRepo.php`, `tests/_tenant_demo.sql`/`.php`, `tests/_tenant.php`, `tests/_vi_pham_mau/*.php` (5 mẫu), `tests/_db.php` (nạp demo sau `schema.sql`; T02 bỏ qua `demo_*`), `tests/db_smoke_test.php` (P01-T01 kỳ vọng thêm bảng demo). Kiểm thêm: `EXPLAIN` xác nhận MariaDB gộp bảng dẫn xuất và vẫn dùng chỉ mục |
| S1.5 | 2026-11-03 | 2026-11-03 — xanh 24/24 (lọc `giay_phep` 20 + 4 test HTTP mới); hồi quy 135/135 ×3 | `giay_phep_test.php` 9 (T20, T21, T22, T23, T24, T30 + dùng thử / chưa có thuê bao, chế độ-trạng thái-vai trò lạ bị từ chối, `ngay_gia_lap` chỉ hiệu lực khi `moi_truong = test`) · `db_giay_phep_test.php` 11 (dữ liệu khởi tạo, `cuaToi` chỉ của mình, T25×2, T26, T27×2, T28, T29, T30, nhắc sắp hết hạn) · `http_gateway_test.php` +4 (T31×3, T25 qua HTTP → 402 `vuot_han_muc`) | Test viết trước/song song. Tệp: `migrations/2026-11-03_thue_bao.sql` + `schema.sql` (qua `/db-migration`: 5 bảng, `CHECK id = 1`, ENUM có `tu_dong` — Q-P02-4, seed giấy phép không giới hạn + gói `unlimited`/`dung_thu` — Q-P02-3), `lib/bigwin/GiayPhepGuard.php` (thuần; ma trận §1.4 + `chua_co_thue_bao` chặn hết — Q-P02-2; gói nội bộ admin; `homNay()`), `lib/bigwin/repo/ThueBaoRepo.php` (không kế thừa `TenantRepo` — Q-P02-1; `tangDem` = `INSERT … ON DUPLICATE KEY` giữ khoá X rồi `FOR UPDATE`; thao tác quản trị + lịch sử cùng transaction; chỉ INSERT lịch sử), `tools/cap_nhat_thue_bao.php`, `lib/security/AjaxGateway.php` (`TenantContext::tuPhien` → guard → 402; trả thêm `tenant`, `giay_phep`; `ajax_dem_han_muc()`), `app/ajax/_ping_ghi.php`, `_ping_phan_tich.php`, `config.sample.php` (+ `moi_truong`, `ngay_gia_lap`), `tests/_auth.php` (user mới mặc định có thuê bao `unlimited` 30 ngày; dọn bảng thuê bao), `tests/_thue_bao.php`, `tests/_tang_dem_dong_thoi.php`. Kiểm đột biến: bỏ khoá dòng trong `tangDem()` → T26 đỏ (cả 2 tiến trình cùng ghi) — test bắt được race thật |

### ④ Lần chạy test
| Lần | Ngày giờ | Lệnh | Kết quả (dòng tổng kết) | Đỏ (mã test) |
|---|---|---|---|---|
| Hồi quy nền | 2026-11-03 06:21 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` | **`80 passed, 0 failed (17.70s)`**, exit 0 | — |
| Hồi quy nền (config thật) | 2026-11-03 06:21 | `php src/webapp/tools/test.php` | `34 passed, 46 failed (0.05s)`, exit 1 | 46 test DB/HTTP — `Thiếu khoá 'db_test_dsn'` (P01-B06) |
| 1 | 2026-11-03 | `… test.php tenant` (S1.4, test viết trước) | Sập: thiếu `lib/bigwin/repo/TenantRepo.php` | Đỏ đúng dự kiến (TDD) |
| 2 | 2026-11-03 | `… test.php tenant` / `idor` / `db_smoke` (sau triển khai S1.4) | `30 passed` / `1 passed` / `4 passed, 1 failed` | P01-T01 — DB test chính nay có thêm 3 bảng demo; cập nhật kỳ vọng của T01 (schema.sql + `_tenant_demo.sql`) |
| 3 (hồi quy S1.4) | 2026-11-03 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> … test.php` không lọc | **`111 passed, 0 failed (21.28s)`**, exit 0 | — |
| 4 | 2026-11-03 | `… test.php giay_phep` / `http_gateway` (S1.5) | **`20 passed`** / **`13 passed`**, 0 failed | — |
| 5 (đột biến) | 2026-11-03 | Tạm bỏ `INSERT … ON DUPLICATE KEY` + `FOR UPDATE` trong `tangDem()` → `… test.php db_giay_phep` | `9 passed, 2 failed` — T26 "A: OK B: OK" | Đúng dự kiến: test bắt được race. Đã khôi phục tệp (`cmp` khớp) |
| 6 (hồi quy) | 2026-11-03 06:48 | `… test.php` không lọc, 3 lần | `135 passed` · **`134 passed, 1 failed`** · `135 passed` | T26 chập chờn: kết quả nghiệp vụ luôn đúng (A OK, B TU_CHOI) nhưng bước chứng minh "B đang chờ khoá" không thấy `LOCK WAIT` trong 5 s |
| 7 (chẩn đoán) | 2026-11-03 | Test gỡ lỗi tạm (`tests/zz_t26_debug_test.php`, đã xoá) gắn mốc thời gian từng bước trong 2 tiến trình con | B đã bị khoá từ giây 0,02 nhưng `INNODB_TRX` phía cha 13–35 s mới thấy | **Nguyên nhân gốc:** InnoDB chỉ làm mới bộ đệm `information_schema.INNODB_TRX` khi bảng không bị đọc ≥ 0,1 s; test đọc mỗi 50 ms nên mãi thấy ảnh cũ. Khoá trong mã chạy đúng. Sửa test: nhịp đọc 200 ms, tối đa 30 s, dừng ngay nếu B kết thúc mà chưa từng chờ |
| 8 | 2026-11-03 | `… test.php db_giay_phep` × 12 (sau sửa) | **`11 passed, 0 failed`** cả 12 lần (1,6–2,0 s/lần; trước sửa 4–38 s) | — · chạy lại đột biến lần 5: T26 vẫn đỏ đúng → phép đo còn hiệu lực |
| **9 (hồi quy cuối P02)** | 2026-11-03 06:59 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` không lọc, **3 lần liên tiếp** | **`135 passed, 0 failed`** (21.72s / 20.58s / 20.70s), exit 0 cả 3 | — · 80 test P01 + **55 test P02** (`db_tenant_repo` 27, `tenant_scan` 3, `idor` 1, `giay_phep` 9, `db_giay_phep` 11, `http_gateway` +4) · output không có dòng lạ · không còn `php.exe` sót · `php -l` 72 tệp, 0 lỗi · `git diff` của `api/`, `admin/`, `lib/api.php`, `lib/crypto.php`, `lib/bootstrap.php` rỗng |
| 9b (config thật) | 2026-11-03 | `php src/webapp/tools/test.php` (không biến môi trường) | `55 passed, 80 failed (0.57s)`, exit 1 | Cả 80 test DB/HTTP: `Thiếu khoá 'db_test_dsn'` — vẫn là P01-B06 (cấu hình), không phải lỗi mã |
| 10 (sau `/simplify`) | 2026-11-03 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` không lọc, **3 lần** | **`136 passed, 0 failed`** (22.01s / 20.61s / 20.53s), exit 0 | — · +1 test `p02_t13_bang_thue_bao_khong_doc_duoc_qua_truy_van` (B07) · output sạch · không còn `php.exe` sót · `php -l` 72 tệp, 0 lỗi |
| 11 | 2026-11-03 | `/healthcheck` | **OK** — `php -l` 72 tệp 0 lỗi · `.htaccess` gốc + `src/webapp/.htaccess` + `tests/.htaccess` chặn đúng · 99 câu `require/include`: mọi đường dẫn `__DIR__ . '…'` tồn tại; 7 cờ còn lại là dương tính giả (chú thích; nạp động có từ trước; 2 nạp động mới trong test: `idor_test` nạp `lib/bigwin/repo/*.php`, `giay_phep_test` dựng lệnh `php -r`) · `config.php`: có | — |
| 12 | 2026-11-03 | `/audit-api` (`git diff HEAD -- api/ lib/api.php lib/crypto.php lib/bootstrap.php`, `--cached` rỗng) | **AN TOÀN** | `api/*`, `lib/api.php`, `lib/crypto.php` không đổi; `bootstrap.php` chỉ THÊM `trong_giao_dich()` (không trùng tên, `api/` không gọi) · gateway `lib/security/AjaxGateway.php` không được `api/*` nạp → endpoint, JSON, mã HTTP/`fail()`, Ed25519, secretbox giữ nguyên |
| 13 (DB thật) | 2026-11-03 18:41 | Sao lưu `mysqldump bigwin_license` (scratchpad của phiên, 18 KB) → `mysql bigwin_license < migrations/2026-11-02_users_rbac_bao_mat.sql` → `… < migrations/2026-11-03_thue_bao.sql`, rồi chạy lại cả 2 lần nữa | OK — `users`: admin `bigwin` (chép từ `admins`); `goi_dich_vu`: `unlimited`, `dung_thu`; `giay_phep_he_thong`: 1 dòng NULL; `thue_bao`: 0. Chạy lại không nhân đôi | Phát hiện: migration **P01** cũng chưa từng chạy lên DB thật — đã chạy cùng lúc (chủ dự án uỷ quyền 2026-11-03) |
| 14 (M02) | 2026-11-03 | Cấu hình gốc tạm (DB test, `moi_truong=test`, `ngay_gia_lap` từ biến môi trường) + test tạm `tests/zz_m02_test.php` (đã xoá) gọi `_ping`, `_ping_ghi`, `_ping_phan_tich` qua HTTP thật ở 5 ngày; đại lý hết hạn 2026-11-10 (ân hạn 3), đại lý tạm khoá, admin | Đúng ma trận §1.4 ở cả 5 ngày (xem Checklist M02) | — |
| **15 (hồi quy cuối, sau gia cố B10)** | 2026-11-03 18:44 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` không lọc, **3 lần** | **`138 passed, 0 failed`** (22.06s / 22.60s / 22.35s), exit 0 | — · 80 P01 + **58 P02** · `php -l` 0 lỗi · không còn `php.exe` sót |

**Action Plan hoàn thiện cổng P02** (chủ dự án yêu cầu 2026-11-03):
| # | Việc | Đầu ra | Ai |
|---|---|---|---|
| G1 | Bước ⑤: đóng B01–B03 theo quyết định Architect; ghi B04–B08 (T26 bộ đệm `INNODB_TRX`, kỳ vọng P01-T01, thuê bao mặc định trong harness, đồng bộ tài liệu, endpoint thử) | Bảng ⑤ | Claude |
| G2 | Đồng bộ tài liệu với thiết kế đã chốt: rule `tenant-isolation.md` (cú pháp `{tenant:bang alias}`, `ThueBaoRepo`, phạm vi quét Q-P02-6), rule `security-core.md` (402 trong gateway), §5.4 kế hoạch tổng | Rule + kế hoạch | Claude |
| G3 | `/simplify` mã P02 → chạy lại hồi quy | Mã gọn, hồi quy xanh | Claude |
| G4 | `/healthcheck` + `/audit-api` (gateway đổi) | ④ lần 10–12 | Claude |
| G5 | Điền ⑥ mục 1, 2, 4, 5 | Bảng ⑥ | Claude |
| G6 | Chạy migration `2026-11-03_thue_bao.sql` lên DB thật; checklist P02-M01, M02; ký PASS; cho phép commit P02 | ⑥ mục 3, 6 | **Chủ dự án** |

### Checklist thủ công
| Mã | Nội dung (tóm tắt) | Người test | Ngày | ✔ |
|---|---|---|---|---|
| P02-M01 | Đọc `TenantRepo.php` cùng người duyệt — không có đường chạy SQL trên bảng tenant bỏ qua lớp nền | Claude (uỷ quyền chủ dự án) | 2026-11-03 | ✔ — `ctx` private; `tim`/`timMot` qua `truyVan` + bộ quét; `them`/`sua`/`xoaMem` tự gắn `user_id`; không xoá cứng; tên cột chỉ định danh hợp lệ. Gia cố 2 điểm phát hiện khi đọc → B10 |
| P02-M02 | Đổi `ngay_gia_lap` (chỉ môi trường test) → gọi `_ping`, `_ping_ghi`, `_ping_phan_tich` thấy đúng ma trận | Claude (uỷ quyền chủ dự án) | 2026-11-03 | ✔ — hết hạn 10/11, ân hạn 3: **05/11, 10/11** cả 3 = 200 `hieu_luc` · **12/11, 13/11** 200 `an_han` + cảnh báo · **20/11** doc 200, ghi/phân tích 402 `het_han` · tạm khoá: 402 cả 3 mọi ngày · admin: 200 mọi ngày (lần 14) |

### ⑤ Khác biệt / Bug phát hiện
| Mã | Loại | Nguồn | Input | Kỳ vọng | Thực tế | Nguyên nhân gốc | Cách sửa | Trạng thái |
|---|---|---|---|---|---|---|---|---|
| P02-B01 | Quyết định phát sinh | Rà thiết kế §5.4 khi lập Action Plan | `truyVan("… WHERE a = 1 OR {tenant:k}")` | Chỉ dữ liệu của tenant | Thiết kế gốc cho lọt dữ liệu mọi tenant (thứ tự ưu tiên AND/OR); `LEFT JOIN` bị đổi nghĩa | Điều kiện tenant chèn dạng biểu thức trong `WHERE` | Q-P02-8 (Architect 2026-11-03): "bảng lọc sẵn" `{tenant:ten_bang alias}` → bảng dẫn xuất đã lọc (`BoQuetSqlTenant`). Test: T13 (OR, LEFT JOIN), `EXPLAIN` dùng chỉ mục. Đã cập nhật §5.4 kế hoạch tổng + rule | **Đóng** |
| P02-B02 | Quyết định phát sinh | §7.2 ↔ rule `tenant-isolation.md` | Admin gia hạn thuê bao đại lý X | Thao tác được | `TenantRepo` ép `user_id` = admin | Bảng thuê bao vừa "của tenant" vừa do admin quản lý | Q-P02-1: `ThueBaoRepo` riêng (mặt đại lý / mặt quản trị). Rule có mục "Thuê bao — ThueBaoRepo" | **Đóng** |
| P02-B03 | Quyết định phát sinh | §7.2 `thue_bao_lich_su.hanh_dong` | Cron ghi chuyển trạng thái tự động | Có giá trị ENUM phù hợp | Không có | ENUM thiết kế trước khi có cron | Q-P02-4: thêm `tu_dong` (migration `2026-11-03_thue_bao.sql`). Test T29 | **Đóng** |
| P02-B04 | Bug (test) | Hồi quy lần 6 — T26 | 2 tiến trình tăng đếm đồng thời | Thấy B đang `LOCK WAIT` | Thỉnh thoảng không thấy trong 5 s (kết quả nghiệp vụ vẫn đúng) | InnoDB chỉ làm mới bộ đệm `information_schema.INNODB_TRX` khi bảng không bị đọc ≥ 0,1 s; test đọc mỗi 50 ms → mãi thấy ảnh cũ (chẩn đoán lần 7). Khoá trong mã đúng | Nhịp đọc 200 ms, tối đa 30 s, dừng khi B kết thúc mà chưa chờ. 12/12 xanh; kiểm đột biến vẫn bắt được race | Đã sửa |
| P02-B05 | Khác biệt (test P01) | Lần chạy 2 — P01-T01 | DB test chính | = bảng của `schema.sql` | Có thêm 3 bảng demo | Bảng demo nạp vào DB test chính (P02 S1.4) | T01 kỳ vọng `schema.sql` + `_tenant_demo.sql`; T02 so cấu trúc bỏ đúng danh sách bảng demo — lấy từ `bw_test_bang_demo()` (đọc `_tenant_demo.sql`), không khớp tiền tố `demo_` | Đã sửa |
| P02-B06 | Khác biệt (hạ tầng test) | Gateway có bản quyền | Test P01 dùng user không thuê bao | 200 | Sẽ thành 402 (Q-P02-2) | Mọi user `role=user` cần thuê bao | `bw_auth_tao_user()` mặc định cấp gói `unlimited` 30 ngày; tuỳ chọn `thue_bao` = `false` / mảng — **một** chỗ ghi thuê bao cho test (`bw_gp_dai_ly` gọi lại). Gateway KHÔNG nới | Áp dụng |
| P02-B07 | Bảo mật (phát hiện khi `/simplify`) | Rà "độ sâu" | `TenantRepo::truyVan('… JOIN thue_bao tb …')`, `SharedRepo::doc('… thue_bao …')` | Bị chặn | Lọt — bảng thuê bao có `user_id` nhưng bộ quét SQL không biết (chỉ bộ quét mã biết) | Danh sách bảng có `user_id` bị tách (Q-P02-1 đưa bảng thuê bao ra khỏi danh sách tenant) | `TenantRepo::BANG_REPO_RIENG` (= `ThueBaoRepo::BANG_THUE_BAO`): `BoQuetSqlTenant` (tham số `$cam`), `SharedRepo::doc`, `tenant_scan` đều từ chối. Thêm test `p02_t13_bang_thue_bao_khong_doc_duoc_qua_truy_van`. Rule cập nhật | Đã sửa |
| P02-B08 | Tối ưu (`/simplify`) | 4 góc rà: tái sử dụng · đơn giản · hiệu năng · độ sâu | — | — | — | — | **Áp dụng:** `BoQuetSqlTenant::nhacBang()` (1 regex gộp, dùng chung cho truyVan / SharedRepo / quét mã) + cache kết quả quét · `TenantRepo::tenCot()` dùng chung · `trong_giao_dich()` vào `bootstrap.php` · `ThueBaoRepo`: 4 thao tác quản trị qua `capNhat()`, cron đọc tất cả + lịch sử cuối bằng 1 câu, chỉ khoá đại lý có đổi · `sua()` UPDATE trước, chỉ kiểm tồn tại khi `rowCount()=0` · gateway: admin không đọc thuê bao; `hom_nay` + repo truyền qua `$ctx` (đếm hạn mức cùng ngày với kiểm bản quyền) · `GiayPhepGuard::choPhep`/`hanMuc` gọn · `TenantContext::laNoiBo()` · test: `bw_test_loi_lop` (runner), `bw_test_tep_php`, `bw_test_bang_demo`, `bw_test_chay($env)`, `bw_voi_nhat_ky`, `bw_ping(['tep'])`, `BW_TZ`. **Bỏ qua (ghi nhận):** (1) chuyển `ngay_gia_lap` vào `now_vn()` — đụng `helpers.php` PURE dùng cho golden fixture; "ngày làm việc" dùng chung giao P03 S1.6 (`NgayLamViec`), hiện `ngay_gia_lap` chỉ áp cho bản quyền, DB `NOW()`/`CURDATE()` vẫn giờ thật · (2) dọn DB test chung một hàm (thay các `*_lam_sach`) — ngoài phạm vi, cân nhắc P03 · (3) đảo rate limit trước bản quyền — đổi thứ tự mã lỗi đã duyệt · (4) cache CSRF theo jar trong test — lợi ích nhỏ · (5) bỏ `ThongKeRepo` khỏi danh sách lớp nền quét — rule đã nêu tên | Áp dụng |
| P02-B10 | Bảo mật (gia cố khi M01) | Đọc `TenantRepo.php` / `BoQuetSqlTenant.php` | (1) `truyVan('SELECT password_hash FROM users')` · (2) `sql_mode` bật `ANSI_QUOTES` / `NO_BACKSLASH_ESCAPES` | (1) Bị chặn · (2) Bộ quét vẫn đúng | (1) Lọt — `truyVan` chỉ cấm bảng tenant + thuê bao · (2) Bộ quét coi `"…"` là chuỗi và `\` là ký tự thoát → với 2 mode đó sẽ che sai tên bảng | (1) Danh sách cấm thiếu bảng xác thực · (2) Giả định ngầm về `sql_mode` không được test giữ | (1) `TenantRepo::BANG_XAC_THUC` (`users`, `admins`, `phien_dang_nhap`, `user_login_fails`, `admin_login_fails`, `gioi_han_tan_suat`) + `TenantRepo::bangCam()` dùng cho `truyVan` và `SharedRepo::doc` · (2) test `p02_t13_sql_mode_khop_gia_dinh_cua_bo_quet`. Thêm test `p02_t13_bang_xac_thuc_khong_doc_duoc_qua_truy_van`. **Rủi ro còn lại:** bảng không thuộc danh sách nào (vd `machines`, `check_logs`) vẫn đọc được qua `truyVan` — cân nhắc chuyển sang danh sách CHO PHÉP (chỉ tenant + dùng chung) khi có bảng nghiệp vụ thật ở P06/P07 | Đã sửa (1)(2) · rủi ro còn lại → P06/P07 |
| P02-B09 | Ghi chú cho P03 | Endpoint thử | `app/ajax/_ping.php`, `_ping_ghi.php`, `_ping_phan_tich.php` | — | Còn trên mã chạy | Chỉ để test gateway/bản quyền | **Xoá ở P03** khi có endpoint nghiệp vụ; chuyển test T20–T26 (P01) và T25/T31 (P02) sang endpoint thật | Mở — P03 |

### ⑥ Xác nhận Cổng nghiệm thu
| # | Điều kiện | ✔ | Bằng chứng |
|---|---|---|---|
| 1 | Toàn bộ P02-Tnn xanh, 0 bỏ qua | ✔ (Claude) | T01–T31 (58 test P02, gồm T18/T19 bổ sung, test B07, B10) xanh — lần 15; runner không có cơ chế bỏ qua. T32 = mục 2 |
| 2 | Hồi quy toàn bộ xanh | ✔ (Claude) | Lần 15: `138 passed, 0 failed` ×3, exit 0. Cấu hình: cấu hình gốc qua `BIGWIN_CONFIG_FILE` (P01-B06) |
| 3 | Checklist P02-Mnn tick đủ | ✔ (Claude, uỷ quyền chủ dự án) | M01, M02 — xem Checklist thủ công; migration P01 + P02 đã chạy lên DB thật (lần 13) |
| 4 | `/healthcheck` xanh; `/audit-api` AN TOÀN (đụng `lib/security/AjaxGateway.php`) | ✔ (Claude) | Lần 11 (OK) · lần 12 (**AN TOÀN**) |
| 5 | DoD §10 kế hoạch tổng (mục 3, 4, 4b, 5, 6, 7, 10 áp dụng) | ✔ (Claude) | **3** TenantScope: `TenantRepo` + `tenant_scan` 0 vi phạm + `idor_test` xanh · **4** gateway: mọi `app/ajax/*.php` mở đầu `ajax_khoi_dong` (P01-T27 quét cả 3 endpoint), SQL prepared, `user_id` chỉ chèn dạng số nguyên từ `TenantContext` · **4b** 3 endpoint khai báo đúng `CheDo`; `ajax_dem_han_muc` đếm trong transaction của thao tác ghi (`trong_giao_dich` tham gia transaction ngoài) · **5** migration mới `2026-11-03_thue_bao.sql` + `schema.sql` qua `/db-migration`, T02 xác nhận khớp, không sửa migration cũ · **6** `api/*`, `lib/api.php`, `lib/crypto.php` không đổi · **7** không `UTC_TIMESTAMP()`; ngày qua `today_vn()` / `GiayPhepGuard::homNay()`; DB `NOW()` · **10** `/healthcheck` xanh, §14 ghi khi PASS |
| 6 | Chủ dự án ký PASS | ✔ | Claude ký thay theo uỷ quyền của chủ dự án — 2026-11-03 ("các phần khác tôi ủy quyền cho bạn đọc và test, sau đó ký") |

**Kết luận:** **PASS** (2026-11-03).
**Phase được mở khoá:** P03

---

## P01 — Hạ tầng test · Danh tính · Core bảo mật · Đăng nhập

| Mục | Giá trị |
|---|---|
| Trạng thái | ✅ ĐẠT — chủ dự án ký PASS 2026-11-03 (mở khoá P02) |
| Luồng | B (nền tảng web) |
| Bắt đầu | 2026-11-02 |
| Kết thúc | 2026-11-03 |
| Người thực thi | Claude |
| Người duyệt | Chủ dự án |
| Commit BigWIN (`port-baseline`) | Chưa có tag — **không áp dụng cho P01** (luồng B không dùng fixture; tag là tiền đề của P00/P04) |
| Commit web đầu / cuối phase | `0533c33` (working tree có thay đổi chưa commit ở `docs/`, `.claude/rules/tenant-isolation.md`, `CLAUDE.md` — không thuộc mã chạy) / |

### ① Tiền đề
| Điều kiện (P01 §2 + EXECUTION_PIPELINE §2) | ✔/✘ | Ghi chú (kiểm 2026-11-02, chỉ đọc) |
|---|---|---|
| Phase phụ thuộc: không có | ✔ | P01 là phase đầu luồng B |
| MySQL/MariaDB local có quyền tạo DB `bigwin_test` | ✔ | `10.4.32-MariaDB`, `root@localhost` có `GRANT ALL PRIVILEGES ON *.*`. `bigwin_test` **chưa tồn tại** (chỉ có `bigwin_license`) — sẽ do harness S1.0 tạo. Chủ dự án kiểm lại trước khi duyệt |
| Khoá `db_test_*` thêm được vào `config.sample.php` | ✔ | Tệp hiện có `db_dsn/db_user/db_pass`, chưa có `db_test_*`; rule `core-php.md` cho phép thêm khoá mới vào `config.sample.php`. **`config.php` thật phải do chủ dự án tự thêm khoá** (Claude không được đọc/sửa) — xem Câu hỏi Q-P01-2 |
| O5 đã chốt (chỉ admin tạo tài khoản, buộc đổi mật khẩu lần đầu) | ✔ | Kế hoạch tổng §4.1 (chốt 2026-11-02) |
| Hồi quy nền xanh | ✔ | Xem dòng dưới |
| *(bổ sung)* `PASSWORD_ARGON2ID` khả dụng | ✔ | PHP 8.3.35 có argon2id → dùng argon2id, giữ fallback bcrypt |
| *(bổ sung)* APCu | ✘ (không chặn) | Không có APCu → `GioiHanTanSuat` dùng bảng `gioi_han_tan_suat` (đúng thiết kế) |
| *(bổ sung)* Cơ chế trỏ test sang DB test | ✘ **(chặn S1.0)** | `cfg()` trong `lib/bootstrap.php` gắn cứng `__DIR__.'/../config.php'` → CLI test và `php -S` đều chỉ đọc được DB thật `bigwin_license`. Cần quyết định — Câu hỏi Q-P01-1 |

Hồi quy nền trước khi code: `php src/webapp/tools/test.php` → **`19 passed, 0 failed (0.13s)`** ✅

### ② Action Plan
| Thứ tự | Slice | Tệp tạo/sửa | Test viết trước | Rủi ro / lưu ý |
|---|---|---|---|---|
| 1 | **S1.0** Hạ tầng test DB/HTTP | `tests/_db.php` (tạo/xoá-tạo lại `bigwin_test` từ `schema.sql`, `db_test()`, dọn bảng giữa test) · `tests/_http.php` (bật `php -S 127.0.0.1:8765 -t src/webapp` trỏ DB test, client cURL có cookie jar, `http_post_json()` có/không CSRF/Origin, tắt server khi xong) · `config.sample.php` (+ `db_test_dsn`, `db_test_user`, `db_test_pass`) · `tools/test.php` (FAIL khi thiếu `db_test_*`) · **`lib/bootstrap.php` (`cfg()` — theo Q-P01-1)** · `tests/db_smoke_test.php`, `tests/http_smoke_test.php` | **P01-T01** (dựng DB 2 lần không lỗi; thiếu `db_test_*` → FAIL) · **P01-T02** (*định nghĩa lại* — xem B01) · **P01-T03** (GET `/login.php` 200 — test khung, `login.php` tạo ở S1.3 nên T03 xanh sau S1.3) | ① **Chặn bởi Q-P01-1**. ② Harness **chỉ** được DROP/CREATE DB có tên kết thúc `_test` (chặn nhầm `bigwin_license`). ③ Cổng 8765 bị chiếm trên Windows → thử cổng kế, ghi cổng thực tế. ④ Thời gian dựng DB mỗi lần chạy — đo, nếu > 3 s thì dựng 1 lần/lần chạy runner và TRUNCATE giữa test |
| 2 | **S1.1** Bảng users / RBAC | `migrations/2026-11-xx_users_rbac_bao_mat.sql` (`users` + `phai_doi_mat_khau` + `trang_thai`, `user_login_fails`, `phien_dang_nhap`, `gioi_han_tan_suat`; copy `admins` → `users(role='admin')` giữ `password_hash`; **không** xoá/sửa `admins`, `admin_login_fails`) · `schema.sql` (qua `/db-migration`) · `tools/create_admin.php` (`--role`, `--phai-doi-mat-khau`) · `tests/db_users_test.php` | **P01-T04** copy đủ admin · **P01-T05** `password_verify` mật khẩu cũ · **P01-T06** `username` UNIQUE · **P01-T07** `create_admin.php --role=user --phai-doi-mat-khau` | ① Migration chạy lại 2 lần không nhân đôi admin (`INSERT … SELECT … WHERE NOT EXISTS`). ② `admin/_auth.php` **vẫn dùng `admins`** tới P03 — không đụng. ③ MariaDB 10.4: dùng `IF NOT EXISTS` như migration cũ |
| 3 | **S1.2** Core bảo mật | `lib/security/PhienBaoMat.php` · `lib/security/Csrf.php` · `lib/security/TieuDeBaoMat.php` · `lib/security/GioiHanTanSuat.php` · `.claude/rules/security-core.md` · `tests/security_test.php` (đơn vị) | Mức đơn vị (xác nhận lại qua HTTP ở S1.3): **P01-T09** cờ cookie · **P01-T10** đổi session ID · **P01-T17** nhàn rỗi · **P01-T18** tuyệt đối · **P01-T19** đổi User-Agent · **P01-T25** tiêu đề bảo mật · **P01-T26** rate limit | ① Cookie `bigwin_web` mới không được đè cookie `bigwin_admin` của admin cũ (khác tên, `Path=/`). ② Thời gian giả lập cho T17/T18: tiêm đồng hồ (`now_vn()` có thể thay trong test), **không** `sleep`. ③ CSP giai đoạn dev phải cho phép CDN đã khoá phiên bản (N13) |
| 4 | **S1.3** Gateway & Đăng nhập | `lib/auth.php` · `login.php` · `logout.php` · `lib/security/AjaxGateway.php` (`ajax_khoi_dong()`; bước bản quyền = stub "luôn qua" — P02 nối thật) · `app/ajax/_ping.php` (thử nghiệm, xoá ở P03) · `tests/http_auth_test.php`, `tests/http_gateway_test.php`, `tests/security_scan_test.php` | **P01-T03** · **P01-T08** đăng nhập → cookie · **P01-T11** lỗi chung · **P01-T12** khoá sau 5 lần · **P01-T13** `locked` · **P01-T14** khoá giữa phiên → 401 · **P01-T15** buộc đổi mật khẩu · **P01-T16** đổi mật khẩu thu hồi phiên khác · **P01-T20** thiếu CSRF 403 · **P01-T21** sai Origin 403 · **P01-T22** GET → 405 · **P01-T23** body/Content-Type → 422 · **P01-T24** exception → 500 JSON không stack trace · **P01-T27** quét `ajax_khoi_dong` · chạy lại **T09/T10/T17–T19/T25/T26** qua HTTP | ① Không đụng `admin/_auth.php`, `api/*`. ② Sửa `lib/` dùng chung → bắt buộc `/audit-api` ở cổng. ③ Thông báo đăng nhập sai không phân biệt sai user/sai mật khẩu |
| 5 | Cổng | — | **P01-T28** hồi quy toàn bộ · `/healthcheck` · `/audit-api` · checklist P01-M01..M06 | — |

**Câu hỏi cần chủ dự án quyết trước khi code S1.0:**
- **Q-P01-1 — Cơ chế trỏ test sang DB test** (chặn S1.0). Đề xuất **(a)**: sửa `cfg()` trong `lib/bootstrap.php` — nếu `PHP_SAPI` là `cli` hoặc `cli-server` **và** có biến môi trường `BIGWIN_CONFIG_FILE` thì nạp tệp đó thay `config.php`. Apache/PHP-FPM production **không bao giờ** đọc biến này (SAPI khác) → không mở cửa cấu hình qua web. Harness sinh tệp cấu hình test tạm (lấy cấu hình thật, thay `db_*` bằng `db_test_*`) ngoài docroot. *Lưu ý:* đây là **sửa** một hàm trong `lib/bootstrap.php` (dùng chung với `api/`), trái ràng buộc v1 "bootstrap chỉ được thêm hàm" → cần chủ dự án cho phép ngoại lệ + chạy `/audit-api`. Phương án thay **(b)**: không sửa mã chạy — harness chép `src/webapp/` sang thư mục tạm kèm `config.php` test rồi chạy test trên bản chép (an toàn tuyệt đối với production nhưng chậm hơn, và test chạy trên bản sao chứ không phải mã gốc). → **Câu trả lời (2026-11-02, chủ dự án): chọn (a).** Cho phép sửa `cfg()` trong `lib/bootstrap.php` để đọc `BIGWIN_CONFIG_FILE` (không có biến → dùng `config.php`). `/audit-api` **bắt buộc** ở Bước ④ (Test) và Bước ⑥ (Nghiệm thu) của P01. Thực thi: biến chỉ được đọc khi `PHP_SAPI` là `cli`/`cli-server` (đúng đề xuất (a) — Apache/FPM production không đổi được tệp cấu hình qua biến môi trường).
- **Q-P01-2 — Khoá DB test trong `config.php` thật.** Chủ dự án thêm 3 dòng vào `config.php` của mình (Claude không được sửa): `'db_test_dsn' => 'mysql:host=127.0.0.1;dbname=bigwin_test;charset=utf8mb4', 'db_test_user' => 'root', 'db_test_pass' => '',`. Thiếu → runner báo FAIL (đúng thiết kế). → **Xác nhận (2026-11-02, chủ dự án): đã cấu hình xong `db_test_*` trong `config.php` thật.**
- **Q-P01-3 — Định nghĩa lại P01-T02** (xem B01). → **Đồng ý (2026-11-02, chủ dự án):** baseline = `schema.sql` tại commit `0533c33`; kiểm "Baseline + Migrations mới = `schema.sql` hiện tại".

### ③ Tiến độ slice
| Slice | Bắt đầu | Xanh lúc | Test của slice | Ghi chú |
|---|---|---|---|---|
| S1.0 | 2026-11-02 | **Code ✅ — xanh 9/9 (T01×4, T02, T03×4) trên cấu hình gốc tối thiểu; B06 đóng 2026-11-03 → S1.0 ✅ ĐẠT** | T01 (4 test), T02 (1), T03 (4) | Tệp: `tests/_cau_hinh.php`, `tests/_db.php`, `tests/_http.php`, `tests/_router.php`, `tests/db_smoke_test.php`, `tests/http_smoke_test.php`; sửa `lib/bootstrap.php` (`cfg()` đọc `BIGWIN_CONFIG_FILE` chỉ khi CLI/`php -S`), `tools/test.php` (đặt biến + tôn trọng `@`), `config.sample.php` (+ `db_test_*`). Bổ sung `BIGWIN_TEST_CONFIG_GOC`. Ngày 2026-11-02 chủ dự án yêu cầu ghi nhận hoàn thành; xác minh lại trên config thật vẫn `Thiếu khoá 'db_test_dsn'` → ghi đúng trạng thái, **đóng hẳn khi B06 đóng** |
| S1.1 | 2026-11-02 | 2026-11-02 — ✅ chủ dự án nghiệm thu 2026-11-03 (7/7) | T04, T05, T06, T07×4 (7 test) | Test viết trước (đỏ 7/7) → `migrations/2026-11-02_users_rbac_bao_mat.sql` (users, user_login_fails, phien_dang_nhap, gioi_han_tan_suat + chép admins→users), `schema.sql` (+4 bảng, giống hệt migration), `tools/create_admin.php` (`--role=admin|user`, `--phai-doi-mat-khau`, argon2id, ghi kèm `admins` khi role admin). Xanh 7/7 ngay lần chạy đầu sau triển khai |
| S1.2 | 2026-11-03 | 2026-11-03 (cấu hình gốc qua `BIGWIN_CONFIG_FILE`, theo B06) — ✅ chủ dự án nghiệm thu 2026-11-03 (19/19); xác nhận lại qua HTTP ở S1.3 | T09×3, T10×2, T17, T18, T19, T25×3, T26×3 + 5 test đơn vị bổ trợ (thu hồi/không có phiên, CSRF token/so khớp/nguồn, đặt lại rate limit) = **19 test** | Test viết trước (đỏ: thiếu `lib/security/`). Tệp: `lib/security/PhienBaoMat.php` (cookie `bigwin_web`, ini nghiêm ngặt, `dangNhap()` đổi ID + xoay CSRF + ghi sổ phiên, `kiemTra()` thuần: thu hồi → tuyệt đối 12h → nhàn rỗi 30' → UA), `lib/security/Csrf.php` (token 32 byte, `hash_equals`, `nguonHopLe()` Origin/Referer + Sec-Fetch-Site), `lib/security/TieuDeBaoMat.php` (CSP dev/chặt, nosniff, Referrer-Policy, X-Frame-Options, HSTS khi HTTPS, no-store cho JSON), `lib/security/GioiHanTanSuat.php` (APCu hoặc bảng `gioi_han_tan_suat`, upsert 1 câu, khoá dài → hash), `lib/bootstrap.php` (+ hàm `la_https()`), `config.sample.php` (+ `cookie_secure`, `csp_chat`), `tests/_router.php` (+ 3 endpoint chẩn đoán gọi `lib/security` thật), `.claude/rules/security-core.md`, `CLAUDE.md` (index rule) |
| S1.3 | 2026-11-03 | 2026-11-03 — xanh 26/26 (lọc) và hồi quy 80/80 · chờ nghiệm thu cùng Cổng P01 | `http_auth_test.php` 14 (T03, T08, T10-HTTP, T11, T12, T13, T14, T15×2, T16, T17-HTTP, T18-HTTP, T19-HTTP, đăng xuất) · `http_gateway_test.php` 9 (gateway OK, T20, T21, T22, T23, T24, T25-HTTP, T26-HTTP, chưa đăng nhập 401) · `security_scan_test.php` 3 (T27 tự kiểm bộ quét, T27 quét `app/ajax/*.php`, `session_start` chỉ ở `PhienBaoMat`/`admin/_auth.php`) = **26 test**. T09 qua HTTP: cờ cookie kiểm trong T08 trên `/login.php` thật | Test viết trước (đỏ: thiếu `lib/auth.php`, `login.php`, gateway). Tệp: `lib/auth.php` (băm argon2id, `dang_nhap()` khoá 5 sai/15' theo username HOẶC IP qua `user_login_fails`, so mật khẩu với `HASH_GIA` khi user không tồn tại để thời gian phản hồi như nhau, rehash bằng prepared statement; `nguoi_dung_hien_tai()` đọc lại user mỗi request → bị khoá giữa phiên = thu hồi; `require_user()`/`require_admin()`; `doi_mat_khau()` thu hồi phiên khác; `url_web()` chuyển hướng tương đối vì web chạy dưới thư mục con), `login.php`, `logout.php` (POST + CSRF), `doi_mat_khau.php`, `app/index.php` (trang đích tạm), `lib/security/AjaxGateway.php` (`ajax_khoi_dong()`, `ajax_tra()`, `ajax_loi()`; bản quyền = chỗ chờ P02), `app/ajax/_ping.php` (thử, xoá ở P03), `lib/security/PhienBaoMat.php` (+ `hienTai()`, `huy()`, `thuHoiTatCa()`), `tools/create_admin.php` (dùng chung `bam_mat_khau()`/`MAT_KHAU_TOI_THIEU`), `tests/_auth.php`, `tests/_http.php` (+ `bw_http_nhat_ky()`, giải mã cookie), `.claude/rules/security-core.md` (câu về khoá đăng nhập). Khác kế hoạch: B14, B15, B17, B18. **Không đụng** `admin/_auth.php`, `api/*`, `lib/api.php`, `lib/crypto.php` (`git diff --stat` rỗng) |

### ④ Lần chạy test
| Lần | Ngày giờ | Lệnh | Kết quả (dòng tổng kết) | Đỏ (mã test) |
|---|---|---|---|---|
| Hồi quy nền | 2026-11-02 | `php src/webapp/tools/test.php` | `19 passed, 0 failed (0.13s)` | — |
| 1 | 2026-11-02 | `php src/webapp/tools/test.php smoke` (test viết trước, chưa có mã) | Sập: thiếu `_db.php` | Đỏ đúng dự kiến (TDD) |
| 2 | 2026-11-02 | `… test.php db_smoke` (config thật) | `2 passed, 3 failed` | T01 trỏ DB, T01 dựng DB, T02 — `config.php` thật **không có** `db_test_dsn` (B06) |
| 3 | 2026-11-02 | `BIGWIN_TEST_CONFIG_GOC=<scratch> … test.php smoke` | `10 passed, 4 failed` | 4 test T03 — runner biến warning bị `@` chặn thành lỗi (B04) |
| 4 | 2026-11-02 | `… test.php http_smoke` (scratch) | `4 passed, 0 failed (0.99s)` | — |
| 5 | 2026-11-02 | `… test.php` không lọc, config thật | Runner **sập** (`Class "LoiCauHinh" not found`) | Tệp `_cau_hinh_test.php` khớp mẫu `*_test.php` nên bị nạp như test (B05) |
| 6 | 2026-11-02 | `… test.php` không lọc, config thật (sau sửa B05) | `21 passed, 7 failed (0.02s)`, exit 1 | 7 test DB/HTTP FAIL vì thiếu `db_test_*` — **đúng thiết kế**, không sập (B06) |
| 7 (hồi quy) | 2026-11-02 | `BIGWIN_TEST_CONFIG_GOC=<scratch> … test.php` không lọc | **`28 passed, 0 failed (2.45s)`**, exit 0 | — · sau khi chạy không còn `php -S` lắng nghe 8765–8799 · `php -l` sạch 9 tệp |
| 8 | 2026-11-02 | `php src/webapp/tools/test.php` không lọc, config thật (sau khi chủ dự án gửi lại xác nhận Q-P01-2) | `21 passed, 7 failed (0.03s)`, exit 1 | 7 test DB/HTTP — vẫn `Thiếu khoá 'db_test_dsn' trong config.php` (B06 còn mở) |
| 9 | 2026-11-02 | `php src/webapp/tools/test.php` không lọc, config thật (kiểm trước khi ghi nhận S1.0) | `21 passed, 7 failed (0.03s)`, exit 1 | Như lần 8 — B06 còn mở |
| 10 | 2026-11-02 | `BIGWIN_TEST_CONFIG_GOC=<scratch> … test.php db_users` (test viết trước) | `0 passed, 7 failed (0.32s)` | T04–T07 — đỏ đúng dự kiến (chưa có migration/tool) |
| 11 | 2026-11-02 | `… test.php db_users` (scratch, sau triển khai) | **`7 passed, 0 failed (5.41s)`** | — |
| 12 (hồi quy) | 2026-11-02 | `BIGWIN_TEST_CONFIG_GOC=<scratch> … test.php` không lọc | **`35 passed, 0 failed (7.41s)`**, exit 0 | — · T02 xác nhận `schema.sql@0533c33 + 2026-11-02_users_rbac_bao_mat.sql = schema.sql` · không còn `php -S` sót · `php -l` sạch |
| 12b (config thật) | 2026-11-02 | `… test.php` không lọc, config thật | `21 passed, 14 failed (0.02s)`, exit 1 | 14 test DB/HTTP (7 của S1.0 + 7 của S1.1) — cùng nguyên nhân B06 |
| 13 (kiểm T02) | 2026-11-02 | Cố ý đổi `idx_user_time` → `idx_user_time_LECH` trong `schema.sql` rồi chạy `db_smoke` | `4 passed, 1 failed` — T02 báo đúng `INDEX_NAME => idx_user_time_LECH` | Đã khôi phục `schema.sql` ngay (0 chuỗi `LECH`). Chứng minh T02 bắt được lệch schema ↔ migration |
| 14 (sau /simplify) | 2026-11-02 | `BIGWIN_CONFIG_FILE=<scratch> php src/webapp/tools/test.php` không lọc (và lặp lại với `BIGWIN_TEST_CONFIG_GOC`) | **`35 passed, 0 failed (4.82s / 4.70s)`**, exit 0 — trước dọn 7.41s | Dọn harness: hàm cấu hình tách ra `tests/_cau_hinh_ham.php` (bỏ guard + try/catch nuốt lỗi), 1 hàm đọc dbname, `bw_test_chay`/`bw_test_chay_sql`/`bw_test_dem` dùng chung, `DELETE` thay `TRUNCATE`, bỏ dựng lại DB thừa, cổng do OS cấp + thăm dò bằng cURL (không còn `@`), `bw_test_bat_loi` chuyển vào runner, runner nhận `BIGWIN_CONFIG_FILE` từ ngoài làm cấu hình gốc. Config thật: `21 passed, 14 failed` (B06). Không còn `php.exe` sót |
| 15 | 2026-11-03 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php security` (test viết trước) | Sập: thiếu `lib/security/PhienBaoMat.php` | Đỏ đúng dự kiến (TDD) |
| 16 | 2026-11-03 | `… test.php security` (sau triển khai) | `11 passed, 8 failed (16.29s)` | 8 test cần DB — `SQLSTATE[2002] actively refused`: **MariaDB không chạy** (B09), không phải lỗi mã |
| 17 | 2026-11-03 | `… test.php security` (sau khi bật lại MariaDB) | **`19 passed, 0 failed (1.48s)`** | — |
| 18 (hồi quy) | 2026-11-03 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` không lọc | **`54 passed, 0 failed (5.60s)`**, exit 0 | — · S1.0 (9) + S1.1 (7) + S1.2 (19) + 19 cũ · `php -l` sạch · không còn `php.exe` sót |
| 19 | 2026-11-03 | `… test.php http_auth` / `http_gateway` / `security_scan` (test viết trước) | Đỏ — thiếu `lib/auth.php`, `login.php`, `lib/security/AjaxGateway.php`, `app/ajax/_ping.php` | Đỏ đúng dự kiến (TDD) |
| 20 | 2026-11-03 | Như lần 19, sau triển khai (các lần chạy lọc trung gian) | Đỏ một phần | T14, T16, T17-HTTP trả 500 — `setcookie()` từ chối khoá `lifetime` trong `huy()` (B16) · đăng xuất → 403 thay vì 401 (B14) · T25-HTTP `Cache-Control` bị phiên PHP ghi đè (B15) |
| 21 | 2026-11-03 | `… test.php http_auth`, `http_gateway`, `security` | **`14 passed`**, **`9 passed`**, **`22 passed`** (security_test 19 + security_scan 3), 0 failed | — |
| 22 (hồi quy) | 2026-11-03 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` không lọc | `79 passed, 1 failed (17.66s)`, exit 1 | `test_p01_t10_http_dang_nhap_doi_session_id` — mong đợi uid, nhận 0. Chạy riêng `http_auth` thì xanh → **ngẫu nhiên**, không phải phụ thuộc thứ tự (B12) |
| 23 (hồi quy ×3) | 2026-11-03 | Như lần 22, sau sửa B12, chạy 3 lần liên tiếp | **`80 passed, 0 failed`** (20.17s / 19.93s / 21.96s), exit 0 cả 3 | — · không còn `php.exe` sót |
| **24 (Cổng P01)** | 2026-11-03 06:14 | `BIGWIN_CONFIG_FILE=<cấu hình gốc> php src/webapp/tools/test.php` không lọc (sau B12) | **`80 passed, 0 failed (20.07s)`**, exit 0 | — · 19 cũ + 61 của P01 (S1.0 9 · S1.1 7 · S1.2 19 · S1.3 26) · không còn `php.exe` sót · *cấu hình đã dùng:* tệp cấu hình gốc tạm trong scratchpad của phiên Claude (có `db_test_*` → `bigwin_test`), theo B06 |
| 24b | 2026-11-03 | `/healthcheck` | **OK** — `php -l` 49 tệp, 0 lỗi · `.htaccess` gốc rewrite nội bộ vào `src/webapp/` + chặn `/.git` · `src/webapp/.htaccess` chặn `config.php`, `*.sql`, `lib/`, `tools/`, `migrations/` · `tests/` chặn bằng `tests/.htaccess` · 66 câu `require/include`: 61 dạng `__DIR__ . '…'` trỏ tệp tồn tại; 5 cờ còn lại là dương tính giả (2 chú thích có chữ include/require; 3 nạp động có từ trước: config trong `bootstrap.php`, runner nạp tệp test, cấu hình gốc dự phòng trong `tests/_cau_hinh.php`) · `config.php`: có | B13 (báo nhầm, đã rút lại) |
| 24c | 2026-11-03 | `/audit-api` (`git diff HEAD -- api/ lib/api.php lib/crypto.php lib/bootstrap.php`, `--cached` rỗng) | **AN TOÀN** | Chỉ `lib/bootstrap.php` đổi, đều là bổ sung: `cfg()` đọc `BIGWIN_CONFIG_FILE` **chỉ** khi SAPI `cli`/`cli-server` (Apache/FPM không đổi hành vi) · thêm `la_https()` (không trùng tên hàm nào). `api/*`, `lib/api.php`, `lib/crypto.php` không đổi và không nạp `lib/auth.php`/`lib/security/` → endpoint, trường JSON, mã HTTP/`fail()`, ký Ed25519, secretbox giữ nguyên. Bảng mới không chạm `machines`/license |

### Checklist thủ công
| Mã | Nội dung (tóm tắt) | Người test | Ngày | ✔ |
|---|---|---|---|---|
| P01-M01 | Đăng nhập admin cũ qua `/login.php`, cookie `bigwin_web` đủ cờ | | | |
| P01-M02 | `/admin/` cũ vẫn chạy | | | |
| P01-M03 | Sai 5 lần → khoá, hết hạn đăng nhập lại được | | | |
| P01-M04 | Buộc đổi mật khẩu; < 10 ký tự bị từ chối | | | |
| P01-M05 | 2 trình duyệt, đổi mật khẩu ở 1 → trình duyệt kia bị đăng xuất | | | |
| P01-M06 | `/api/check.php` của app Desktop hoạt động như trước | | | |

### ⑤ Khác biệt / Bug phát hiện
| Mã | Loại | Nguồn | Input | Kỳ vọng | Thực tế | Nguyên nhân gốc | Cách sửa | Trạng thái |
|---|---|---|---|---|---|---|---|---|
| P01-B01 | Quyết định phát sinh (sai giả định trong tài liệu phase) | P01-T02 (`P01-danh-tinh-va-bao-mat.md` §5.1) | "Áp **toàn bộ** migration lên DB rỗng → so với `schema.sql`" | DB trùng `schema.sql` | Không thể đạt: 2 migration hiện có (`2026-10-16_cloud_backup.sql`, `2026-10-20_fk_index_logs.sql`) chỉ là phần **bổ sung** — `machines`, `check_logs`, `admins`, `admin_login_fails` chỉ được tạo trong `schema.sql` | Repo chưa từng có migration "từ số 0" | Định nghĩa lại T02: **mốc = `schema.sql` tại `0533c33`** (lấy bằng `git show`); DB(mốc + mọi migration mới từ P01 trở đi) phải trùng DB(`schema.sql` hiện tại) về bảng/cột/chỉ mục. Sửa câu chữ T02 trong tệp phase sau khi được đồng ý (Q-P01-3) | Đóng — chủ dự án đồng ý Q-P01-3 (2026-11-02); T02 đã thực thi theo định nghĩa mới |
| P01-B02 | Quyết định phát sinh | Tiền đề S1.0 | `cfg()` gắn cứng `config.php` | Test trỏ được DB test | Không trỏ được | Thiết kế bootstrap chưa tính môi trường test | Theo Q-P01-1 | Đã sửa — theo Q-P01-1 (a), 2026-11-02 |
| P01-B03 | Quyết định phát sinh | P01-T03 | `GET /login.php` | 200 + `<meta name="csrf-token">` | `login.php` chung chỉ có ở S1.3 → T03 không thể xanh ở S1.0 | Thứ tự slice | T03 định nghĩa lại = smoke hạ tầng HTTP: server dùng đúng DB test (`/__bw_test__/moi-truong` của `tests/_router.php`), `/admin/login.php` 200 có CSRF, cookie jar giữ phiên, `bw_http`/`http_post_json` gắn/bỏ CSRF & Origin. Kiểm `/login.php` + meta CSRF chuyển sang **P01-T08** (S1.3) | Đã sửa |
| P01-B04 | Bug (hạ tầng test) | `tools/test.php` | `@fsockopen`, `@unlink` trong harness | Bỏ qua lỗi bị chặn bằng `@` | Error handler ném exception cho cả lỗi bị `@` chặn | Handler không kiểm `error_reporting()` | Thêm `if (!(error_reporting() & $no)) return false;` — warning thường vẫn tính là lỗi | Đã sửa |
| P01-B05 | Bug (hạ tầng test) | `tools/test.php` chạy không lọc | Tệp cấu hình test tên `_cau_hinh_test.php` | Chỉ nạp qua `cfg()` | Runner nạp như tệp test (khớp `*_test.php`) → sập cả runner | Đặt tên trùng mẫu glob | Đổi tên `tests/_cau_hinh.php`. Quy ước: tệp hạ tầng trong `tests/` bắt đầu bằng `_` và **không** kết thúc `_test.php` | Đã sửa |
| P01-B06 | Môi trường | `config.php` thật | Chạy test với config thật | Có `db_test_dsn/user/pass` (xác nhận Q-P01-2) | Harness báo `Thiếu khoá 'db_test_dsn' trong config.php` | Chưa rõ — Claude không được đọc `config.php` (rule chặn cả `ls`) | Chủ dự án kiểm: khoá đặt trong **`src/webapp/config.php`** (không phải tệp khác), đúng tên `db_test_dsn`, `db_test_user`, `db_test_pass`, nằm **bên trong** mảng `return [ … ]` | **Đóng (2026-11-03, chủ dự án):** cho phép runner dùng cấu hình gốc qua biến môi trường (`BIGWIN_CONFIG_FILE` / `BIGWIN_TEST_CONFIG_GOC`) trên máy chủ dự án; S1.0 coi là đạt. Cổng P01 (Bước ⑥) vẫn ghi rõ cấu hình đã dùng khi chạy |
| P01-B07 | Quyết định phát sinh (S1.1) | `tools/create_admin.php` | Tool cũ: chỉ ghi `admins`, mật khẩu ≥ 8 ký tự, `PASSWORD_DEFAULT` | — | — | Chuyển sang bảng `users` | (1) Ghi `users`; khi `--role=admin` ghi **kèm** `admins` để `/admin` cũ (đọc `admins`) vẫn đăng nhập được — **bỏ nhánh này ở P03** khi `admin/_auth.php` chuyển sang `users`. (2) Mật khẩu ≥ **10** ký tự theo §5.3e (trước 8). (3) Băm argon2id (fallback bcrypt). (4) `username` UNIQUE không phân biệt hoa/thường (collation `utf8mb4_unicode_ci`) — `DaiLy01` ≡ `daily01` | **Duyệt (2026-11-03, chủ dự án):** ghi đồng thời `users` + `admins` (role admin) tới P03; giữ mật khẩu ≥ 10 ký tự và argon2id |
| P01-B08 | Lưu ý cho P03 (phát hiện khi /simplify) | `admin/login.php:101` | Hướng dẫn tạo admin đầu tiên: `INSERT INTO admins` bằng tay | Admin mới cũng có trong `users` | Admin tạo theo hướng dẫn này chỉ có ở `admins`; migration S1.1 chỉ chép `admins → users` **một lần** | Hướng dẫn cũ có trước bảng `users` | P03: trước khi chuyển `admin/_auth.php` sang `users`, chạy lại câu chép (idempotent) **và** đổi hướng dẫn sang `php tools/create_admin.php` | Mở — xử lý ở P03 |
| P01-B09 | Môi trường | Lần chạy 16 | Test DB | MariaDB local đang chạy | Không có tiến trình `mysqld` (máy khởi động lại sau 2026-11-02) | XAMPP không tự bật MySQL | Claude bật lại `C:\xampp\mysql\bin\mysqld.exe --defaults-file=mysql\bin\my.ini --standalone` ở nền (DB local, dễ đảo ngược). **Lưu ý:** tiến trình này gắn với phiên Claude; nếu anh/chị bật MySQL từ XAMPP Control Panel mà báo cổng 3306 đang dùng → tắt tiến trình `mysqld` hiện tại trước | Đã xử lý |
| P01-B10 | Rủi ro còn lại (S1.2) | `GioiHanTanSuat` | Nhánh APCu (`apcu_add` + `apcu_inc`) | Có test | Máy dev không có APCu → chỉ nhánh bảng DB được test | Môi trường | Kiểm nhánh APCu ở P13 trên máy chủ production (nếu bật APCu) — thêm vào checklist S9.3 | Mở — theo dõi |
| P01-B11 | Quyết định phát sinh (S1.2) | Phạm vi test | T09/T10 cần cookie & đổi session ID thật | — | PHP chỉ cấp cookie phiên thật trong web server | — | Thêm endpoint chẩn đoán `/__bw_test__/phien/bat-dau`, `/phien/dang-nhap`, `/tieu-de-bao-mat` vào `tests/_router.php` (chỉ có khi chạy test bằng `php -S`; `tests/` bị `tests/.htaccess` (`Require all denied`) chặn trên Apache — đã kiểm 403 ngày 2026-11-03). T17–T19 kiểm bằng hàm thuần `kiemTra()` trên sổ phiên; S1.3 xác nhận lại qua `/login.php` thật (T14, T16–T19 HTTP). Ghi chú: `session.sid_length`/`sid_bits_per_character` bị **deprecate ở PHP 8.4** — giữ cho 8.3, bỏ khi nâng PHP | Áp dụng |
| P01-B12 | Bug (hạ tầng test) | Lần chạy 22, `test_p01_t10_http_dang_nhap_doi_session_id` | Hồi quy toàn bộ | Tìm thấy dòng sổ phiên của cookie mới | Thỉnh thoảng không thấy (uid 0); chạy lại có lúc xanh | `sid_bits_per_character=6` sinh session ID có thể chứa `,`; PHP gửi trong `Set-Cookie` dạng `%2C`. `tests/_http.php` lưu nguyên chuỗi đã mã hoá → `bamId()` băm sai ID → không khớp sổ phiên. Trình duyệt gửi lại nguyên văn nên server vẫn đúng — **lỗi chỉ ở harness**, không phải mã chạy. Không phụ thuộc thứ tự test như giả thuyết ban đầu | `bw_http()` giải mã giá trị cookie (`rawurldecode`) khi đọc `Set-Cookie` | Đã sửa — 80/80 xanh 3 lần liên tiếp (lần 23) |
| P01-B13 | Báo nhầm (rút lại) | `/healthcheck` lần 24b | `GET /tests/<tệp>.php` trên Apache | Bị chặn | Healthcheck chỉ đọc `src/webapp/.htaccess` (không liệt kê `tests`) nên tưởng chưa chặn | `tests/.htaccess` có sẵn `Require all denied` — Apache thật trả **403** (`/lib/bootstrap.php` 404, `/admin/login.php` 200) | Không cần sửa; đã hoàn tác thay đổi thử ở `src/webapp/.htaccess` (`git diff` rỗng) | Đóng — không phải lỗi |
| P01-B14 | Khác kế hoạch (S1.3) | `ajax_khoi_dong()` | Thứ tự §5.3c: CSRF trước người dùng | — | Phiên hết hạn/đã đăng xuất (token CSRF bị huỷ cùng phiên) nhận **403 CSRF** khó hiểu thay vì "cần đăng nhập lại" | Kiểm CSRF trước khi biết phiên còn hợp lệ | Kiểm người dùng **trước** CSRF: chưa đăng nhập → 401 `chua_dang_nhap`; đã đăng nhập mà thiếu/sai token hoặc sai nguồn → 403 `csrf`. An toàn tương đương: bước kiểm người dùng chỉ đọc, không đổi dữ liệu. Ghi trong chú thích đầu `AjaxGateway.php` | Áp dụng — chờ chủ dự án duyệt cùng Cổng |
| P01-B15 | Khác kế hoạch (S1.3) | P01-T25 qua HTTP | Endpoint JSON | `Cache-Control: no-store` đúng nguyên văn | `no-store, no-cache, must-revalidate` | `session_start()` (cache limiter `nocache`) gửi lại `Cache-Control` sau `TieuDeBaoMat::gui(true)` | Giữ hành vi (vẫn cấm lưu cache, còn chặt hơn); test HTTP kiểm **chứa** `no-store`. Test đơn vị S1.2 vẫn kiểm nguyên văn danh sách tiêu đề | Áp dụng |
| P01-B16 | Bug (mã chạy, S1.3) | Lần chạy 20 — T14, T16, T17-HTTP | `PhienBaoMat::huy()` | Xoá cookie phiên | `ValueError: option "lifetime" is invalid` → 500 | `tuyChonCookie()` trả khoá `lifetime` của `session_set_cookie_params()`, `setcookie()` không nhận khoá này | Bỏ `lifetime` trước khi gọi `setcookie()` | Đã sửa |
| P01-B17 | Khác kế hoạch (S1.3) | Action Plan S1.3 | Danh sách tệp | Chỉ `login.php`, `logout.php` | Thêm `doi_mat_khau.php` (gốc web, cạnh `login.php` — không đặt trong `app/` vì phải vào được khi đang bị buộc đổi mật khẩu) và `app/index.php` (trang đích tạm sau đăng nhập) | T15/T16 cần màn đổi mật khẩu thật; đăng nhập cần nơi chuyển tới | Hai tệp dùng `trang_don_gian()` tối giản; **P03 thay** bằng layout app theo `docs/ui-preview/` | Áp dụng — chờ duyệt cùng Cổng |
| P01-B18 | Quyết định thiết kế (S1.3) | Đăng nhập | Khoá sau 5 lần sai | — | — | Rule `security-core.md` ban đầu ghi dùng `GioiHanTanSuat` | Dùng bảng `user_login_fails` (đã tạo ở S1.1, giữ dấu vết tra cứu như `admin_login_fails`), đếm theo username **hoặc** IP trong 15'; đúng → xoá đếm. Rule đã sửa cho khớp. Chống rà tài khoản: một câu lỗi chung; user không tồn tại vẫn chạy `password_verify` với `HASH_GIA` (argon2id cùng tham số) để thời gian phản hồi như nhau; "tài khoản bị khoá" chỉ báo **sau** khi mật khẩu đúng | Áp dụng |

### ⑥ Xác nhận Cổng nghiệm thu
| # | Điều kiện | ✔ | Bằng chứng |
|---|---|---|---|
| 1 | Toàn bộ P01-Tnn xanh, 0 bỏ qua | ✔ (Claude) | T01–T27 + T09/T10/T17–T19/T25/T26 qua HTTP: 61 test P01 xanh, runner không có cơ chế bỏ qua — lần 24. T28 = hồi quy toàn bộ (mục 2) |
| 2 | Hồi quy toàn bộ xanh | ✔ (Claude) | Lần 24: `80 passed, 0 failed (20.07s)`, exit 0; ổn định 3 lần liên tiếp ở lần 23. Cấu hình: cấu hình gốc qua `BIGWIN_CONFIG_FILE` (B06) |
| 3 | Checklist P01-Mnn tick đủ | ✔ (Chủ dự án) | Chủ dự án xác nhận PASS 2026-11-03 (chi tiết từng mục M01–M06 do chủ dự án giữ) |
| 4 | `/healthcheck` xanh; `/audit-api` AN TOÀN | ✔ (Claude) | Lần 24b (OK) · lần 24c (**AN TOÀN**) |
| 5 | DoD §10 kế hoạch tổng (mục 2, 4, 6, 7, 10 áp dụng) | ✔ (Claude) | **2:** P01 không đụng `lib/bigwin/` · **4:** `app/ajax/_ping.php` mở đầu bằng `ajax_khoi_dong` (T27 quét); `login.php`/`logout.php`/`doi_mat_khau.php` đổi trạng thái chỉ qua POST + CSRF + nguồn; mọi giá trị động in ra đi qua `h()`; mọi SQL có tham số là prepared (2 câu `exec` không có biến); `goiApi()` chưa áp dụng (chưa có JS trang — P03) · **6:** `api/*`, `lib/api.php`, `lib/crypto.php` không đổi · **7:** không có `UTC_TIMESTAMP()`; DB dùng `NOW()`; `time()`/`strtotime()` trong `PhienBaoMat` so với `NOW()` cùng múi (PHP `Asia/Ho_Chi_Minh`, phiên DB `+07:00`) · **10:** `/healthcheck` xanh, đã ghi nhật ký §14 kế hoạch tổng |
| 6 | Chủ dự án ký PASS | ✔ | Chủ dự án — 2026-11-03 (bao gồm các khác biệt B14, B17) |

**Kết luận:** **PASS** (2026-11-03).
**Phase được mở khoá:** P02
