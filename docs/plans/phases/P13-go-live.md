# P13 — Go-live

| Thuộc tính | Giá trị |
|---|---|
| Slice | S9.1 (rà soát bảo mật), S9.2 (UAT song song Desktop), S9.3 (triển khai), S9.4 (kiểm thử tải) |
| Phụ thuộc | **P12 ĐẠT** |
| Quyết định áp dụng | O3 (VPS + systemd), N13 (CSS build sẵn + Alpine CSP build), N16, N17 |

## 1. Mục tiêu
Đưa hệ thống lên production an toàn: không còn lỗi bảo mật mức Cao, tiền khớp Desktop trên dữ liệu thật, chịu tải mục tiêu, vận hành được (cron, daemon, backup).

## 2. Điều kiện bắt đầu
- [ ] P12 ĐẠT; mọi phase trước ở trạng thái ✅ trong `README.md`.
- [ ] Có VPS production, tên miền, chứng chỉ HTTPS.

## 3. Hạng mục
| Slice | Việc | Đầu ra |
|---|---|---|
| S9.1 | `/security-review` toàn nhánh; skill `check-idor-for-dev` trên `app/**`, `lib/bigwin/repo/**`; `/audit-api`; rà `.htaccess` chặn `tests/`, `workers/`, `var/cache/`, `migrations/`, `tools/` | Biên bản: 0 lỗi Cao, lỗi TB có kế hoạch |
| S9.2 | UAT: 3 đại lý thật × 3 ngày, nhập song song Desktop và Web | Bảng so sánh tổng tiền từng khách/ngày |
| S9.3 | Triển khai theo checklist §4 | Checklist ký duyệt |
| S9.4 | Kiểm thử tải 50 phiên gõ tin đồng thời trên `phan_tich.php` | Báo cáo p95, lỗi |

## 4. Checklist triển khai (S9.3)
**Máy chủ & PHP**
- [ ] Linux + systemd; PHP 8.3 với `intl`, `mbstring`, `sodium`, `curl`, `dom`, `pdo_mysql`, `openssl`.
- [ ] **OPcache bật** (`opcache.enable=1`, `validate_timestamps=1`, `revalidate_freq=2`); APCu nếu có.
- [ ] Document root = `src/webapp/`; HTTPS bắt buộc; `cookie_secure=true`.
- [ ] `cfg('moi_truong') = 'production'` → `ngay_gia_lap` bị bỏ qua; `che_do_debug` tắt.

**Giao diện & CSP**
- [ ] CSS Tailwind build sẵn thành tệp tĩnh; Alpine **CSP build**; bật CSP chặt (không `unsafe-eval`, không `unsafe-inline` cho script).
- [ ] Toàn bộ page chạy được dưới CSP chặt (mở từng page, Console không có vi phạm CSP).

**Dữ liệu**
- [ ] Chạy toàn bộ migration trên DB production; `schema.sql` khớp.
- [ ] Seed `cau_hinh_mac_dinh` (21 khoá), `parser_dan_so_goc` (115 dàn), gói dịch vụ, giấy phép nền tảng.
- [ ] Backup MySQL hằng ngày + thử khôi phục 1 lần.

**Tác vụ nền**
- [ ] Cron: `scrape_xsmb.php` (`*/5 18-19 * * *` + 06:00), `cap_nhat_thue_bao.php` (00:05), `don_cache.php` (hằng ngày).
- [ ] `bigwin-telegram.service` enable + chạy; heartbeat hiện trên UI.

**Tương thích Desktop**
- [ ] `/api/*` hoạt động với app Desktop đã phát hành (gọi thử `check.php`, `backup_*`, `usage_push`).

## 5. Kế hoạch test
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P13-T01 | Toàn bộ bộ test trên máy chủ production-like (DB test riêng) | Xanh |
| P13-T02 | Truy cập trực tiếp `tests/`, `workers/`, `var/cache/`, `migrations/`, `tools/`, `config.php` qua HTTP | 403/404 |
| P13-T03 | Tiêu đề bảo mật trên production (CSP chặt, HSTS) | Có đủ |
| P13-T04 | Tải 50 phiên × 5 phút trên `phan_tich.php` | p95 < 80 ms, 0 lỗi 5xx |
| P13-T05 | UAT S9.2 | Lệch tiền **0 đồng** (trừ lỗi Desktop đã liệt kê §13, có ghi chú) |
| P13-M01 | Người vận hành thực hiện lại checklist §4 theo tài liệu, không cần hỏi dev | Thành công |

## 6. Cổng nghiệm thu (GO / NO-GO)
GO khi: P13-T01..T05, M01 đạt; biên bản S9.1 không còn lỗi Cao; chủ dự án ký duyệt. Ngược lại NO-GO, ghi lý do và phase cần mở lại.

## 7. Kế hoạch lùi (rollback)
- Web mới chạy độc lập với `/api/` → tắt `app/` (trả trang bảo trì) không ảnh hưởng app Desktop.
- Backup DB trước khi migration production; giữ bản backup ít nhất 30 ngày.

## 8. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
