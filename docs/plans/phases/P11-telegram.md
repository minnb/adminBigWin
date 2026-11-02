# P11 — Telegram (daemon systemd)

| Thuộc tính | Giá trị |
|---|---|
| Slice | S7.1 (migration + daemon), S7.2 (`TelegramService` + kết nối/cấu hình), S7.3 (page Telegram), S7.4 (ghi DB + tổng kết + nhật ký) |
| Phụ thuộc | **P08 ĐẠT** + **máy chủ Linux có systemd** sẵn sàng (O3) — chạy song song được với P09–P10 |
| Mở khoá | P12 |
| Quyết định áp dụng | Q1, O3, O1, O2, N2, N3, Q6 (không Gemini) |
| Thiết kế chuẩn | Preview `#telegram` · luồng §8.6 · Phụ lục A-TG |
| Cỡ | L + M + L + M |

## 1. Mục tiêu
Đại lý kết nối tài khoản Telegram của mình, đồng bộ tin theo khách/ngày/chiều, rà soát master–detail, sửa tin, ghi vào sổ — **web không bao giờ chờ Telethon**; daemon độc lập, khởi động lại không mất việc.

## 2. Điều kiện bắt đầu
- [ ] P08 ĐẠT.
- [ ] Có VPS/máy Linux test với systemd, Python 3.11+, truy cập MySQL test.
- [ ] Có tài khoản Telegram thử nghiệm (không dùng tài khoản thật của đại lý trong test).

## 3. Phạm vi — slice & tệp
| Slice | Tệp | Ghi chú |
|---|---|---|
| S7.1 | `migrations/2026-11-xx_telegram.sql`, `schema.sql`, `workers/telegram/worker.py`, `requirements.txt`, `bigwin-telegram.service`, `README.md` | `telegram_tai_khoan` (session mã hoá), `telegram_jobs` (+`tien_do`, `tong`, `heartbeat_luc`), `telegram_inbox`, `telegram_sync_log`, `telegram_worker_trang_thai`; worker: `FOR UPDATE SKIP LOCKED`, heartbeat 15 s, SIGTERM dừng êm, job dở quay về `cho`; DB user chỉ quyền `telegram_*`; kiểm `cho_phep_telegram` + thuê bao |
| S7.2 | `lib/bigwin/TelegramService.php`, `lib/bigwin/repo/TelegramRepo.php`, `app/ajax/telegram_job.php` | Modal Kết nối: SĐT → OTP → 2FA **chỉ khi** worker trả `2fa_required`; 4 trạng thái đăng nhập; modal Cấu hình: API ID, API Hash (che), Session — **gỡ ô Gemini** khỏi preview |
| S7.3 | `app/telegram.php`, `app/assets/telegram.js`, `app/ajax/telegram_inbox.php` | 5 điều kiện trước đồng bộ; master 4 trạng thái (OK / NGHI NGỜ / PARSE_ERROR / LỖI) + tooltip lý do; băng "tin cần chú ý" (tách lỗi/nghi ngờ, tự tắt lọc khi hết); dock 3 bố cục; Parse thử; Lưu tin & cập nhật (**cho lưu cả khi còn lỗi**, chặn khi rỗng, luôn học); khoá giao diện khi đang chạy |
| S7.4 | `app/ajax/telegram_ghi.php` (+ sửa S7.3) | Modal xác nhận có số liệu thật; transaction xoá mềm phiên TELEGRAM cũ theo **ngày + khách** rồi ghi lại với snapshot; tổng kết: thành công = OK + nghi ngờ, bỏ qua = lỗi cú pháp, lỗi = exception; nhật ký thu gọn; tiến trình [i/n] |

## 4. Kế hoạch test
### 4.1 Tự động (PHP — không cần Telegram thật; worker giả lập ghi `telegram_jobs`/`telegram_inbox`)
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P11-T01 | Tạo job `fetch` | Trả `job_id` < 50 ms; không gọi mạng |
| P11-T02 | Tạo job khi `cho_phep_telegram = 0` / thuê bao hết hạn | 402 |
| P11-T03 | Tạo job khi heartbeat worker > 60 s | 503 "Dịch vụ Telegram đang tạm ngừng" |
| P11-T04 | Điều kiện trước đồng bộ: khách không có Telegram ID / Max ≤ 0 / chưa đăng nhập TG / chưa có KQ | 422 từng trường hợp |
| P11-T05 | Poll `telegram_job.php` của tenant khác | 404 |
| P11-T06 | Phân loại 4 trạng thái trên bộ tin mẫu | Đúng |
| P11-T07 | Ghi DB: dữ liệu cũ cùng ngày + khách | Xoá mềm `dong_bo_lai`; khác ngày / khác khách **không** đụng |
| P11-T08 | Ghi DB có tin lỗi phát sinh khi tính lại | Không ghi gì |
| P11-T09 | Tổng kết đếm đúng 3 nhóm | Đúng |
| P11-T10 | Thành tiền & nhãn ở dock, thanh tổng, 2 modal theo N2 | Đúng dấu |
| P11-T11 | Hạn mức tin/ngày khi ghi lô Telegram | Vượt → 402, không ghi phần nào |
| P11-T12 | `idor_test` cho `TelegramRepo` | Xanh |
| P11-T13 | Quét mã: modal cấu hình không còn `gemini` | 0 |
| P11-T14 | Hồi quy P00–P10 (đã đạt) | Xanh |

### 4.2 Tự động (worker — `pytest` trong `workers/telegram/tests/`)
| Mã | Kiểm tra | Kỳ vọng |
|---|---|---|
| P11-W01 | 2 worker cùng lấy job | Mỗi job chỉ 1 worker nhận (`SKIP LOCKED`) |
| P11-W02 | SIGTERM giữa job | Job về `cho`, không mất, không ghi dở inbox |
| P11-W03 | Tin trùng `(user_id, peer_id, telegram_msg_id)` | Không nhân bản; cập nhật `edit_date` |
| P11-W04 | Session lưu DB | Đã mã hoá; khoá giải mã đọc từ tệp ngoài repo |
| P11-W05 | Worker không import module parse/tính tiền | Đúng (quét import) |

### 4.3 Thủ công (trên VPS test)
- [ ] P11-M01: `systemctl start/stop/restart bigwin-telegram` — trạng thái worker trên UI cập nhật; restart không mất job.
- [ ] P11-M02: Kết nối tài khoản TG thử: có 2FA và không 2FA.
- [ ] P11-M03: Đồng bộ 1 khách 1 ngày (Nhận về / Gửi đi) → tiến trình [i/n] chạy; giao diện khoá khi đang chạy.
- [ ] P11-M04: Sửa tin lỗi ở dock → lưu → trạng thái đổi; ghi DB → Chốt sổ có số.
- [ ] P11-M05: **So Desktop:** cùng khách, cùng ngày → tổng tiền khớp.

## 5. Cổng nghiệm thu
README §2 **và**: P11-T01..T14, W01..W05 xanh; Phụ lục A-TG tick đủ; `README.md` vận hành worker được người vận hành đọc và làm theo thành công.

## 6. Rủi ro & xử lý
| Rủi ro | Xử lý |
|---|---|
| Không có VPS đúng hạn | Phase tách rời; các phase khác không chờ; go-live có thể bật Telegram sau (cờ tính năng) |
| Telegram giới hạn tần suất (FloodWait) | Worker tôn trọng thời gian chờ, ghi vào `ket_qua_json`, UI hiển thị |
| Lộ session Telegram | Mã hoá bằng `lib/crypto.php`/sodium, khoá ngoài repo, DB user quyền tối thiểu |

## 7. Ngoài phạm vi
Gemini AI (Q6); Bot API.

## 8. Nhật ký
| Ngày | Người | Nội dung | Kết quả |
|---|---|---|---|
| | | | |
