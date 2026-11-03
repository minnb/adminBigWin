---
paths:
  - "src/webapp/lib/bigwin/**"
---
# Lõi nghiệp vụ BigWIN (port từ Python)

Nguồn chân lý là code Python ở `D:\Projects\bigwin` (`core/`, `utils/`) — port NGUYÊN VĂN hành vi, kể cả chỗ "lạ". Không "sửa cho đẹp" logic tiền/cú pháp.

## PURE
- Tệp trong `lib/bigwin/` KHÔNG gọi `db()`, PDO, cURL, session, `echo`/output — **trừ** `lib/bigwin/repo/**` và `*Service.php`.
- Lõi nhận dữ liệu qua tham số (hệ số đã resolve, mảng KQ 27 giải, bảng dàn số), trả mảng/giá trị.
- Thời gian chỉ qua `now_vn()` / `today_vn()` / `hom_nay_vn_str()` (`helpers.php`); CẤM `date()`, `time()`, `new DateTime()` không kèm múi giờ.

## Regex & Unicode
- Mọi pattern dựng bằng `bw_re($body, $flags)` → luôn có `/u` + `(*UCP)` (để `\w \d \b \s` giống `re` Python 3). CẤM viết `'/.../'` trần.
- Lấy vị trí match bằng `bw_finditer()` → offset theo **ký tự (code point)**, nhóm không khớp = `null`. CẤM dùng thẳng offset byte của `PREG_OFFSET_CAPTURE`.
- Độ dài/cắt chuỗi: `mb_strlen` / `mb_substr` / `mb_str_split` với `'UTF-8'`; CẤM `strlen`/`substr`/`strtolower`/`str_split`/`strpos`… trong lõi — chỉ dòng thao tác byte có chủ đích (đổi offset PCRE) được phép, kèm chú thích `// bw-byte` (quét: `tests/core_pure_scan_test.php` P04-T11).
- Chữ thường: `mb_strtolower($s, 'UTF-8')`. `isdigit/isalnum` Python → `preg_match(bw_re('^\d+$'), ...)`, KHÔNG `ctype_*`.
- NFC/NFD: `bw_nfc()` / `Normalizer::normalize`; dấu tổ hợp: `\p{Mn}`. Ký tự vô hình: `bw_sanitize()` / `BW_KY_TU_AN` (giữ nguyên độ dài).
- `\s` / `str.isspace()` / `str.strip()` Python gồm cả `\x1c-\x1f` (PCRE `\s` thì không): ở chỗ tin THÔ chưa gộp khoảng trắng dùng `BW_PY_S` và `bw_py_strip()` — `trim()` PHP chỉ bỏ khoảng trắng ASCII.
- `str.isalpha()` = `\p{L}` (KHÔNG phải `[^\W\d_]`, lớp này gồm cả `²`, `½`); `str.isalnum()` = `[^\W_]`. Neo cuối dùng `\z` (không `$`).
- `float()` Python nhận mọi chữ số Unicode (`\d` = Nd) — quy về ASCII (`IntlChar::charDigitValue`) trước khi `(float)`.
- Python `if s:` với chuỗi: chỉ `''`/`None` là sai — PHP coi `"0"` là falsy → so tường minh `!== null && !== ''`.
- `re.sub(callable)` → `preg_replace_callback`; giữ đúng THỨ TỰ bảng luật như Python.

## Số học & sắp xếp
- `round()` Python = banker's rounding CHÍNH XÁC → `bw_py_round($x)`. CẤM `round($x, 0, PHP_ROUND_HALF_EVEN)`: PHP 8.3 làm tròn trước 15 chữ số → `round(501.49999999999994)` = 502, Python = 501 (P04-B04). Số nguyên vượt int64 in bằng `sprintf('%.0f', …)` (đúng `int(float)` Python).
- `int(x)` Python (cắt về 0) → `(int)$x`. Tiền là `int`; hệ số là `float` (giống REAL/float Python), tính `(int)($tienVnd * $heSo)` đúng thứ tự phép tính của Python.
- `sorted()` chuỗi số → `sort($a, SORT_STRING)` / `strcmp` — CẤM sort mặc định (PHP so "05" như số).
- Dedup giữ thứ tự (`dict.fromkeys`) → vòng lặp + mảng `$daCo["k:$x"]`. CẤM `array_flip`/`array_unique` làm khoá: PHP đổi `"12"` thành int 12 (mất số `"05"`→5). Khoá tuple → `implode("\x1F", ...)`.
- `difflib.SequenceMatcher` → `SequenceMatcher.php` (BigWIN luôn gọi `autojunk=False`).
- Fixture JSON ghi bằng `sort_keys`: so dict với output PHP (giữ thứ tự khoá như Python) thì sắp khoá hai vế trước `===`.

## Kiểm chứng
- Mỗi module có `tests/<module>_test.php` so khớp `===` với `tests/fixtures/*.json` (sinh bởi `scripts/export_golden.py` bên BigWIN). Fixture lệch = BUG PHP, không sửa fixture.
- Chạy: `php src/webapp/tools/test.php [lọc]`.
