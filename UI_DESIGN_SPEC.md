# UI Design Spec — BigWIN Web (phân hệ Người dùng)

> Prototype tương tác: `docs/ui-preview/index.html` (mở trực tiếp bằng trình duyệt, không cần server).
> Dữ liệu, bộ parse và kết quả xổ số trong prototype là **giả lập**. Công thức tiền dùng đúng quy tắc đã xác minh ở `WEB_MIGRATION_PLAN.md` §4.2.
> Deep link để review: `#nhap` `#mau1`…`#mau4` `#telegram` `#kqxs` `#khach` `#lichsu` `#tuhoc` `#dan` `#admin`.

## 1. Nguyên tắc

Người dùng là chủ bảng/đại lý, làm việc dưới áp lực cao trong khung 17:30–18:30. Ba câu hỏi phải trả lời được **trong một cái liếc**:

1. Tin có lỗi ở đâu? → tô đỏ ngay trong ô nhập + danh sách lỗi bấm để nhảy tới.
2. App đã tự hiểu gì? → tô vàng + dòng "App tự hiểu `1 lít` → `100k`".
3. Khách này thu hay trả bao nhiêu? → thẻ **Cân đối** lớn nhất màn hình, xanh = khách nộp, đỏ = chủ trả.

Ưu tiên bàn phím hơn chuột, chữ to, số căn cột, không cuộn trang ở màn hình chính (≥1280px).

## 2. Design tokens

### 2.1 Màu (kế thừa `FLAT_*` của `ui/core/theme.py`, chỉnh cho WCAG AA trên nền trắng)

| Token | Hex | Dùng cho | Nguồn Desktop |
|---|---|---|---|
| `--flat-app-bg` | `#EEF3F9` | Nền ứng dụng | `APP_BG #DCE8F6` (dịu hơn) |
| `--flat-card` | `#FFFFFF` | Thẻ, bảng | `CARD_BG` |
| `--flat-panel` | `#F5F8FC` | Header bảng, vùng phụ | `PANEL_BG #EAF2FA` |
| `--flat-border` | `#E2E8F0` | Viền mảnh | `BORDER_LIGHT` |
| `--flat-text` | `#14293D` | Chữ chính | `TEXT_MAIN` |
| `--flat-title` | `#0B2F55` | Tiêu đề | `TEXT_TITLE #003366` |
| `--flat-muted` | `#4A6882` | Nhãn, chú thích | `TEXT_MUTED` |
| `--flat-primary` / hover | `#1B6EC2` / `#155799` | Hành động chính, tab đang chọn | `PRIMARY` |
| `--flat-success` / bg | `#107C41` / `#E6F4EA` | Hợp lệ, khách nộp, nút Lưu | `SUCCESS` |
| `--flat-danger-strong` / bg | `#CC0000` / `#FDE8E8` | Lỗi cú pháp, tiền trúng, chủ trả | `TEXT_ERROR`, `ERR_BG` |
| `--flat-warning` / line / bg | `#B86200` / `#D87A00` / `#FFF9DB` | App tự sửa, tin bị sửa trên TG | `WARNING`, `SYNTAX_WARN_BG` |
| `--flat-learn` / bg | `#6F42C1` / `#F1EBFB` | Bộ nhớ tự học | `PURPLE` |

Chữ cảnh báo dùng `#B86200` thay vì `#D87A00` (gốc chỉ đạt ~3:1 trên trắng); `#D87A00` giữ cho gạch chân/chấm trạng thái.

Badge loại cược (nền nhạt + chữ đậm, phân biệt được cả khi mù màu nhờ có chữ):

| Đề | Lô | Xiên 2/3/4 | 3 Càng | Xiên quay |
|---|---|---|---|---|
| `#FDE8E8` / `#A61B1B` | `#E6F4EA` / `#0C5E31` | `#E8F1FB` / `#124891` | `#FFF1DC` / `#8A4B00` | `#F1EBFB` / `#59359A` |

### 2.2 Chữ

| Vai trò | Font | Cỡ / độ đậm |
|---|---|---|
| Giao diện | **Be Vietnam Pro** (đã dùng ở `admin/`) | thân 15px/400–600, nhãn 12px/700 IN HOA, tiêu đề 18px/800 |
| Số, tiền, KQXS, ô nhập tin | **JetBrains Mono** + `tabular-nums` (fallback Consolas) | ô nhập 17px, bảng 14.5–16px, KPI 24–28px/800, Giải ĐB 34px/800 |

Mọi con số dùng class `.mono`/`.num` → căn phải, thẳng cột.

### 2.3 Khoảng cách, bo góc, kích thước chạm

- Lưới 4px; padding thẻ 16px; khoảng cách thẻ 12–16px.
- Bo góc: thẻ 14px, control 10px, badge 7px, pill 999px.
- Mọi nút/ô nhập cao **≥ 44px**; viền focus 3px `#8EC5FF` (luôn thấy khi dùng bàn phím).
- Dòng ô nhập tin 30px để highlight không chồng lên nhau.

### 2.4 Phím tắt

| Phím | Hành động | Ghi chú |
|---|---|---|
| `Ctrl + Enter` | Đối soát & Lưu | như Desktop |
| `Alt + R` | Cập nhật KQXS | thay `F5` của Desktop (F5 trình duyệt = tải lại trang) |
| `/` | Focus ô tìm khách | khi không đang gõ |
| `↑ ↓ Enter` | Chọn khách trong danh sách | |
| `Alt + 1…5` (`6` cho admin) | Chuyển màn hình | |
| `Esc` | Đóng hộp thoại / danh sách | như Desktop |
| `?` | Bảng phím tắt | |

Phím tắt in ngay trên nút (`.kbd`) để người dùng học dần.

## 3. Cấu trúc màn hình

| Màn | Bố cục | Thay cho (Desktop) |
|---|---|---|
| Thanh trạng thái (luôn hiện) | Ngày làm việc (viền cam + "Về hôm nay" khi xem ngày cũ) · trạng thái KQXS (Đủ 27 giải / Đang quay / Chưa có) · đồng hồ · vai trò | Thanh trên cửa sổ chính |
| 1. Nhập tin nhanh | Chia đôi 45/55: trái = khách + ngày + mẫu + ô nhập có highlight + "App tự hiểu / Lỗi" + nút; phải = 4 KPI + bảng dòng cược đã bung + tổng | `ZaloInputForm` + console kết quả |
| 2. Telegram | 3 vùng: danh sách tin (lọc Tất cả / Chưa xử lý / Có lỗi / Đã lưu) · tin gốc · dock sửa + xem trước tiền + "Lưu & Dạy app" | `TelegramSyncDialog` + dock |
| 3. Kết quả XSMB | Bảng 27 giải (ĐB 34px đỏ, Nhất xanh, bật/tắt 2 số cuối) · Loto Đầu–Đuôi (số mũ = nháy) · "Lô về nhiều nháy" | Cửa sổ chính (`tree_kq`, `tree_loto`) |
| 4. Khách hàng & Giá | Một lưới duy nhất: Hệ thống (khoá) → Mặc định đại lý → từng khách, sửa trực tiếp trong ô | `KhachHangQuanLyDialog` + `CauHinhDialog` |
| 5. Lịch sử & Chốt sổ | Tab Chốt sổ (KPI ngày + bảng theo khách + phiếu chốt) · Bộ nhớ tự học · Dàn số | `LichSuDoiSoatDialog`, `QuyTacTuHocDialog`, `CuPhapCuocDialog` |
| Admin | Mục "Quản trị hệ thống" chỉ hiện khi role = admin; chỉ số tổng hợp, không lộ nội dung tin | `admin/` hiện có |

## 4. Cải tiến UX so với bản Tkinter

1. **Bố cục chia đôi, không mở cửa sổ con.** Desktop phải mở form Zalo rồi mới thấy console kết quả; web hiện nhập tin và tiền cạnh nhau, cập nhật sau mỗi lần gõ (debounce 300ms như Desktop).
2. **Thẻ "Cân đối" đổi màu.** Trả lời ngay "khách nộp hay chủ trả" thay vì đọc bảng text `build_summary_table`.
3. **Lỗi có thể bấm.** Mỗi lỗi có nút "Nhảy tới" đặt con trỏ đúng đoạn trong ô nhập (Desktop có, nhưng web thêm lời giải thích lỗi bằng tiếng Việt ngay cạnh).
4. **Copy phiếu chốt gửi khách 1 chạm.** Sinh văn bản có emoji/ngắt dòng hợp Zalo/Telegram (Đề/Lô/Xiên, số trúng kèm số nháy, tổng ghi, chiết khấu, "Anh/chị nộp" hoặc "Bên em trả"). Desktop chỉ xuất PDF.
5. **In / Xuất PDF ngay trên trình duyệt** (`window.print` + CSS in riêng phiếu) — bỏ phụ thuộc reportlab.
6. **Lưới bảng giá sửa trực tiếp + thứ tự kế thừa nhìn thấy được.** Ô trống hiện giá đang kế thừa (xám); ô vàng = giá riêng của khách; ô xanh = giá đại lý. Desktop phải mở từng khách một.
7. **Telegram theo luồng "xử lý hàng đợi".** Bộ lọc có đếm số; lưu xong tự nhảy sang tin chưa xử lý kế tiếp; trạng thái tính lại ngay khi app học được từ mới (tin đỏ chuyển xanh).
8. **Dạy app minh bạch.** Sau "Lưu & Dạy", hiện thông báo tím "Bộ nhớ tự học đã ghi nhận: `sien` → `xien`"; quy tắc bị chặn (làm đổi con số) cũng được báo rõ lý do thay vì lặng lẽ bỏ qua.
9. **Cảnh báo trùng bộ cược** nói rõ hậu quả ("Lưu thêm sẽ tính tiền 2 lần") với 3 lựa chọn: Huỷ / Vẫn lưu thêm / Ghi đè tin lúc HH:MM.
10. **Cảnh báo làm việc trên ngày cũ.** Ô ngày viền cam + nút "Về hôm nay" — chống nhập nhầm ngày, lỗi đắt tiền nhất.
11. **Combobox khách dùng hoàn toàn bằng bàn phím** (`/` → gõ → `↑↓` → `Enter` → con trỏ về ô nhập tin).
12. **Phản hồi trạng thái KQXS liên tục** trên mọi màn, thay cho banner chỉ có ở cửa sổ chính.
13. **Chạy được trên điện thoại/máy tính bảng** (≥ 360px): các cột xếp chồng, menu thành thanh tab cuộn ngang, bảng cuộn ngang.

## 5. Ghi chú cho bản thật (khi port vào `src/webapp/app/`)

- Highlight lỗi/vàng phải dùng offset ký tự trả về từ `ajax/phan_tich.php` (PHP port của parser), **không** tự parse ở JS như prototype.
- JS dùng UTF-16: emoji (2 đơn vị) trong tin làm lệch offset so với code point của PHP → chuyển đổi offset ở client.
- Role switcher chỉ có trong prototype; bản thật lấy quyền từ phiên đăng nhập.
- Ghim phiên bản CDN (đã ghim `tailwindcss 3.4.16`, `alpinejs 3.13.5`, Font Awesome `6.5.1`); production nên build Tailwind tĩnh thay vì CDN.
