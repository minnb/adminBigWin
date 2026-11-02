# EXECUTION PIPELINE — Quy trình Thực thi Liên hoàn P00 → P09

| Mục | Giá trị |
|---|---|
| Nguồn | Kế hoạch tổng [`../2026-11-02-ke-hoach-chuyen-doi-python-php.md`](../2026-11-02-ke-hoach-chuyen-doi-python-php.md) (v2.1) · Tệp phase [`P00`](P00-dong-bang-va-fixture.md) … [`P09`](P09-cu-phap-va-tu-hoc.md) · Quy tắc cổng [`README.md`](README.md) §2 |
| Phạm vi | Điều phối P00–P09 (lõi + nghiệp vụ chính). P10–P13 dùng cùng quy trình, chỉ ghi ở bản đồ phụ thuộc |
| Nhật ký | [`TASK_LOG.md`](TASK_LOG.md) — mọi bước của pipeline đều ghi vào đây |
| Thứ tự ưu tiên khi mâu thuẫn | Kế hoạch tổng > tệp phase > tài liệu này. Tài liệu này **không** đặt yêu cầu kỹ thuật mới, chỉ điều phối |

**Vai trò:**
| Vai trò | Ai | Quyền/trách nhiệm trong pipeline |
|---|---|---|
| **Thực thi** | Claude (+ dev) | Đánh giá tiền đề, lập Action Plan, code, chạy test, ghi log |
| **Duyệt** | Chủ dự án | Duyệt Action Plan khi có điểm cần quyết; tick checklist thủ công; **ký pass cổng** |
| **Chủ repo BigWIN** | Chủ repo Python | Commit code treo, gắn tag `port-baseline` (P00 bước 1–3); duyệt CR khi Python đổi |

---

## 1. Kiến trúc Workflow tổng quát

### 1.1 Chu trình chuẩn của một Phase (Standard Loop)

```
 ┌──────────────────────────────────────────────────────────────────────────────┐
 │ ① ĐÁNH GIÁ TIỀN ĐỀ ─► ② LẬP ACTION PLAN ─► ③ CODE ─► ④ TEST ─► ⑤ GHI LOG ─► ⑥ CHUYỂN PHASE │
 │        ▲                                      ▲          │                              │
 │        │                                      └── đỏ ────┘ (vòng sửa trong slice)       │
 │        └──────────── ⛔ Mở lại phase trước nếu lỗi gốc nằm ở đó ◄──────────────────────┘
 └──────────────────────────────────────────────────────────────────────────────┘
```

| Bước | Việc phải làm | Đầu vào | Đầu ra (bắt buộc) | Điều kiện sang bước kế |
|---|---|---|---|---|
| **① Đánh giá Tiền đề** | Kiểm từng mục "Điều kiện bắt đầu" của phase (§2 tài liệu này + §2 tệp phase); xác nhận mọi phase phụ thuộc ở trạng thái ✅ trong `README.md` §1; chạy **bộ hồi quy nền** (`php src/webapp/tools/test.php`) để chắc điểm xuất phát xanh | Tệp phase, `README.md`, `TASK_LOG.md` | Mục "① Tiền đề" trong `TASK_LOG.md`: từng điều kiện ✔/✘ + kết quả hồi quy nền | **Mọi** điều kiện ✔ và hồi quy nền xanh. Có ✘ → dừng, ghi "BỊ CHẶN" + lý do, không gõ code |
| **② Lập Action Plan** | Chia phase thành slice theo thứ tự trong tệp phase; với mỗi slice: tệp tạo/sửa, test case (`Pxx-Tnn`) sẽ viết trước, rủi ro; xác định điểm cần chủ dự án quyết (nếu có) | Tệp phase §3–§5 | Mục "② Action Plan" trong `TASK_LOG.md` | Không có câu hỏi mở chặn; nếu có → hỏi và ghi câu trả lời trước khi code |
| **③ Code** | Làm **từng slice một**: viết test của slice trước (hoặc cùng lúc) → code tới khi test của slice xanh → chạy lại test của các slice trước trong phase. Tuân thủ DoD §10 kế hoạch tổng và các rule `.claude/rules/*` | Action Plan | Mã + test của slice; cập nhật bảng "Tiến độ slice" trong log | Test của slice xanh |
| **④ Test** | (a) **Tự động** — toàn bộ `Pxx-Tnn` của phase; (b) **Hồi quy** — cả bộ test không lọc; (c) `/healthcheck` (+ `/audit-api` khi đụng `api/`, `lib/api.php`, `lib/crypto.php`, `lib/` dùng chung); (d) **Thủ công** — từng `Pxx-Mnn`, do chủ dự án hoặc người được chỉ định tick | Mã hoàn tất của phase | Bảng "Lần chạy test" + "Checklist thủ công" trong log | 0 test đỏ, 0 test bị bỏ qua, checklist tick đủ |
| **⑤ Ghi Log** | Ghi mọi khác biệt/bug (đặc biệt **ca lệch Golden Fixture**), nguyên nhân, cách sửa; điền "Xác nhận cổng nghiệm thu" 6 điều kiện; ghi 1 dòng vào §14 kế hoạch tổng và mục Nhật ký của tệp phase | Kết quả ④ | `TASK_LOG.md` đầy đủ, chữ ký duyệt | Chủ dự án ký **PASS** |
| **⑥ Chuyển Phase** | Đổi trạng thái phase sang `✅ ĐẠT (ngày)` trong `README.md` §1; xác định các phase vừa được mở khoá theo bản đồ §1.2; bắt đầu lại ① cho phase kế | Chữ ký PASS | `README.md` cập nhật | — |

**Nguyên tắc vòng lặp:**
- **Một slice xanh rồi mới sang slice kế.** Không mở nhiều slice dở dang cùng lúc trong một phase.
- **Test viết trước hoặc cùng lúc với code**, không viết bù sau khi phase "xong".
- **Fixture không bao giờ bị sửa để test xanh.** Golden Fixture lệch = bug PHP (rule `bigwin-core.md`).
- Lỗi phát hiện ở phase sau mà gốc ở phase trước → **⛔ Mở lại** phase trước (§3.4), không vá tạm ở phase sau.

### 1.2 Bản đồ phụ thuộc (Dependency Map)

```
                 ┌──────────── LUỒNG A: LÕI PURE (không DB) ─────────────┐
 [Chủ repo R1] ─► P00 ───────────► P04 ───────────► P05 ─────┐
                                                              │  (cả hai ✅)
                 ┌──────────── LUỒNG B: NỀN TẢNG WEB ────────┐ ▼
 (bắt đầu ngay) ─► P01 ───► P02 ───► P03 ────────────────────► P06 ──► P07 ──► P08 ─┬─► P09 ──► P10 ──┐
                                                                                    │                 ├─► P12 ──► P13
                                                                                    └─► P11 ──────────┘
                                                                         (cần VPS systemd, song song P09–P10)
```

| Phase | Chờ phase nào ĐẠT (khoá chặn) | Có thể chạy song song với | Mở khoá |
|---|---|---|---|
| P00 | — (chờ **chủ repo** làm R1 bước 1–3) | P01, P02, P03 | P04 |
| P01 | — | P00, P04, P05 | P02 |
| P02 | P01 | P00, P04, P05 | P03 |
| P03 | P02 | P00, P04, P05 | P06 (cùng P05) |
| P04 | P00 | P01, P02, P03 | P05 |
| P05 | P04 | P01, P02, P03 | P06 (cùng P03) |
| **P06** | **P03 VÀ P05** (điểm hợp nhất 2 luồng) | — | P07 |
| P07 | P06 | — | P08 |
| **P08** | P07 | — | **P09 và P11** (điểm rẽ nhánh) |
| P09 | P08 | P11 | P10 |
| P10 | P09 | P11 | P12 (cùng P11) |
| P11 | P08 + hạ tầng VPS/systemd | P09, P10 | P12 |
| P12 | P10 VÀ P11 (hoặc P11 hoãn có biên bản) | — | P13 |
| P13 | P12 | — | Go-live |

**Đường găng (critical path):** `P00 → P04 → P05 → P06 → P07 → P08 → P09 → P10 → P12 → P13`. P00 phụ thuộc chủ repo BigWIN → **mọi ngày trễ của R1 là ngày trễ của cả dự án**; trong lúc chờ, dồn lực cho luồng B (P01→P03) để luồng B không thành đường găng.

**Quy tắc song song:**
- Hai luồng A và B **không chạm cùng tệp**: luồng A chỉ ở `lib/bigwin/*.php` PURE + `tests/<module>_test.php`; luồng B ở `lib/security/`, `lib/auth.php`, `lib/bigwin/repo/`, `app/`, `admin/`, `migrations/`.
- Mỗi luồng có `TASK_LOG.md` entry riêng; hồi quy cuối phase luôn chạy **cả bộ** (gồm test của luồng kia) — nếu đỏ do luồng kia thì báo cho luồng kia, không tự sửa chéo.

---

## 2. Khung triển khai từng Phase (P00 → P09)

Ký hiệu lệnh: `T` = `php src/webapp/tools/test.php` (runner lọc theo **chuỗi con tên tệp**, nên `T parser` chạy cả `rule_parser_test.php`, `T tien` chạy cả `pipeline_tien_test.php` — vô hại, chỉ dùng khi đang làm). Tên tệp test chưa ghi trong tệp phase được **chốt tại đây**. Mọi phase kết thúc bằng **`T` không lọc** (hồi quy) — xem §3.2.

---

### P00 — Đóng băng BigWIN & Golden Fixture engine Mới
| | |
|---|---|
| **Core Objective** | Mốc mã nguồn Python bất biến `port-baseline` + **12 Golden Fixture** (6 cũ xuất lại + 6 mới: `cleaner_moi`, `rule_parser`, `dan_so`, `tong_hop`, `doi_chieu`, `pipeline`) sinh từ đúng tag |
| **Prerequisites (trước dòng code đầu tiên)** | ☐ Chủ repo đã **dọn dẹp + commit ~1.300 dòng treo** trong `core/`, `utils/` — `git status` sạch · ☐ Test Python BigWIN xanh trên commit đó · ☐ Tag `port-baseline` tồn tại, `git describe --exact-match` khớp · ☐ Claude chỉ sửa `D:\Projects\bigwin\scripts\export_golden.py` (Q7) |
| **Files** | BigWIN: `scripts/export_golden.py` · Web: `src/webapp/tests/fixtures/*.json` (12 tệp), `tests/fixture_meta_test.php` |
| **Test tự động** | `T fixture_meta` (P00-T01..T03) · `T helpers` + `T smoke` (P00-T04) |
| **Cổng** | 12 fixture cùng `meta.bigwin_commit`, không `-dirty`; exporter chạy 2 lần trùng SHA-256 (P00-M02) |
| **Bẫy cần tránh** | Xuất fixture hoặc gắn tag khi repo còn code treo = **S0.4 không đạt** (R1) |

---

### P01 — Hạ tầng test · Danh tính · Core bảo mật · Đăng nhập
| | |
|---|---|
| **Core Objective** | Hạ tầng test tích hợp (DB `bigwin_test` + HTTP `php -S`); bảng `users` (admin cũ chuyển sang); `lib/security/` (session, CSRF, tiêu đề, rate limit); `login.php` + AJAX gateway `ajax_khoi_dong()` |
| **Prerequisites** | ☐ MySQL/MariaDB local có quyền tạo `bigwin_test` · ☐ Khoá `db_test_*` thêm vào `config.sample.php` (không đụng `config.php`) · ☐ O5 đã chốt (chỉ admin tạo tài khoản, buộc đổi mật khẩu lần đầu) · ☐ Hồi quy nền `T` xanh (19 test) |
| **Files** | S1.0: `tests/_db.php`, `tests/_http.php`, `config.sample.php`, `tools/test.php` · S1.1: `migrations/…_users_rbac_bao_mat.sql`, `schema.sql`, `tools/create_admin.php` · S1.2: `lib/security/{PhienBaoMat,Csrf,TieuDeBaoMat,GioiHanTanSuat}.php`, `.claude/rules/security-core.md` · S1.3: `lib/auth.php`, `login.php`, `logout.php`, `lib/security/AjaxGateway.php`, `app/ajax/_ping.php` |
| **Test tự động** | `T db_smoke` · `T http_smoke` (T01–T03) · `T db_users` (T04–T07) · `T http_auth` (T08–T19) · `T http_gateway` (T20–T26) · `T security_scan` (T27) |
| **Cổng** | P01-T01..T28 xanh · P01-M01..M06 tick · `/audit-api` AN TOÀN (đụng `lib/` dùng chung) |
| **Bẫy cần tránh** | Không xoá/sửa bảng `admins` (admin cũ vẫn dùng tới P03); runner phải báo FAIL khi thiếu `db_test` (không tự bỏ qua) |

---

### P02 — TenantScope · Bản quyền SaaS
| | |
|---|---|
| **Core Objective** | `TenantContext` + `TenantRepo` tự chèn `user_id` & `deleted_at IS NULL`, SQL thiếu `{tenant:x}` → `LoiCoLapTenant` (fail-closed); `GiayPhepGuard` + bảng thuê bao, ma trận 5 trạng thái, đếm hạn mức nguyên tử |
| **Prerequisites** | ☐ **P01 ✅** · ☐ Đọc lại `.claude/rules/tenant-isolation.md` (v2.1) · ☐ Bảng demo `tests/_tenant_demo.sql` chỉ nạp vào DB test (không vào `schema.sql`) |
| **Files** | S1.4: `lib/bigwin/TenantContext.php`, `lib/bigwin/repo/{TenantRepo,SharedRepo}.php`, `tests/_tenant_demo.sql`, `tests/{tenant_scan,db_tenant_repo,idor}_test.php` · S1.5: `migrations/…_thue_bao.sql`, `schema.sql`, `lib/bigwin/GiayPhepGuard.php`, `lib/bigwin/repo/ThueBaoRepo.php`, `tools/cap_nhat_thue_bao.php`, `AjaxGateway.php` (nối guard), `app/ajax/_ping_ghi.php`, `_ping_phan_tich.php` |
| **Test tự động** | `T db_tenant_repo` (T01–T15) · `T tenant_scan` (T16) · `T idor` (T17) · `T giay_phep` (T20–T30) · `T http_gateway` (T31) |
| **Cổng** | P02-T01..T32 xanh · `tenant_scan` = 0 vi phạm trên mã thật · bảng demo không có trong `schema.sql` |
| **Bẫy cần tránh** | `ngay_gia_lap` chỉ đọc khi `moi_truong = test`; bộ quét SQL gặp cú pháp lạ phải ném lỗi (không cho qua) |

---

### P03 — Khung app 6 page · Quản trị tài khoản & thuê bao
| | |
|---|---|
| **Core Objective** | Khung app theo preview (topbar ngày N8, sidebar 6 page, phím tắt, đổi mật khẩu, băng thuê bao, ẩn hành động khi hết hạn); admin tạo/quản lý đại lý và Gói & Thuê bao; `admin/_auth.php` chuyển sang `users` |
| **Prerequisites** | ☐ **P02 ✅** · ☐ Token giao diện lấy từ `docs/ui-preview/app.css` (không vẽ lại) · ☐ Tailwind khoá `3.4.16` như preview |
| **Files** | S1.6: `app/_layout.php`, `app/assets/{app.css,app.js}`, `lib/bigwin/NgayLamViec.php`, `app/{nhap_tin,telegram,kqxs,khach_hang,lich_su,cu_phap}.php` (rỗng), `app/ajax/doi_mat_khau.php`; xoá `app/ajax/_ping*.php` · S1.7: `admin/{users,thue_bao}.php`, `lib/bigwin/repo/UserRepo.php`, sửa `admin/_auth.php` |
| **Test tự động** | `T ngay_lam_viec` (T01–T03) · `T http_app` (T04–T07, T09–T11) · `T app_js_scan` (T08) · `T http_admin` (T12–T18) |
| **Cổng** | P03-T01..T18 xanh · P03-M01..M07 tick (so preview 1440 px & 375 px; trang admin cũ còn chạy) · không còn `_ping*.php` |
| **Bẫy cần tránh** | Chuyển `admin/_auth.php` làm **cuối phase**; rollback = khôi phục `_auth.php` cũ (bảng `admins` vẫn còn) |

---

### P04 — Lõi cú pháp 1: SequenceMatcher · HeuristicCleaner · Đối chiếu nghi ngờ
| | |
|---|---|
| **Core Objective** | Port PURE 3 khối nền, **khớp 100% Golden Fixture**: `SequenceMatcher` (Opcodes, ratio — không autojunk) → `HeuristicCleaner` (cả `bungDanKep = true/false`) → `DiffAnalyzer` + `DoiChieuTin::danhDauNghiNgo` (offset ký tự) |
| **Prerequisites** | ☐ **P00 ✅** · ☐ Fixture `seqmatch`, `cleaner`, `cleaner_moi`, `learning`, `doi_chieu` có `meta.bigwin_commit` = tag · ☐ Mã Python đọc **tại tag** (`git show port-baseline:core/heuristic_cleaner.py`), không đọc working tree · ☐ Đã đọc `.claude/rules/bigwin-core.md` + kỹ thuật regex v1 §4.1 |
| **Files** | `lib/bigwin/{SequenceMatcher,HeuristicCleaner,DiffAnalyzer,DoiChieuTin}.php` · `tests/{seqmatch,cleaner,doi_chieu,core_pure_scan}_test.php` |
| **Test tự động** | `T seqmatch` (T01–T02) · `T cleaner` (T03–T07) · `T doi_chieu` (T08–T09) · `T core_pure_scan` (T10) |
| **Cổng** | P04-T01..T11 xanh, **0 ca bị bỏ qua / "biết lệch"** · ghi 10 ca lệch đã gặp + nguyên nhân (P04-M01) · ghi thời gian chạy corpus (P04-M02) |
| **Bẫy cần tránh** | Port cleaner **theo khối pipeline**, chạy fixture sau mỗi khối để khoanh vùng lệch; cấm `array_unique`/khoá mảng với chuỗi số |

---

### P05 — Lõi cú pháp 2: MessageParser · RuleParser · Dàn số · Cache
| | |
|---|---|
| **Core Objective** | Engine duy nhất `ParserService` = cleaner (`bungDanKep=false`) → `RuleParser` (115 dàn) → đối chiếu, khớp 100% `rule_parser.json`; `QuanLyDanSo` khớp `dan_so.json`; `BoNhoDem` (L0–L3) đạt p95 < 30 ms khi cache trúng |
| **Prerequisites** | ☐ **P04 ✅** · ☐ Đã đọc `core/rule_parser.py` tại tag: các móc `_phan_tich`, `_dung_pattern` (điểm neo đúng 2 lần), `_bo_dau`, `_duoc_bo_dau` · ☐ Máy đo hiệu năng **bật OPcache** (máy dev hiện đang tắt — bật trước khi đo P05-H01) |
| **Files** | S2.3: `lib/bigwin/MessageParser.php`, `tests/parser_test.php` · S2.4: `lib/bigwin/{DanSoMacDinh,RuleParser,ParserService}.php`, `tests/rule_parser_test.php` · S2.5: `lib/bigwin/QuanLyDanSo.php`, `tests/dan_so_test.php` · S2.7: `lib/bigwin/BoNhoDem.php`, `tests/{pipeline_parse,cache}_test.php`, `tools/do_hieu_nang_parser.php` |
| **Test tự động** | `T parser` (T01–T02) · `T rule_parser` (T03–T07) · `T dan_so` (T08–T09) · `T pipeline_parse` (T10) · `T cache` (T11–T14) · đo: `php src/webapp/tools/do_hieu_nang_parser.php` (H01–H03) |
| **Cổng** | P05-T01..T15 xanh · H01 < 30 ms, H02 < 150 ms (không đạt → áp phương án hạ cấp §8.1d, ghi log) |
| **Bẫy cần tránh** | Kết quả parse **phải giống hệt** khi cache tắt/trượt/trúng (P05-T11); không lộ MessageParser như một engine cho người dùng (N1) |

---

### P06 — Tiền · Dò trúng · Tổng hợp · KQXS
| | |
|---|---|
| **Core Objective** | `TinhTien`/`DoTrung`/`TongHop` khớp **từng đồng**; `bang_tong_hop.js` (chỉ hiển thị, N3); bảng KQ + Scraper + cron; page Kết quả XSMB |
| **Prerequisites** | ☐ **P03 ✅ VÀ P05 ✅** (điểm hợp nhất) · ☐ Fixture `tinh_tien`, `do_trung`, `tong_hop`, `pipeline` từ tag · ☐ HTML mẫu trang KQ lưu sẵn trong `tests/fixtures/html/` (**test không gọi mạng**) |
| **Files** | S3.1: `lib/bigwin/{TinhTien,DoTrung,TongHop}.php`, `tests/{tien,pipeline_tien}_test.php`, `app/assets/bang_tong_hop.js` · S3.2: `migrations/…_ket_qua_xo_so.sql`, `schema.sql`, `lib/bigwin/Scraper.php`, `lib/bigwin/repo/SharedKetQuaRepo.php`, `tools/scrape_xsmb.php` · S3.3: `app/kqxs.php`, `app/ajax/kq_lay.php`, `lib/bigwin/KetQuaService.php` |
| **Test tự động** | `T tien` (T01–T03, T05) · `T pipeline_tien` (T04) · `T scraper` (T06–T07) · `T db_ket_qua` (T08–T10) · `T http_kqxs` (T11–T14) · `T core_pure_scan` (T15) |
| **Cổng** | P06-T01..T16 xanh, **lệch 0 đồng** toàn fixture · P06-M01 so 27 giải 3 ngày với Desktop |
| **Bẫy cần tránh** | Ép kiểu đúng bảng v1 §4.2 (thứ tự nhân, `(int)` cắt về 0); quy ước dấu N2 (`thanhTien = tien − trung − ck`) |

---

### P07 — Khách hàng & Bảng giá
| | |
|---|---|
| **Core Objective** | Repo nghiệp vụ thật đầu tiên trên **TenantScope**; migration tenant (FK kép, cột sinh `…_hoat_dong`, trigger chặn UPDATE tiền, không CASCADE); page Khách hàng & Bảng giá 21 khoá, 3 tầng, ẩn khách = xoá mềm |
| **Prerequisites** | ☐ **P06 ✅** · ☐ **Đã xác nhận MySQL/MariaDB production hỗ trợ cột sinh STORED + UNIQUE và trigger** (ghi phiên bản vào log; không hỗ trợ → dùng phương án thay thế trong P07 §7 trước khi viết migration) |
| **Files** | S4.1: `migrations/…_nghiep_vu_tenant.sql`, `schema.sql`, `lib/bigwin/repo/{KhachHangRepo,HeSoRepo}.php` · S4.2: `app/khach_hang.php`, `app/ajax/{khach_luu,khach_xoa,he_so_luu}.php` |
| **Test tự động** | `T db_khach_hang` (T01–T08) · `T db_he_so` (T09–T14) · `T idor` (T15) · `T db_rang_buoc` (T16–T17) · `T http_khach_hang` (T18–T19) · `T db_smoke` (P01-T02 schema = migration) |
| **Cổng** | P07-T01..T20 xanh · `idor` + `tenant_scan` xanh · Phụ lục A-KH tick đủ |
| **Bẫy cần tránh** | Modal ẩn khách đúng câu chữ "Ẩn khách hàng, bảo toàn lịch sử đối soát cũ"; lưu nhiều giá có 1 ô lỗi → không ghi ô nào (khắc phục L4) |

---

### P08 — Đối soát · Page Nhập tin
| | |
|---|---|
| **Core Objective** | `DoiSoatService` (lưu, trùng, ghi đè, xoá, sửa phiên) với **snapshot bất biến** + `he_so_ap_dung_json` + xoá mềm; page Nhập tin 3 chế độ; **E2E parity `pipeline.json` qua DB thật** |
| **Prerequisites** | ☐ **P07 ✅** (bảng `tin_nhan_log`, `lich_su_doi_soat` + trigger đã có) · ☐ Chấp nhận tạm: `ParserService` dùng 115 dàn mặc định + tự học rỗng (DB dàn/tự học ở P09) — ghi chú "tạm" trong code · ☐ Có 20 tin thật **ẩn danh** + KQ + giá tương ứng cho so song song Desktop (P08-M07) |
| **Files** | S4.3: `lib/bigwin/DoiSoatService.php`, `lib/bigwin/repo/{TinNhanRepo,DoiSoatRepo}.php` · S4.4: `app/nhap_tin.php`, `app/ajax/{phan_tich,doi_soat,phien_xoa}.php`, `app/assets/editor.js` |
| **Test tự động** | `T db_doi_soat` (T01–T13) · `T idor` (T14) · `T bat_bien_scan` (T15) · `T http_nhap_tin` (T20–T24) |
| **Cổng** | P08-T01..T25 xanh · **P08-M07 lệch 0 đồng** so Desktop · Phụ lục A-NT tick (trừ mục mở chế độ sửa từ Lịch sử → P10) |
| **Bẫy cần tránh** | Không có lựa chọn "lưu thêm" khi trùng (N7); xoá trắng ô nhập sau lưu; offset tô màu với emoji (code point → UTF-16 trong `editor.js`) |

---

### P09 — Cú pháp (bảng dàn số) · Bộ nhớ tự học
| | |
|---|---|
| **Core Objective** | Bảng dàn số + bộ nhớ tự học **theo user**; `LearningService` là cổng ghi duy nhất (5 guard + shadow test); sửa dàn có đo ảnh hưởng; thay đổi áp dụng ngay (cache `phien_ban`); `ParserService` chuyển sang dữ liệu user |
| **Prerequisites** | ☐ **P08 ✅** · ☐ Fixture `learning`, `dan_so` từ tag · ☐ O6 áp dụng: dàn gốc không tự lan sang user cũ |
| **Files** | S5.1: `lib/bigwin/LearningService.php`, `lib/bigwin/repo/BoNhoRepo.php`, `tests/learning_test.php` · S5.2: `migrations/…_dan_so_tu_hoc.sql`, `schema.sql`, `lib/bigwin/repo/DanSoRepo.php`, `tools/seed_dan_so.php` · S5.3: `app/cu_phap.php`, `app/ajax/{dan_so_kiem_tra,dan_so_luu,dan_so_xoa,dan_so_khoi_phuc}.php` · S5.4: `app/ajax/{tu_hoc_them,tu_hoc_xoa}.php` (+ tab trong `cu_phap.php`) |
| **Test tự động** | `T learning` (T01–T05) · `T db_dan_so` (T06–T13) · `T idor` (T14) · `T db_doi_soat` (T15) · `T pipeline` (T16 — toàn bộ `pipeline.json` khi đọc bảng từ DB) |
| **Cổng** | P09-T01..T17 xanh · Phụ lục A-CP + A-TH tick đủ · page Lịch sử không còn tab Tự học |
| **Bẫy cần tránh** | Hàng rào số so danh sách `\d+` **đúng thứ tự** (`12 3` ≠ `1 23`); số trùng trong dàn là **lỗi**, không tự lọc khi lưu |

---

## 3. Quy định Cổng Nghiệm thu (Gatekeeping)

### 3.1 Nguyên tắc tuyệt đối
> **KHÔNG BAO GIỜ chuyển sang Phase tiếp theo nếu Phase hiện tại còn BẤT KỲ test tự động `Pxx-Tnn` nào đỏ, bị bỏ qua, bị comment, hoặc còn BẤT KỲ mục checklist thủ công `Pxx-Mnn` nào chưa tick.**

Không có ngoại lệ "đỏ nhưng biết lý do", "để phase sau sửa", "test chập chờn". Test chập chờn = bug (của test hoặc của mã) và phải sửa trong phase hiện tại.

### 3.2 Hồi quy bắt buộc cuối mỗi Phase
Cuối bước ④, **luôn** chạy theo đúng thứ tự:
```
1. php src/webapp/tools/test.php            # TOÀN BỘ bộ test — KHÔNG lọc (gồm test của mọi phase trước, cả luồng song song)
2. /healthcheck                              # php -l toàn bộ + .htaccess + đường dẫn require
3. /audit-api                                # bắt buộc nếu phase đụng api/, lib/api.php, lib/crypto.php hoặc lib/ dùng chung
```
- Runner thoát mã `1` khi có test đỏ hoặc khi **0 test chạy** → coi là FAIL.
- Thiếu cấu hình `db_test` → runner báo FAIL (từ P01) — không được chạy "chỉ test PURE" để qua cổng.
- Dán **dòng tổng kết** (`N passed, 0 failed (x.xxs)`) vào `TASK_LOG.md`.

### 3.3 Sáu điều kiện PASS (theo `README.md` §2)
| # | Điều kiện | Bằng chứng ghi trong log |
|---|---|---|
| 1 | Toàn bộ `Pxx-Tnn` của phase xanh | Bảng "Lần chạy test" |
| 2 | Hồi quy toàn bộ xanh | Dòng tổng kết `T` không lọc |
| 3 | Checklist `Pxx-Mnn` tick đủ (người test + ngày) | Bảng "Checklist thủ công" |
| 4 | `/healthcheck` xanh; `/audit-api` AN TOÀN khi áp dụng | Kết quả lệnh |
| 5 | DoD §10 kế hoạch tổng (TenantScope, Core bảo mật, bất biến tiền, PURE…) | Tick từng mục áp dụng |
| 6 | Chủ dự án ký **PASS** | Chữ ký + ngày |

### 3.4 Mở lại Phase (⛔)
Khi một phase sau phát hiện lỗi có **gốc** ở phase đã ĐẠT:
1. Đổi trạng thái phase gốc thành `⛔ Mở lại` trong `README.md`; phase hiện tại **tạm dừng** ở slice đang làm.
2. Thêm entry "Mở lại" trong `TASK_LOG.md` của phase gốc: lỗi, phát hiện ở đâu, test bổ sung để bắt lỗi.
3. Sửa trong phạm vi phase gốc, **viết test mới tái hiện lỗi trước khi sửa**, chạy lại toàn bộ cổng của phase gốc.
4. PASS lại → tiếp tục phase hiện tại từ bước ④.

### 3.5 Xử lý ca lệch Golden Fixture
| Tình huống | Hành động | Cấm |
|---|---|---|
| Test parity đỏ | Ghi ca lệch (tên fixture + chỉ số ca + input + kỳ vọng/thực tế) vào bảng "Khác biệt/Bug"; tìm nguyên nhân trong PHP | Sửa fixture, thêm ca vào danh sách bỏ qua |
| Nghi Python có bug | Vẫn port **nguyên văn** hành vi Python (rule `bigwin-core.md`); ghi đề xuất vào log để chủ repo xem xét qua CR | Tự "sửa cho đúng" ở PHP |
| Python thay đổi sau `port-baseline` | Ngoài phạm vi; chỉ vào qua CR → chạy lại exporter → ghi log | Đọc code Python từ working tree |
| Lỗi Desktop đã biết (§13 L1–L6) | Theo hành vi web đã định nghĩa ở §13; ghi chú khi so song song Desktop | Port lỗi |

---

## 4. Quản lý Nhật ký (Task Log)

- Tệp: [`TASK_LOG.md`](TASK_LOG.md) — **một tệp duy nhất**, mỗi phase một entry (mới nhất ở trên), dùng đúng mẫu ở đầu tệp.
- Ghi **trong lúc làm**, không ghi bù cuối phase: mỗi bước ①–⑥ cập nhật mục tương ứng.
- Mọi ca lệch Golden Fixture, bug, khác biệt so preview, quyết định phát sinh → một dòng trong bảng "Khác biệt / Bug" với mã `Pxx-Bnn`.
- Khi PASS: chép 1 dòng tóm tắt vào §14 kế hoạch tổng và mục "Nhật ký" của tệp phase; đổi trạng thái trong `README.md` §1.
- Mẫu đầy đủ: xem phần **"MẪU ENTRY"** ở đầu `TASK_LOG.md`.
