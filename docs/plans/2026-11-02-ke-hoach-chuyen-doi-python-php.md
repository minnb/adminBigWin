# Kế hoạch triển khai chuyển đổi BigWIN Desktop (Python) → Web (PHP) — v2

| Mục | Giá trị |
|---|---|
| Phiên bản | **v2.1** — 2026-11-02 (refactor sau review kiến trúc: chốt O1–O4, thêm bản quyền SaaS, TenantScope, Core bảo mật, cache parser) |
| Trạng thái | Đã duyệt bố cục; O1–O5, O8 **đã chốt** (§4.1); O6, O7, O9 còn mở (§4.2) · **Thực thi theo phase:** [`phases/README.md`](phases/README.md) |
| Kế thừa | `WEB_MIGRATION_PLAN.md` (v1, duyệt 2026-10-30). v2 **thay thế** v1 ở phạm vi, lộ trình, ánh xạ màn hình; v1 vẫn là tài liệu tham chiếu cho kỹ thuật regex/Unicode (§4.1) và quy tắc ép kiểu tiền (§4.2) |
| Nguồn Python | `D:\Projects\bigwin` — commit `3c5fab3` + working tree chưa commit (xem R1) |
| Đích PHP | `src/webapp/` — PHP 8.3 thuần, MySQL/MariaDB InnoDB, không Composer |
| Thiết kế UI chuẩn | `docs/ui-preview/` (`index.html`, `app.js`, `app.css`, `mock-data.js`) — đã duyệt bố cục 2026-11-02 |

**Cách đọc:** §1–§4 là phạm vi và quyết định (đọc trước khi làm bất cứ slice nào). §5–§8 là thiết kế đích. §9 là lộ trình thực thi theo slice. §10–§13 là quy tắc, kiểm thử, rủi ro. Phụ lục là checklist nghiệm thu.

---

## 1. Mục tiêu & phạm vi

### 1.1 Mục tiêu
1. Chạy được toàn bộ nghiệp vụ đối soát XSMB của BigWIN trên web, **nhiều đại lý (tenant) dùng chung một hệ thống**, dữ liệu cô lập tuyệt đối theo `user_id`.
2. **Tiền khớp Desktop từng đồng**: cùng tin + cùng KQXS + cùng hệ số → cùng tiền ghi / CK / trúng / thành tiền (kiểm chứng bằng golden fixture).
3. Giao diện theo `docs/ui-preview/` (6 page nghiệp vụ + trang Đăng nhập), giữ tốc độ thao tác bàn phím như Desktop.
4. Không ảnh hưởng app Desktop đã phát hành: `/api/*.php`, `lib/api.php`, `lib/crypto.php` đóng băng.
5. Vận hành như **dịch vụ thuê bao (SaaS)**: mỗi đại lý có gói, hạn dùng, hạn mức; hết hạn thì ngừng phân tích nhưng **không mất dữ liệu** (§1.4).
6. Dữ liệu tiền **bất biến và truy vết được**: không xoá cứng, tiền đã chốt không đổi theo giá về sau (§4.1 O1, O2).

### 1.2 Trong phạm vi
Đăng nhập & phân quyền · **Bản quyền SaaS (gói, thuê bao, hạn mức, khoá tenant)** · **Core bảo mật (session, CSRF, AJAX gateway)** · KQXS (lấy tự động + thủ công) · Nhập tin / phân tích / đối soát / lưu / sửa / xoá phiên · Khách hàng & bảng giá 3 tầng · Định nghĩa cú pháp (bảng dàn số) · Bộ nhớ tự học · Lịch sử, chốt sổ, in báo cáo · Đồng bộ Telegram (daemon) · Quản trị tài khoản, thuê bao & thống kê · Import dữ liệu `xoso.db` (tuỳ chọn).

### 1.3 Ngoài phạm vi (KHÔNG port)
| Hạng mục Desktop | Lý do |
|---|---|
| Engine cú pháp **Cũ** và chế độ **So sánh**, `parser_facade` 3 chế độ, `parser_diff_log`, tab "Log lệch" | Quyết định N1: web chỉ dùng engine Mới |
| License máy (`license_service.py`, `LicenseDialog`), splash | Đặc thù Desktop (gắn `machine_id`). Web thay bằng **bản quyền SaaS theo tài khoản** (§1.4). `api/check.php` + bảng `machines` vẫn phục vụ app Desktop, không dùng chung |
| Sao lưu/khôi phục cloud (`cloud_backup.py`, `KhoiPhucCloudDialog`) | Web lưu trên server; backup DB do hạ tầng |
| Gemini AI (`gemini_api_key`) | Q6 (v1) — gỡ luôn ô nhập khỏi modal cấu hình Telegram của preview |
| Mật khẩu app đơn (`app_auth`) | Thay bằng tài khoản người dùng |
| Đồng bộ hai chiều Desktop ↔ Web | Q2 (v1) — chỉ import một chiều |

### 1.4 Bản quyền SaaS (thay `license_service.py`)

**Mô hình:** **1 User = 1 Đại lý** (O8) — tenant = một tài khoản `users.role='user'`, khoá cô lập `user_id`. Admin là chủ nền tảng. Bản quyền có **2 tầng**:

| Tầng | Kiểm soát | Lưu ở |
|---|---|---|
| **Giấy phép nền tảng** | Số tài khoản đại lý **đang hoạt động** tối đa toàn hệ thống (tier của bản cài đặt) | `giay_phep_he_thong` (1 dòng) |
| **Thuê bao đại lý** | Gói, ngày bắt đầu/hết hạn, ân hạn, hạn mức sử dụng | `goi_dich_vu`, `thue_bao`, `thue_bao_lich_su`, `su_dung_ngay` (§7.2) |

**Hạn mức của gói (tier limits)** — giá trị `NULL` = không giới hạn:
`so_khach_toi_da` · `so_tin_ngay_toi_da` (tin đối soát/ngày) · `cho_phep_telegram` · `so_ngay_tra_cuu_lich_su` · `so_ngay_an_han` (mặc định 3).

**Trạng thái thuê bao** (tính từ ngày theo `today_vn()`, **không** phụ thuộc cron; cron chỉ gửi nhắc và ghi lịch sử):

| Trạng thái | Điều kiện | Phân tích / Đối soát / Đồng bộ TG | Sửa khách, giá, dàn, tự học | Xem lịch sử, chốt sổ, in | Đăng nhập |
|---|---|---|---|---|---|
| `dung_thu` / `hieu_luc` | `today ≤ het_han` | ✅ | ✅ | ✅ | ✅ |
| `an_han` | `het_han < today ≤ het_han + so_ngay_an_han` | ✅ + băng cảnh báo đỏ trên topbar | ✅ | ✅ | ✅ |
| `het_han` (**khoá mõm**) | quá ân hạn | ❌ — mọi endpoint chế độ `phan_tich`/`ghi` trả `402` | ❌ | ✅ (dữ liệu là của đại lý, không giữ làm con tin) | ✅ (màn gia hạn) |
| `tam_khoa` | admin khoá (vi phạm, nợ…) | ❌ | ❌ | ❌ | ✅ chỉ thấy thông báo |
| `users.trang_thai='locked'` | admin khoá tài khoản | — | — | — | ❌ |

**Thực thi:** `GiayPhepGuard::kiemTra(TenantContext, CheDo)` với `CheDo ∈ {doc, ghi, phan_tich}` — gọi **bắt buộc** trong AJAX gateway (§5.3) và `app/_layout.php`; hạn mức số khách kiểm trong `KhachHangRepo::them()`, số tin/ngày kiểm trong `DoiSoatService::luu()` (đếm `su_dung_ngay` trong cùng transaction, `SELECT … FOR UPDATE`); worker Telegram kiểm `cho_phep_telegram` + trạng thái trước khi nhận job. Giấy phép nền tảng kiểm khi admin **tạo** hoặc **mở khoá** tài khoản đại lý. Mọi thay đổi thuê bao ghi `thue_bao_lich_su` (append-only, ai/khi nào/trước–sau).

---

## 2. Hiện trạng (rà soát 2026-11-02)

### 2.1 Python (nguồn)
| Module | Dòng | Ghi chú rà soát |
|---|---|---|
| `core/database.py` | 4354 | CRUD + tính tiền + dò trúng + tổng hợp; ~140 hàm. Logic đối soát một phần nằm ở `ui/app.py::_doi_soat_tin_nhan_cho_khach_no_ui` |
| `core/messageParser.py` | 1298 | Engine cũ — **vẫn phải port** vì `RuleParser` gọi `MessageParser._phan_tich` qua hook và dùng `_PATTERN`, `_RE_SO_TOKEN`, `_parse_tien`, mã lỗi (`rule_parser.py:22,52-66,211-226`) |
| `core/heuristic_cleaner.py` | 1169 | Có cờ `bung_dan_kep` — engine Mới chạy với `False` (`parser_facade.bung_dan_kep()`) |
| `core/telegram_sync.py` | 731 | Telethon — giữ bằng worker Python (Q1) |
| `core/learning_service.py` | 474 | 5 hàng rào + shadow test 16 tin chuẩn |
| `core/rule_parser.py` | 412 | **Engine duy nhất của web** |
| `core/quan_ly_dan_so.py` | 315 | `kiem_tra_dan`, `anh_huong`, `tach_*` |
| `utils/diff_analyzer.py` · `core/doi_chieu_tin.py` | 321 · 279 | `SequenceMatcher`; `danh_dau_nghi_ngo` dùng ở Zalo + Telegram |
| `core/scraper.py` | 178 | 3 tầng fallback |
| `core/parser_facade.py` | 107 | **Không port** (thay bằng `ParserService` 1 engine) |
| `core/dan_so_mac_dinh.py` | 94 | Seed 115 dàn: 15 bộ · 11 tổng · 50 chia · 10 chạm · 10 đầu · 10 đít · 9 dàn |
| `ui/**` | ~10k | Chỉ tham khảo hành vi |

### 2.2 PHP (đích)
| Hạng mục | Trạng thái |
|---|---|
| Giai đoạn 0 (v1): `tools/test.php`, `lib/bigwin/helpers.php`, fixture | ✅ Xong — `php src/webapp/tools/test.php` → **19 passed, 0 failed** |
| Fixture parity | 6 tệp: `cleaner`, `parser`, `tinh_tien`, `do_trung`, `seqmatch`, `learning` — **đều chạy engine CŨ** (`MessageParser`, cleaner `bung_dan_kep=True`). Engine Mới **chưa có fixture** |
| Schema | Chỉ bảng phục vụ Desktop (`owners`, `machines`, `admins`, `backup_versions`, `usage_summaries`…). Chưa có bảng nghiệp vụ |
| Rule Claude | `.claude/rules/bigwin-core.md`, `tenant-isolation.md` đã có |
| UI | Preview tĩnh hoàn chỉnh (mock), chưa có `app/` |

### 2.3 Khoảng trống phát hiện khi rà soát
| # | Khoảng trống | Xử lý ở |
|---|---|---|
| K1 | Không có fixture engine Mới (cleaner `bung_dan_kep=False`, `RuleParser`, `kiem_tra_dan`, `anh_huong`, tổng hợp) | S0.5 |
| K2 | BigWIN có ~1.300 dòng thay đổi **chưa commit** trong `core/` — fixture sinh từ working tree "dirty" | S0.4 (O4) |
| K3 | Preview là mock: parser JS rút gọn, tính tiền chạy ở client | Thay toàn bộ bằng AJAX gọi lõi PHP (§7) |
| K4 | 17 lệch logic UI ↔ Desktop (báo cáo rà soát 2026-11-02) | Đưa thành tiêu chí nghiệm thu từng slice — Phụ lục A |

---

## 3. Quyết định đã chốt

### 3.1 Kế thừa v1 (không đổi)
D1 lõi PURE trong `lib/bigwin/` · D2 bảng `users` có `role` · D3 `app/` cho user, `admin/` cho quản trị · D4 mọi bảng nghiệp vụ có `user_id` (cơ chế thực thi **thay bằng N15** — `TenantRepo` tự chèn, không truyền `$userId` thủ công) · D5 `ket_qua_xo_so` dùng chung · D6/Q1 Telegram = worker Python Telethon qua hàng đợi MySQL · D7 `/api` đóng băng, AJAX web ở `app/ajax/` · D8 parity bằng golden fixture · Q2 Desktop/Web độc lập · Q3 fixture ẩn danh · Q4 giá 3 tầng · Q5 admin dùng app như user + xem thống kê tổng hợp · Q6 không Gemini · Q7 chỉ được sửa `scripts/export_golden.py` bên BigWIN.

### 3.2 Quyết định mới (từ thiết kế UI 2026-11-02)
| # | Quyết định | Hệ quả kỹ thuật |
|---|---|---|
| **N1** | **Chỉ một engine cú pháp: Mới** (tính theo bảng dàn số) | Không port `parser_facade`, `parser_diff_log`, `lay/luu_parser_engine`. Port `MessageParser` làm **lõi nền** (pattern, `_phan_tich`, tiền, lỗi, span) — không lộ ra như engine. Cleaner luôn `bung_dan_kep=false` |
| **N2** | **Một quy ước dấu**: `THÀNH TIỀN = Tiền ghi − Trúng − CK`; ≥ 0 → "PHẢI THU KHÁCH", < 0 → "PHẢI TRẢ KHÁCH" | Áp cho mọi màn, phiếu chốt, báo cáo in. Không port công thức ngược dấu của PDF Desktop |
| **N3** | **Bảng tổng hợp theo loại cược là 1 component** (7 cột STT·Loại·ĐÁNH·TIỀN·TRÚNG·CK·THÀNH TIỀN; Lô hiện điểm; Xiên 2/3/4 tách dòng; dòng "Số trúng"; khung kết luận) | Số liệu tính ở PHP `TongHop.php` (port `tong_hop_doi_soat_bang_bieu`); hiển thị bằng **một** renderer JS `bang_tong_hop.js` dùng ở Nhập tin, Telegram (dock + 2 modal), Chốt sổ, In báo cáo |
| **N4** | **Bố cục**: 6 page + Đăng nhập (xem §6) | Gộp: Hồ sơ khách + giá riêng → page Khách hàng; Tự học + Hướng dẫn → page Cú pháp; Báo cáo in → page Lịch sử; Sửa tin → **chế độ sửa** của page Nhập tin |
| **N5** | Bảng giá **21 khoá** (3 nhóm × 7 loại, có Xiên quay) theo 3 tầng Hệ thống → Đại lý → Khách | Tầng "Đại lý" = bảng `he_so_user`. Tầng "Hệ thống" chỉ admin sửa |
| **N6** | **Có chức năng Xoá khách hàng** (Desktop không có) | Kiểu xoá: xem O1 |
| **N7** | Trùng bộ cược: chỉ **Ghi đè** hoặc **Huỷ** (bỏ "Vẫn lưu thêm") | Như Desktop `zalo_forms.py:775-787` |
| **N8** | Ngày làm việc mặc định: **trước 18:30 → D-1, từ 18:30 → D** | Như `ui/app.py:649-653`; topbar dùng chung cho mọi page |
| **N9** | Lấy KQXS **tự động bằng cron** + nút "Cập nhật KQ" | Tính năng mới so với Desktop (Desktop chỉ F5) |
| **N10** | Sửa phiên đã lưu = mở phiên ở page Nhập tin chế độ "Đang sửa phiên #id" → lưu = ghi đè + đối soát lại + học quy tắc | Không làm màn "Chi tiết đối soát" riêng như Desktop |
| **N11** | Hàng rào tự học đầy đủ như `learning_service.py` (5 guard + shadow test), áp cho cả "thêm tay" | Preview đang yếu hơn — không lấy preview làm chuẩn |
| **N12** | Không port 4 lỗi đã biết của Desktop (§13) | — |
| **N13** | UI web dùng Tailwind + Alpine.js như `admin/_auth.php`. Giai đoạn dev: CDN khoá phiên bản. **Trước go-live (S9.3):** CSS Tailwind build sẵn thành tệp tĩnh + bản **Alpine CSP build** để bật CSP chặt (không `unsafe-eval`) | Nhất quán với admin; không vướng CSP (§5.3) |
| **N14** | **Bản quyền SaaS** 2 tầng: giấy phép nền tảng + thuê bao đại lý có hạn mức; hết hạn = khoá mõm (ngừng phân tích/ghi, vẫn xem/in) | §1.4, `GiayPhepGuard`, bảng §7.2 |
| **N15** | **TenantScope tự động**: repo nghiệp vụ kế thừa `TenantRepo`, `user_id` lấy từ `TenantContext` bất biến và **tự chèn** vào mọi truy vấn; repo không nhận `$userId` từ bên ngoài | §5.4, rule `tenant-isolation.md` |
| **N16** | **Core bảo mật tự build** (`lib/security/`): session nghiêm ngặt, CSRF cho 100% request đổi trạng thái, một AJAX gateway duy nhất | §5.3 |
| **N17** | Parser **chỉ chạy ở server** (một nguồn sự thật); tăng tốc bằng **cache nhiều tầng** cho artefact đã biên dịch của bảng dàn số / quy tắc tự học. **Không** port parser sang JS/Web Worker | §8.1 |
| **N18** | Dữ liệu tiền **không bao giờ xoá cứng**; tiền đã chốt bất biến | §4.1 O1, O2 |

---

## 4. Quyết định từ danh sách mở

### 4.1 Đã chốt (2026-11-02, review kiến trúc)

**O1 — Xoá mềm BẮT BUỘC cho dữ liệu liên quan tiền.**
- *Lý do:* hệ thống đối soát tiền tệ **tuyệt đối không xoá cứng** để bảo toàn tính nhất quán của lịch sử dòng tiền — mọi con số từng hiện trên phiếu chốt / báo cáo đã gửi khách phải truy lại được nguồn gốc.
- *Phạm vi:* `khach_hang`, `tin_nhan_log`, `lich_su_doi_soat` có `deleted_at DATETIME NULL` + `deleted_by INT NULL` + `ly_do_xoa VARCHAR(255) NULL`. `users` không xoá — chỉ `locked`.
- *Hệ quả nghiệp vụ:* "Xoá khách" = xoá mềm khách (phiên cũ **giữ nguyên**, vẫn vào báo cáo các ngày cũ dưới tên khách kèm nhãn "đã xoá"). "Xoá phiên" = xoá mềm `tin_nhan_log` + các dòng `lich_su_doi_soat` của phiên. "Ghi đè phiên trùng", "Sửa phiên", "Ghi Telegram thay dữ liệu cũ" = xoá mềm bản cũ + ghi bản mới, liên kết bằng `thay_the_boi_msg_id`. Không có nút "Xoá vĩnh viễn" trong app; dọn dữ liệu (nếu luật yêu cầu) là thủ tục vận hành có biên bản, ngoài phạm vi.
- *Hệ quả kỹ thuật:* UNIQUE trên cột có thể trùng sau khi xoá dùng **cột sinh** chỉ có giá trị khi còn hoạt động (§7.1). `TenantRepo` tự thêm `deleted_at IS NULL` (§5.4). Câu chữ UI chuẩn: **"Ẩn khách hàng, bảo toàn lịch sử đối soát cũ"** (Phụ lục A-KH; preview đã cập nhật).

**O2 — Snapshot tiền BẮT BUỘC, tiền đã chốt bất biến (immutable).**
- `lich_su_doi_soat` lưu `tien_vnd`, `tien_ghi`, `tien_trung`, `tien_chiet_khau`, `so_lan_trung`, `giai_trung` **và** `he_so_ap_dung_json` (hệ số đã resolve lúc đối soát — để giải thích con số) tại thời điểm đối soát.
- Dòng đã ghi **không UPDATE cột tiền** — chỉ được xoá mềm. Đổi giá (bất kỳ tầng nào) chỉ ảnh hưởng tin đối soát sau đó; muốn áp giá mới cho tin cũ → "Sửa phiên" (tạo bản mới, bản cũ xoá mềm). Báo cáo/chốt sổ/thống kê **chỉ cộng snapshot**, không gọi lại `TinhTien`.
- Ngoại lệ có kiểm soát: KQXS về muộn/sửa → chạy "Đối soát lại ngày" (tạo phiên mới thay phiên cũ theo cùng cơ chế O1), có ghi nhật ký.

**O3 — Telegram worker là daemon độc lập (systemd), giao tiếp qua job table.**
- Tiến trình Python Telethon chạy dưới `systemd` (`bigwin-telegram.service`: `Restart=always`, `User=bigwin-tg` không đăng nhập, `EnvironmentFile` ngoài repo, log qua journald), **không** do web khởi chạy.
- Giao tiếp **chỉ** qua bảng `telegram_jobs` / `telegram_inbox` (MySQL làm hàng đợi: worker lấy job bằng `SELECT … FOR UPDATE SKIP LOCKED`, heartbeat `telegram_worker_trang_thai`). Web request **không bao giờ chờ** Telethon: tạo job → trả `job_id` ngay → UI poll `ajax/telegram_job.php` (1–2 s, có backoff).
- Hệ quả hạ tầng: production **bắt buộc** có máy chủ Linux chạy được systemd (VPS). Shared hosting không đáp ứng → ghi vào checklist triển khai S9.3.

**O4 — Đóng băng mã nguồn BigWIN: THỰC THI NGAY.**
- Chủ repo BigWIN commit toàn bộ working tree hiện tại (~1.300 dòng chưa commit trong `core/`, `utils/`) rồi tạo tag: `git tag -a port-baseline -m "Mốc mã nguồn cho migration PHP"`.
- **Mọi thay đổi logic Python sau tag nằm ngoài phạm vi migration.** Nếu bắt buộc mang sang: lập yêu cầu thay đổi riêng (CR) → port → chạy lại exporter trên commit mới → ghi nhật ký §14.
- Là điều kiện chặn của S0.4 → G2. Claude không tự tạo tag ở repo BigWIN (Q7) — người thực hiện: chủ repo. Thứ tự bắt buộc xem R1 (§12).

**O8 — Mô hình tenant: 1 User = 1 Đại lý.**
- Mỗi tài khoản `users` (role `user`) **là** một đại lý; dữ liệu nghiệp vụ thuộc về đúng tài khoản đó.
- **Khoá cô lập (isolation key) giữ nguyên là `user_id`** cho toàn bộ schema, `TenantContext`, `TenantRepo`, khoá cache.
- **Không** triển khai `tenant_id`, không có tài khoản nhân viên phụ / nhiều đăng nhập dùng chung một đại lý trong phiên bản này.
- Giới hạn của giấy phép nền tảng (§1.4, `giay_phep_he_thong.so_dai_ly_toi_da`) = **tổng số đại lý** (= số tài khoản role `user` đang hoạt động; tài khoản admin không tính).
- Nếu sau này cần tài khoản phụ: là một dự án riêng (đổi khoá cô lập sang `tenant_id`, migrate toàn bộ bảng tenant), ngoài phạm vi v2.

**O5 — Tạo tài khoản đại lý (chốt 2026-11-02).** Chỉ admin tạo tài khoản đại lý, không có tự đăng ký. Tài khoản mới có `phai_doi_mat_khau = 1` → lần đăng nhập đầu buộc đổi mật khẩu. Tạo/mở khoá được hay không phụ thuộc giấy phép nền tảng (§1.4).

### 4.2 Còn mở (phải chốt trước giai đoạn ghi ở cột cuối)

| # | Câu hỏi | Phương án đề xuất | Chốt trước |
|---|---|---|---|
| **O6** | Bộ dàn gốc khi admin sửa: có lan sang user đã có không? | **Không tự lan.** User nhận bản sao lúc tạo tài khoản; "Khôi phục dàn gốc" kéo bộ gốc **hiện tại** | G5 |
| **O7** | Có import dữ liệu `xoso.db` cũ không, cho tài khoản nào? | Tuỳ nhu cầu go-live | G8 |
| **O9** | Bảng giá gói & cách thu tiền thuê bao (thủ công hay cổng thanh toán)? | Đợt này: admin gia hạn thủ công trong trang Quản trị; cổng thanh toán ngoài phạm vi | G8 |

---

## 5. Kiến trúc đích

### 5.1 Phân lớp
```
Trình duyệt (page PHP + Alpine.js + bang_tong_hop.js + editor highlight)
      │  fetch JSON — header X-CSRF-Token, same-origin
      ▼
app/ajax/*.php ──► ajax_khoi_dong(cheDo)                        ◄── lib/security/ (Core bảo mật, §5.3)
                    ├ method/Content-Type/kích thước body
                    ├ PhienBaoMat (session, timeout, thu hồi)
                    ├ Csrf + Origin/Sec-Fetch-Site
                    ├ require_user() ──► TenantContext (user_id bất biến)
                    ├ GiayPhepGuard::kiemTra(ctx, cheDo)     (§1.4)
                    ├ GioiHanTanSuat (rate limit)
                    └ handler JSON lỗi chung (không lộ stack trace)
      ▼
lib/bigwin/*Service.php  (điều phối, transaction, KHÔNG HTML) — nhận TenantContext
      ├──► lib/bigwin/*.php (PURE: cleaner, parser, rule parser, tính tiền, dò trúng, tổng hợp, dàn số, tự học)
      ├──► lib/bigwin/BoNhoDem.php (cache nhiều tầng cho artefact parser, §8.1)
      └──► lib/bigwin/repo/*Repo.php extends TenantRepo (§5.4) ── tự chèn user_id + deleted_at IS NULL
           lib/bigwin/repo/Shared*Repo.php (bảng dùng chung, user chỉ đọc)
                 ▼
               MySQL (InnoDB, utf8mb4, time_zone +07:00)

tools/scrape_xsmb.php (cron) ──► Scraper.php ──► SharedKetQuaRepo
workers/telegram/ (Python Telethon, systemd) ◄──► telegram_jobs / telegram_inbox ◄──► TelegramService
```
**Luật phụ thuộc:** `app/**` không gọi `db()`; Service không chứa SQL; chỉ `TenantRepo`/`SharedRepo` chạm PDO; lõi PURE không biết DB, session, cache.

### 5.2 Cấu trúc thư mục (chỉ phần mới/đổi)
```
src/webapp/
├── index.php                  # đã login → app/nhap_tin.php; chưa → login.php
├── login.php  logout.php      # MỚI — đăng nhập chung user & admin
├── app/                       # MỚI — phân hệ đại lý
│   ├── _layout.php            # require_user(), topbar (ngày 18:30, KQ, đồng hồ, avatar menu), sidebar 6 page, phím tắt
│   ├── nhap_tin.php           # Page 1 — chế độ: nhập mới / đang sửa phiên / đã lưu
│   ├── telegram.php           # Page 2
│   ├── kqxs.php               # Page 3
│   ├── khach_hang.php         # Page 4 — lưới giá + drawer hồ sơ/giá riêng + thêm/xoá
│   ├── lich_su.php            # Page 5 — tab Lịch sử tin · tab Chốt sổ & In
│   ├── in_bao_cao.php         # Trang chỉ để in (Tổng hợp/Chi tiết) — @media print
│   ├── cu_phap.php            # Page 6 — tab Dàn số · Bộ nhớ tự học · Hướng dẫn & Quy tắc
│   ├── ajax/                  # JSON — danh sách ở Phụ lục B
│   └── assets/  app.css  app.js  bang_tong_hop.js  editor.js  (+ js riêng từng page)
├── admin/                     # GIỮ — thêm users.php, thong_ke.php (tab trong "Quản trị")
├── lib/
│   ├── auth.php               # MỚI — require_user/require_admin, đăng nhập/đổi mật khẩu (dùng lib/security)
│   ├── security/              # MỚI — Core bảo mật tự build (§5.3)
│   │   ├── PhienBaoMat.php    # cấu hình & vòng đời session, timeout, thu hồi
│   │   ├── Csrf.php           # token đồng bộ, kiểm header/field, Origin
│   │   ├── AjaxGateway.php    # ajax_khoi_dong(), phản hồi JSON chuẩn
│   │   ├── TieuDeBaoMat.php   # CSP, HSTS, X-Content-Type-Options, frame-ancestors
│   │   └── GioiHanTanSuat.php # rate limit đăng nhập & AJAX
│   └── bigwin/
│       ├── helpers.php        # ✅ đã có
│       ├── TenantContext.php GiayPhepGuard.php BoNhoDem.php
│       ├── SequenceMatcher.php DiffAnalyzer.php DoiChieuTin.php
│       ├── HeuristicCleaner.php MessageParser.php RuleParser.php DanSoMacDinh.php QuanLyDanSo.php
│       ├── TinhTien.php DoTrung.php TongHop.php NgayLamViec.php
│       ├── LearningService.php ParserService.php DoiSoatService.php KetQuaService.php TelegramService.php
│       ├── Scraper.php
│       └── repo/ TenantRepo SharedRepo  KhachHangRepo HeSoRepo TinNhanRepo DoiSoatRepo DanSoRepo BoNhoRepo
│                 TelegramRepo ThueBaoRepo  SharedKetQuaRepo SharedCauHinhRepo  UserRepo(admin)
├── workers/telegram/          # MỚI — Python, không chạy qua web (.htaccess chặn) + bigwin-telegram.service (systemd)
├── tools/  scrape_xsmb.php  import_sqlite.php  seed_dan_so.php  cap_nhat_thue_bao.php  don_cache.php
└── tests/  *_test.php  fixtures/
```
Không tạo: `app/he_so.php`, `app/tu_hoc.php`, `app/huong_dan.php`, `app/telegram_ket_noi.php` (v1) — đã gộp theo N4.

### 5.3 Core bảo mật (`lib/security/`) — bù cho việc không có framework

Không Composer/framework nghĩa là **không có middleware mặc định**; mọi bảo vệ phải nằm trong một lớp dùng chung, không để từng trang tự làm.

**a) Session — `PhienBaoMat.php`**
| Thiết lập | Giá trị |
|---|---|
| Tên cookie | `bigwin_web` (admin cũ `bigwin_admin` dùng song song tới hết G1 rồi gộp) |
| Cờ cookie | `HttpOnly`, `Secure` (bắt buộc khi HTTPS; `cfg('cookie_secure')`), `SameSite=Lax`, `Path=/`, không đặt `Domain` |
| ini | `session.use_strict_mode=1`, `use_only_cookies=1`, `use_trans_sid=0`, `sid_length=48`, `sid_bits_per_character=6`, `cookie_lifetime=0` |
| Đổi ID | `session_regenerate_id(true)` khi đăng nhập, đổi mật khẩu, đổi role, gia hạn quyền |
| Hết hạn | Nhàn rỗi **30 phút**, tuyệt đối **12 giờ** (kiểm mỗi request) |
| Thu hồi | Bảng `phien_dang_nhap` (hash session id, user_id, ip, ua_hash, tao_luc, hoat_dong_luc, thu_hoi_luc). Khoá tài khoản / đổi mật khẩu / admin "đăng xuất mọi nơi" → thu hồi ngay, request kế bị đẩy về login |
| Dấu vân tay mềm | Đổi User-Agent giữa phiên → đăng nhập lại (không ràng IP vì mạng di động đổi IP) |

**b) CSRF — `Csrf.php`**
- Synchronizer token 32 byte/phiên (`random_bytes`), xoay khi đăng nhập. Trang in token vào `<meta name="csrf-token">`; `app/assets/app.js` có **một** hàm `goiApi()` duy nhất tự gắn header `X-CSRF-Token` cho mọi `fetch` — cấm gọi `fetch` trực tiếp trong page JS.
- Kiểm bằng `hash_equals`; form HTML thường dùng field `csrf` (tương thích `csrf_check()` của admin).
- Kiểm thêm `Origin` (hoặc `Referer`) khớp host và `Sec-Fetch-Site ∈ {same-origin, none}` cho mọi request đổi trạng thái.
- Mọi thao tác đổi dữ liệu chỉ qua `POST`; `GET` đổi dữ liệu = 405.

**c) AJAX gateway — `AjaxGateway.php`: `ajax_khoi_dong(string $cheDo, array $tuyChon = []): TenantContext`**
Là **dòng đầu tiên** của mọi `app/ajax/*.php`. Thứ tự: tiêu đề bảo mật → method (`POST` trừ khi `get: true`) → `Content-Type: application/json` → giới hạn body (mặc định 64 KB; tin nhắn ≤ 20 KB) → phiên hợp lệ/chưa thu hồi → CSRF + Origin → `require_user()` → dựng `TenantContext` → `GiayPhepGuard::kiemTra(ctx, cheDo)` → rate limit → đăng ký handler lỗi trả JSON `{ok:false, ma, thong_diep}` (không stack trace; ghi `ghi_loi()` kèm mã tham chiếu). Mã lỗi thống nhất: `401` chưa đăng nhập · `402` hết hạn thuê bao · `403` sai CSRF/không quyền · `404` không tồn tại **hoặc không thuộc tenant** · `409` trùng · `422` dữ liệu sai · `429` quá tần suất.

**d) Tiêu đề bảo mật — `TieuDeBaoMat.php`**
`Content-Security-Policy` (`default-src 'self'`; script chỉ `'self'` + CDN đã khoá phiên bản trong giai đoạn dev; `frame-ancestors 'none'`; `object-src 'none'`; `base-uri 'self'`) · `X-Content-Type-Options: nosniff` · `Referrer-Policy: same-origin` · `Strict-Transport-Security` khi HTTPS · `Cache-Control: no-store` cho JSON. CSP chặt (không `unsafe-eval`/`unsafe-inline`) bật ở S9.3 khi chuyển sang Alpine CSP build + CSS build sẵn (N13).

**e) Đăng nhập & mật khẩu**
`password_hash(PASSWORD_ARGON2ID)` (fallback `PASSWORD_BCRYPT` nếu thiếu), tối thiểu 10 ký tự; `password_needs_rehash` khi đăng nhập; chống dò: `user_login_fails` theo username + IP, khoá tăng dần (5 lần → 15 phút); thông báo lỗi chung "Sai tên đăng nhập hoặc mật khẩu".

**f) Rule & kiểm chứng:** tạo `.claude/rules/security-core.md` (paths `src/webapp/app/**`, `src/webapp/lib/security/**`) ở S1.2; test `tests/security_test.php` (§11).

### 5.4 TenantScope — tự động cô lập `user_id` (thay việc rà `WHERE user_id = ?` thủ công)

**Vấn đề:** để mỗi repo tự viết `AND user_id = ?` là phụ thuộc vào trí nhớ người viết — quên một chỗ là rò dữ liệu chéo đại lý.

**Thiết kế:**
```php
final class TenantContext {                       // bất biến, 1 instance/request
    private function __construct(public readonly int $userId) {}
    public static function tuPhien(array $user): self          // CHỈ AjaxGateway / _layout gọi
    public static function choCli(int $userId, string $lyDo): self  // tools/worker — ghi log lý do
}

abstract class TenantRepo {
    protected const BANG = '';          // bảng chính, phải nằm trong DANH_SACH_BANG_TENANT
    protected const XOA_MEM = true;     // tự thêm "deleted_at IS NULL"
    public function __construct(private readonly TenantContext $ctx) {}

    // CRUD an toàn — user_id & deleted_at do lớp nền tự chèn
    final protected function tim(string $dieuKien = '1=1', array $thamSo = [], string $sau = ''): array;
    final protected function timMot(int $id): ?array;            // WHERE id=:id AND user_id=:__uid
    final protected function them(array $cot): int;               // ép cot['user_id']=ctx; cấm truyền user_id
    final protected function sua(int $id, array $cot): bool;      // cấm sửa user_id, cấm sửa cột tiền snapshot
    final protected function xoaMem(int $id, string $lyDo): bool;

    // SQL tự viết (JOIN, tổng hợp): BẮT BUỘC viết từng bảng tenant là {tenant:ten_bang alias}
    final protected function truyVan(string $sql, array $thamSo): PDOStatement;
}
```
- **(Cập nhật P02, Q-P02-8 — "bảng lọc sẵn")** placeholder đứng **thay tên bảng** sau `FROM`/`JOIN`: `{tenant:khach_hang k}` → `(SELECT * FROM khach_hang WHERE user_id = <id> AND deleted_at IS NULL) k` (`{tenant_ca_xoa:…}` bỏ phần `deleted_at`). Thiết kế cũ (chèn `k.user_id = … AND …` vào `WHERE`) bị loại vì `WHERE a OR {tenant:k}` làm lộ dữ liệu mọi đại lý và đổi nghĩa `LEFT JOIN` (P02-B01). Trước khi prepare, `BoQuetSqlTenant` quét SQL: tên bảng tenant xuất hiện "trần" ở bất cứ đâu, chú thích, `;`, `UNION`, `WITH`, `INTO`, câu không phải `SELECT` → `LoiCoLapTenant` (fail-closed, kể cả trên production). `user_id` chèn dạng số nguyên lấy từ `TenantContext` (PDO native prepare không cho trộn `?` với tham số tên).
- **Bảng thuê bao** (`thue_bao`, `thue_bao_lich_su`, `su_dung_ngay`) không thuộc `DANH_SACH_BANG_TENANT`: truy cập qua `ThueBaoRepo` riêng (Q-P02-1) vì admin phải thao tác trên thuê bao của đại lý khác.
- Bảng con không có `user_id` (`he_so_khach_hang`, `parser_dan_so_khoa`, `parser_dan_so_so`): **thêm cột `user_id`** + FK kép (`(khach_hang_id, user_id)` → `khach_hang(id, user_id)`) để mọi bảng tenant đều tự chèn được, không phải nhớ JOIN.
- Tham chiếu chéo khi ghi (`khach_hang_id` trong phiên) xác thực bằng `timMot()` của repo tương ứng trước khi ghi; FK kép ở DB là lớp chặn thứ hai.
- `SharedRepo`: chỉ cho bảng dùng chung; method ghi yêu cầu `TenantContext` có role admin hoặc ngữ cảnh CLI.
- Truy vấn **chéo tenant** duy nhất được phép: thống kê admin (`admin/thong_ke.php`) dùng `ThongKeRepo` riêng, chỉ trả số tổng hợp, không có cột nội dung.

**Ba lớp kiểm chứng tự động:** (1) `LoiCoLapTenant` lúc chạy; (2) `tests/tenant_scan_test.php` quét mã: cấm `db()`, `->prepare(`, `->query(`, `->exec(` ngoài `TenantRepo`/`SharedRepo`/`ThongKeRepo`/migration/tools; cấm chuỗi `user_id` trong tham số `them()/sua()`; (3) `tests/idor_test.php` sinh dữ liệu cho 2 tenant và gọi **mọi** method public của mọi repo dưới ngữ cảnh tenant kia → phải rỗng/404.

---

## 6. Ánh xạ Page (preview) → PHP → nguồn Desktop

| Page (preview) | Tệp PHP | Thành phần UI (preview) | Nguồn Desktop | Service / Lõi |
|---|---|---|---|---|
| **Đăng nhập** *(page mới)* | `login.php` | Form, lỗi chung, khoá tăng dần, buộc đổi mật khẩu, thông báo tài khoản bị khoá / thuê bao tạm khoá | `auth_forms.py:28` | `lib/auth.php`, `lib/security/*`, `UserRepo` |
| Topbar (mọi page) | `app/_layout.php` | Ngày làm việc (N8), trạng thái KQ, menu avatar: Đổi mật khẩu (modal), Đăng xuất; **băng thuê bao** (còn N ngày / ân hạn / hết hạn — khoá mõm: ẩn nút phân tích, lưu, đồng bộ) | `app.py:649` | `NgayLamViec.php`, `GiayPhepGuard` |
| **1. Nhập tin nhanh** | `app/nhap_tin.php` | Chọn khách (`/`), editor tô đỏ/vàng 300 ms, chẩn đoán, KPI, **bảng tổng hợp** + chi tiết từng cược, modal trùng (Ghi đè/Huỷ), **chế độ Đã lưu** (mã phiên, trúng/trượt, Xoá phiên), **chế độ Đang sửa phiên** | `zalo_forms.py:70`, `customer_forms.py:26`, `history_forms.py:452` | `ParserService`, `DoiSoatService`, `DoiChieuTin`, `TongHop` |
| **2. Telegram** | `app/telegram.php` | Thanh điều khiển, danh sách master (OK/Nghi ngờ/Lỗi cú pháp/Lỗi), dock sửa (3 bố cục), bảng tổng hợp, modal: kết nối OTP/2FA, cấu hình (bỏ Gemini), tổng kết, tổng hợp, bạn trên TG, **xác nhận ghi DB có số liệu**, nhật ký | `telegram_forms.py` (toàn bộ) | `TelegramService`, `ParserService`, `DoiSoatService` |
| **3. Kết quả XSMB** | `app/kqxs.php` | Bảng 27 giải, đầu–đuôi (+ cột số lượng), lô nhiều nháy, chỉ 2 số cuối, 3 ngày trước, cập nhật KQ | `ui/app.py:485-1050` | `KetQuaService`, `Scraper` |
| **4. Khách hàng & Bảng giá** | `app/khach_hang.php` | Lưới 21 cột (bật/tắt nhóm, cột tên ghim), dòng Hệ thống / Đại lý / khách, drawer hồ sơ + 21 giá riêng, modal Thêm, modal Xoá (**xoá mềm** — O1; câu chữ "Ẩn khách hàng, bảo toàn lịch sử đối soát cũ"); chặn thêm khi chạm `so_khach_toi_da` | `customer_forms.py:153,355,461` | `KhachHangRepo`, `HeSoRepo` |
| **5. Lịch sử & Chốt sổ** | `app/lich_su.php`, `app/in_bao_cao.php` | Tab **Lịch sử tin** (lọc khách/trạng thái, Sửa → Nhập tin, Xoá) · Tab **Chốt sổ & In** (KPI ngày, bảng theo khách, phiếu chốt copy, bảng tổng hợp/khách, In tổng hợp, In chi tiết) | `history_forms.py:25`, `app.py:1754,1823,2121` | `DoiSoatRepo`, `TongHop` |
| **6. Cú pháp & Tự học** | `app/cu_phap.php` | Tab **Dàn số** (lọc nhóm, bảng 6 cột, form 50-50, chọn nhanh 00–99, modal đo ảnh hưởng, khôi phục dàn gốc gõ xác nhận) · Tab **Bộ nhớ tự học** (bảng, thêm tay, xoá nhiều) · Tab **Hướng dẫn & Quy tắc** (chỉ đọc) | `rules_forms.py:63,288,382` | `QuanLyDanSo`, `DanSoRepo`, `LearningService` |
| **Quản trị** *(admin)* | `admin/users.php`, `admin/thue_bao.php`, `admin/thong_ke.php` | Tab Tài khoản đại lý (tạo/khoá/đăng xuất mọi nơi) · Tab **Gói & Thuê bao** (gói, gia hạn, tạm khoá, lịch sử, giấy phép nền tảng: đã dùng X/Y tài khoản) · Tab Thống kê; dòng "Hệ thống" ở page 4 và bộ dàn gốc ở page 6 **mở sửa khi role=admin** | — | `UserRepo`, `ThueBaoRepo`, `ThongKeRepo` |

---

## 7. Thiết kế dữ liệu

### 7.1 Nguyên tắc
**Giữ từ v1:** `DATE`/`DATETIME` giờ VN · tiền `BIGINT` · hệ số `DOUBLE` · `utf8mb4_unicode_ci`, riêng cột so khớp chính xác (`so`, `khoa`, `cum_tu_goc`) dùng `utf8mb4_bin` · import làm sạch FK mồ côi.

**Bổ sung v2.1:**
1. **Mọi bảng tenant có cột `user_id NOT NULL`** — kể cả bảng con (`he_so_khach_hang`, `parser_dan_so_khoa`, `parser_dan_so_so`) — để `TenantRepo` tự chèn điều kiện (§5.4). Danh sách bảng tenant khai báo **một chỗ** (`TenantRepo::DANH_SACH_BANG_TENANT`), test đối chiếu với `schema.sql`.
2. **FK kép chống tham chiếu chéo tenant:** bảng cha có `UNIQUE(id, user_id)`; bảng con tham chiếu `(cha_id, user_id)` → DB tự chặn việc gắn bản ghi của đại lý A vào khách của đại lý B.
3. **Không `ON DELETE CASCADE`** trên dữ liệu tiền (O1). FK dùng `ON DELETE RESTRICT`; xoá = xoá mềm.
4. **Xoá mềm + UNIQUE:** cột sinh `…_hoat_dong` = giá trị khi `deleted_at IS NULL`, ngược lại `NULL`; UNIQUE trên `(user_id, …_hoat_dong)` (NULL không đụng nhau). Ví dụ: `ten_khach_hoat_dong VARCHAR(120) AS (IF(deleted_at IS NULL, ten_khach_chuan, NULL)) STORED`. Kiểm tra phiên bản MySQL/MariaDB production hỗ trợ ở S4.1.
5. **Cột tiền snapshot bất biến (O2):** `lich_su_doi_soat` không có đường UPDATE cột tiền — `DoiSoatRepo` khai báo `COT_BAT_BIEN`, `TenantRepo::sua()` ném lỗi nếu đụng; thêm trigger `BEFORE UPDATE` từ chối thay đổi các cột này (lớp chặn ở DB).
6. Thời điểm ghi/xoá: `created_at`, `deleted_at` dùng `NOW()` (time_zone +07:00), `deleted_by` = user thao tác.

### 7.2 Danh sách bảng & thay đổi so với v1
| Bảng | Tenant | Thay đổi v2 |
|---|---|---|
| `users`, `user_login_fails` | — | + `phai_doi_mat_khau TINYINT` (O5); không xoá, chỉ `locked` |
| `phien_dang_nhap` | — | **MỚI** (§5.3a): `id_hash CHAR(64) PK, user_id, ip, ua_hash, tao_luc, hoat_dong_luc, thu_hoi_luc NULL` |
| `gioi_han_tan_suat` | — | **MỚI** (§5.3c, khi không có APCu): `khoa VARCHAR(120) PK, so_lan INT, het_luc DATETIME` |
| `giay_phep_he_thong` | nền tảng | **MỚI** (§1.4): 1 dòng — `so_dai_ly_toi_da INT NULL, ghi_chu, cap_nhat_luc` |
| `goi_dich_vu` | nền tảng | **MỚI**: `id, ma UNIQUE, ten, gia_thang BIGINT, so_khach_toi_da, so_tin_ngay_toi_da, cho_phep_telegram TINYINT, so_ngay_tra_cuu_lich_su, so_ngay_an_han DEFAULT 3, dang_ban TINYINT` |
| `thue_bao` | ✅ (1 dòng/tenant) | **MỚI**: `user_id PK/FK, goi_id FK, bat_dau DATE, het_han DATE, tam_khoa TINYINT, ly_do_khoa, cap_nhat_boi, cap_nhat_luc`. Trạng thái **tính ra** từ ngày (§1.4), không lưu cứng |
| `thue_bao_lich_su` | ✅ | **MỚI**, append-only: `id, user_id, hanh_dong ENUM('tao','gia_han','doi_goi','tam_khoa','mo_khoa'), truoc_json, sau_json, boi, luc` |
| `su_dung_ngay` | ✅ | **MỚI**: `user_id, ngay DATE, so_tin_doi_soat INT, so_lan_phan_tich INT, PK(user_id, ngay)` — đếm hạn mức |
| `ket_qua_xo_so`, `scraper_log` | dùng chung | — |
| `cau_hinh_mac_dinh` (21 khoá) | dùng chung | Admin sửa = tầng "Hệ thống" |
| `parser_dan_so_goc` (+ `_khoa`, `_so`) | dùng chung | Bộ 115 dàn gốc; admin sửa (O6) |
| `khach_hang` | ✅ | `deleted_at`, `deleted_by`, `ly_do_xoa` (O1); `ten_khach_hoat_dong`, `id_telegram_hoat_dong` (cột sinh) + UNIQUE `(user_id, …_hoat_dong)`; `UNIQUE(id, user_id)` cho FK kép |
| `he_so_user` | ✅ | Tầng "Đại lý" |
| `he_so_khach_hang` | ✅ | **+ `user_id`** + FK kép `(khach_hang_id, user_id)`; chỉ lưu khoá **khác** tầng trên (§13 L5) |
| `tin_nhan_log` | ✅ | `noi_dung` (gốc) + `noi_dung_kiem_tra`; `nguon ENUM('ZALO','TELEGRAM','MANUAL')`; `deleted_at`, `deleted_by`, `ly_do_xoa`, `thay_the_boi_msg_id` (O1); FK kép tới `khach_hang` |
| `lich_su_doi_soat` | ✅ | **Snapshot bất biến** `tien_vnd`, `tien_ghi`, `tien_trung`, `tien_chiet_khau`, `so_lan_trung`, `giai_trung`, `he_so_ap_dung_json` (O2) + trigger chặn UPDATE cột tiền; `deleted_at`… (O1) |
| `bo_nho_sua_tin` | ✅ | — |
| `parser_dan_so` (+ `_khoa`, `_so` — **+ `user_id`**) | ✅ | `nguon ENUM('goc','sua','user')`; `phien_ban INT` (tăng mỗi lần ghi — khoá cache §8.1); **bỏ** `parser_cau_hinh.engine` và `parser_diff_log` (N1) |
| `telegram_tai_khoan`, `telegram_jobs`, `telegram_inbox`, `telegram_sync_log` | ✅ | `telegram_jobs` + `tien_do INT, tong INT, heartbeat_luc` (tiến trình [i/n]) |
| `telegram_worker_trang_thai` | nền tảng | **MỚI** (O3): `ten_worker PK, pid, phien_ban, heartbeat_luc` — UI báo "worker không hoạt động" khi heartbeat > 60 s |

### 7.3 Thứ tự migration (mỗi tệp = 1 slice, luôn kèm cập nhật `schema.sql` qua `/db-migration`)
1. `…_users_rbac_bao_mat.sql` (users, phien_dang_nhap, gioi_han_tan_suat — G1) → 2. `…_thue_bao.sql` (giay_phep_he_thong, goi_dich_vu, thue_bao, thue_bao_lich_su, su_dung_ngay — G1) → 3. `…_ket_qua_xo_so.sql` (G3) → 4. `…_nghiep_vu_tenant.sql` (khach_hang, he_so_*, tin_nhan_log, lich_su_doi_soat + trigger — G4) → 5. `…_dan_so_tu_hoc.sql` (parser_dan_so*, bo_nho_sua_tin — G5) → 6. `…_telegram.sql` (G7).

---

## 8. Luồng nghiệp vụ chính (đặc tả để code Service)

### 8.1 Phân tích tin (gõ phím, debounce 300 ms) — thiết kế hiệu năng

`POST ajax/phan_tich.php {tin, khach_id, ngay}` → `ajax_khoi_dong('phan_tich')` → `ParserService::phanTich(ctx, $tin)`:
1. Lấy **artefact đã biên dịch** của tenant từ `BoNhoDem` (bên dưới) — không đọc DB, không dựng regex nếu cache trúng.
2. `HeuristicCleaner(quyTac, bungDanKep=false)->clean()` → `RuleParser(artefact)->parseDetailed($sach)` → `DoiChieuTin::danhDauNghiNgo()`.
3. Nếu có `khach_id`: hệ số 3 tầng (cache) + KQ ngày (cache dùng chung) → `TinhTien` + `DoTrung` → `TongHop::bang()`.
4. Trả `{marks[] (offset ký tự), loi[], dich[], bets[], tong_hop, can_save}`.

**Vì sao chọn cache phía server, KHÔNG đẩy parser xuống Web Worker (N17):** parser JS sẽ là **bản lõi thứ hai** phải giữ khớp từng offset/tiền với PHP — phá nguyên tắc một nguồn sự thật và vô hiệu hoá golden fixture (lỗi tiền chỉ lộ ở trình duyệt). Chi phí thật nằm ở **dựng** artefact (115 dàn → pattern, bảng khoá, quy tắc tự học), không nằm ở chạy regex trên một tin ngắn → cache artefact giải quyết đúng chỗ.

**a) Artefact được cache** (PURE, dạng mảng/chuỗi thuần — serialize được):
| Artefact | Khoá cache | Vô hiệu khi |
|---|---|---|
| RuleParser đã biên dịch (pattern hợp nhất, bảng khoá tĩnh, bảng họ tham số) | `rp:{user_id}:{parser_dan_so.phien_ban}` | Ghi/xoá/khôi phục dàn → `phien_ban++` (khoá mới, khoá cũ tự hết hạn) |
| Quy tắc tự học đã sắp xếp + regex | `th:{user_id}:{bo_nho.phien_ban}` | `LearningService` ghi |
| Hệ số 3 tầng đã resolve theo khách | `hs:{user_id}:{khach_id}:{he_so.phien_ban}` | Sửa giá bất kỳ tầng |
| KQXS 27 giải của ngày | `kq:{ngay}` (dùng chung) | Scraper ghi ngày đó |

**b) `BoNhoDem` — cache nhiều tầng, tự chọn driver theo môi trường** (môi trường dev hiện tại **không có** APCu/Redis, OPcache đang tắt — đã kiểm 2026-11-02):
| Tầng | Driver | Khi dùng |
|---|---|---|
| L0 | Mảng `static` trong request | Luôn bật |
| L1 | **APCu** (bộ nhớ chung của PHP-FPM) | Nếu `ext-apcu` bật — nhanh nhất cho 1 máy chủ |
| L2 | **Redis/Memcached** | Nếu có nhiều máy chủ web (cấu hình `cfg('cache_dsn')`) |
| L3 | **Tệp PHP biên dịch sẵn** (`var/cache/rp_{user}_{v}.php` chứa `return [...]` qua `var_export`, ghi nguyên tử bằng `rename`) | Mặc định khi không có L1/L2 — **OPcache** giữ tệp này trong bộ nhớ dạng opcode, không parse lại |
- Bắt buộc ở production (checklist S9.3): bật **OPcache** (`opcache.enable=1`, `validate_timestamps=1`, `revalidate_freq=2`); APCu nếu hosting cho phép. `var/cache/` nằm ngoài docroot hoặc bị `.htaccess` chặn; `tools/don_cache.php` dọn khoá cũ.
- Cache chỉ chứa dữ liệu của chính tenant, khoá luôn có `user_id` → không rò chéo.

**c) Phía trình duyệt (không chứa logic nghiệp vụ):** debounce 300 ms · `AbortController` huỷ request cũ · bỏ qua nếu nội dung không đổi (so hash) · **chỉ tô lại vùng thay đổi** từ `marks` · request cuối cùng thắng (đánh số thứ tự) · nếu phản hồi > 400 ms thì hiện chấm "đang kiểm tra" thay vì chặn gõ.

**d) Mục tiêu đo (S2.7, S4.4):** cache trúng p95 **< 30 ms**/tin, cache trượt (dựng lại artefact) p95 < 150 ms; endpoint `phan_tich.php` p95 < 80 ms dưới 50 request đồng thời. Không đạt → hạ cấp theo thứ tự: tăng debounce 450 ms → bỏ bước tính tiền khi đang gõ (chỉ tính khi dừng 1 s hoặc khi lưu).

### 8.2 Đối soát & lưu (Ctrl+Enter)
Chặn theo thứ tự (Desktop): chưa có KQXS → chưa chọn khách → tin trống → còn lỗi cú pháp → **trùng bộ cược** (cùng ngày + khách + nguồn; so bộ `loai,so,tien_vnd` không phụ thuộc thứ tự; trả **mọi** phiên trùng) → modal Ghi đè/Huỷ.
`DoiSoatService::luu(ctx, …)`: `BEGIN` → khoá & kiểm hạn mức `su_dung_ngay` (`FOR UPDATE`; vượt `so_tin_ngay_toi_da` → 402) → (ghi đè: **xoá mềm** các phiên trùng, `ly_do_xoa='ghi_de'`) → `tao_msg_id` → ghi `tin_nhan_log` + các dòng `lich_su_doi_soat` với **snapshot tiền + `he_so_ap_dung_json`** (O2) → gán `thay_the_boi_msg_id` cho phiên bị ghi đè → tăng `su_dung_ngay` → `COMMIT` → đọc lại xác minh → **sau commit** `LearningService::ghiNhanLuotApDung()` + ghi log tin gốc. Trả về dữ liệu chế độ "Đã lưu" (mã phiên, đã ghi đè N phiên, trúng/trượt, tổng hợp). UI **xoá trắng ô nhập** sau khi lưu thành công.

### 8.3 Sửa phiên (N10)
Lịch sử → "Sửa" → `nhap_tin.php?sua=<msg_id>` nạp tin + khách + ngày, băng "Đang sửa phiên #id". Lưu → xác nhận → `DoiSoatService::suaPhien()` trong 1 transaction: **xoá mềm** phiên cũ (`ly_do_xoa='sua_phien'`) → đối soát lại theo giá **hiện tại** → ghi phiên mới (snapshot mới) → liên kết `thay_the_boi_msg_id` → commit → `LearningService::hocTuLanSuaTin(tinCu, tinMoi)` → thông báo "Đã ghi nhớ N quy tắc". Lịch sử hiển thị được chuỗi phiên cũ → mới.

### 8.4 Xoá phiên / xoá khách
- Xoá phiên: từ chế độ "Đã lưu" hoặc Lịch sử → xác nhận có mã phiên → `DoiSoatService::xoaPhien(ctx, $msgId, $lyDo)` = **xoá mềm** `tin_nhan_log` + các dòng `lich_su_doi_soat` (repo tự kiểm thuộc tenant).
- Xoá khách: `KhachHangRepo::xoaMem()` — khách biến khỏi danh sách chọn/Telegram; phiên cũ **giữ nguyên** và vẫn được cộng trong báo cáo các ngày cũ (nhãn "đã xoá"). Không có xoá vĩnh viễn trong app (O1).

### 8.5 Sửa dàn số
Form → `ajax/dan_so_kiem_tra.php` chạy `QuanLyDanSo::kiemTraDan()` (đủ luật §Phụ lục A-CP) → nếu hợp lệ chạy `anhHuong(bangCu, bangMoi, tinThat)` (tin chuẩn + tin thử tự sinh `de|lo <khoá> x10k` + tối đa N tin thật gần nhất của user) → modal → xác nhận → `ajax/dan_so_luu.php` (ghi + vô hiệu cache).

### 8.6 Đồng bộ Telegram (Q1 + O3)
Web (`ajax_khoi_dong('phan_tich')`, kiểm `cho_phep_telegram`) tạo job `fetch` → **trả `job_id` ngay, không chờ** → daemon systemd lấy job (`FOR UPDATE SKIP LOCKED`), lấy tin theo (khách, ngày, chiều, max), cập nhật `tien_do/tong` + heartbeat → ghi `telegram_inbox` → web poll `telegram_job.php` (1–2 s, backoff) hiển thị tiến trình [i/n] → xong thì parse từng tin (8.1, dùng cache) → danh sách master **preview** → "Ghi vào DB": modal nêu số tin cũ sẽ bị thay (chỉ khi có), số dòng vàng vẫn ghi, tối đa 10 dòng đỏ bị bỏ qua kèm lý do → transaction: **xoá mềm** phiên TELEGRAM cũ theo **ngày + khách** (`ly_do_xoa='dong_bo_lai'`) rồi ghi lại với snapshot; kiểm hạn mức tin/ngày; có tin lỗi phát sinh khi tính lại → huỷ toàn bộ. Worker mất heartbeat > 60 s → UI báo "Dịch vụ Telegram đang tạm ngừng" và không cho tạo job mới.

---

## 9. Lộ trình triển khai

> **Thực thi theo 14 phase P00–P13** tại [`phases/`](phases/README.md) — mỗi phase có thứ tự làm, test case (`Pxx-Tnn`/`Pxx-Mnn`) và **cổng nghiệm thu** riêng; không qua cổng thì không mở phase phụ thuộc. Bảng slice dưới đây là nguồn phạm vi; ánh xạ slice → phase ở `phases/README.md` §1. Slice bổ sung khi tách phase: **S1.0** hạ tầng test DB/HTTP (P01); fixture `doi_chieu.json` thêm vào S0.5 (P00).

**Quy ước slice:** ≤ 4 tệp mã/slice (không tính fixture, migration, `schema.sql`) · mỗi slice kết thúc bằng test xanh + nghiệm thu · cỡ: **S** ≤ 0,5 ngày, **M** 1–2 ngày, **L** 3–5 ngày (ước lượng cho 1 dev + Claude).

**Phụ thuộc giữa giai đoạn:**
```
G0b ──► G2 ──► G3 ──► G4 ──► G5 ──► G6
 │              ▲       │              │
G1 ─────────────┘       └──► G7 ───────┤
                                       ▼
                                  G8 ──► G9 (go-live)
```
G1 làm song song G0b/G2 nhưng **S1.2–S1.5 (Core bảo mật, TenantScope, Thuê bao) phải xong trước S4.1** — không repo nghiệp vụ nào được viết trước `TenantRepo`. G7 bắt đầu khi xong G4 và hạ tầng VPS/systemd (O3) đã sẵn sàng.

### G0 — Nền tảng ✅ (đã xong — v1 §8)
S0.1 runner · S0.2 golden exporter + 6 fixture · S0.3 helpers & Unicode.

### G0b — Bổ sung nền tảng cho engine Mới
| Slice | Nội dung | Tệp | Nghiệm thu | Cỡ |
|---|---|---|---|---|
| S0.4 ⚡ **THỰC THI NGAY** | Đóng băng BigWIN (O4 — đã chốt), theo đúng thứ tự R1: chủ repo **dọn dẹp + commit ~1.300 dòng đang treo** → test Python xanh → `git tag -a port-baseline` → chạy lại exporter **trên tag**; ghi hash vào meta fixture. Từ đây thay đổi Python = ngoài phạm vi (chỉ vào qua CR) | `fixtures/*.json` | `git describe` ra `port-baseline`; `meta.bigwin_commit` không còn `-dirty`; 19 test hiện có vẫn xanh | S |
| S0.5 | Mở rộng `export_golden.py` (được phép theo Q7): `cleaner_moi.json` (bung_dan_kep=False) · `rule_parser.json` (RuleParser + 115 dàn mặc định, đủ markup/lỗi) · `dan_so.json` (`tach_danh_sach_so`, `tach_khoa`, `kiem_tra_dan` ≥ 200 ca hợp lệ/không hợp lệ, `anh_huong`) · `tong_hop.json` (`tong_hop_doi_soat_bang_bieu`) · `doi_chieu.json` (`danh_dau_nghi_ngo`) · `pipeline.json` (tin → cleaner → RuleParser → tiền → dò trúng → tổng hợp, 3 khách hệ số khác nhau) | `scripts/export_golden.py` (BigWIN), 6 fixture | Sinh 2 lần trùng SHA-256; corpus ≥ 1.300 tin | M |

### G1 — Danh tính, Core bảo mật, TenantScope, Thuê bao (song song G0b/G2)
**Nguyên tắc:** G1 xây **toàn bộ hạ tầng an toàn trước** khi có bất kỳ bảng nghiệp vụ nào (G4) — repo nghiệp vụ đầu tiên sinh ra đã phải đứng trên `TenantRepo`.

| Slice | Nội dung | Tệp | Nghiệm thu | Cỡ |
|---|---|---|---|---|
| S1.1 | Migration `users_rbac_bao_mat` (users + `phai_doi_mat_khau`, `phien_dang_nhap`, `gioi_han_tan_suat`), chuyển `admins` → `users(role=admin)` | migration, `schema.sql`, `tools/create_admin.php` | Admin cũ đăng nhập bằng mật khẩu cũ | S |
| S1.2 | **Core bảo mật** (§5.3): `PhienBaoMat`, `Csrf`, `TieuDeBaoMat`, `GioiHanTanSuat` + rule `.claude/rules/security-core.md` | 4 tệp + rule + `tests/security_test.php` | Cookie đủ cờ; ID đổi sau đăng nhập; nhàn rỗi 30' → login; thu hồi phiên có hiệu lực ngay; CSRF sai / thiếu Origin → 403; GET đổi dữ liệu → 405 | M |
| S1.3 | `lib/auth.php` (`require_user/require_admin`, đăng nhập argon2id, khoá tăng dần, đổi mật khẩu thu hồi phiên khác), `login.php`, `logout.php`, **`AjaxGateway`** (`ajax_khoi_dong`) | 4 tệp | User bị khoá → 401 ngay request kế; endpoint thử nghiệm qua gateway trả đúng bảng mã lỗi §5.3c | M |
| S1.4 | **TenantScope** (§5.4): `TenantContext`, `TenantRepo` (CRUD + `truyVan` với `{tenant:x}` + quét SQL fail-closed), `SharedRepo` + `tests/tenant_scan_test.php` + khung `tests/idor_test.php` | 3 tệp + 2 test | Repo mẫu thiếu `{tenant:x}` → `LoiCoLapTenant`; quét mã báo vi phạm khi cố ý gọi `db()` trong `app/` | M |
| S1.5 | **Bản quyền SaaS** (§1.4): migration `thue_bao`, `GiayPhepGuard` (tính trạng thái từ ngày, ma trận quyền theo `CheDo`), `ThueBaoRepo`, `tools/cap_nhat_thue_bao.php` (cron 00:05: ghi lịch sử chuyển trạng thái, nhắc gia hạn) | migration + 3 tệp + test | Ma trận §1.4 đúng 100% (test theo ngày giả lập: còn hạn / ân hạn / hết hạn / tạm khoá) | M |
| S1.6 | Khung app: `_layout.php` (topbar: ngày theo **N8**, đồng hồ, avatar menu, modal Đổi mật khẩu, **băng thuê bao**; sidebar 6 page; Alt+1…7, `?`), `app.css`, `app.js` (`goiApi()` duy nhất gắn CSRF), `NgayLamViec.php` | 4 tệp | 17:00 → D-1; 18:30 → D; user không vào `admin/*`; hết hạn → nút phân tích/lưu ẩn và endpoint trả 402 | M |
| S1.7 | Admin: tab Tài khoản đại lý (tạo/khoá/mở **kiểm giấy phép nền tảng**, đặt lại mật khẩu → buộc đổi, đăng xuất mọi nơi, đổi role; luôn còn ≥ 1 admin) + tab **Gói & Thuê bao** (CRUD gói, gia hạn, đổi gói, tạm khoá, xem lịch sử) | `admin/users.php`, `admin/thue_bao.php`, `UserRepo.php` | Không tự khoá/hạ quyền chính mình; vượt `so_dai_ly_toi_da` → không tạo được; mọi thao tác thuê bao có dòng `thue_bao_lich_su` | L |

### G2 — Lõi cú pháp (PURE)
| Slice | Nội dung | Tệp | Nghiệm thu | Cỡ |
|---|---|---|---|---|
| S2.1 | `SequenceMatcher` (không autojunk) | 1 + test | 100% `seqmatch.json` | M |
| S2.2 | `HeuristicCleaner` (cả 2 chế độ `bungDanKep`) | 1 + test | 100% `cleaner.json` **và** `cleaner_moi.json` | L |
| S2.3 | `MessageParser` — port đầy đủ (dùng `parser.json` làm lưới an toàn cho đường chạy chung); thiết kế **hook** giống Python: `phanTich(tin, pattern, cheCum, demTokenSo, xuLyBet, canhBaoGap)` | 1 + test | 100% `parser.json` | L |
| S2.4 | `DanSoMacDinh` (sinh 115 dàn) + `RuleParser` (dùng lại hook S2.3; `_bo_dau` giữ độ dài; khoá có/không dấu theo `_duoc_bo_dau`) + `ParserService` (1 engine) | 3 + test | 100% `rule_parser.json`; seed khớp `dan_so_mac_dinh.py` (115 dàn, đúng thứ tự số) | L |
| S2.5 | `QuanLyDanSo` (`tachDanhSachSo`, `tachKhoa`, `kiemTraDan`, `anhHuong`, `thayDan`, `boDan`) | 1 + test | 100% `dan_so.json` | M |
| S2.6 | `DiffAnalyzer` + `DoiChieuTin::danhDauNghiNgo` | 2 + test | 100% fixture tương ứng (bổ sung vào S0.5 nếu thiếu) | M |
| S2.7 | **`BoNhoDem`** (L0 static · L1 APCu · L2 Redis/Memcached · L3 tệp PHP + OPcache, §8.1b) + tách `RuleParser` thành "biên dịch artefact" và "chạy trên artefact" + script đo trên 1.300 tin | `BoNhoDem.php` + sửa `RuleParser.php`, `ParserService.php` + script đo | Kết quả parse **giống hệt** khi cache trúng/trượt (chạy lại `rule_parser.json` ở cả 2 chế độ); cache trúng p95 < 30 ms, trượt p95 < 150 ms; chạy được khi không có APCu/Redis | M |

### G3 — Tiền & KQXS
| Slice | Nội dung | Tệp | Nghiệm thu | Cỡ |
|---|---|---|---|---|
| S3.1 | `TinhTien` (v1 §4.2 nguyên văn), `DoTrung`, `TongHop` (N2/N3) | 3 + test | 100% `tinh_tien.json`, `do_trung.json`, `tong_hop.json` | M |
| S3.2 | Bảng KQ + `Scraper` (cURL + DOMXPath + 2 fallback, đủ 27 giải mới ghi) + `tools/scrape_xsmb.php` + cron (`*/5 18-19`, 06:00 bù) + `scraper_log` | migration + 3 tệp | Lấy đủ 27 giải 3 ngày mẫu; chạy đồng thời 2 lần không ghi đôi (`GET_LOCK`) | M |
| S3.3 | Page **KQXS** + `ajax/kq_lay.php` (chặn ngày tương lai & hôm nay trước 18:30; rate-limit) + `KetQuaService` | 3 tệp | Phụ lục A-KQ | M |

### G4 — Khách hàng & Đối soát (giá trị cốt lõi)
| Slice | Nội dung | Tệp | Nghiệm thu | Cỡ |
|---|---|---|---|---|
| S4.1 | Migration tenant (user_id ở mọi bảng con, FK kép, cột sinh `…_hoat_dong`, trigger chặn UPDATE cột tiền, **không CASCADE**) + `KhachHangRepo extends TenantRepo` (tạo/sửa/**xoá mềm**, chặn trùng tên không phân biệt hoa thường, chặn trùng Telegram ID, kiểm `so_khach_toi_da`) + `HeSoRepo` (resolve 3 tầng, 21 khoá, `phien_ban` cho cache) | migration + 2 repo + test | `idor_test` xanh cho 2 repo; tạo lại khách trùng tên **sau khi xoá mềm** được; FK kép chặn gắn chéo tenant ở DB; MySQL/MariaDB production hỗ trợ cột sinh + UNIQUE | M |
| S4.2 | Page **Khách hàng & Bảng giá** + `ajax/khach_luu.php`, `khach_xoa.php`, `he_so_luu.php` | 4 tệp | Phụ lục A-KH | L |
| S4.3 | `DoiSoatService` (`luu`, `timTrung`, `ghiDe`, `xoaPhien`, `suaPhien` — mọi "xoá" là xoá mềm + `thay_the_boi_msg_id`) + `TinNhanRepo` + `DoiSoatRepo` (`COT_BAT_BIEN`) + đếm `su_dung_ngay` | 3 + test | **E2E parity 100% `pipeline.json`**; trùng bộ cược đúng phạm vi §8.2; rollback khi lỗi giữa chừng; UPDATE cột tiền bị chặn ở cả repo lẫn trigger; đổi giá sau khi lưu → tổng chốt sổ **không đổi**; vượt hạn mức tin/ngày → 402 | L |
| S4.4 | Page **Nhập tin** (3 chế độ, editor tô màu offset ký tự, chẩn đoán + nhảy tới, chọn khách `/` ↑↓ Enter, modal trùng, xoá ô sau lưu) + `ajax/phan_tich.php`, `ajax/doi_soat.php` + `assets/editor.js`, `bang_tong_hop.js` | 4 tệp (+1 asset) | Phụ lục A-NT | L |

### G5 — Cú pháp & Tự học
| Slice | Nội dung | Tệp | Nghiệm thu | Cỡ |
|---|---|---|---|---|
| S5.1 | `LearningService` (cổng ghi **duy nhất** `bo_nho_sua_tin`; 5 guard; shadow test 16 tin trên bảng dàn của user; giới hạn theo user) + `BoNhoRepo` | 2 + test | 100% `learning.json`; grep: không nơi nào khác INSERT/UPDATE `bo_nho_sua_tin` | L |
| S5.2 | Migration dàn số + `DanSoRepo` (copy bộ gốc khi tạo user, khôi phục theo O6) + `tools/seed_dan_so.php` | migration + 2 tệp | User mới có đủ 115 dàn | M |
| S5.3 | Page **Cú pháp** — tab Dàn số + `ajax/dan_so_kiem_tra.php`, `dan_so_luu.php`, `dan_so_khoi_phuc.php` | 4 tệp | Phụ lục A-CP | L |
| S5.4 | Tab **Bộ nhớ tự học** (thêm tay qua LearningService, xoá nhiều có xác nhận) + tab **Hướng dẫn & Quy tắc** + `ajax/tu_hoc_*.php` | 3 tệp | Phụ lục A-TH | M |

### G6 — Lịch sử, Chốt sổ, Báo cáo
| Slice | Nội dung | Tệp | Nghiệm thu | Cỡ |
|---|---|---|---|---|
| S6.1 | Tab **Lịch sử tin**: lọc ngày (topbar) + khách (Tất cả) + trạng thái; cột STT/khách/giờ/nguồn/tin/trạng thái/tiền; Sửa (→ S4.4 chế độ sửa) / Xoá | `lich_su.php`, `ajax/lich_su.php` | Phụ lục A-LS | M |
| S6.2 | Tab **Chốt sổ**: KPI ngày, bảng theo khách (Thành tiền Thu/Trả), mở rộng = bảng tổng hợp + phiếu chốt (copy) | `ajax/chot_so.php`, `assets/lich_su.js` | Tổng khớp `pipeline.json` | M |
| S6.3 | `in_bao_cao.php` + `print.css`: **Tổng hợp** (khách × loại) và **Chi tiết** (từng tin), quy ước N2 | 2 tệp | In ra PDF qua trình duyệt đúng 1 quy ước dấu | M |

### G7 — Telegram (O3 đã chốt: daemon systemd)
| Slice | Nội dung | Tệp | Nghiệm thu | Cỡ |
|---|---|---|---|---|
| S7.1 | Migration telegram (+ `telegram_worker_trang_thai`) + **daemon Python** (`worker.py`: vòng lặp lấy job `FOR UPDATE SKIP LOCKED`, job `login_code`/`login_verify`/`fetch`, cập nhật `tien_do/tong`, heartbeat 15 s, dừng êm khi nhận SIGTERM; dedup `UNIQUE(user_id,peer_id,telegram_msg_id)`; session mã hoá bằng khoá ngoài repo; DB user quyền tối thiểu chỉ trên `telegram_*`; kiểm thuê bao `cho_phep_telegram` trước khi chạy job) + `bigwin-telegram.service` (systemd: `Restart=always`, user riêng, `EnvironmentFile`) + `README.md` vận hành | migration + `workers/telegram/*` | `systemctl restart` không mất job (job đang chạy quay về `cho`); web request không bao giờ chờ Telethon (đo: tạo job < 50 ms); worker không parse/tính tiền | L |
| S7.2 | `TelegramService` + modal Kết nối (2FA **chỉ hiện khi** worker trả `2fa_required`) + modal Cấu hình (API ID, API Hash, Session — **không** Gemini) + `ajax/telegram_job.php` | 3 tệp | Login được; session lưu mã hoá | M |
| S7.3 | Page **Telegram**: điều kiện trước đồng bộ (khách có Telegram ID, Max nguyên > 0, đã login, có KQ), master 4 trạng thái (OK / NGHI NGỜ / PARSE_ERROR / LỖI), dock (Parse thử, Lưu tin & cập nhật — **cho lưu cả khi còn lỗi**, chạy học), khoá giao diện khi đang chạy | `telegram.php`, `assets/telegram.js`, `ajax/telegram_inbox.php` | Phụ lục A-TG | L |
| S7.4 | Ghi DB (§8.6) + modal tổng kết đúng số (thành công = OK + nghi ngờ; bỏ qua = lỗi cú pháp; lỗi = exception) + nhật ký thu gọn + tiến trình [i/n] | `ajax/telegram_ghi.php` (+ sửa S7.3) | Phụ lục A-TG | M |

### G8 — Quản trị & dữ liệu
| Slice | Nội dung | Tệp | Nghiệm thu | Cỡ |
|---|---|---|---|---|
| S8.1 | Tab **Thống kê** (theo ngày/đại lý: số tin, tổng ghi, thành tiền **từ snapshot**, mức dùng hạn mức, ngày hết hạn thuê bao — không lộ nội dung tin) qua `ThongKeRepo` (truy vấn chéo tenant duy nhất được phép) | `admin/thong_ke.php`, `ThongKeRepo.php` | Không truy vấn nào trả `noi_dung`; `tenant_scan_test` chỉ cho phép `ThongKeRepo` | M |
| S8.2 | Quyền admin trên page có sẵn: sửa dòng "Hệ thống" (page 4) và bộ dàn gốc (page 6) | sửa 2 page + 2 ajax | User thường gọi thẳng endpoint → 403 | S |
| S8.3 | `tools/import_sqlite.php` (`--dry-run`, gán `user_id`, đổi `dd-mm-yyyy`→DATE, làm sạch FK mồ côi, bỏ hệ số trùng mặc định) — theo O7 | 1 tệp | Số dòng + tổng tiền/ngày trước/sau khớp | M |

### G9 — Go-live
| Slice | Nội dung | Nghiệm thu |
|---|---|---|
| S9.1 | Rà soát bảo mật: `/security-review`, skill `check-idor-for-dev` trên `app/**` + `repo/**`, `/audit-api` | Không còn lỗi Cao |
| S9.2 | UAT song song Desktop: 3 đại lý thật × 3 ngày, cùng tin nhập cả hai bên | Lệch tiền = 0 đồng |
| S9.3 | Triển khai: máy chủ Linux có systemd (O3); docroot `src/webapp/`; HTTPS + `cookie_secure`; **OPcache bật** (+ APCu nếu có) (§8.1b); CSS Tailwind build sẵn + Alpine CSP build → bật CSP chặt (N13); cron scraper + `cap_nhat_thue_bao.php` + `don_cache.php`; `bigwin-telegram.service` enable; backup MySQL hằng ngày; `che_do_debug` tắt; giấy phép nền tảng + gói được cấu hình | Checklist triển khai ký duyệt |
| S9.4 | Kiểm thử tải: 50 phiên gõ tin đồng thời trên `phan_tich.php` | p95 < 80 ms, không lỗi 5xx |

---

## 10. Quy tắc xuyên suốt (Definition of Done chung cho MỌI slice)

1. `php src/webapp/tools/test.php` xanh 100%; fixture lệch = bug PHP, **không sửa fixture**.
2. Lõi `lib/bigwin/*.php` PURE theo `.claude/rules/bigwin-core.md` (`bw_re`, `bw_finditer`, `mb_*`, `PHP_ROUND_HALF_EVEN`, `SORT_STRING`, cấm `array_unique` làm khoá số).
3. **TenantScope:** repo bảng tenant **bắt buộc** `extends TenantRepo`; không viết tay `user_id = ?`, không nhận `$userId` từ ngoài — dùng `TenantContext`; SQL tự viết đánh dấu `{tenant:x}`; `tenant_scan_test` + `idor_test` xanh (§5.4, `.claude/rules/tenant-isolation.md`).
4. **Core bảo mật:** mọi `app/ajax/*.php` mở đầu bằng `ajax_khoi_dong(cheDo)` (không ngoại lệ); mọi request đổi trạng thái qua `POST` + CSRF + Origin; page JS chỉ gọi server qua `goiApi()`; mọi output `h()`; mọi SQL prepared (`.claude/rules/security-core.md`).
4b. **Bản quyền:** mọi endpoint khai báo đúng `CheDo` (`doc`/`ghi`/`phan_tich`); thao tác tạo dữ liệu đếm hạn mức trong cùng transaction.
4c. **Bất biến tiền:** không có câu lệnh `DELETE` trên `khach_hang`, `tin_nhan_log`, `lich_su_doi_soat`; không UPDATE cột tiền snapshot; báo cáo chỉ cộng snapshot.
5. Thay đổi DB = migration mới + `schema.sql` (`/db-migration`); không sửa migration cũ.
6. Không đụng `/api/*`, `lib/api.php`, `lib/crypto.php`; nếu có đụng → `/audit-api` trước commit.
7. Giờ chỉ qua `now_vn()`/`today_vn()`; DB `NOW()`; cấm `UTC_TIMESTAMP()`.
8. Tiền hiển thị theo N2; bảng tiền chỉ qua `bang_tong_hop.js` (N3) — không vẽ bảng tiền thứ hai.
9. UI bám `docs/ui-preview/`; khác preview phải ghi lý do vào nhật ký §14.
10. `/healthcheck` xanh; ghi 1 dòng vào nhật ký §14.

---

## 11. Chiến lược kiểm thử

| Tầng | Công cụ | Phạm vi |
|---|---|---|
| Parity lõi | Golden fixture (12 tệp sau S0.5) + `tools/test.php` | Cleaner, parser, rule parser, dàn số, tiền, dò trúng, tổng hợp, tự học, seqmatch |
| E2E nghiệp vụ | `pipeline.json` | Tin → tiền cuối cùng theo 3 bộ hệ số |
| Cô lập tenant | `tests/tenant_scan_test.php` (quét mã tĩnh) · `tests/idor_test.php` (2 tenant giả, gọi **mọi** method public của mọi repo + mọi endpoint `ajax/*`) · `LoiCoLapTenant` lúc chạy | Đọc/sửa/xoá chéo → rỗng/404; SQL thiếu `{tenant:x}` → ném lỗi |
| Bảo mật | `tests/security_test.php` | Cờ cookie, đổi session ID, timeout nhàn rỗi/tuyệt đối, thu hồi phiên, CSRF thiếu/sai/khác Origin, GET đổi dữ liệu, rate limit đăng nhập, mọi tệp `ajax/*` gọi `ajax_khoi_dong` (quét mã) |
| Bản quyền | `tests/giay_phep_test.php` (ngày giả lập) | Ma trận §1.4 · hạn mức khách/tin/ngày · giấy phép nền tảng · lịch sử thuê bao append-only |
| Bất biến tiền | `tests/bat_bien_test.php` | Xoá = xoá mềm; UPDATE cột tiền bị chặn (repo + trigger); đổi giá không làm đổi chốt sổ cũ |
| Cache | chạy `rule_parser.json` với cache trúng / trượt / tắt | Kết quả giống hệt nhau; vô hiệu đúng khi `phien_ban` đổi |
| Giao dịch | test rollback | Lỗi giữa `luu/suaPhien/ghi Telegram` → DB như trước |
| UI | Checklist Phụ lục A, chạy tay trên trình duyệt (desktop + 375 px) | 6 page + đăng nhập |
| Song song Desktop | S9.2 | Dữ liệu thật |

---

## 12. Rủi ro & giảm thiểu

| # | Rủi ro | Mức | Giảm thiểu |
|---|---|---|---|
| R1 | BigWIN tiếp tục thay đổi / đang có ~1.300 dòng chưa commit trong `core/`, `utils/` → port trên nền không ổn định; fixture hiện có sinh từ working tree "dirty" (`3c5fab3-dirty`) nên không tái lập được | **Cao** | O4 đã chốt. **Chủ repo gốc BẮT BUỘC** làm theo đúng thứ tự: (1) rà soát, dọn dẹp và **commit toàn bộ ~1.300 dòng đang treo** (không để thay đổi chưa commit, không stash bỏ dở); (2) chạy test Python của BigWIN xanh; (3) gắn tag `port-baseline` lên commit đó; (4) mới **xuất Golden Fixture** từ tag (`git status` sạch, `meta.bigwin_commit` không có `-dirty`). Làm sai thứ tự (xuất fixture hoặc gắn tag khi còn code treo) = S0.4 không đạt. Thay đổi sau tag = ngoài phạm vi, chỉ vào qua CR + chạy lại exporter |
| R2 | `RuleParser` phụ thuộc chi tiết regex `_PATTERN` của MessageParser (Python có kiểm tra `neo` xuất hiện đúng 2 lần) | Cao | Port nguyên chuỗi pattern; thêm test PHP khẳng định điểm neo như Python `rule_parser.py:62-66` |
| R3 | Lệch regex Unicode / offset byte | Cao | `bw_re`, `bw_finditer`; fixture có markup |
| R4 | Nghẽn khi nhiều người gõ cùng lúc (debounce 300 ms → nhiều request parse) | TB | Cache artefact nhiều tầng + OPcache (§8.1, S2.7); tối ưu phía client; kiểm thử tải S9.4; phương án hạ cấp đã định nghĩa |
| R5 | Rò dữ liệu giữa đại lý (IDOR) | Cao | TenantScope tự chèn `user_id` + quét SQL fail-closed + FK kép ở DB + `tenant_scan_test` + `idor_test` + S9.1 |
| R6 | Hạ tầng production không có máy chủ chạy systemd | Cao | O3 đã chốt daemon systemd → **bắt buộc VPS Linux**; xác nhận hạ tầng trước G7; G7 tách rời, giai đoạn khác không phụ thuộc |
| R9 | CSP chặt xung đột Tailwind CDN / Alpine bản thường (`unsafe-eval`) | TB | N13: dev dùng CSP nới; S9.3 chuyển CSS build sẵn + Alpine CSP build rồi mới siết |
| R10 | Xoá mềm làm hỏng ràng buộc UNIQUE / báo cáo đếm nhầm bản đã xoá | TB | Cột sinh `…_hoat_dong` (§7.1); `TenantRepo` mặc định lọc `deleted_at IS NULL`; báo cáo cần bản đã xoá phải dùng `{tenant_ca_xoa:x}` tường minh |
| R11 | Cache trả artefact cũ sau khi sửa dàn → phân tích sai | TB | Khoá cache gắn `phien_ban` tăng trong cùng transaction ghi; test cache trúng/trượt cho kết quả giống hệt |
| R12 | Khoá mõm nhầm khi thuê bao còn hạn (lệch múi giờ, cron trễ) | TB | Trạng thái tính trực tiếp từ ngày bằng `today_vn()`, không phụ thuộc cron; ân hạn 3 ngày |
| R7 | Lệch tiền do ép kiểu/làm tròn | TB | v1 §4.2 đã xác minh; fixture 4.800 ca + E2E |
| R8 | Hiểu sai nghiệp vụ do preview dùng mock | TB | Phụ lục A là chuẩn nghiệm thu, không lấy hành vi mock làm chuẩn |

---

## 13. Lỗi Desktop đã biết — KHÔNG port

| # | Lỗi | Vị trí Desktop | Hành vi web |
|---|---|---|---|
| L1 | PDF tổng hợp ghi "PHẢI TRẢ = trúng − ghi + CK" (ngược dấu lưới) và luôn ghi nhãn PHẢI TRẢ | `ui/app.py:1996` | Theo N2 |
| L2 | Thống kê nhanh cộng `SUM(tien_vnd)` → Lô cộng theo điểm | `ui/app.py:985-1018` | Cộng `tien_ghi` |
| L3 | Lịch sử "TẤT CẢ" tính tiền ghi với `khach_hang_id=None` → giá mặc định | `database.py:4343` | Dùng snapshot `tien_ghi` của từng dòng |
| L4 | Lưu cấu hình giá dừng giữa chừng khi gặp ô lỗi → lưu dở | `customer_forms.py:335-346` | Kiểm tra cả form trước, ghi trong 1 transaction |
| L5 | Khách mới được chép cứng 21 giá mặc định → tầng trên không còn tác dụng | `database.py:4053-4060` | Chỉ lưu giá khác tầng trên |
| L6 | Gợi ý "1 lit ➔ 100k" bị chính hàng rào tự học từ chối | `rules_forms.py` | Sửa câu gợi ý |

---

## 14. Nhật ký thực thi
| Ngày | Slice | Kết quả | Ghi chú |
|---|---|---|---|
| 2026-10-30 | S0.1–S0.3 | ✅ | Xem v1 §8 |
| 2026-11-02 | — | Lập kế hoạch v2 | Thiết kế UI preview đã duyệt |
| 2026-11-02 | — | Refactor **v2.1** | Chốt O1 (xoá mềm), O2 (snapshot bất biến), O3 (daemon systemd), O4 (tag `port-baseline` — chờ chủ repo BigWIN thực hiện); thêm bản quyền SaaS (§1.4), Core bảo mật (§5.3), TenantScope (§5.4), cache parser (§8.1); G1 mở rộng S1.1–S1.7; cập nhật `.claude/rules/tenant-isolation.md` |
| 2026-11-02 | — | Hoàn thiện v2.1 | Chốt O8 (1 User = 1 Đại lý, khoá cô lập `user_id`); R1/S0.4: bắt buộc commit ~1.300 dòng treo trước khi gắn tag và xuất fixture; câu chữ Xoá khách "Ẩn khách hàng, bảo toàn lịch sử đối soát cũ" (Phụ lục A-KH + preview) |
| 2026-11-02 | — | Tách phase | Chốt O5; tách lộ trình thành 14 phase `phases/P00…P13` có cổng nghiệm thu; thêm S1.0 (hạ tầng test DB/HTTP) và fixture `doi_chieu.json` |
| 2026-11-03 | S1.0–S1.3 (P01) | ✅ **P01 PASS** (chủ dự án ký 2026-11-03) | Hồi quy 80/80, `/healthcheck` OK, `/audit-api` AN TOÀN, checklist M01–M06 đạt; chi tiết, khác biệt B01–B18: `phases/TASK_LOG.md` mục P01. Mở khoá P02 |
| 2026-11-03 | S1.4–S1.5 (P02) | ✅ **P02 PASS** (ký theo uỷ quyền chủ dự án) | Hồi quy 138/138, `/healthcheck` OK, `/audit-api` AN TOÀN, M01/M02 đạt; §5.4 cập nhật "bảng lọc sẵn" (Q-P02-8); migration P01 + P02 đã chạy lên DB thật; chi tiết B01–B10: `phases/TASK_LOG.md` mục P02. Mở khoá P03 |
| 2026-11-03 | S1.6–S1.7 (P03) | ✅ **P03 PASS** (chủ dự án test tay; ký theo uỷ quyền) | Khung app 6 page + quản trị tài khoản / gói & thuê bao; `admin/_auth.php` dùng phiên chung + bảng `users` (bỏ `bigwin_admin`); hồi quy 183/183, `/healthcheck` OK, `/audit-api` AN TOÀN; QĐ1–QĐ7, B01–B09: `phases/TASK_LOG.md` mục P03. Mở khoá P06 (cùng P05) |
| 2026-11-03 | P03 — chốt sổ | ✅ **P03 PASS** (Principal Architect) | Áp PRG cho form quản trị (P03-B09 chốt sớm) + `/simplify` (CSRF form 1 điểm kiểm, `dong_bo_admins`, giấy phép nền tảng khoá 1 lần, `giaHanThem`, test 54s → 16s); hồi quy 186/186. Nợ còn mở: P03-B05 (P13), P01-B07 (P12/P13). Luồng B tạm dừng — chờ Luồng A (P00, P04, P05) hợp nhất tại P06 |

---

## Phụ lục A — Checklist nghiệm thu UI ↔ logic Desktop

Mỗi mục là điều kiện "xong" của slice ghi trong ngoặc. Nguồn: rà soát preview so với Desktop ngày 2026-11-02.

**A-NT · Nhập tin (S4.4)**
- [ ] Ngày mặc định theo 18:30 (N8).
- [ ] Trùng bộ cược: phạm vi ngày + khách + nguồn; liệt kê mọi phiên trùng (mã phiên, giờ, số dòng); chỉ Ghi đè / Huỷ (N7).
- [ ] Lưu thành công → xoá trắng ô nhập; hiện chế độ "Đã lưu" (mã phiên, "đã ghi đè N phiên", trúng/trượt, Xoá phiên có xác nhận).
- [ ] Lưu khi tin trống → "Vui lòng nhập nội dung".
- [ ] Chưa chọn khách khi lưu → mở chọn khách; gõ tên mới → tạo khách (không trùng tên, không phân biệt hoa thường).
- [ ] Tạm tính khi chưa chọn khách → cảnh báo "tạm tính theo giá mặc định".
- [ ] Chưa có KQXS → bảng tổng hợp ghi "tạm tính — mọi số đang tính là trượt".
- [ ] Tin còn lỗi → không cho lưu; tiền chỉ hiển thị phần đã hiểu kèm ghi chú.
- [ ] Sau lưu: cộng `hit_count` quy tắc đã dùng; lưu cả tin gốc lẫn bản chuẩn hoá.
- [ ] Chế độ "Đang sửa phiên" (N10) → ghi đè + học quy tắc + báo số quy tắc đã nhớ.

**A-TG · Telegram (S7.3–S7.4)**
- [ ] Thành tiền và nhãn theo N2 ở dock, thanh tổng, modal tổng kết, modal tổng hợp.
- [ ] Ghi DB xoá theo **ngày + khách**, trong 1 transaction; tin lỗi khi tính lại → không ghi gì.
- [ ] Modal xác nhận ghi DB có số liệu thật (tin cũ sẽ thay, dòng vàng, ≤ 10 dòng đỏ + lý do).
- [ ] Tổng kết: thành công = OK + nghi ngờ; bỏ qua = lỗi cú pháp; lỗi = exception.
- [ ] 4 trạng thái (thêm LỖI); tooltip lý do ở cột trạng thái.
- [ ] Lưu tin ở dock được phép khi còn lỗi (giữ PARSE_ERROR), chặn khi rỗng, luôn chạy học.
- [ ] Bộ lọc "chỉ tin cần chú ý": băng cảnh báo tách số lỗi/nghi ngờ; tự tắt khi hết.
- [ ] Điều kiện trước đồng bộ đủ 5 mục; khoá toàn bộ input khi đang chạy.
- [ ] 2FA chỉ hiện khi Telegram yêu cầu; 4 trạng thái đăng nhập.
- [ ] Không có ô Gemini Key.

**A-KQ · KQXS (S3.3)**
- [ ] Nhãn trạng thái đếm số giải thật ("n giải"), không ghi cứng.
- [ ] Chặn lấy KQ ngày tương lai / hôm nay trước 18:30; có nút "lấy lại" khi KQ thiếu giải.
- [ ] Bảng đầu–đuôi có cột tổng số nháy theo đầu; panel 3 ngày trước.

**A-KH · Khách hàng & Bảng giá (S4.2)**
- [ ] Đủ 21 khoá; CK Xiên tách LX2/LX3/LX4.
- [ ] Ô giá sai → báo lỗi, trả giá cũ (không xoá âm thầm); CK 0–100.
- [ ] Hồ sơ: tên bắt buộc, không trùng; Telegram ID là số, không trùng.
- [ ] Xoá khách = **xoá mềm** (O1). Modal ghi rõ **"Ẩn khách hàng, bảo toàn lịch sử đối soát cũ"** (KHÔNG dùng "xoá vĩnh viễn kèm lịch sử"); nút xác nhận là "Ẩn khách hàng"; xác nhận bằng cách gõ đúng tên; khách biến khỏi danh sách chọn / lưới giá / Telegram; phiên cũ không bị đụng tới và báo cáo các ngày cũ vẫn có số (nhãn "đã xoá"); tạo lại khách cùng tên được. *Đã cập nhật trong preview: modal `kh_xoa`, vùng nguy hiểm của drawer, `xoaKhach()` trong `docs/ui-preview/app.js`.*
- [ ] Đổi giá không làm đổi sổ đã chốt (O2).
- [ ] Chạm `so_khach_toi_da` → nút Thêm khách bị khoá kèm lý do.

**A-BM · Bảo mật (S1.2–S1.4, kiểm lại ở S9.1)**
- [ ] Cookie `bigwin_web`: HttpOnly, Secure (HTTPS), SameSite=Lax; ID đổi sau đăng nhập/đổi mật khẩu/đổi role.
- [ ] Nhàn rỗi 30 phút / tuyệt đối 12 giờ → về trang đăng nhập; khoá tài khoản hoặc "đăng xuất mọi nơi" có hiệu lực ở request kế.
- [ ] Mọi `app/ajax/*.php` qua `ajax_khoi_dong`; thiếu/sai CSRF hoặc khác Origin → 403; GET đổi dữ liệu → 405; lỗi không lộ stack trace.
- [ ] Đăng nhập sai 5 lần → khoá 15 phút; thông báo lỗi chung.
- [ ] Không endpoint nào trả dữ liệu tenant khác (`idor_test`); không truy vấn tenant nào ngoài `TenantRepo` (`tenant_scan_test`).

**A-GP · Bản quyền SaaS (S1.5, S1.7)**
- [ ] Ma trận trạng thái §1.4 đúng ở UI (băng topbar, nút bị ẩn) **và** ở server (402).
- [ ] Hết hạn: vẫn đăng nhập, xem lịch sử, chốt sổ, in; không phân tích/lưu/đồng bộ/sửa cấu hình.
- [ ] Hạn mức số khách, số tin/ngày, Telegram theo gói; giấy phép nền tảng chặn tạo/mở khoá đại lý vượt số lượng.
- [ ] Mọi thao tác gói/thuê bao có dòng `thue_bao_lich_su`.

**A-LS · Lịch sử & Chốt sổ (S6.1–S6.3)**
- [ ] Bảng tổng hợp 7 cột theo N3; Xiên 2/3/4 tách dòng; Lô hiện điểm.
- [ ] Lọc khách (Tất cả) + trạng thái OK/Cảnh báo/Lỗi; xoá có xác nhận; sửa → chế độ sửa.
- [ ] In Tổng hợp / Chi tiết đúng N2.

**A-TH · Tự học (S5.4)**
- [ ] So số hai vế theo **danh sách `\d+` đúng thứ tự** (không nối chuỗi); cụm gốc phải có chữ.
- [ ] Đủ 16 từ khoá cấm; độ dài gốc 2–60, thay ≤ 60; chặn hai vế giống nhau và đệ quy tự bọc.
- [ ] Shadow test 16 tin chuẩn thật; chuẩn hoá ký tự ẩn trước khi kiểm.
- [ ] Xoá nhiều dòng + xác nhận "không hoàn tác"; trạng thái rỗng; lý do từ chối theo từng hàng rào.

**A-CP · Cú pháp — Dàn số (S5.3)** (theo `quan_ly_dan_so.kiem_tra_dan`)
- [ ] Tên không trùng; nhóm hợp lệ; Bộ/Tổng/Chia chỉ áp ĐỀ.
- [ ] Khoá tách bằng **dấu phẩy hoặc xuống dòng**; chuẩn hoá NFC + chữ thường + gộp khoảng trắng.
- [ ] Khoá trùng `TU_KHOA_HE_THONG`; trùng khoá dàn khác (**kể cả dàn đang tắt**).
- [ ] Nhóm họ tham số: khoá dạng `<tiền tố> <số>` với tiền tố đúng `TIEN_TO_HO`; trùng tham số (`bo 2` ≡ `bo 02`).
- [ ] Dàn tĩnh: khoá phải có chữ cái; không chứa từ trong `TU_CAM_TRONG_CUM`; không có dạng tiền; không chứa `bộ`; không có dạng `<tiền tố họ> <số>`.
- [ ] Số: đủ 2 chữ số; **trùng số là lỗi** (nút "Lọc trùng & sắp xếp" để sửa); giữ thứ tự nhập khi lưu.
- [ ] Đo ảnh hưởng gồm tin thử tự sinh `de|lo <khoá> x10k` của mọi dàn thay đổi + tin thật gần nhất.
- [ ] Dữ liệu seed đúng dạng khoá thật (`c 8`, `chia het cho 3`, `đit 7`…), không dùng khoá mock sai của preview.

---

## Phụ lục B — Endpoint AJAX (`app/ajax/`)

Mọi endpoint: dòng đầu `ajax_khoi_dong(CheDo)` (§5.3c) · JSON vào/ra · POST + CSRF + Origin, trừ khi ghi GET · lỗi theo bảng mã §5.3c. Cột **Chế độ** quyết định `GiayPhepGuard` (§1.4).

| Endpoint | Slice | Chế độ | Vào | Ra |
|---|---|---|---|---|
| `kq_lay.php` | S3.3 | `ghi` | `ngay` | trạng thái, số giải |
| `phan_tich.php` | S4.4 | `phan_tich` | `tin, khach_id?, ngay` | marks, loi, dich, bets, tong_hop, can_save |
| `doi_soat.php` | S4.4 | `phan_tich` | `tin, khach_id, ngay, ghi_de[]?, sua_msg_id?` | `trung[]` **hoặc** kết quả phiên |
| `phien_xoa.php` | S4.4 | `ghi` | `msg_id, ly_do?` | ok (xoá mềm) |
| `khach_luu.php` · `khach_xoa.php` · `he_so_luu.php` | S4.2 | `ghi` | hồ sơ / id / (khach_id?, khoa, gia_tri) | khách / ok (xoá mềm) / giá đã chuẩn hoá |
| `dan_so_kiem_tra.php` · `dan_so_luu.php` · `dan_so_xoa.php` · `dan_so_khoi_phuc.php` | S5.3 | `ghi` | dàn | lỗi[] + ảnh hưởng[] / ok |
| `tu_hoc_them.php` · `tu_hoc_xoa.php` | S5.4 | `ghi` | goc, moi / ids[] | ok hoặc lý do từ chối |
| `lich_su.php` (GET) · `chot_so.php` (GET) | S6.1–S6.2 | `doc` | ngay, khach_id?, trang_thai? | danh sách / tổng hợp (từ snapshot) |
| `telegram_job.php` · `telegram_inbox.php` · `telegram_ghi.php` | S7.2–S7.4 | `phan_tich` (tạo job, ghi) · `doc` (poll) | job / (khach, ngay, chieu) / danh sách tin | `job_id` ngay lập tức / tiến trình [i/n] / kết quả ghi |
| `doi_mat_khau.php` | S1.6 | `doc` (luôn được, kể cả hết hạn) | cũ, mới, xác nhận | ok + thu hồi phiên khác |

Trang admin (`admin/users.php`, `admin/thue_bao.php`) là form POST thường, dùng `require_admin()` + `csrf_check()`; không qua `app/ajax/`.

---

## Phụ lục C — Tài liệu liên quan
- [`phases/`](phases/README.md) — 14 tệp thực thi P00–P13 (test case + cổng nghiệm thu).
- `WEB_MIGRATION_PLAN.md` — v1 (kỹ thuật regex §4.1, tiền §4.2, Telegram §4.4).
- `docs/ui-preview/` — thiết kế UI chuẩn; deep link review: `#mau1 #telegram #kqxs #khach #hoso #khachmoi #xoakhach #lichsu #tuhoc #dan #quytac #anhhuong #khoiphuc #admin`.
- `.claude/rules/bigwin-core.md`, `tenant-isolation.md`, `core-php.md`, `admin-security.md`, `api-contract.md`.
