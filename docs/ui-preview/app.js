// Logic prototype BigWIN Web (Alpine.js). Bộ parse ở đây là BẢN GIẢ LẬP RÚT GỌN để demo
// giao diện — bản thật là port PHP của core/messageParser.py (xem WEB_MIGRATION_PLAN.md).
// Công thức tiền dùng ĐÚNG quy tắc đã xác minh ở §4.2: int(tiền × hệ số), cắt về 0.

const M = window.BW_MOCK;
const BIEN = '(?<![\\p{L}\\d])';
const HET = '(?![\\p{L}\\d])';
const NHAN_LOAI = { DE: 'Đề', LO: 'Lô', '3C': '3 Càng', LX2: 'Xiên 2', LX3: 'Xiên 3', LX4: 'Xiên 4' };
const KHOA_TRUNG = { DE: 'he_so_de', LO: 'he_so_lo', '3C': 'he_so_3c', LX2: 'he_so_lx2', LX3: 'he_so_lx3', LX4: 'he_so_lx4' };
const KHOA_GHI = { DE: 'he_so_ghi_de', LO: 'he_so_ghi_lo', '3C': 'he_so_ghi_3c', LX2: 'he_so_ghi_lx2', LX3: 'he_so_ghi_lx3', LX4: 'he_so_ghi_lx4' };
const KHOA_CK = { DE: 'ti_le_chiet_khau_de', LO: 'ti_le_chiet_khau_lo', '3C': 'ti_le_chiet_khau_3c', LX2: 'ti_le_chiet_khau_lx2', LX3: 'ti_le_chiet_khau_lx3', LX4: 'ti_le_chiet_khau_lx4' };
const SO_HOC_SAN = new Set(['lít', 'lit', 'chục', 'chuc', 'củ', 'cu']);

const fmt = (n) => Math.trunc(n).toLocaleString('vi-VN');
const esc = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const z2 = (n) => String(n).padStart(2, '0');

function toHop(arr, k) {
  const out = [];
  const go = (start, cur) => {
    if (cur.length === k) { out.push(cur.slice()); return; }
    for (let i = start; i < arr.length; i++) { cur.push(arr[i]); go(i + 1, cur); cur.pop(); }
  };
  go(0, []);
  return out;
}

/** Giả lập HeuristicCleaner + MessageParser trên chuỗi thô. Offset theo ký tự của tin GỐC. */
function phanTich(raw, tuHoc) {
  const marks = [];
  const dich = [];
  let s = raw;
  const thay = (re, fn, loai = 'warn') => {
    s = s.replace(re, (...a) => {
      const m = a[0];
      const moi = fn(...a);
      if (moi.toLowerCase() !== m.toLowerCase()) {
        dich.push({ goc: m.trim(), moi: moi.trim() });
        let from = 0;
        let idx;
        while ((idx = raw.indexOf(m, from)) !== -1 && marks.some((k) => k.start === idx)) from = idx + 1;
        if (idx !== -1) marks.push({ start: idx, end: idx + m.length, type: loai, msg: `App tự hiểu “${m.trim()}” thành “${moi.trim()}”` });
      }
      return moi;
    });
  };
  // Tiếng lóng số tiền (luật cứng của cleaner)
  thay(new RegExp(`${BIEN}(\\d+)\\s*(?:lít|lit)${HET}`, 'giu'), (_, n) => `${n}00k`);
  thay(new RegExp(`${BIEN}(\\d+)\\s*(?:chục|chuc)${HET}`, 'giu'), (_, n) => `${n}0k`);
  thay(new RegExp(`${BIEN}(\\d+)\\s*(?:củ|cu)${HET}`, 'giu'), (_, n) => `${n}tr`);
  // Từ khoá có dấu + bộ nhớ tự học (theo từ)
  for (const r of tuHoc) {
    if (SO_HOC_SAN.has(r.goc.toLowerCase())) continue;
    const re = new RegExp(`${BIEN}${r.goc.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}${HET}`, 'giu');
    thay(re, () => r.moi);
  }
  thay(new RegExp(`${BIEN}(?:đề|đê)${HET}`, 'giu'), () => 'de', 'none');
  thay(new RegExp(`${BIEN}lô${HET}`, 'giu'), () => 'lo', 'none');
  thay(new RegExp(`${BIEN}xiên${HET}`, 'giu'), () => 'xien', 'none');
  for (let i = marks.length - 1; i >= 0; i--) if (marks[i].type === 'none') marks.splice(i, 1);
  for (let i = dich.length - 1; i >= 0; i--) if (['de', 'lo', 'xien'].includes(dich[i].moi)) dich.splice(i, 1);

  const sach = s.toLowerCase().replace(/[ \t]+/g, ' ');
  const loi = [];
  const bets = [];
  let conTro = 0;
  const danhDauLoi = (doan, msg) => {
    const idx = raw.toLowerCase().indexOf(doan.toLowerCase(), conTro);
    const start = idx === -1 ? raw.toLowerCase().indexOf(doan.toLowerCase()) : idx;
    if (start === -1) { loi.push({ msg, text: doan, start: -1, end: -1 }); return; }
    marks.push({ start, end: start + doan.length, type: 'err', msg });
    loi.push({ msg, text: doan, start, end: start + doan.length });
  };

  const SEG = /(de|lo|xien|xq|3c)\s+((?:bo|cham|tong|kep)?[\s\d,.\-]*?)\s*x\s*(\d+)\s*(tr|k|n|d)?(?![\p{L}\d])/gu;
  let cuoi = 0;
  let m;
  while ((m = SEG.exec(sach)) !== null) {
    const thua = sach.slice(cuoi, m.index).trim();
    if (thua) thua.split(/\n+/).map((x) => x.trim()).filter(Boolean).forEach((x) => danhDauLoi(x, 'Không hiểu đoạn này — thiếu loại cược hoặc tiền'));
    cuoi = m.index + m[0].length;
    const loai = m[1];
    let phan = m[2].trim();
    let mod = '';
    const mm = phan.match(/^(bo|cham|tong|kep)\s*/);
    if (mm) { mod = mm[1]; phan = phan.slice(mm[0].length); }
    const tok = phan.split(/[\s,.\-]+/).filter(Boolean);
    const v = parseInt(m[3], 10);
    const dv = m[4] || '';
    const rawSeg = raw.toLowerCase().indexOf(m[3], conTro);
    const tien = loai === 'lo' ? v : (dv === 'tr' ? v * 1000000 : v * 1000);
    const nhanTien = loai === 'lo' ? `${v} điểm` : `${fmt(tien)}`;

    const mo2 = (t) => {
      if (/^\d$/.test(t)) return [z2(t)];
      if (/^\d\d$/.test(t)) return [t];
      if (/^\d{3}$/.test(t) && t[0] === t[2]) return [t.slice(0, 2), t.slice(1)];
      return null;
    };

    if (loai === 'de' || loai === 'lo') {
      const L = loai === 'de' ? 'DE' : 'LO';
      let so = [];
      if (mod === 'kep') so = [...Array(10).keys()].map((i) => `${i}${i}`);
      else if (mod === 'bo' && /^\d\d$/.test(tok[0] || '')) {
        const [a, b] = [+tok[0][0], +tok[0][1]];
        const A = [a, (a + 5) % 10]; const B = [b, (b + 5) % 10];
        const set = []; for (const x of A) for (const y of B) for (const p of [`${x}${y}`, `${y}${x}`]) if (!set.includes(p)) set.push(p);
        so = set;
      } else if (mod === 'cham' && /^\d$/.test(tok[0] || '')) {
        const d = tok[0]; const set = [];
        for (let i = 0; i < 10; i++) for (const p of [`${i}${d}`, `${d}${i}`]) if (!set.includes(p)) set.push(p);
        so = set;
      } else if (mod === 'tong' && /^\d+$/.test(tok[0] || '')) {
        const n = +tok[0];
        for (let i = 0; i < 100; i++) { const a = Math.floor(i / 10); const b = i % 10; if (n === 10 ? a + b === 10 : (a + b) % 10 === n) so.push(z2(i)); }
      } else {
        for (const t of tok) {
          const r = mo2(t);
          if (r) so.push(...r); else danhDauLoi(t, `“${t}” không phải số ${L === 'DE' ? 'đề' : 'lô'} hợp lệ (2 chữ số, hoặc 3 chữ số dạng ABA)`);
        }
      }
      if (L === 'LO' && dv === 'tr') danhDauLoi(m[3], `Lô tính theo ĐIỂM — “${m[3]} triệu” không hợp lệ cho lô`);
      const dan = mod ? `${mod === 'bo' ? 'Bộ' : mod === 'cham' ? 'Chạm' : mod === 'tong' ? 'Tổng' : 'Kép'} ${tok[0] || ''}`.trim() : '';
      so.forEach((x) => bets.push({ loai: L, so: [x], tien, nhanTien, dan }));
    } else if (loai === '3c') {
      for (const t of tok) {
        if (/^\d{1,3}$/.test(t)) bets.push({ loai: '3C', so: [t.padStart(3, '0')], tien, nhanTien, dan: '' });
        else danhDauLoi(t, `3 Càng cần tối đa 3 chữ số — “${t}” có ${t.length} chữ số`);
      }
    } else {
      const so = [];
      for (const t of tok) { const r = mo2(t); if (r) r.forEach((x) => { if (!so.includes(x)) so.push(x); }); else danhDauLoi(t, `“${t}” không phải số xiên hợp lệ`); }
      if (loai === 'xien') {
        if (so.length >= 2 && so.length <= 4) bets.push({ loai: `LX${so.length}`, so, tien, nhanTien, dan: '' });
        else danhDauLoi(m[0].trim(), `Xiên cần 2–4 số, đang có ${so.length}`);
      } else if (so.length < 2) danhDauLoi(m[0].trim(), 'Xiên quay cần ít nhất 2 số');
      else for (let k = 2; k <= Math.min(4, so.length); k++) toHop(so, k).forEach((c) => bets.push({ loai: `LX${k}`, so: c, tien, nhanTien, dan: 'Xiên quay' }));
    }
    if (rawSeg !== -1) conTro = rawSeg;
  }
  const duoi = sach.slice(cuoi).trim();
  if (duoi) duoi.split(/\n+/).map((x) => x.trim()).filter(Boolean).forEach((x) => danhDauLoi(x, 'Không hiểu đoạn này — thiếu loại cược hoặc tiền'));

  marks.sort((a, b) => a.start - b.start);
  return { marks, dich, loi, bets, sach: s, canSave: loi.length === 0 && bets.length > 0 };
}

function veHighlight(raw, marks) {
  let html = '';
  let pos = 0;
  for (const k of marks) {
    if (k.start < pos) continue;
    html += esc(raw.slice(pos, k.start));
    html += `<mark class="hl-${k.type}">${esc(raw.slice(k.start, k.end))}</mark>`;
    pos = k.end;
  }
  return html + esc(raw.slice(pos)) + '\n';
}

function bw() {
  return {
    // ── trạng thái chung ──
    man: 'nhap',
    role: 'user',
    ngay: M.homNay,
    gio: '',
    kqDangTai: false,
    kqCapNhatLuc: '17:35',
    toasts: [],
    modal: null,
    help: false,
    kqData: JSON.parse(JSON.stringify(M.KQ)),

    // ── dữ liệu ──
    khach: JSON.parse(JSON.stringify(M.KHACH_HANG)),
    daiLy: {},
    tuHoc: JSON.parse(JSON.stringify(M.TU_HOC)),
    danSo: JSON.parse(JSON.stringify(M.DAN_SO)),
    daLuu: M.DA_LUU.map((x) => ({ ...x, bets: x.bets.map(([loai, so, tien]) => ({ loai, so, tien })) })),
    tg: JSON.parse(JSON.stringify(M.TELEGRAM_TIN)),

    // ── màn nhập tin ──
    khachId: 1,
    timKhach: '',
    moKhach: false,
    khachIdx: 0,
    tin: M.MAU_TIN[0].text,
    kq: null,
    dangPhanTich: false,
    _timer: null,
    luuTrung: null,

    // ── telegram (chuẩn theo telegram_forms.py) ──
    tgKetNoi: true,
    tgBuoc: 'sdt',
    tgPhone: '0903 112 445',
    tgOtp: '',
    tg2fa: '',
    tgConfig: JSON.parse(JSON.stringify(M.TG_CONFIG || { api_id: '2981726', api_hash: 'c8f12a3b90e1f728c89b27182903ab81', session_name: 'bigwin_telethon', gemini_api_key: 'AIzaSyA8B9123-Kj98234-Mn76231' })),
    tgShowMaskedHash: false,
    tgShowMaskedGemini: false,

    tgKhachId: 1,
    tgChieu: 'in', // 'in': Nhận về, 'out': Gửi đi
    tgMaxTin: 2000,
    tgDangDongBo: false,
    tgTimerStr: '⏱ 00s',
    _tgTimerJob: null,
    _tgTimerStart: 0,

    tgDanhSach: [],
    tgChiXemLoi: false,
    tgTinChon: null,
    tgDockText: '',
    tgDockKetQua: null,
    tgDockChiTiet: [],
    tgPreviewDaSanSang: false,
    tgDockLayout: 'split', // 'split' (chia trên/dưới tự co giãn), 'side' (song song 2 cột), 'max' (phóng to editor)
    tgChanDoanMo: true, // Cho phép gấp gọn chẩn đoán lỗi

    tgSummaryData: null,
    tgTongHopBets: [],
    tgBanTgList: M.USERS_CHATTED_TODAY || [],

    // ── kqxs / khách / lịch sử ──
    chi2So: false,
    timKH: '',
    lsTab: 'chot',
    lsMo: null,
    timTuHoc: '',
    themGoc: '',
    themMoi: '',

    MAU: M.MAU_TIN,
    COT: M.COT_HE_SO,
    cotPhang: M.COT_HE_SO.flatMap((g) => g.cot.map(([k, nhan], i) => ({ k, nhan, dau: i === 0 }))),
    NHAN_LOAI,

    init() {
      const tick = () => { this.gio = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); };
      tick(); setInterval(tick, 1000);
      // Deep link để review: #telegram, #kqxs, #khach, #lichsu, #tuhoc, #admin, #mau3 …
      const h = location.hash.slice(1);
      if (['nhap', 'telegram', 'kqxs', 'khach', 'lichsu'].includes(h)) this.man = h;
      if (h === 'tuhoc' || h === 'dan') { this.man = 'lichsu'; this.lsTab = h; }
      if (h === 'admin') { this.role = 'admin'; this.man = 'admin'; }
      if (/^mau[1-4]$/.test(h)) this.tin = M.MAU_TIN[+h[3] - 1].text;
      if (h === 'lichsu') this.lsMo = 5;
      this.phanTichNgay();
      this.batDauDongBoTg(true);
      window.addEventListener('keydown', (e) => this.phim(e));
    },

    // ═════════ phím tắt ═════════
    phim(e) {
      const dangGo = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
      if (e.key === 'Escape') { this.modal = null; this.help = false; this.moKhach = false; return; }
      if (e.ctrlKey && e.key === 'Enter' && this.man === 'nhap') { e.preventDefault(); this.luu(); return; }
      if (e.altKey && (e.key === 'r' || e.key === 'R')) { e.preventDefault(); this.capNhatKQ(); return; }
      if (e.altKey && /^[1-6]$/.test(e.key)) {
        e.preventDefault();
        const ds = ['nhap', 'telegram', 'kqxs', 'khach', 'lichsu', 'admin'];
        const dich = ds[+e.key - 1];
        if (dich !== 'admin' || this.role === 'admin') this.man = dich;
        return;
      }
      if (!dangGo && e.key === '/' && this.man === 'nhap') { e.preventDefault(); this.$refs.oKhach?.focus(); this.moKhach = true; return; }
      if (!dangGo && e.key === '?') { this.help = true; }
    },

    // ═════════ toast ═════════
    bao(msg, loai = 'ok', icon = 'fa-circle-check') {
      const id = Date.now() + Math.random();
      this.toasts.push({ id, msg, loai, icon });
      setTimeout(() => { this.toasts = this.toasts.filter((t) => t.id !== id); }, 4200);
    },

    // ═════════ ngày & KQ ═════════
    get laHomNay() { return this.ngay === M.homNay; },
    get ngayHienThi() { const [y, m, d] = this.ngay.split('-'); return `${d}/${m}/${y}`; },
    get kqNgay() { return this.kqData[this.ngay] || null; },
    get trangThaiKQ() {
      if (this.kqDangTai) return { ma: 'dang', nhan: 'Đang cập nhật…', cls: 'pill-info' };
      if (this.kqNgay) return { ma: 'du', nhan: 'Đủ 27 giải', cls: 'pill-ok' };
      if (this.laHomNay) return { ma: 'quay', nhan: 'Đang quay', cls: 'pill-warn' };
      return { ma: 'chua', nhan: 'Chưa có KQ', cls: 'pill-err' };
    },
    get loto() {
      if (!this.kqNgay) return [];
      const out = [];
      this.kqNgay.forEach(([key, ten, ds]) => ds.forEach((so) => out.push({ key, ten, so, hai: so.slice(-2) })));
      return out;
    },
    get dauDuoi() {
      const dem = {};
      this.loto.forEach((x) => { dem[x.hai] = (dem[x.hai] || 0) + 1; });
      return [...Array(10).keys()].map((d) => ({
        dau: d,
        duoi: Object.keys(dem).filter((k) => +k[0] === d).sort().map((k) => ({ so: k, n: dem[k], db: this.kqNgay && this.kqNgay[0][2][0].slice(-2) === k })),
      }));
    },
    get loVeNhieu() {
      const dem = {};
      this.loto.forEach((x) => { dem[x.hai] = (dem[x.hai] || 0) + 1; });
      return Object.entries(dem).filter(([, n]) => n >= 2).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]));
    },
    capNhatKQ() {
      if (this.kqDangTai) return;
      this.kqDangTai = true;
      setTimeout(() => {
        this.kqDangTai = false;
        if (!this.kqData[this.ngay]) {
          if (this.laHomNay) this.kqData[this.ngay] = M.KQ[M.homNay];
          else { this.bao(`Nguồn minhngoc chưa có KQ ngày ${this.ngayHienThi}`, 'err', 'fa-circle-xmark'); return; }
        }
        this.kqCapNhatLuc = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
        this.bao(`Đã cập nhật KQXS ${this.ngayHienThi} — đủ 27 giải`, 'ok', 'fa-trophy');
        this.phanTichNgay();
      }, 1400);
    },

    // ═════════ hệ số 3 tầng ═════════
    heSoHeThong(k) { return M.HE_SO_MAC_DINH[k]; },
    heSoDaiLy(k) { return this.daiLy[k] ?? M.HE_SO_MAC_DINH[k]; },
    heSo(kh, k) { return kh?.he_so?.[k] ?? this.heSoDaiLy(k); },
    nguonHeSo(kh, k) { return kh?.he_so?.[k] !== undefined ? 'khach' : (this.daiLy[k] !== undefined ? 'daily' : 'heThong'); },
    setHeSo(kh, k, v) {
      const val = v === '' ? undefined : Number(String(v).replace(',', '.'));
      if (kh) { if (val === undefined || Number.isNaN(val)) delete kh.he_so[k]; else kh.he_so[k] = val; }
      else if (val === undefined || Number.isNaN(val)) delete this.daiLy[k]; else this.daiLy[k] = val;
      this.phanTichNgay();
    },

    // ═════════ khách hàng ═════════
    get khachChon() { return this.khach.find((k) => k.id === this.khachId) || null; },
    get khachLoc() {
      const q = this.timKhach.trim().toLowerCase();
      return this.khach.filter((k) => !q || k.ten.toLowerCase().includes(q) || k.telegram.toLowerCase().includes(q));
    },
    tenKhach(id) { return this.khach.find((k) => k.id === id)?.ten || '—'; },
    chonKhach(k) { this.khachId = k.id; this.timKhach = ''; this.moKhach = false; this.phanTichNgay(); },
    phimKhach(e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); this.khachIdx = Math.min(this.khachIdx + 1, this.khachLoc.length - 1); }
      if (e.key === 'ArrowUp') { e.preventDefault(); this.khachIdx = Math.max(this.khachIdx - 1, 0); }
      if (e.key === 'Enter') { e.preventDefault(); const k = this.khachLoc[this.khachIdx]; if (k) { this.chonKhach(k); this.$refs.oTin.focus(); } }
    },
    themKhachNhanh() {
      const ten = prompt('Tên khách mới:');
      if (!ten) return;
      const id = Math.max(...this.khach.map((k) => k.id)) + 1;
      this.khach.push({ id, ten: ten.trim(), zalo: '', telegram: '', he_so: {} });
      this.khachId = id;
      this.bao(`Đã thêm khách “${ten.trim()}” — dùng giá mặc định của đại lý`, 'ok', 'fa-user-plus');
    },
    goiYHeSo(kh) {
      const ck = this.heSo(kh, 'ti_le_chiet_khau_de');
      const lo = this.heSo(kh, 'he_so_ghi_lo');
      return `CK đề ${ck}% · Lô ${fmt(lo)}/đ`;
    },

    // ═════════ nhập tin & phân tích ═════════
    goTin() {
      clearTimeout(this._timer);
      this.dangPhanTich = true;
      this._timer = setTimeout(() => this.phanTichNgay(), 300);
    },
    phanTichNgay() {
      this.kq = phanTich(this.tin, this.tuHoc);
      this.dangPhanTich = false;
    },
    dungMau(m) { this.tin = m.text; this.phanTichNgay(); this.$nextTick(() => this.$refs.oTin?.focus()); },
    get highlight() { return veHighlight(this.tin, this.kq?.marks || []); },
    dongBo(e) { this.$refs.lop.scrollTop = e.target.scrollTop; },
    nhayToi(l) {
      if (l.start < 0) return;
      const el = this.$refs.oTin;
      el.focus();
      el.setSelectionRange(l.start, l.end);
      const dong = this.tin.slice(0, l.start).split('\n').length - 1;
      el.scrollTop = Math.max(0, dong * 30 - 40);
      this.dongBo({ target: el });
    },

    tinhDong(b, kh, kqNgay = this.kqNgay) {
      const heGhi = this.heSo(kh, KHOA_GHI[b.loai]);
      const tiLe = this.heSo(kh, KHOA_CK[b.loai]);
      const ghi = Math.trunc(b.tien * heGhi);
      const ck = Math.trunc(tiLe * b.tien / 100);
      let soLan = 0;
      const giai = [];
      if (kqNgay) {
        const tat = [];
        kqNgay.forEach(([, ten, ds]) => ds.forEach((so) => tat.push({ ten, so })));
        const db = kqNgay[0][2][0];
        if (b.loai === 'DE') { if (db.slice(-2) === b.so[0]) { soLan = 1; giai.push('ĐB'); } }
        else if (b.loai === '3C') { if (db.slice(-3) === b.so[0]) { soLan = 1; giai.push('ĐB'); } }
        else {
          const dem = b.so.map((s) => tat.filter((x) => x.so.slice(-2) === s));
          soLan = Math.min(...dem.map((d) => d.length));
          if (soLan > 0) dem.flat().forEach((x) => { const t = x.ten.replace('Giải ', 'G.'); if (!giai.includes(t)) giai.push(t); });
        }
      }
      const he = this.heSo(kh, KHOA_TRUNG[b.loai]);
      const trung = soLan === 0 ? 0 : (['DE', '3C'].includes(b.loai) ? Math.trunc(b.tien * he) : Math.trunc(b.tien * soLan * he));
      return { ...b, ghi, ck, soLan, giai, trung, tiLe };
    },
    get dong() { return (this.kq?.bets || []).map((b) => this.tinhDong(b, this.khachChon)); },
    tong(ds) {
      const t = ds.reduce((a, d) => ({ ghi: a.ghi + d.ghi, ck: a.ck + d.ck, trung: a.trung + d.trung }), { ghi: 0, ck: 0, trung: 0 });
      t.thuc = t.ghi - t.ck;
      t.can = t.thuc - t.trung;
      return t;
    },
    get kpi() { return this.tong(this.dong); },
    get demLoai() {
      const d = {};
      this.dong.forEach((x) => { d[x.loai] = (d[x.loai] || 0) + 1; });
      return d;
    },
    chuKy(bets) { return bets.map((b) => `${b.loai}:${b.so.join(',')}:${b.tien}`).sort().join('|'); },

    luu(boQuaTrung = false) {
      if (!this.kq) this.phanTichNgay();
      if (!this.kqNgay) { this.bao(`Chưa có KQXS ngày ${this.ngayHienThi} — bấm Cập nhật KQ (Alt+R) trước`, 'err', 'fa-circle-xmark'); return; }
      if (!this.khachChon) { this.moKhach = true; this.$refs.oKhach?.focus(); this.bao('Chọn khách trước khi lưu', 'warn', 'fa-user'); return; }
      if (!this.kq.canSave) { this.bao(`Tin còn ${this.kq.loi.length} lỗi — sửa phần tô đỏ trước khi lưu`, 'err', 'fa-triangle-exclamation'); return; }
      const ck = this.chuKy(this.kq.bets);
      const trung = this.daLuu.find((p) => p.khach_id === this.khachId && this.chuKy(p.bets) === ck);
      if (trung && !boQuaTrung) { this.luuTrung = trung; this.modal = 'trung'; return; }
      const gio = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
      this.daLuu.push({ khach_id: this.khachId, gio, nguon: 'Nhập tay', text: this.tin, bets: this.kq.bets.map(({ loai, so, tien }) => ({ loai, so, tien })) });
      const t = this.kpi;
      this.bao(`Đã lưu ${this.kq.bets.length} dòng cược cho ${this.khachChon.ten} · ${t.can >= 0 ? 'Khách nộp' : 'Chủ trả'} ${fmt(Math.abs(t.can))}đ`, 'ok', 'fa-floppy-disk');
    },
    ghiDe() {
      this.daLuu = this.daLuu.filter((p) => p !== this.luuTrung);
      this.modal = null;
      this.luu(true);
    },
    gaLapTrung() {
      this.luuTrung = { gio: '17:42', khach_id: this.khachId };
      this.modal = 'trung';
    },

    // ═════════ telegram (chuẩn theo telegram_forms.py) ═════════
    get khachCoTelegram() {
      return this.khach.filter((k) => k.telegram && k.telegram.trim() !== '');
    },
    get tgKhachChon() {
      return this.khach.find((k) => k.id === Number(this.tgKhachId)) || this.khachCoTelegram[0] || this.khach[0];
    },
    doiKhachTg() {
      if (this.tgDangDongBo) return;
      this.tgDanhSach = [];
      this.tgTinChon = null;
      this.tgDockText = '';
      this.tgDockChiTiet = [];
      this.tgPreviewDaSanSang = false;
      this.bao(`Đã chuyển sang khách: ${this.tgKhachChon?.ten}. Bấm "⚡ BẮT ĐẦU ĐỒNG BỘ" để lấy tin.`, 'ok', 'fa-user');
    },
    batDauDongBoTg(silent = false) {
      if (this.tgDangDongBo) return;
      if (!this.kqNgay) {
        this.bao(`Chưa có KQXS ngày ${this.ngayHienThi} — vui lòng Cập nhật KQXS (Alt+R) trước khi đồng bộ!`, 'err', 'fa-circle-xmark');
        return;
      }
      const kh = this.tgKhachChon;
      if (!kh) {
        this.bao('Vui lòng chọn khách hàng có tài khoản Telegram', 'warn', 'fa-user');
        return;
      }

      this.tgDangDongBo = true;
      this._tgTimerStart = Date.now();
      this.tgTimerStr = '⏱ 00s';
      clearInterval(this._tgTimerJob);
      this._tgTimerJob = setInterval(() => {
        const s = Math.floor((Date.now() - this._tgTimerStart) / 1000);
        const m = Math.floor(s / 60);
        const sec = s % 60;
        this.tgTimerStr = m > 0 ? `⏱ ${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}` : `⏱ ${String(sec).padStart(2, '0')}s`;
      }, 1000);

      const delay = silent ? 10 : 700;
      setTimeout(() => {
        clearInterval(this._tgTimerJob);
        this.tgDangDongBo = false;

        const khId = Number(this.tgKhachId);
        let tinNguon = M.TELEGRAM_TIN.filter((t) => t.khach_id === khId && (t.chieu || 'in') === this.tgChieu);
        if (!tinNguon.length) {
          tinNguon = M.TELEGRAM_TIN.filter((t) => (t.chieu || 'in') === this.tgChieu).slice(0, 3);
        }

        let stt = 1;
        const dsMoi = [];
        for (const t of tinNguon) {
          const phan = phanTich(t.text, this.tuHoc);
          const bets = phan.bets.map((b) => {
            const d = this.tinhDong(b, kh);
            d.thanh_tien = d.trung - d.ghi + d.ck;
            return d;
          });
          const totals = this.tong(bets);
          const isErr = !phan.canSave;
          const isWarn = phan.dich && phan.dich.length > 0;
          const status = isErr ? 'error' : (isWarn ? 'warn' : 'ok');
          const status_label = isErr ? 'PARSE_ERROR' : (isWarn ? '⚠️ NGHI NGỜ' : '✅ OK');

          dsMoi.push({
            id: t.id,
            stt: stt++,
            message_id: t.message_id || (10400 + stt),
            khach_id: khId,
            chieu: this.tgChieu,
            gio: t.gio || '17:30',
            text_goc: t.text,
            text_hien_thi: t.text,
            text_kiem_tra: phan.sach,
            status: status,
            status_label: status_label,
            tien_ghi: totals.ghi,
            tien_ck: totals.ck,
            tien_trung: totals.trung,
            phai_tra: totals.trung - totals.ghi + totals.ck,
            errors: phan.loi || [],
            he_thong_dich: phan.dich || [],
            bets: bets,
          });
        }

        this.tgDanhSach = dsMoi;
        this.tgPreviewDaSanSang = dsMoi.some((t) => t.status !== 'error');

        // Tự động chọn dòng lỗi đầu tiên hoặc dòng đầu tiên
        const loiDau = dsMoi.find((t) => t.status === 'error') || dsMoi[0];
        if (loiDau) this.chonTinTg(loiDau);

        if (!silent) {
          this.bao(`Đã quét ${dsMoi.length} tin Telegram của ${kh.ten} (${this.tgChieu === 'in' ? 'Nhận về' : 'Gửi đi'})`, 'ok', 'fa-bolt');
          this.moSyncSummaryDialog(true);
        }
      }, delay);
    },
    chonTinTg(t) {
      this.tgTinChon = t;
      this.tgDockText = t.text_hien_thi;
      this.capNhatDockChiTiet();
    },
    capNhatDockChiTiet() {
      if (!this.tgTinChon) return;
      const kh = this.tgKhachChon;
      const phan = phanTich(this.tgDockText, this.tuHoc);
      const bets = phan.bets.map((b) => {
        const d = this.tinhDong(b, kh);
        d.thanh_tien = d.trung - d.ghi + d.ck;
        return d;
      });
      this.tgDockKetQua = phan;
      this.tgDockChiTiet = bets;
    },
    parseThuDock() {
      if (!this.tgTinChon) return;
      this.capNhatDockChiTiet();
      const phan = this.tgDockKetQua;
      if (!phan.canSave) {
        this.bao(`Parse thử: Tin còn ${phan.loi.length} lỗi cú pháp cần sửa`, 'err', 'fa-circle-xmark');
      } else {
        const totals = this.tong(this.tgDockChiTiet);
        this.bao(`Parse thử thành công: ${this.tgDockChiTiet.length} cược · Ghi ${fmt(totals.ghi)}đ · ${totals.can >= 0 ? 'Khách nộp' : 'Chủ trả'} ${fmt(Math.abs(totals.can))}đ`, 'ok', 'fa-circle-check');
      }
    },
    luuDockThayDoi() {
      if (!this.tgTinChon) return;
      this.capNhatDockChiTiet();
      const phan = this.tgDockKetQua;
      if (!phan.canSave) {
        this.bao(`Không thể lưu: Tin vẫn còn ${phan.loi.length} lỗi cú pháp!`, 'err', 'fa-triangle-exclamation');
        return;
      }

      const t = this.tgTinChon;
      const moi = this.tgDockText.trim();
      t.text_hien_thi = moi;
      t.text_kiem_tra = phan.sach;

      // Học quy tắc nếu người dùng sửa từ viết tắt
      const qt = this.rutQuyTac(t.text_goc, moi);
      let ghiChuHoc = '';
      if (qt && !qt.tuChoi) {
        const co = this.tuHoc.find((r) => r.goc === qt.x);
        if (co) { co.moi = qt.y; co.hit++; }
        else this.tuHoc.unshift({ goc: qt.x, moi: qt.y, hit: 1, dung_cuoi: 'vừa xong', nguon: 'tự học' });
        ghiChuHoc = ` 🧠 Đã ghi nhớ: "${qt.x}" → "${qt.y}"`;
      }

      const isWarn = phan.dich && phan.dich.length > 0;
      t.status = isWarn ? 'warn' : 'ok';
      t.status_label = isWarn ? '⚠️ NGHI NGỜ' : '✅ OK';
      t.errors = [];
      t.he_thong_dich = phan.dich || [];
      t.bets = this.tgDockChiTiet;
      const totals = this.tong(this.tgDockChiTiet);
      t.tien_ghi = totals.ghi;
      t.tien_ck = totals.ck;
      t.tien_trung = totals.trung;
      t.phai_tra = totals.trung - totals.ghi + totals.ck;

      this.tgPreviewDaSanSang = this.tgDanhSach.some((x) => x.status !== 'error');
      this.bao(`Đã cập nhật tin #${t.stt} (msg ${t.message_id}) -> ${t.status_label}.${ghiChuHoc}`, 'ok', 'fa-floppy-disk');
    },
    xoaTinTg(id) {
      const idx = this.tgDanhSach.findIndex((x) => x.id === id);
      if (idx === -1) return;
      const item = this.tgDanhSach[idx];
      if (!confirm(`Xoá tin #${item.stt} (msg ${item.message_id}) khỏi danh sách đối soát?\n\nKhi ghi DB, tin này sẽ không được ghi vào sổ.`)) {
        return;
      }
      this.tgDanhSach.splice(idx, 1);
      this.tgDanhSach.forEach((t, i) => { t.stt = i + 1; });
      if (this.tgTinChon?.id === id) {
        this.tgTinChon = this.tgDanhSach[idx] || this.tgDanhSach[idx - 1] || null;
        if (this.tgTinChon) this.chonTinTg(this.tgTinChon);
        else { this.tgDockText = ''; this.tgDockChiTiet = []; }
      }
      this.tgPreviewDaSanSang = this.tgDanhSach.some((x) => x.status !== 'error');
      this.bao(`Đã xoá tin #${item.stt} khỏi danh sách`, 'warn', 'fa-trash');
    },
    troToiViTriLoi(err) {
      const el = this.tgDockLayout === 'side' ? this.$refs.tgDockEditorSide : this.$refs.tgDockEditor;
      if (!el || err.start < 0) return;
      el.focus();
      el.setSelectionRange(err.start, err.end);
    },
    get tgDockLineCount() {
      if (!this.tgDockText) return 1;
      return (this.tgDockText.match(/\n/g) || []).length + 1;
    },
    get tgDockCharCount() {
      return (this.tgDockText || '').length;
    },
    get tgCalcEditorHeight() {
      if (this.tgDockLayout === 'side' || this.tgDockLayout === 'max') {
        return '100%';
      }
      // Chế độ split: tự động tính chiều cao theo số lượng dòng
      const lines = this.tgDockLineCount;
      // 1-3 dòng: 160px; 4-7 dòng: 220px; 8-12 dòng: 300px; >12 dòng: 380px
      const calcPx = Math.min(Math.max(lines * 28 + 64, 170), 380);
      return calcPx + 'px';
    },
    doiLayoutTg(mode) {
      this.tgDockLayout = mode;
      const ten = mode === 'side' ? 'Song song 2 cột (Editor // Bảng đối soát)' : mode === 'max' ? 'Phóng to tối đa ô soạn thảo' : 'Chia trên / dưới (Tự co giãn)';
      this.bao(`Chế độ xem: ${ten}`, 'ok', 'fa-table-columns');
    },
    get tgDanhSachHienThi() {
      if (this.tgChiXemLoi) {
        return this.tgDanhSach.filter((t) => t.status === 'error' || t.status === 'warn');
      }
      return this.tgDanhSach;
    },
    get tgWritableRows() {
      return this.tgDanhSach.filter((t) => t.status !== 'error');
    },
    get tgCoDuLieuGhi() {
      return this.tgPreviewDaSanSang && this.tgWritableRows.length > 0;
    },
    get tgDem() {
      const ok = this.tgDanhSach.filter((t) => t.status === 'ok').length;
      const warn = this.tgDanhSach.filter((t) => t.status === 'warn').length;
      const err = this.tgDanhSach.filter((t) => t.status === 'error').length;
      return { tong: this.tgDanhSach.length, ok, warn, err, moi: err };
    },
    get tgCanChuY() {
      return this.tgDem.warn + this.tgDem.err;
    },
    get tgTongTien() {
      const okRows = this.tgWritableRows;
      const ghi = okRows.reduce((s, t) => s + (t.tien_ghi || 0), 0);
      const ck = okRows.reduce((s, t) => s + (t.tien_ck || 0), 0);
      const trung = okRows.reduce((s, t) => s + (t.tien_trung || 0), 0);
      const phai_tra = trung - ghi + ck;
      return { ghi, ck, trung, phai_tra };
    },
    xacNhanGhiDB() {
      const okRows = this.tgWritableRows;
      if (!okRows.length) {
        this.bao('Không có tin hợp lệ nào để ghi vào Database!', 'err', 'fa-triangle-exclamation');
        return;
      }
      const soLoi = this.tgDanhSach.length - okRows.length;
      let msg = `Ghi ${okRows.length} tin nhắn vào cơ sở dữ liệu đối soát cho khách ${this.tgKhachChon?.ten}?`;
      if (soLoi > 0) {
        msg += `\n\n⚠️ Có ${soLoi} tin lỗi cú pháp (đỏ) sẽ BỊ BỎ QUA.`;
      }
      msg += `\n\n🔁 Tin Telegram cũ của khách trong ngày ${this.ngayHienThi} sẽ được ghi đè bằng danh sách này (tin nhập tay giữ nguyên).`;

      if (!confirm(msg)) return;

      this.daLuu = this.daLuu.filter((p) => !(p.khach_id === Number(this.tgKhachId) && p.nguon === 'Telegram'));
      for (const t of okRows) {
        this.daLuu.push({
          khach_id: Number(this.tgKhachId),
          gio: t.gio || new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
          nguon: 'Telegram',
          text: t.text_hien_thi,
          bets: t.bets.map(({ loai, so, tien }) => ({ loai, so, tien })),
        });
      }

      this.bao(`✅ Đã ghi thành công ${okRows.length} tin vào Database!`, 'ok', 'fa-floppy-disk');
      this.moSyncSummaryDialog(false);
    },
    moSyncSummaryDialog(isPreview = false) {
      const okRows = this.tgWritableRows;
      const allBets = okRows.flatMap((t) => t.bets || []);
      const loaiSoMap = {};
      for (const b of allBets) {
        const k = b.loai;
        if (!loaiSoMap[k]) loaiSoMap[k] = { loai: k, nhan: NHAN_LOAI[k] || k, ghi: 0, ck: 0, trung: 0, diem_trung: 0 };
        loaiSoMap[k].ghi += b.ghi || 0;
        loaiSoMap[k].ck += b.ck || 0;
        loaiSoMap[k].trung += b.trung || 0;
        if (k === 'LO' && b.soLan) loaiSoMap[k].diem_trung += (b.tien * b.soLan);
      }
      const ORDER = ['DE', 'LO', '3C', 'LX2', 'LX3', 'LX4'];
      const loaiSoList = [];
      ORDER.forEach((ls) => { if (loaiSoMap[ls]) loaiSoList.push(loaiSoMap[ls]); });
      Object.keys(loaiSoMap).forEach((ls) => { if (!ORDER.includes(ls)) loaiSoList.push(loaiSoMap[ls]); });

      const tt = this.tgTongTien;
      this.tgSummaryData = {
        isPreview,
        tenKhach: this.tgKhachChon?.ten,
        ngay: this.ngayHienThi,
        done: okRows.length,
        skipped: 0,
        failed: this.tgDem.err,
        tong_ghi: tt.ghi,
        tong_ck: tt.ck,
        tong_trung: tt.trung,
        phai_tra: tt.phai_tra,
        loai_so: loaiSoList,
      };
      this.modal = 'tg_summary';
    },
    moTongHopToanBo() {
      const okRows = this.tgWritableRows;
      const combined = okRows.flatMap((t) => t.bets || []);
      this.tgTongHopBets = combined;
      this.modal = 'tg_tonghop';
    },
    taiBanTelegram() {
      this.modal = 'tg_friends';
    },
    taiLaiKH() {
      this.khach = JSON.parse(JSON.stringify(M.KHACH_HANG));
      this.bao('Đã tải lại danh sách khách hàng có liên kết Telegram', 'ok', 'fa-rotate');
    },
    luuTgConfig() {
      this.modal = null;
      this.bao('Đã lưu cấu hình Telegram & AI thành công', 'ok', 'fa-floppy-disk');
    },
    rutQuyTac(goc, moi) {
      const a = goc.split(/\s+/); const b = moi.split(/\s+/);
      let i = 0; while (i < a.length && i < b.length && a[i] === b[i]) i++;
      let j = 0; while (j < a.length - i && j < b.length - i && a[a.length - 1 - j] === b[b.length - 1 - j]) j++;
      const x = a.slice(i, a.length - j).join(' '); const y = b.slice(i, b.length - j).join(' ');
      if (!x || !y || x === y) return null;
      const so = (s) => (s.match(/\d/g) || []).join('');
      if (so(x) !== so(y) || !/\p{L}/u.test(x) || x.length > 60) return { tuChoi: true, x, y };
      return { x, y };
    },
    tgTiep() {
      if (this.tgBuoc === 'sdt') this.tgBuoc = 'otp';
      else if (this.tgBuoc === 'otp') this.tgBuoc = '2fa';
      else { this.tgKetNoi = true; this.modal = null; this.tgBuoc = 'sdt'; this.bao('Đã kết nối Telegram — phiên được mã hoá an toàn', 'ok', 'fa-paper-plane'); }
    },

    // ═════════ lịch sử & chốt sổ ═════════
    get chotSo() {
      const kqNgay = this.kqNgay;
      const nhom = {};
      this.daLuu.forEach((p) => {
        const kh = this.khach.find((k) => k.id === p.khach_id);
        const d = p.bets.map((b) => this.tinhDong(b, kh, kqNgay));
        (nhom[p.khach_id] ||= { kh, phien: [], dong: [] });
        nhom[p.khach_id].phien.push({ ...p, dong: d, t: this.tong(d) });
        nhom[p.khach_id].dong.push(...d);
      });
      return Object.values(nhom).map((g) => ({ ...g, t: this.tong(g.dong) })).sort((a, b) => b.t.ghi - a.t.ghi);
    },
    get tongNgay() { return this.tong(this.chotSo.flatMap((g) => g.dong)); },
    phieuChot(g) {
      const theo = {};
      g.dong.forEach((d) => {
        const n = d.loai.startsWith('LX') ? 'Xiên' : NHAN_LOAI[d.loai];
        const x = (theo[n] ||= { danh: 0, ghi: 0, ck: 0, trung: 0, lo: d.loai === 'LO', so: [] });
        x.danh += d.tien; x.ghi += d.ghi; x.ck += d.ck; x.trung += d.trung;
        if (d.trung > 0) x.so.push(`${d.so.join('-')}${d.soLan > 1 ? ` (${d.soLan} nháy)` : ''}`);
      });
      const L = [`📋 PHIẾU CHỐT ${this.ngayHienThi}`, `👤 ${g.kh.ten}`, '──────────────'];
      for (const [n, x] of Object.entries(theo)) {
        L.push(`${n}: ${x.lo ? `${fmt(x.danh)} điểm` : fmt(x.danh)} → ghi ${fmt(x.ghi)}${x.ck ? ` · CK −${fmt(x.ck)}` : ''}`);
        if (x.trung) L.push(`   ✅ Trúng ${x.so.join(', ')}: ${fmt(x.trung)}`);
      }
      L.push('──────────────');
      L.push(`Tổng ghi: ${fmt(g.t.ghi)}`);
      L.push(`Chiết khấu: −${fmt(g.t.ck)}`);
      L.push(`Thực thu: ${fmt(g.t.thuc)}`);
      L.push(`Tiền trúng: ${fmt(g.t.trung)}`);
      L.push(g.t.can >= 0 ? `👉 ANH/CHỊ NỘP: ${fmt(g.t.can)}đ` : `👉 BÊN EM TRẢ: ${fmt(-g.t.can)}đ`);
      return L.join('\n');
    },
    async copyPhieu(g) {
      const txt = this.phieuChot(g);
      try { await navigator.clipboard.writeText(txt); } catch {
        const ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
      }
      this.bao(`Đã copy phiếu chốt của ${g.kh.ten} — dán vào Zalo/Telegram`, 'ok', 'fa-copy');
    },
    inPhieu(g) { this.lsMo = g.kh.id; this.$nextTick(() => window.print()); },

    // ═════════ tự học ═════════
    get tuHocLoc() {
      const q = this.timTuHoc.trim().toLowerCase();
      return this.tuHoc.filter((r) => !q || r.goc.toLowerCase().includes(q) || r.moi.toLowerCase().includes(q)).sort((a, b) => b.hit - a.hit);
    },
    themQuyTac() {
      const x = this.themGoc.trim(); const y = this.themMoi.trim();
      if (!x || !y) return;
      const so = (s) => (s.match(/\d/g) || []).join('');
      if (so(x) !== so(y)) { this.bao('Từ chối: quy tắc làm thay đổi con số cược', 'err', 'fa-shield-halved'); return; }
      if (['de', 'lo', 'xien', 'xq', '3c'].includes(x.toLowerCase())) { this.bao(`Từ chối: “${x}” là từ khoá chuẩn`, 'err', 'fa-shield-halved'); return; }
      this.tuHoc.unshift({ goc: x, moi: y, hit: 0, dung_cuoi: '—', nguon: 'thêm tay' });
      this.themGoc = ''; this.themMoi = '';
      this.bao(`Đã thêm quy tắc “${x}” → “${y}”`, 'learn', 'fa-brain');
      this.phanTichNgay();
    },
    xoaQuyTac(r) { this.tuHoc = this.tuHoc.filter((x) => x !== r); this.bao(`Đã xoá quy tắc “${r.goc}”`, 'warn', 'fa-trash'); this.phanTichNgay(); },

    // ═════════ tiện ích hiển thị ═════════
    fmt,
    badge(loai) { return { DE: 'b-de', LO: 'b-lo', '3C': 'b-3c', LX2: 'b-xien', LX3: 'b-xien', LX4: 'b-xien' }[loai]; },
    soHienThi(so) { return this.chi2So ? so.slice(-2) : so; },
  };
}
