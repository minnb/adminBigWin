// Khung app BigWIN (P03 S1.6) — dùng với app/_layout.php + Alpine 3.13.5.
// goiApi() là đường DUY NHẤT gọi server (rule security-core.md; tests/app_js_scan_test.php — P03-T08): luôn POST JSON,
// gắn X-CSRF-Token từ <meta name="csrf-token">; 401 → về trang đăng nhập; lỗi khác ném Error có .ma (HTTP) và .duLieu (JSON).
'use strict';

async function goiApi(duongDan, duLieu = {}) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
  let r;
  try {
    r = await fetch(duongDan, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify(duLieu),
    });
  } catch (_) {
    throw Object.assign(new Error('Không kết nối được máy chủ — kiểm tra mạng rồi thử lại.'), { ma: 0, duLieu: null });
  }
  const json = await r.json().catch(() => null);
  if (r.status === 401) {
    window.location.href = window.BW_KHUNG?.urlDangNhap ?? '/login.php';
  }
  if (!r.ok || json?.ok !== true) {
    throw Object.assign(new Error(json?.thong_diep ?? `Lỗi máy chủ (${r.status}).`), { ma: r.status, duLieu: json });
  }
  return json;
}
window.goiApi = goiApi;

/** Cấu hình khung do _layout.php in ở <body data-khung="…"> (không dùng <script> nội tuyến — sẵn cho CSP chặt S9.3). */
window.BW_KHUNG = JSON.parse(document.body?.dataset.khung ?? '{}');

/** x-data của <body>: đồng hồ, phím tắt, menu tài khoản, modal Phím tắt, modal Đổi mật khẩu. */
window.bwKhung = function bwKhung() {
  return {
    gio: '',
    help: false,
    menu: false,
    modalMk: false,
    mk: { mat_khau_cu: '', mat_khau_moi: '', xac_nhan: '' },
    loiMk: {},
    thongBaoMk: '',
    dangGuiMk: false,

    init() {
      const tick = () => { this.gio = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); };
      tick();
      setInterval(tick, 1000);
      window.addEventListener('keydown', (e) => this.phim(e));
    },

    phim(e) {
      const dangGo = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
      if (e.key === 'Escape') { this.help = false; this.menu = false; this.dongMk(); return; }
      if (e.altKey && /^[1-7]$/.test(e.key)) {
        const dich = window.BW_KHUNG.trang?.[+e.key - 1];
        if (dich) { e.preventDefault(); window.location.href = dich; }
        return;
      }
      if (!dangGo && e.key === '?') { this.help = true; }
    },

    moMk() {
      this.menu = false;
      this.mk = { mat_khau_cu: '', mat_khau_moi: '', xac_nhan: '' };
      this.loiMk = {};
      this.thongBaoMk = '';
      this.modalMk = true;
      this.$nextTick(() => this.$refs.mkCu?.focus());
    },

    dongMk() { if (!this.dangGuiMk) { this.modalMk = false; } },

    async doiMatKhau() {
      this.dangGuiMk = true;
      this.loiMk = {};
      this.thongBaoMk = '';
      try {
        const kq = await goiApi(window.BW_KHUNG.urlDoiMatKhau, this.mk);
        this.thongBaoMk = kq.thong_diep;
        this.mk = { mat_khau_cu: '', mat_khau_moi: '', xac_nhan: '' };
      } catch (e) {
        this.loiMk = e.duLieu?.loi_truong ?? { chung: e.message };
      } finally {
        this.dangGuiMk = false;
      }
    },
  };
};
