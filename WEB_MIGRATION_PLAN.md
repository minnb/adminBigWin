# Master Migration Plan — BigWIN Desktop (Python) → adminBigWin Web (PHP)

> Trạng thái: **ĐÃ PHÊ DUYỆT** (2026-10-30) · Quyết định Q1–Q6 ở §7 · Giai đoạn 0 (S0.1–S0.3) đã xong.
> Nguồn: `D:\Projects\bigwin` (~44k dòng, Python 3.13/Tkinter/SQLite WAL/Telethon).
> Đích: `src/webapp/` (PHP 8.3 thuần, MySQL/MariaDB, không Composer). Môi trường có sẵn: `intl mbstring sodium curl dom pdo_mysql openssl`.

---

## 0. Quyết định cốt lõi (tóm tắt)

| # | Quyết định | Lý do |
|---|---|---|
| D1 | Lõi nghiệp vụ port sang `src/webapp/lib/bigwin/` dưới dạng **hàm/lớp PHP thuần, không DB** (giữ kiến trúc PURE của `core/`) | Cho phép test parity độc lập DB, giống `heuristic_cleaner`/`messageParser` hiện tại |
| D2 | Một bảng `users` duy nhất có cột `role ENUM('user','admin')`; admin hiện có (`admins`) được chuyển vào `users` | Admin = User + quyền quản trị → một phiên đăng nhập, không trùng logic |
| D3 | Phân hệ người dùng mới ở `src/webapp/app/`; `admin/` giữ vai trò quản trị | Tách rõ ranh giới quyền theo thư mục, dễ viết rule `.claude/rules/` |
| D4 | Mọi bảng nghiệp vụ có `user_id NOT NULL` + FK; truy cập DB nghiệp vụ **chỉ** qua repository nhận `user_id` từ session | Cô lập tenant, chặn IDOR ngay từ tầng truy vấn |
| D5 | `ket_qua_xo_so` là dữ liệu **dùng chung** (không có `user_id`), chỉ scraper/admin được ghi | KQXS là sự thật khách quan, tránh nhân bản 27 dòng/ngày/user |
| D6 | Telegram: **giữ worker Python (Telethon) chạy ngầm**, đẩy tin vào MySQL qua bảng hàng đợi; PHP chỉ đọc/xử lý | Bot API không đọc được chat riêng 1-1 của tài khoản người dùng (xem §4.4) |
| D7 | `/api/*.php` hiện tại **đóng băng** — endpoint AJAX của web đặt tại `app/ajax/` | Bảo vệ hợp đồng với app Desktop đã phát hành (`.claude/rules/api-contract.md`) |
| D8 | Parity test bằng **golden fixtures JSON** sinh từ Python, chạy bởi test runner PHP tự viết (`tools/test.php`) | Không có Composer/PHPUnit; đảm bảo tính tiền khớp 100% |

---

## 1. Bảng ánh xạ Kiến trúc & Module

### 1.1 Lõi nghiệp vụ (`core/`, `utils/` → `lib/bigwin/`)

| Python (BigWIN) | Dòng | PHP đích | Loại | Ghi chú port |
|---|---|---|---|---|
| `utils/helpers.py` | 135 | `lib/bigwin/helpers.php` | PURE | `now_vn()`→`new DateTimeImmutable('now', TZ_VN)`; `fmt_tien` dùng `number_format($n,0,',','.')`; `GIAI_MAP`, `build_summary_table` |
| `core/heuristic_cleaner.py` | 1169 | `lib/bigwin/HeuristicCleaner.php` | PURE | Rủi ro cao nhất về regex/Unicode (§4.1). Giữ **nguyên thứ tự** bảng luật :1104–1169 và pipeline :1045–1090 |
| `core/messageParser.py` | 1298 | `lib/bigwin/MessageParser.php` | PURE | `parse()`, `parse_detailed()`, hook `_phan_tich`; named group `(?P<kw>)`; markup theo **offset ký tự** |
| `core/rule_parser.py` | 412 | `lib/bigwin/RuleParser.php` | PURE | Nhận bảng dàn số làm tham số; cache `_PATTERN_DA_DUNG` → `static` |
| `core/parser_facade.py` | — | `lib/bigwin/ParserFacade.php` | Điều phối | Chọn engine (`parser_cau_hinh.engine`) theo **user** |
| `core/quan_ly_dan_so.py` | 315 | `lib/bigwin/QuanLyDanSo.php` | PURE | `sort` phải `SORT_STRING` (tránh "05" bị so như số) |
| `core/doi_chieu_tin.py` | 279 | `lib/bigwin/DoiChieuTin.php` | PURE | Cần `SequenceMatcher` (§4.1.4) + `\p{Mn}` |
| `utils/diff_analyzer.py` | 321 | `lib/bigwin/DiffAnalyzer.php` | PURE | Dùng chung `SequenceMatcher.php` |
| *(difflib stdlib)* | — | `lib/bigwin/SequenceMatcher.php` | PURE | Port Ratcliff/Obershelp **kèm autojunk** để opcodes trùng 100% |
| `core/learning_service.py` | 474 | `lib/bigwin/LearningService.php` | Service | **Cổng ghi duy nhất** vào `bo_nho_sua_tin`; 5 guard + shadow test `TIN_CHUAN_KIEM_CHUNG` |
| `core/database.py` — tính tiền (`get_he_so` :1792, `tinh_tien_trung` :1820, `tinh_tien_ghi` :1898, `tinh_tien_chiet_khau` :1923) | — | `lib/bigwin/TinhTien.php` | PURE | Nhận mảng hệ số đã resolve; không đọc DB |
| `core/database.py` — dò trúng (`tim_trung*` :1581–1700) | — | `lib/bigwin/DoTrung.php` | PURE | Nhận mảng KQ 27 giải của ngày; trả `[trung, so_lan, giai]` |
| `core/database.py` — CRUD (4354 dòng) | — | `lib/bigwin/repo/*.php` (`KhachHangRepo`, `TinNhanRepo`, `DoiSoatRepo`, `HeSoRepo`, `KetQuaRepo`, `DanSoRepo`, `BoNhoRepo`) | DB | Mọi hàm nhận `int $userId` bắt buộc; PDO prepared |
| `core/database.py::ghi_ket_qua_tin_nhan` :2312, `tim_tin_trung_bo_cuoc` :2390 | — | `lib/bigwin/DoiSoatService.php` | Service | Transaction InnoDB thay `BEGIN IMMEDIATE`; chống trùng bộ cược |
| `ui/app.py::_doi_soat_tin_nhan_cho_khach_no_ui` :1072 | — | `DoiSoatService::doiSoat()` | Service | **Chuyển business logic ra khỏi UI** (bên Python đang nằm ở `ui/app.py`) |
| `core/scraper.py` | 178 | `lib/bigwin/Scraper.php` + `tools/scrape_xsmb.php` | Network | cURL + `DOMXPath`; giữ 3 tầng fallback |
| `core/telegram_sync.py` | 731 | `workers/telegram/` (Python) + `lib/bigwin/TelegramInbox.php` | Sidecar | Xem §4.4 |
| `core/license_service.py`, `api_client.py`, `cloud_backup.py` | — | *Không port* | — | Phục vụ app Desktop; web không cần license máy. Bên server giữ nguyên `api/` |
| `utils/auth.py` + `app_auth` | — | `lib/auth.php` | Auth | Thay bằng `users` + `password_hash()` |
| reportlab (PDF) | — | `app/bao_cao.php` + CSS `@media print` | UI | Không Composer → in trình duyệt ra PDF; FPDF 1 tệp là phương án dự phòng |

### 1.2 Màn hình (`ui/` → `app/`, `admin/`)

| Tkinter | Tệp:dòng | Web | Quyền |
|---|---|---|---|
| Cửa sổ chính (KQXS, bảng loto, F5) | `ui/app.py:485` | `app/index.php` (dashboard KQ ngày) | user |
| ZaloInputForm (nhập tin, Ctrl+Enter) | `zalo_forms.py:70` | `app/nhap_tin.php` ⭐ màn chính | user |
| TelegramSyncDialog (master-detail + dock sửa) | `telegram_forms.py:396` | `app/telegram.php` | user |
| TelegramConfigDialog / đăng nhập OTP/2FA | `telegram_forms.py:141` | `app/telegram_ket_noi.php` | user |
| LichSuDoiSoatDialog | `history_forms.py:25` | `app/lich_su.php` | user |
| KhachHangQuanLyDialog / ChinhSuaKhachHang | `customer_forms.py:461,355` | `app/khach_hang.php` | user |
| CauHinhDialog (hệ số/giá ghi theo khách) | `customer_forms.py:153` | `app/he_so.php` | user |
| QuyTacTuHocDialog | `rules_forms.py:63` | `app/tu_hoc.php` | user |
| CuPhapCuocDialog (engine + dàn số) | `rules_forms.py:382` | `app/cu_phap.php` | user |
| QuyTacDialog (trợ giúp cú pháp) | `rules_forms.py:288` | `app/huong_dan.php` | user |
| Báo cáo PDF | `ui/app.py:431` | `app/bao_cao.php` | user |
| AuthDialog (mật khẩu app đơn) | `auth_forms.py:28` | `login.php` chung + `app/doi_mat_khau.php` | mọi role |
| LicenseDialog, KhoiPhucCloudDialog | `license_forms.py`, `sao_luu_forms.py` | *Không port* (đặc thù Desktop) | — |
| *(mới)* Quản lý tài khoản, khoá/mở, thống kê toàn hệ thống | — | `admin/users.php`, `admin/thong_ke.php`, `admin/he_thong.php` | admin |
| *(hiện có)* Máy/license, owners, backups, usage | `admin/*.php` | giữ nguyên | admin |

---

## 2. Thiết kế Schema DB mới & Ma trận Phân quyền

### 2.1 Nguyên tắc chuyển SQLite → MySQL

- Kiểu: `TEXT` ngày → `DATE`; timestamp text UTC+7 → `DATETIME` (kết nối đã `SET time_zone='+07:00'` trong `lib/bootstrap.php:106`, PHP `Asia/Ho_Chi_Minh` :6). CẤM `UTC_TIMESTAMP()`; dùng `NOW()`.
- Tiền: `INT` SQLite → `BIGINT` (VNĐ nguyên). Hệ số `REAL` → `DOUBLE` (giữ ngữ nghĩa float của Python, xem §4.2).
- `PRAGMA foreign_keys` bên SQLite **chưa từng bật** → dữ liệu cũ có thể có FK mồ côi; script import phải làm sạch trước khi bật FK InnoDB.
- `UNIQUE` toàn cục cũ (vd `khach_hang.ten_khach`, `tin_nhan_log.msg_id`) → `UNIQUE (user_id, ...)`.
- Bảng tạm `lich_su_hien_thi` (drop mỗi lần khởi động) → **không tạo**; dùng session/JS.
- Ký tự: `utf8mb4_unicode_ci`; riêng cột chứa số/khoá so khớp chính xác (`so`, `cum_tu_goc`) dùng `utf8mb4_bin` để không bị so sánh không dấu/không hoa thường.

### 2.2 Bảng mới / chuyển đổi (dự kiến — sẽ tạo thành các migration ở §5)

**Danh tính & phân quyền**
```
users(id PK, username UNIQUE, password_hash, role ENUM('user','admin') DEFAULT 'user',
      trang_thai ENUM('active','locked') DEFAULT 'active', ten_hien_thi,
      created_at, last_login_at, created_by FK users NULL)
user_login_fails(...)            -- tái dùng logic admin_login_fails
```
`admins` cũ: migration copy sang `users(role='admin')` giữ nguyên `password_hash`; giữ bảng `admins` read-only 1 phiên bản rồi mới xoá.

**Dùng chung (không `user_id`)**
```
ket_qua_xo_so(id, ngay DATE, giai, giai_key, so_trung, hai_so_cuoi CHAR(2), created_at,
              UNIQUE(ngay,giai_key,so_trung), KEY(ngay,hai_so_cuoi))
cau_hinh_mac_dinh(khoa PK, gia_tri DOUBLE, mo_ta)         -- 21 khoá he_so_*/he_so_ghi_*/ti_le_chiet_khau_*
parser_dan_so_mac_dinh / parser_tu_khoa_mac_dinh / parser_ma_loi   -- dữ liệu seed từ dan_so_mac_dinh.py
scraper_log(id, ngay, nguon, trang_thai, loi, created_at)
```

**Theo tenant (`user_id NOT NULL`, FK `users(id)` ON DELETE CASCADE)**
```
khach_hang(id, user_id, ten_khach, id_zalo, id_telegram, created_at,
           UNIQUE(user_id,ten_khach), KEY(user_id,id_telegram))
he_so_user(user_id, khoa, gia_tri, PK(user_id,khoa))            -- override cau_hinh mặc định ở mức user
he_so_khach_hang(khach_hang_id, khoa, gia_tri, PK(khach_hang_id,khoa))  -- user_id suy ra qua khach_hang
tin_nhan_log(id, user_id, msg_id, ngay DATE, noi_dung, noi_dung_kiem_tra, khach_hang_id,
             nguon ENUM('MANUAL','TELEGRAM','ZALO'), telegram_msg_id BIGINT, created_at,
             UNIQUE(user_id,msg_id), KEY(user_id,ngay,khach_hang_id), KEY(user_id,telegram_msg_id))
lich_su_doi_soat(id, user_id, tin_nhan_id FK, msg_id, ngay, so_ghi, loai_so ENUM('DE','LO','3C','LX2','LX3','LX4','XQ'),
                 so_tien, tien_vnd BIGINT, tien_ghi BIGINT, ket_qua, giai_trung, so_lan_trung,
                 tien_trung BIGINT, tien_chiet_khau BIGINT, khach_hang_id, telegram_msg_id, created_at,
                 KEY(user_id,ngay,khach_hang_id))
bo_nho_sua_tin(id, user_id, tin_goc, tin_da_sua, cum_tu_goc, cum_tu_thay_the, so_lan_ap_dung,
               hit_count, last_used, ngay_tao, ngay_cap_nhat,
               UNIQUE(user_id,cum_tu_goc,cum_tu_thay_the), KEY(user_id,hit_count,last_used))
parser_dan_so / parser_dan_so_khoa / parser_dan_so_so / parser_tu_khoa / parser_cau_hinh  -- + user_id;
               nguon='mac_dinh' sao chép từ bảng *_mac_dinh khi tạo user
parser_diff_log(id, user_id, ...)
telegram_tai_khoan(user_id PK, api_id, api_hash_enc, session_enc MEDIUMBLOB, phone_hint,
                   trang_thai, updated_at)                      -- bí mật mã hoá bằng lib/crypto.php
telegram_inbox(id, user_id, peer_id, telegram_msg_id, ngay DATE, text, date_ts, edit_date_ts,
               direction ENUM('in','out'), xu_ly ENUM('moi','da_xu_ly','bo_qua'),
               UNIQUE(user_id,peer_id,telegram_msg_id))
telegram_jobs(id, user_id, loai ENUM('fetch','login_code','login_verify'), tham_so_json,
              trang_thai, ket_qua_json, created_at, finished_at)
telegram_sync_log(id, user_id, sync_session_id UNIQUE, ...)   -- như cũ + user_id
```
`ket_qua_xo_so` không thuộc tenant nên **tuyệt đối không** được join kèm điều kiện user nhầm sang dữ liệu khách.

**Quan hệ với bảng Desktop hiện có:** `machines/owners/backup_versions/usage_summaries` giữ nguyên. Tuỳ chọn tương lai: `owners.user_id` để admin gắn chủ máy Desktop với tài khoản web (không bắt buộc ở giai đoạn này).

### 2.3 Ma trận phân quyền (RBAC)

| Chức năng | Khách (chưa login) | `user` | `admin` |
|---|---|---|---|
| Đăng nhập / đổi mật khẩu của mình | login | ✅ | ✅ |
| Xem KQXS, bảng loto | ❌ | ✅ | ✅ |
| Lấy KQ thủ công (F5 → gọi scraper) | ❌ | ✅ (giới hạn tần suất) | ✅ |
| Nhập tin / làm sạch / phân tích / đối soát & lưu | ❌ | ✅ dữ liệu **của mình** | ✅ dữ liệu **của mình** |
| Khách hàng, hệ số, giá ghi | ❌ | ✅ của mình | ✅ của mình |
| Bộ nhớ tự học, cú pháp/dàn số | ❌ | ✅ của mình | ✅ của mình + sửa **bộ mặc định** toàn hệ thống |
| Telegram: kết nối & đồng bộ | ❌ | ✅ tài khoản TG của mình | ✅ của mình |
| Lịch sử, báo cáo | ❌ | ✅ của mình | ✅ của mình |
| Xem dữ liệu nghiệp vụ của user khác | ❌ | ❌ | ❌ mặc định; chỉ **thống kê tổng hợp** (đếm tin, tổng tiền/ngày) |
| Quản lý user (tạo/khoá/reset mật khẩu/đổi role) | ❌ | ❌ | ✅ (không tự hạ quyền/khoá chính mình; luôn còn ≥1 admin) |
| Cấu hình hệ thống, hệ số mặc định, scraper log | ❌ | ❌ | ✅ |
| Máy/license/owners/backups/usage Desktop (`admin/` hiện có) | ❌ | ❌ | ✅ |
| `/api/*.php` (app Desktop) | ký số/machine_id như cũ | — | — |

**Cơ chế thực thi** (`lib/auth.php`, mở rộng từ `admin/_auth.php`):
- Session `bigwin_web` (cookie `HttpOnly`, `Secure`, `SameSite=Lax`, `session_regenerate_id` sau login). Phiên admin cũ `bigwin_admin` dùng song song tới khi xong Giai đoạn 1.
- `require_user(): array` → trả `['id','role',...]`, nạp lại từ DB mỗi request để khoá tài khoản có hiệu lực ngay.
- `require_admin()` = `require_user()` + `role==='admin'`, ngược lại 403.
- `app/_layout.php` cho user; `admin/_auth.php` cho admin (menu admin có thêm link sang `app/`).
- CSRF: tái dùng `csrf_token()` hiện có cho mọi POST, kể cả AJAX (header `X-CSRF-Token`).
- **Chống IDOR:** repo luôn `WHERE id = ? AND user_id = ?`; tham chiếu chéo (`khach_hang_id` trong `lich_su_doi_soat`) phải kiểm tra thuộc cùng `user_id` trước khi ghi. Rule mới `.claude/rules/tenant-isolation.md` (paths `src/webapp/app/**`, `src/webapp/lib/bigwin/repo/**`).

---

## 3. Cấu trúc thư mục dự kiến của `src/webapp/`

```
src/webapp/
├── index.php                 # → app/ nếu đã login, ngược lại login.php
├── login.php / logout.php    # MỚI: đăng nhập chung cho user & admin
├── .htaccess                 # + chặn workers/, tests/
├── admin/                    # GIỮ — thêm users.php, thong_ke.php, he_thong.php
├── api/                      # ĐÓNG BĂNG — hợp đồng app Desktop
├── app/                      # MỚI — phân hệ người dùng
│   ├── _layout.php           # require_user() + layout (Tailwind/Alpine như admin)
│   ├── index.php  nhap_tin.php  telegram.php  telegram_ket_noi.php  lich_su.php
│   ├── khach_hang.php  he_so.php  tu_hoc.php  cu_phap.php  huong_dan.php  bao_cao.php
│   ├── ajax/                 # JSON nội bộ cho web (phan_tich.php, doi_soat.php, telegram_*.php...)
│   └── assets/               # app.css (token FLAT_*), nhap_tin.js, highlight.js
├── lib/
│   ├── bootstrap.php api.php crypto.php      # GIỮ
│   ├── auth.php                               # MỚI: session, require_user/require_admin, csrf
│   └── bigwin/                                # MỚI: lõi nghiệp vụ (bảng §1.1)
│       ├── helpers.php HeuristicCleaner.php MessageParser.php RuleParser.php ParserFacade.php
│       ├── QuanLyDanSo.php DoiChieuTin.php DiffAnalyzer.php SequenceMatcher.php
│       ├── TinhTien.php DoTrung.php DoiSoatService.php LearningService.php Scraper.php TelegramInbox.php
│       └── repo/  KhachHangRepo.php TinNhanRepo.php DoiSoatRepo.php HeSoRepo.php KetQuaRepo.php DanSoRepo.php BoNhoRepo.php
├── tests/                    # MỚI — runner tối giản không Composer
│   ├── fixtures/             # *.json golden sinh từ Python
│   └── *_test.php
├── tools/                    # GIỮ + scrape_xsmb.php, test.php, import_sqlite.php, telegram_worker_ctl.php
├── workers/telegram/         # MỚI — sidecar Python (Telethon) + requirements.txt (KHÔNG chạy qua web)
├── migrations/  schema.sql
```

Quy ước `require`: `app/*` → `__DIR__.'/_layout.php'` → `__DIR__.'/../lib/auth.php'`; lõi `lib/bigwin/*` không `require` DB (PURE), chỉ `repo/` và `*Service.php` dùng `db()`.

---

## 4. Giải pháp kỹ thuật

### 4.1 Regex & Unicode (Python `re`/`unicodedata` → PCRE)

1. **Mọi pattern có cờ `/u`** và bọc helper `bw_re(string $pattern): string` thêm `(*UCP)` để `\w \d \b` có ngữ nghĩa Unicode như Python 3 (`_L/_W` `heuristic_cleaner.py:223–224`, `messageParser.py:413`, `rule_parser.py:146`). Lưu ý `\d` Python khớp cả chữ số Unicode khác ASCII — kiểm tra fixture có chữ số full-width (`１２`) để chốt hành vi.
2. **Offset ký tự vs byte:** `PREG_OFFSET_CAPTURE` trả **byte**. Markup tô xanh/đỏ và `_bo_dau` (`rule_parser.py:34–46`, yêu cầu chuỗi bỏ dấu cùng độ dài) cần offset **ký tự** → helper `bw_match_all()` chuyển byte→char bằng `mb_strlen(substr(...))`, hoặc xử lý nội bộ bằng mảng ký tự `mb_str_split`. Kết quả trả về UI luôn là offset ký tự (JS cũng dùng UTF-16 — với tiếng Việt BMP là khớp).
3. **Named group** `(?P<kw>…)` giữ nguyên; luôn dùng `PREG_UNMATCHED_AS_NULL` để tương đương `None`.
4. **`difflib.SequenceMatcher`** (`doi_chieu_tin.py:239`, `diff_analyzer.py:298`): **đã xác minh** cả hai nơi gọi `SequenceMatcher(None, list_a, list_b, autojunk=False)` trên **danh sách phần tử** (ký tự đã qua `_khoa()` / token) → chỉ cần port `find_longest_match`, `get_matching_blocks`, `get_opcodes`, `ratio` **không có autojunk, không isjunk**, trên mảng PHP. Fixture `seqmatch.json` (1.400 ca: cấp ký tự + cấp token) sinh từ chính `difflib`.
5. **Unicode normalization:** `unicodedata.normalize('NFC'|'NFD')` → `Normalizer::normalize(..., FORM_C|FORM_D)` (ext-intl có sẵn); `category()=='Mn'` → `preg_match('/\p{Mn}/u')`. Khai báo `intl` là **bắt buộc** trong `/healthcheck`.
6. **Ký tự ẩn** (`str.translate` tại `heuristic_cleaner.py:388`, `messageParser.py:187`): chuyển thành **một** hằng `BW_KY_TU_AN` dùng chung cho `strtr` — test đảm bảo khớp 1-1 với hai bảng Python.
7. **Hoa/thường:** `.lower()` → `mb_strtolower($s, 'UTF-8')`; `Đ/đ` + `IGNORECASE` cần `/iu`. `isdigit/isalnum` → `ctype_*` **không dùng được** cho Unicode → `preg_match('/^\d+$/u')` (với `(*UCP)` nếu cần).
8. **`re.sub(callable)`** (`heuristic_cleaner.py:471,589,705,740,839,932`) → `preg_replace_callback`; closure bắt biến bằng `use` (thay mẹo `lambda m, t=…`).
9. **Sắp xếp:** sort theo độ dài giảm dần (`heuristic_cleaner.py:209,309`, `rule_parser.py:141`) → `usort` + `mb_strlen` (PHP 8 sort ổn định như Python); `sorted()` chuỗi số → `sort($a, SORT_STRING)`; khoá dedup tuple → `implode("\x1F", ...)`. Thứ tự dict (`_SLANG_FACTOR` :283) → mảng PHP giữ thứ tự chèn, giữ nguyên thứ tự khai báo.
10. **`round()` Python là banker's rounding** (`heuristic_cleaner.py:702–703,729–738`) → `round($x, 0, PHP_ROUND_HALF_EVEN)`; float nhân hệ số tiếng lóng giữ `float` như Python (IEEE-754 giống nhau) rồi mới làm tròn.

### 4.2 Tính tiền & đối soát

- `TinhTien.php` port **nguyên văn** `database.py:1792–1945`. **Quy tắc ép kiểu ĐÃ XÁC MINH (đọc code 2026-10-30):**

  | Hàm Python | Biểu thức chính xác | PHP tương đương |
  |---|---|---|
  | `get_he_so(khoa, kh_id, mac_dinh)` | `default = float(DEFAULT_HE_SO.get(khoa, mac_dinh))`; nếu `kh_id` falsy → `default`; ngược lại `float(row[0])` từ `he_so_khach_hang` nếu có, không thì `default` | trả `float`; `kh_id` null/0 → bỏ qua tầng khách |
  | `tinh_tien_trung` DE / 3C | `int(tien_vnd * he_so)` (**không** nhân `so_lan_trung`) | `(int)($tien * $heSo)` |
  | `tinh_tien_trung` LO / LX2 / LX3 / LX4 / XQ | `int(tien_vnd * so_lan_trung * he_so)` — nhân trái→phải: `(tien*so_lan)` là int rồi × float | `(int)($tien * $soLan * $heSo)` |
  | `tinh_tien_trung` loại khác | `0` | `0` |
  | `tinh_tien_ghi` | `int(tien_vnd * get_he_so_ghi(loai, kh))`; loại lạ → hệ số `1` | `(int)($tien * $heSoGhi)` |
  | `tinh_tien_chiet_khau` | `int(ti_le * tien_vnd / 100)` — **true division float**, thứ tự `(ti_le*tien)/100` | `(int)($tiLe * $tien / 100)` — `$tiLe` phải là `float` để `/` không đổi nghĩa |

  - `int()` Python = **cắt về 0** (không làm tròn) ≡ `(int)` PHP; float IEEE-754 hai bên giống nhau → khớp từng đồng (đã thử: `(int)(12345*72.5)=895012`, `(int)(23.5*12345/100)=2901` trùng fixture).
  - **Mặc định thực tế lấy từ `Database.DEFAULT_HE_SO`**, KHÔNG phải tham số `mac_dinh` trong lời gọi (vd lời gọi ghi `70`/`24000`/`300` cho DE/LO/LX4 nhưng dict ghi đè thành `80`/`80000`/`100`). Giá trị: DE 80 · LO 80000 · 3C 400 · XQ2 15 · LX2 10 · LX3 40 · LX4 100 · ghi LO 23000, ghi loại khác 1 · chiết khấu DE 23, loại khác 0.
  - Nhánh XQ có code chết (`so_cap`, `tien_vnd_goc`, `so_nghin` tính rồi bỏ) → **không port**, chỉ giữ `int(tien*so_lan*he_so_xq2)`.
  - Fixture `tinh_tien.json`: 4.800 ca (3 khách × 8 loại gồm 1 loại lạ × 50 mức tiền × 4 số lần trúng), khách 7 có hệ số lẻ (`72.5`, `22950.5`, `33.333`…) để bắt lệch ép kiểu.
- Thứ tự ưu tiên hệ số (Q4): `he_so_khach_hang` → `he_so_user` → `cau_hinh_mac_dinh`. Python chỉ có 2 tầng; tầng `he_so_user` rỗng mặc định nên kết quả trùng Python. `cau_hinh_mac_dinh` seed đúng `DEFAULT_HE_SO`.
- Số học: `int` PHP (64-bit) cho VNĐ; hệ số lưu `DOUBLE` (không `DECIMAL`, để giá trị đọc ra là đúng float như REAL SQLite) và ép `(float)` khi đọc. Ràng buộc `MAX_TIEN_VND` (1 tỷ), `MAX_DIEM_LO` (10.000), `MAX_SO_TOKENS` (60) giữ nguyên hằng.
- `ngay` bên Desktop là chuỗi `dd-mm-yyyy`; MySQL lưu `DATE`, chuyển đổi tại repo/import.
- `DoTrung.php`: nhận mảng 27 giải của ngày (đọc 1 lần), port `tim_trung`, `tim_trung_de`, `tim_trung_3c`, `tim_trung_xq`, `tim_trung_xq2` (`database.py:1581–1750`) thành hàm thuần → không N+1 query.
- `DoiSoatService::luu()`: `beginTransaction` → kiểm tra trùng bộ cược (`tim_tin_trung_bo_cuoc`) → ghi `tin_nhan_log` + các dòng `lich_su_doi_soat` → commit → **sau commit** mới `LearningService::ghiNhanLuotApDung()` (giống thứ tự Python). Không gọi mạng trong transaction. Đọc lại xác minh sau ghi (luật "ghi DB xong chưa xong" của BigWIN).

### 4.3 Bộ nhớ tự học

- `LearningService` là **lớp duy nhất** được gọi `BoNhoRepo::luu()`; `BoNhoRepo` không public hàm ghi cho nơi khác (đặt `@internal` + rule `.claude/rules/learning.md` + grep trong `/healthcheck`).
- Cache `_CACHE_QUY_TAC` → cache theo request `static array $cache[$userId]`; vô hiệu hoá khi ghi. Shadow test `TIN_CHUAN_KIEM_CHUNG` chạy trên **bộ dàn số của user** trước khi chấp nhận luật.
- Giới hạn `MAX_QUY_TAC_TU_HOC` áp **theo user**.

### 4.4 Tác vụ nền

**Scraper XSMB** — `tools/scrape_xsmb.php [YYYY-MM-DD]` (CLI-only, như `keygen.php`):
- cURL (`CURLOPT_CAINFO` theo CA hệ thống, timeout 15s, header trình duyệt + Referer như Python) → `DOMDocument::loadHTML` + `DOMXPath` tìm `table.bkqmiennam.bkqmienbac` → fallback theo dòng bảng → fallback regex (`_parse_bang_regex`).
- Validate đủ 27 giải, đúng độ dài từng giải trước khi ghi; `INSERT IGNORE` trên `UNIQUE(ngay,giai_key,so_trung)` trong 1 transaction; ghi `scraper_log`.
- Cron cPanel: `*/5 18-19 * * *` (18:30–19:55 giờ VN, dừng khi đủ 27 giải) + 1 lần 06:00 bù ngày hôm trước. Retry: 3 lần, backoff 10/30/60s trong 1 lần chạy.
- Nút "Lấy KQ" trên web gọi `app/ajax/lay_kq.php` dùng chung `Scraper.php`, rate-limit theo user + khoá `GET_LOCK('scrape_<ngay>')` chống chạy song song.

**Telegram Sync** — so sánh phương án:

| | A. Worker Python Telethon (đề xuất) | B. Bot API trên PHP (webhook) | C. MadelineProto (PHP MTProto) |
|---|---|---|---|
| Đọc chat riêng 1-1 của chính người dùng với khách | ✅ như Desktop | ❌ bot chỉ nhận tin gửi **cho bot** | ✅ |
| Đọc lịch sử ngày cũ, tin "out" | ✅ | ❌ | ✅ |
| Tái dùng code đã chạy ổn (731 dòng) | ✅ gần như nguyên | ❌ viết lại + đổi quy trình khách | ❌ |
| Không Composer | ✅ (Python riêng) | ✅ | ❌ cần Composer/phar lớn |
| Hosting | Cần VPS / tiến trình nền dài hạn — **shared cPanel thường không cho** | Chạy được trên cPanel | Nặng, khó trên cPanel |
| Rủi ro | Lưu session Telethon của nhiều user trên server (bí mật cao) | Thay đổi thói quen khách hàng | Phụ thuộc lớn |

Kiến trúc A:
- PHP (web) ghi yêu cầu vào `telegram_jobs` (`fetch`, `login_code`, `login_verify`); worker Python poll bảng (hoặc `SELECT ... FOR UPDATE SKIP LOCKED`), dùng Telethon với session của **từng user** (giải mã bằng khoá trong file ngoài repo, giống `backup_key_file`), ghi tin vào `telegram_inbox` (dedup `UNIQUE(user_id,peer_id,telegram_msg_id)`, giữ `edit_date`).
- PHP đọc `telegram_inbox` → master-detail → `DoiSoatService`. Worker **không** tính tiền/parse → chỉ còn 1 bản lõi nghiệp vụ (PHP).
- Worker kết nối MySQL trực tiếp bằng user DB riêng chỉ có quyền trên `telegram_*` (least privilege). Không mở cổng HTTP.
- Đăng nhập OTP/2FA đi qua `telegram_jobs` → UI poll `app/ajax/telegram_job.php`.
- Nếu production chỉ có shared hosting: worker chạy trên máy Windows của chủ hệ thống (Task Scheduler) trỏ tới MySQL từ xa qua TLS — hoặc chọn B và chấp nhận đổi quy trình. **Cần người dùng quyết định (Q1).**

### 4.5 Giao diện Web

- Token màu từ `ui/core/theme.py` → CSS custom properties trong `app/assets/app.css` (`--flat-app-bg:#DCE8F6; --flat-primary:#1B6EC2; --flat-success:#107C41; --flat-danger:#D83B01; --flat-warning:#D87A00; --flat-err-text:#CC0000; --flat-err-bg:#FDE8E8; --flat-syntax-warn-bg:#FFF9DB; --flat-row-alt:#F2F7FD; --flat-console-bg:#1E293B; …`) và cấu hình `tailwind.config` theme extend như `admin/_auth.php` đang làm (`pos-blue` = `#1B6EC2` sẵn khớp). Font: Segoe UI → `"Segoe UI", "Be Vietnam Pro", system-ui`; Consolas cho tổng tiền.
- **Màn nhập tin** (`app/nhap_tin.php`), giữ tốc độ Desktop:
  - Textarea + lớp `<div>` highlight chồng phía dưới (mirror) → tô đỏ/vàng theo offset ký tự từ `ajax/phan_tich.php`; debounce **300 ms** như `zalo_forms`, `AbortController` huỷ request cũ.
  - Dò lỗi chạy **server-side** (một nguồn sự thật PHP), payload nhỏ, thời gian mục tiêu < 80 ms/tin.
  - Phím tắt: `Ctrl+Enter` đối soát & lưu · `Esc` đóng modal · `F5`→`Alt+R` lấy KQ (F5 trình duyệt dành cho reload) · `/` focus ô tìm khách · `Enter` chọn khách trong popup.
  - Combobox khách hàng có lọc tức thời (Alpine), nhớ khách cuối cùng theo `localStorage`.
  - Cảnh báo trùng bộ cược → modal "Ghi đè phiên cũ?" như Desktop.
  - Dòng "App tự hiểu 'X' thành 'Y'" + click lỗi → nhảy tới vị trí trong textarea (`setSelectionRange`).
- **Telegram**: bảng master (xanh/vàng/đỏ theo trạng thái) + dock sửa bên phải; sửa → parse preview → lưu → học.
- **Lịch sử / báo cáo**: bảng phân trang server-side; in PDF qua `window.print()` với CSS in riêng.

---

## 5. Lộ trình triển khai (Vertical Slices) & Parity Testing

### 5.1 Hạ tầng kiểm thử parity (làm ĐẦU TIÊN)

- **Bên Python** (`D:\Projects\bigwin\scripts\export_golden.py` — tạo ở repo BigWIN, cần duyệt riêng): import trực tiếp `core/*` (không UI, không DB thật — dùng DB tạm `:memory:` với seed mặc định), chạy trên corpus và xuất JSON:
  - `cleaner.json`: `input → clean() output + thống kê áp dụng`
  - `parser.json`: `input → results[] + markup[] + mã lỗi + can_save`
  - `tinh_tien.json`: `(loai_so, tien_vnd, he_so…, so_lan) → tien_ghi, chiet_khau, tien_trung`
  - `do_trung.json`: `(ngay KQ 27 giải, so, loai) → trung, so_lan, giai`
  - `seqmatch.json`: `(a, b) → opcodes, ratio`
  - `learning.json`: `(tin_goc, tin_da_sua, bộ luật hiện có) → chấp nhận/từ chối + lý do`
- **Corpus:** toàn bộ chuỗi trong `tests/test_parse_message.py`, `test_parse_edge_cases.py`, `test_heuristic_cleaner.py`, `test_rule_parser.py`, `TIN_CHUAN_KIEM_CHUNG`, các mẫu `logic.txt`, cộng **tin thật đã ẩn danh** xuất từ `lich_su_doi_soat` (do người dùng chạy, không commit dữ liệu thật — Q3).
- **Bên PHP:** `src/webapp/tools/test.php` (runner ~80 dòng: tìm `tests/*_test.php`, hàm `assert_same()`, in diff, exit code ≠ 0 khi fail). `tests/parity_test.php` đọc từng fixture, so **nguyên văn** (`===`) — tiền khớp **từng đồng**, markup khớp từng offset.
- **Tiêu chí qua cửa mỗi slice:** 100% fixture của module đó xanh + `/healthcheck` xanh + `/audit-api` báo AN TOÀN.

### 5.2 Các giai đoạn & slice (≤ 3–4 tệp mã/slice, không tính fixture)

**Giai đoạn 0 — Nền tảng**
| Slice | Tệp | Kết quả kiểm chứng |
|---|---|---|
| S0.1 Test runner | `tools/test.php`, `tests/smoke_test.php` | `php tools/test.php` chạy được |
| S0.2 Golden exporter | `bigwin/scripts/export_golden.py`, `tests/fixtures/*.json` | Fixture sinh ổn định, chạy 2 lần cho cùng kết quả |
| S0.3 Helpers & Unicode | `lib/bigwin/helpers.php`, `tests/helpers_test.php` | `now_vn`, `fmt_tien`, `bw_re`, chuyển offset |

**Giai đoạn 1 — Danh tính & RBAC**
| S1.1 Bảng `users` | `migrations/…_users_rbac.sql`, `schema.sql`, `tools/create_admin.php` (thêm `--role`) | Admin cũ đăng nhập được bằng mật khẩu cũ |
| S1.2 Auth chung | `lib/auth.php`, `login.php`, `logout.php` | Test: user bị khoá → 403 ngay request kế |
| S1.3 Phân hệ app rỗng + admin users | `app/_layout.php`, `app/index.php`, `admin/users.php` | User không vào được `admin/*`; admin vào được cả hai |

**Giai đoạn 2 — Lõi parse (PURE, chưa UI)**
| S2.1 SequenceMatcher | `SequenceMatcher.php`, `tests/seqmatch_test.php` | 100% `seqmatch.json` |
| S2.2 HeuristicCleaner | `HeuristicCleaner.php`, `tests/cleaner_test.php` | 100% `cleaner.json` |
| S2.3 MessageParser | `MessageParser.php`, `tests/parser_test.php` | 100% `parser.json` (7 loại cược, 12 case đề, XQ C(n,k)) |
| S2.4 RuleParser + Facade + dàn số | `RuleParser.php`, `ParserFacade.php`, `QuanLyDanSo.php` | Fixture cả 2 engine `cu`/`moi` |
| S2.5 Đối chiếu nghi ngờ | `DoiChieuTin.php`, `DiffAnalyzer.php` | 100% fixture tương ứng |

**Giai đoạn 3 — Tiền & KQXS**
| S3.1 Bảng KQ + scraper | `migrations/…_ket_qua_xo_so.sql`, `Scraper.php`, `tools/scrape_xsmb.php` | Lưu đủ 27 giải 3 ngày mẫu; so khớp với `xoso.db` |
| S3.2 Tính tiền & dò trúng | `TinhTien.php`, `DoTrung.php`, `tests/tien_test.php` | 100% `tinh_tien.json`, `do_trung.json` |
| S3.3 Dashboard KQ | `app/index.php`, `app/ajax/lay_kq.php`, `app/assets/app.css` | Hiển thị như cửa sổ chính Desktop |

**Giai đoạn 4 — Khách hàng & đối soát (giá trị cốt lõi)**
| S4.1 Bảng tenant | `migrations/…_nghiep_vu_tenant.sql`, `schema.sql`, `repo/KhachHangRepo.php`, `repo/HeSoRepo.php` | Test IDOR: user A không đọc/sửa được khách user B |
| S4.2 Màn khách hàng & hệ số | `app/khach_hang.php`, `app/he_so.php` | CRUD + hệ số override đúng thứ tự ưu tiên |
| S4.3 Service đối soát | `DoiSoatService.php`, `repo/TinNhanRepo.php`, `repo/DoiSoatRepo.php` | **Parity E2E:** cùng tin + cùng KQ + cùng hệ số → tổng ghi/chiết khấu/trúng/phải trả khớp Desktop |
| S4.4 Màn nhập tin | `app/nhap_tin.php`, `app/ajax/phan_tich.php`, `app/ajax/doi_soat.php`, `app/assets/nhap_tin.js` | Highlight 300 ms, Ctrl+Enter, chống trùng bộ cược |

**Giai đoạn 5 — Tự học & cú pháp**
| S5.1 LearningService | `LearningService.php`, `repo/BoNhoRepo.php`, `tests/learning_test.php` | 100% `learning.json`; grep: không nơi khác ghi `bo_nho_sua_tin` |
| S5.2 Màn tự học & cú pháp | `app/tu_hoc.php`, `app/cu_phap.php`, `repo/DanSoRepo.php` | Sửa tin → luật mới áp dụng ngay lần parse kế |

**Giai đoạn 6 — Lịch sử & báo cáo**
| S6.1 | `app/lich_su.php`, `app/bao_cao.php`, `app/assets/print.css` | Tổng theo ngày/khách khớp `build_summary_table` Python |

**Giai đoạn 7 — Telegram** *(sau khi chốt Q1)*
| S7.1 Bảng + worker | `migrations/…_telegram.sql`, `workers/telegram/worker.py`, `workers/telegram/README.md` | Worker đọc job, ghi inbox, dedup |
| S7.2 Kết nối OTP/2FA | `app/telegram_ket_noi.php`, `app/ajax/telegram_job.php`, `lib/bigwin/TelegramInbox.php` | Login được, session lưu mã hoá |
| S7.3 Đồng bộ master-detail | `app/telegram.php`, `app/assets/telegram.js` | Parity với `test_ui_vs_telegram.py` |

**Giai đoạn 8 — Admin hệ thống & chuyển dữ liệu**
| S8.1 Thống kê toàn hệ thống | `admin/thong_ke.php`, `admin/he_thong.php` | Chỉ số tổng hợp, không lộ nội dung tin |
| S8.2 Import từ `xoso.db` | `tools/import_sqlite.php` (PDO sqlite → MySQL, gán `user_id`) | Đếm dòng + tổng tiền/ngày trước/sau khớp; chạy `--dry-run` trước |

### 5.3 Ràng buộc xuyên suốt mọi slice

- `/api/*.php`, `lib/api.php`, `lib/crypto.php`: **không đổi**; mỗi PR chạy `/audit-api`. Thay đổi `lib/bootstrap.php` chỉ được **thêm** hàm.
- Mọi thay đổi DB: migration mới + cập nhật `schema.sql` (`/db-migration`).
- Mọi SQL: PDO prepared; mọi output: `h()`; mọi POST: CSRF.
- Giờ: `Asia/Ho_Chi_Minh` + `time_zone '+07:00'` (đã có trong `bootstrap.php`); CẤM `UTC_TIMESTAMP()`/`gmdate()` cho dữ liệu nghiệp vụ.
- Thêm rule mới cho Claude: `.claude/rules/bigwin-core.md` (paths `src/webapp/lib/bigwin/**`: PURE, `/u`+`(*UCP)`, `PHP_ROUND_HALF_EVEN`, `SORT_STRING`, offset ký tự), `tenant-isolation.md`, `learning.md`.

---

## 6. Rủi ro chính

| Rủi ro | Mức | Giảm thiểu |
|---|---|---|
| Lệch tiền do ép kiểu/làm tròn | Trung bình (đã xác minh quy tắc §4.2) | Fixture `tinh_tien.json` 4.800 ca + E2E |
| Lệch regex Unicode/offset byte | Cao | Helper `bw_re`/offset tập trung; fixture markup |
| `SequenceMatcher` sai opcodes → nghi ngờ/tự học khác Desktop | Trung bình | Port nguyên thuật toán + fixture ngẫu nhiên |
| Telegram không chạy được trên shared hosting | Cao | Chốt Q1 trước Giai đoạn 7 |
| Rò dữ liệu giữa user (IDOR) | Cao | Repo bắt buộc `$userId`, test IDOR mỗi slice, `/code-review` + skill `check-idor-for-dev` |
| Bản Python tiếp tục thay đổi trong lúc port | Trung bình | Ghi commit BigWIN gốc trong fixture; cập nhật fixture khi Python đổi |

## 7. Quyết định đã chốt (2026-10-30)

1. **Q1 — Telegram:** Phương án **A** — worker Python Telethon qua hàng đợi MySQL `telegram_jobs` / `telegram_inbox`, ở Giai đoạn 7. Nếu hosting web không chạy được daemon Python, worker chạy trên VPS/máy trạm riêng kết nối MySQL.
2. **Q2 — Desktop & Web:** Chạy **song song độc lập**, KHÔNG đồng bộ hai chiều. Chỉ migrate một chiều `xoso.db` → `user_id` trên web bằng `tools/import_sqlite.php` (Giai đoạn 8) khi cần. `/api/*.php` giữ nguyên tuyệt đối.
3. **Q3 — Fixture:** Corpus lấy từ `tests/` + `TIN_CHUAN_KIEM_CHUNG` + mẫu `logic.txt` (đã làm). Tin trích từ `xoso.db` (nếu có) phải **ẩn danh 100%** thông tin khách và lưu ở `src/webapp/tests/fixtures/local/` (đã chặn bằng `.gitignore`).
4. **Q4 — Hệ số:** Mô hình 3 tầng `he_so_khach_hang` → `he_so_user` → `cau_hinh_mac_dinh`.
5. **Q5 — Admin:** Admin dùng app như một user độc lập (bằng `user_id` của chính mình); trang `admin/` chỉ xem **thống kê tổng hợp** của user khác + quản lý tài khoản/cấu hình hệ thống.
6. **Q6 — Gemini:** KHÔNG port trong đợt này.
7. **Quyền ở repo Python:** chỉ được tạo `D:\Projects\bigwin\scripts\export_golden.py`; không sửa tệp nào khác của BigWIN.

## 8. Nhật ký thực thi

### Giai đoạn 0 — XONG (2026-10-30)
- **S0.1** `src/webapp/tools/test.php` (runner CLI: tìm `tests/*_test.php`, chạy `test_*`, `assert_same` + diff dòng, warning = lỗi, exit 1 khi có lỗi, lọc theo tên tệp), `tests/.htaccess` (`Require all denied`), `tests/smoke_test.php`.
- **S0.2** `D:\Projects\bigwin\scripts\export_golden.py` → `src/webapp/tests/fixtures/{cleaner,parser,tinh_tien,do_trung,seqmatch,learning}.json` (BigWIN `3c5fab3-dirty` — working tree BigWIN đang có thay đổi chưa commit ở `core/`/`utils/` từ trước; fixture phản ánh working tree đó. Corpus 1.319 tin, 0 ca ném lỗi, tổng 6 MB). Khi BigWIN commit/đổi logic → chạy lại exporter. Chạy 2 lần → SHA-256 trùng 100%. Không mở `xoso.db`; tính tiền/dò trúng chạy trên SQLite `:memory:` bằng chính method của `Database`.
- **S0.3** `src/webapp/lib/bigwin/helpers.php` + `tests/helpers_test.php` (14 test, so với output thật của Python, gồm 1.319 ca `chuan_hoa_ky_tu_an` từ fixture).
- Rule mới: `.claude/rules/bigwin-core.md`, `.claude/rules/tenant-isolation.md`.
