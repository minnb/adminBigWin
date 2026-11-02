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

const SO_VN = new Intl.NumberFormat('vi-VN');
const GIA_VN = new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 });
const fmt = (n) => SO_VN.format(Math.trunc(n));
const esc = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const z2 = (n) => String(n).padStart(2, '0');
const ngayVN = (d = new Date()) => `${z2(d.getDate())}/${z2(d.getMonth() + 1)}/${d.getFullYear()}`;
const idMoi = (ds) => Math.max(0, ...ds.map((x) => x.id)) + 1;
/** Regex khớp nguyên cụm (không dính chữ/số hai bên) — dùng cho quy tắc tự học và từ khoá dàn. */
const reKhoa = (k) => new RegExp(`${BIEN}${k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}${HET}`, 'giu');

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
    thay(reKhoa(r.goc), () => r.moi);
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

// ═════════ Bảng tổng hợp theo loại cược — component DÙNG CHUNG ═════════
// Port Database.tong_hop_doi_soat_bang_bieu + ui/core/report_grid.ve_bang_bao_cao.
// Quy ước dấu DUY NHẤT toàn app: THÀNH TIỀN = TIỀN (ghi) − TRÚNG − CK; ≥ 0 → PHẢI THU KHÁCH, < 0 → PHẢI TRẢ KHÁCH.
const THU_TU_BANG = ['DE', 'LO', 'LX2', 'LX3', 'LX4', '3C'];
const NHAN_BANG = Object.fromEntries(Object.entries(NHAN_LOAI).map(([k, v]) => [k, v.toLocaleUpperCase('vi')]));

/** dong = kết quả tinhDong() → { rows, tong, soTrung }. Chỉ gom số, không tự tính lại tiền. */
function bangTongHop(dong) {
  const g = {};
  for (const d of dong || []) {
    const x = (g[d.loai] ||= { danh: 0, tien: 0, trung: 0, diemTrung: 0, ck: 0, soTrung: [] });
    x.danh += d.tien; x.tien += d.ghi; x.ck += d.ck; x.trung += d.trung;
    if (d.trung > 0 && d.soLan > 0) {
      if (d.loai === 'LO') x.diemTrung += d.tien * d.soLan; // LÔ đánh theo ĐIỂM → ô TRÚNG hiện điểm
      x.soTrung.push({ loai: NHAN_BANG[d.loai] || d.loai, so: d.so.join('-'), nhay: d.soLan, tienThang: d.trung });
    }
  }
  const keys = [...THU_TU_BANG.filter((k) => g[k]), ...Object.keys(g).filter((k) => !THU_TU_BANG.includes(k))];
  const rows = keys.map((k, i) => ({ stt: i + 1, loaiKey: k, loai: NHAN_BANG[k] || k, ...g[k], thanhTien: g[k].tien - g[k].trung - g[k].ck }));
  const tong = rows.reduce((a, r) => ({ tien: a.tien + r.tien, trung: a.trung + r.trung, ck: a.ck + r.ck }), { tien: 0, trung: 0, ck: 0 });
  tong.thanhTien = tong.tien - tong.trung - tong.ck;
  return { rows, tong, soTrung: rows.flatMap((r) => r.soTrung) };
}
const ketLuanTien = (tt) => (tt >= 0 ? { nhan: 'PHẢI THU KHÁCH', cls: 'ok' } : { nhan: 'PHẢI TRẢ KHÁCH', cls: 'err' });

/** HTML lưới 7 cột + dòng "Số trúng" + kết luận. o: { chuaKQ, gon, anKetLuan, rong } */
function veBangTongHop(bt, o = {}) {
  if (!bt.rows.length) return `<div class="btk-rong">${esc(o.rong || 'Chưa có cược hợp lệ')}</div>`;
  const so = (n, rongKhiKhong) => (rongKhiKhong && !n ? '' : fmt(n));
  const oTrung = (r) => {
    if (o.chuaKQ) return '<span class="btk-mo">—</span>';
    const co = r.trung > 0;
    const txt = r.loaiKey === 'LO' ? (r.diemTrung ? `${fmt(r.diemTrung)} điểm` : '0') : fmt(r.trung);
    return `<span class="${co ? 'btk-co' : ''}">${txt}</span>`;
  };
  const tt = (n) => `<span class="${n < 0 ? 'btk-am' : ''}">${fmt(n)}</span>`;
  let h = `<div class="btk-wrap"><table class="btk${o.gon ? ' btk-gon' : ''}"><thead><tr><th>STT</th><th>Loại</th><th>ĐÁNH</th><th class="btk-tien">TIỀN</th><th>TRÚNG</th><th>CK</th><th>THÀNH TIỀN</th></tr></thead><tbody>`;
  for (const r of bt.rows) {
    h += `<tr><td class="c">${r.stt}</td><td class="c b">${r.loai}</td><td>${fmt(r.danh)}${r.loaiKey === 'LO' ? '<small> điểm</small>' : ''}</td>`
      + `<td class="btk-tien b">${fmt(r.tien)}</td><td>${oTrung(r)}</td><td>${so(r.ck, true)}</td><td class="b">${tt(r.thanhTien)}</td></tr>`;
  }
  const t = bt.tong;
  h += `<tr class="btk-tong"><td class="c">${bt.rows.length + 1}</td><td class="c">TỔNG CỘNG</td><td></td><td class="btk-tien">${fmt(t.tien)}</td>`
    + `<td>${o.chuaKQ ? '<span class="btk-mo">—</span>' : `<span class="${t.trung ? 'btk-co' : ''}">${fmt(t.trung)}</span>`}</td><td>${so(t.ck, true)}</td><td class="btk-tt">${tt(t.thanhTien)}</td></tr></tbody></table></div>`;
  if (bt.soTrung.length) {
    h += `<div class="btk-trung"><b><i class="fa-solid fa-trophy"></i> Số trúng:</b> ${bt.soTrung
      .map((s) => `<span>${s.loai} <b class="mono">${s.so}</b>${s.nhay > 1 ? ` (${s.nhay}n)` : ''} = ${fmt(s.tienThang)}</span>`).join('<i>·</i>')}</div>`;
  }
  if (!o.anKetLuan) {
    const kl = ketLuanTien(t.thanhTien);
    h += `<div class="btk-kl btk-kl-${o.chuaKQ ? 'cho' : kl.cls}"><span>${kl.nhan}${o.chuaKQ ? ' <small>(tạm tính — chưa có KQXS, mọi số đang tính là trượt)</small>' : ''}</span><b class="mono">${fmt(Math.abs(t.thanhTien))}đ</b></div>`;
  }
  return h;
}

// ═════════ Định nghĩa cú pháp cược (giả lập core/quan_ly_dan_so.py) ═════════
const NHOM_CP = [['dan', 'Dàn'], ['user', 'Của tôi'], ['bo', 'Bộ'], ['tong', 'Tổng'], ['chia', 'Chia'], ['cham', 'Chạm'], ['dau', 'Đầu'], ['dit', 'Đít']];
const HO_CHI_DE = ['bo', 'tong', 'chia']; // ql.HO_CHI_DE — chỉ áp ĐỀ (ERR_LO_DAN_DE)
const KHOA_CHUAN = ['de', 'lo', 'xien', 'xq', '3c', 'x', 'k', 'd', 'n'];
const boDau = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/gi, 'd').toLowerCase();

// Bảng dàn → [{ re, d }] (cụm dài nhất trước) — biên dịch 1 lần cho mỗi phiên bản bảng (lưu dàn = mảng mới)
const _khoaBang = new WeakMap();
function khoaDaBienDich(bang) {
  let ds = _khoaBang.get(bang);
  if (!ds) {
    ds = bang.filter((d) => d.bat).flatMap((d) => [...new Set(d.khoa.map(boDau))].map((k) => ({ k, d })))
      .sort((x, y) => y.k.length - x.k.length).map(({ k, d }) => ({ re: reKhoa(k), d }));
    _khoaBang.set(bang, ds);
  }
  return ds;
}
// Chuỗi tìm kiếm đã bỏ dấu của mỗi dàn (tên + từ khoá) — tính 1 lần cho mỗi đối tượng dàn
const _chuoiTim = new WeakMap();
const chuoiTimDan = (d) => {
  let c = _chuoiTim.get(d);
  if (c === undefined) { c = boDau([d.ten, ...d.khoa].join('\n')); _chuoiTim.set(d, c); }
  return c;
};

/** Engine duy nhất (bảng dàn số): thay từ khoá dàn (cụm dài nhất khớp trước) bằng danh sách số, rồi parse. */
function phanTichMoi(raw, tuHoc, bang) {
  // Dò từ khoá trên bản bỏ dấu (cùng độ dài ký tự với tin NFC) nhưng thay trên tin gốc,
  // để quy tắc tự học có dấu (vd “điểm”) vẫn khớp.
  let s = raw.normalize('NFC');
  let b = boDau(s);
  const thayDoan = (re, fn) => {
    const ds = [...b.matchAll(re)].reverse();
    for (const m of ds) {
      const moi = fn(m[0], m.index, b);
      s = s.slice(0, m.index) + moi + s.slice(m.index + m[0].length);
      b = b.slice(0, m.index) + boDau(moi) + b.slice(m.index + m[0].length);
    }
  };
  for (const { re, d } of khoaDaBienDich(bang)) {
    thayDoan(re, (m, off, all) => {
      const truoc = all.slice(0, off).match(/^.*(?<![\p{L}\d])(de|lo|xien|xq|3c)(?![\p{L}\d])[^\n]*$/su);
      const loai = truoc?.[1] === 'lo' ? 'LO' : 'DE';
      return d.ap.includes(loai) ? d.so.join(' ') : `⛔${m.replace(/\s+/g, '_')}`;
    });
  }
  // Không còn công thức cứng bo/chạm/tổng/kép — chưa có dàn khớp ⇒ lỗi
  thayDoan(/(?<![\p{L}\d_⛔])(bo|cham|tong|kep)(?:\s+\d+)?(?![\p{L}\d])/gu, (m) => `⛔${m.replace(/\s+/g, '_')}`);
  return phanTich(s, tuHoc);
}

function tomTatKQ(r) {
  if (r.loi.length) return { text: `Lỗi: ${r.loi[0].text.replace(/⛔/g, '').replace(/_/g, ' ')}${r.loi.length > 1 ? ` (+${r.loi.length - 1})` : ''}`, so: [], loi: true, key: `ERR:${r.loi.map((l) => l.text).join('|')}` };
  const nhom = {};
  for (const b of r.bets) (nhom[b.loai] ||= { n: 0, tien: b.nhanTien }).n++;
  const text = Object.entries(nhom).map(([l, g]) => `${NHAN_LOAI[l]} ${g.n} số × ${g.tien}`).join(' · ') || 'Không có cược';
  const key = r.bets.map((b) => `${b.loai}:${b.so.join('-')}`).sort().join('|');
  return { text, so: [...new Set(r.bets.map((b) => b.so.join('-')))], loi: false, key };
}

const CP_RONG = () => ({ id: null, ten: '', nhom: 'user', de: true, lo: true, bat: true, khoaText: '', soText: '' });
const KH_RONG = () => ({ id: null, ten: '', zalo: '', tele_id: '', telegram: '' });
const _soCache = { text: null, v: null }; // cpSoForm: chỉ tách lại khi ô số đổi (lưới 100 ô đọc liên tục)

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
    nhapXem: 'tong',           // 'tong' = bảng tổng hợp theo loại (desktop) · 'chi' = từng cược

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

    // ── định nghĩa cú pháp cược (rules_forms.py CuPhapCuocDialog) ──
    cpTab: 'dan',
    cpLocNhom: '',
    cpTim: '',
    cpForm: CP_RONG(),
    cpLoiForm: [],
    cpXacNhan: null,            // { tieuDe, icon, ds, bangMoi, chonId, nut, nguy, thongBao }
    cpGoKP: '',
    NHOM_CP,
    SO_DAN_GOC: M.SO_DAN_GOC,

    MAU: M.MAU_TIN,
    COT: M.COT_HE_SO,
    // ── khách hàng & bảng giá ──
    khNhomHien: { thuong: true, ghi: false, ck: true }, // bật/tắt nhóm cột trên lưới (21 cột quá rộng)
    khForm: KH_RONG(),
    khLoi: {},
    khDrawerId: null,
    khXoa: null,                // { kh, phien, tinTg, giaRieng, tongGhi }
    khGoXoa: '',
    NHAN_LOAI,

    init() {
      const tick = () => { this.gio = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); };
      tick(); setInterval(tick, 1000);
      // Deep link để review: #telegram, #kqxs, #khach, #lichsu, #tuhoc, #admin, #mau3 …
      const h = location.hash.slice(1);
      if (['nhap', 'telegram', 'kqxs', 'khach', 'lichsu', 'cuphap'].includes(h)) this.man = h;
      if (h === 'tuhoc') { this.man = 'lichsu'; this.lsTab = h; }
      if (h === 'dan' || h === 'quytac') { this.man = 'cuphap'; this.cpTab = h; }
      this.cpChonDan(this.danSo[0]);
      // Review modal: #anhhuong (sửa dàn Sát kép), #khoiphuc
      if (['anhhuong', 'khoiphuc'].includes(h)) {
        this.man = 'cuphap';
        if (h === 'anhhuong') { this.cpChonDan(this.danSo[2]); this.cpForm.soText = '01 10 12 21 23 32 34 43 45 54 09 90'; this.cpLuu(); }
        if (h === 'khoiphuc') this.modal = 'cp_khoi_phuc';
      }
      // Review khách hàng: #khachmoi (form thêm), #hoso (drawer khách 1), #xoakhach (xác nhận xoá khách 1)
      if (['khachmoi', 'hoso', 'xoakhach'].includes(h)) {
        this.man = 'khach';
        this.$nextTick(() => {
          if (h === 'khachmoi') this.moFormKhach(null);
          if (h === 'hoso') this.moDrawerKhach(this.khach[0]);
          if (h === 'xoakhach') this.moXoaKhach(this.khach[0]);
        });
      }
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
      if (e.key === 'Escape') { if (!this.modal && !this.help) this.khDrawerId = null; this.modal = null; this.help = false; this.moKhach = false; return; }
      if (e.ctrlKey && e.key === 'Enter' && this.man === 'nhap') { e.preventDefault(); this.luu(); return; }
      if (e.altKey && (e.key === 'r' || e.key === 'R')) { e.preventDefault(); this.capNhatKQ(); return; }
      if (e.ctrlKey && (e.key === 's' || e.key === 'S') && this.man === 'cuphap' && this.cpTab === 'dan') { e.preventDefault(); this.cpLuu(); return; }
      if (e.altKey && /^[1-7]$/.test(e.key)) {
        e.preventDefault();
        const ds = ['nhap', 'telegram', 'kqxs', 'khach', 'lichsu', 'cuphap', 'admin'];
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
    setHeSo(kh, k, v, el) {
      const raw = String(v).trim().replace(/\./g, '').replace(',', '.');
      const val = raw === '' ? undefined : Number(raw);
      // Giá sai thì báo lỗi & trả ô về giá cũ — không âm thầm xoá (desktop: float('') báo lỗi)
      if (val !== undefined && (!Number.isFinite(val) || val < 0 || (k.startsWith('ti_le') && val > 100))) {
        this.bao(`Giá “${v}” không hợp lệ — ${k.startsWith('ti_le') ? 'chiết khấu phải từ 0 đến 100%' : 'phải là số ≥ 0'}`, 'err', 'fa-triangle-exclamation');
        if (el) el.value = this.fmtGia(kh ? kh.he_so[k] : this.daiLy[k]);
        return;
      }
      if (kh) { if (val === undefined) delete kh.he_so[k]; else kh.he_so[k] = val; }
      else if (val === undefined) delete this.daiLy[k]; else this.daiLy[k] = val;
      if (el && val !== undefined) el.value = this.fmtGia(val);
    },
    fmtGia(v) { return v === undefined || v === null ? '' : GIA_VN.format(v); },
    get cotNhomHien() { return this.COT.filter((g) => this.khNhomHien[g.ma]); },
    get cotPhangHien() { return this.cotNhomHien.flatMap((g) => g.cot.map(([k, nhan], i) => ({ k, nhan, dau: i === 0 }))); },
    soGiaRieng(kh) { return Object.keys(kh.he_so).length; },
    xoaGiaRieng(kh) {
      const n = this.soGiaRieng(kh);
      kh.he_so = {};
      this.bao(`Đã bỏ ${n} giá riêng của ${kh.ten} — dùng lại giá mặc định của đại lý`, 'warn', 'fa-rotate-left');
    },

    // ═════════ khách hàng ═════════
    get khachChon() { return this.khach.find((k) => k.id === this.khachId) || null; },
    get khachHoatDong() { return this.khach.filter((k) => !k.deleted_at); }, // khách đã ẩn (xoá mềm) không còn trong danh sách chọn
    get khachLoc() {
      const q = this.timKhach.trim().toLowerCase();
      return this.khachHoatDong.filter((k) => !q || k.ten.toLowerCase().includes(q) || k.telegram.toLowerCase().includes(q));
    },
    tenKhach(id) { return this.khach.find((k) => k.id === id)?.ten || '—'; },
    chonKhach(k) { this.khachId = k.id; this.timKhach = ''; this.moKhach = false; this.phanTichNgay(); },
    phimKhach(e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); this.khachIdx = Math.min(this.khachIdx + 1, this.khachLoc.length - 1); }
      if (e.key === 'ArrowUp') { e.preventDefault(); this.khachIdx = Math.max(this.khachIdx - 1, 0); }
      if (e.key === 'Enter') { e.preventDefault(); const k = this.khachLoc[this.khachIdx]; if (k) { this.chonKhach(k); this.$refs.oTin.focus(); } }
    },
    // Hồ sơ khách: Tên bắt buộc & không trùng (không phân biệt hoa thường) · Telegram ID là số (customer_forms.py:355,461)
    moFormKhach(kh) {
      this.khLoi = {};
      this.khForm = kh ? { id: kh.id, ten: kh.ten, zalo: kh.zalo, tele_id: kh.tele_id ?? '', telegram: kh.telegram }
        : KH_RONG();
      if (!kh) { this.modal = 'kh_form'; this.$nextTick(() => this.$refs.khTen?.focus()); }
    },
    kiemKhach(f) {
      const loi = {};
      const ten = f.ten.trim().replace(/\s+/g, ' ');
      if (!ten) loi.ten = 'Tên khách không được để trống';
      // chỉ so với khách đang hoạt động — khách đã ẩn không giữ chỗ tên (≡ cột sinh ten_khach_hoat_dong)
      else if (this.khachHoatDong.some((k) => k.id !== f.id && k.ten.trim().toLowerCase() === ten.toLowerCase())) loi.ten = `Đã có khách tên “${ten}”`;
      const tid = String(f.tele_id ?? '').trim();
      if (tid && !/^-?\d{4,15}$/.test(tid)) loi.tele_id = 'Telegram ID phải là dãy số (vd 5012345678)';
      else if (tid) {
        const trung = this.khachHoatDong.find((k) => k.id !== f.id && String(k.tele_id ?? '') === tid);
        if (trung) loi.tele_id = `Telegram ID này đã gắn cho “${trung.ten}”`;
      }
      const tg = f.telegram.trim();
      if (tg && !/^@?[A-Za-z][A-Za-z0-9_]{3,31}$/.test(tg)) loi.telegram = 'Username chỉ gồm chữ, số, “_” (5–32 ký tự)';
      return { loi, ten, tid, tg: tg && !tg.startsWith('@') ? `@${tg}` : tg };
    },
    luuKhach() {
      const { loi, ten, tid, tg } = this.kiemKhach(this.khForm);
      this.khLoi = loi;
      if (Object.keys(loi).length) return;
      const f = this.khForm;
      if (f.id) {
        Object.assign(this.khach.find((k) => k.id === f.id), { ten, zalo: f.zalo.trim(), tele_id: tid ? Number(tid) : null, telegram: tg });
        this.bao(`Đã lưu hồ sơ “${ten}”`, 'ok', 'fa-floppy-disk');
        return;
      }
      const id = idMoi(this.khach);
      this.khach.push({ id, ngay_tao: ngayVN(), ten, zalo: f.zalo.trim(), tele_id: tid ? Number(tid) : null, telegram: tg, he_so: {} });
      this.khachId = id;
      this.modal = null;
      if (this.man === 'khach') this.moDrawerKhach(this.khach.find((k) => k.id === id)); // thêm từ màn Nhập tin thì ở lại màn đó
      this.bao(`Đã thêm khách “${ten}” — đang dùng giá mặc định của đại lý`, 'ok', 'fa-user-plus');
    },
    get khDrawer() { return this.khach.find((k) => k.id === this.khDrawerId) || null; },
    moDrawerKhach(kh) { this.khDrawerId = kh.id; this.moFormKhach(kh); },
    // Xoá khách: kéo theo lịch sử đối soát + tin Telegram đã đồng bộ + giá riêng
    moXoaKhach(kh) {
      const phien = this.daLuu.filter((p) => p.khach_id === kh.id);
      const dong = phien.flatMap((p) => p.bets.map((b) => this.tinhDong(b, kh)));
      this.khXoa = { kh, phien: phien.length, tinTg: this.tg.filter((t) => t.khach_id === kh.id).length,
        giaRieng: this.soGiaRieng(kh), tongGhi: this.tong(dong).ghi };
      this.khGoXoa = '';
      this.modal = 'kh_xoa';
    },
    xoaKhach() {
      const { kh, phien } = this.khXoa;
      if (this.khGoXoa.trim().toLowerCase() !== kh.ten.trim().toLowerCase()) return;
      // Xoá mềm (O1): chỉ ẩn khách — phiên đối soát & tin Telegram cũ giữ nguyên, vẫn vào báo cáo ngày cũ
      kh.deleted_at = ngayVN();
      if (this.khachId === kh.id) this.khachId = this.khachHoatDong[0]?.id ?? null;
      if (Number(this.tgKhachId) === kh.id) this.tgKhachId = this.khachCoTelegram[0]?.id ?? null;
      if (this.khDrawerId === kh.id) this.khDrawerId = null;
      this.modal = null; this.khXoa = null;
      this.bao(`Đã ẩn khách “${kh.ten}”${phien ? ` — ${phien} phiên đối soát cũ vẫn được giữ trong lịch sử` : ''}`, 'warn', 'fa-user-slash');
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
      t.can = t.thuc - t.trung;  // = THÀNH TIỀN = ghi − trúng − CK (≥ 0: phải thu khách)
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
      this.bao(`Đã lưu ${this.kq.bets.length} dòng cược cho ${this.khachChon.ten} · ${ketLuanTien(t.can).nhan} ${fmt(Math.abs(t.can))}đ`, 'ok', 'fa-floppy-disk');
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
      return this.khachHoatDong.filter((k) => k.tele_id); // desktop: chỉ khách có id_telegram
    },
    get tgKhachChon() {
      return this.khachCoTelegram.find((k) => k.id === Number(this.tgKhachId)) || this.khachCoTelegram[0] || null;
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
          const bets = phan.bets.map((b) => this.tinhDong(b, kh));
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
      const bets = phan.bets.map((b) => this.tinhDong(b, kh));
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
        this.bao(`Parse thử thành công: ${this.tgDockChiTiet.length} cược · Ghi ${fmt(totals.ghi)}đ · ${ketLuanTien(totals.can).nhan} ${fmt(Math.abs(totals.can))}đ`, 'ok', 'fa-circle-check');
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
    get tgDockBangHtml() {
      return veBangTongHop(bangTongHop(this.tgDockChiTiet), { gon: true, chuaKQ: !this.kqNgay, rong: 'Chưa có cược hợp lệ nào.' });
    },
    get tgTongTien() {
      const t = this.tong(this.tgWritableRows.flatMap((x) => x.bets || []));
      return { ghi: t.ghi, ck: t.ck, trung: t.trung, thanh_tien: t.can }; // ≥ 0: phải thu khách
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
      this.tgSummaryData = { isPreview, done: okRows.length, skipped: 0, failed: this.tgDem.err, dong: okRows.flatMap((t) => t.bets || []) };
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
      const L = [`📋 PHIẾU CHỐT ${this.ngayHienThi}`, `👤 ${g.kh.ten}`, '──────────────'];
      for (const r of bangTongHop(g.dong).rows) {
        L.push(`${NHAN_LOAI[r.loaiKey]}: ${r.loaiKey === 'LO' ? `${fmt(r.danh)} điểm` : fmt(r.danh)} → ghi ${fmt(r.tien)}${r.ck ? ` · CK −${fmt(r.ck)}` : ''}`);
        if (r.trung) L.push(`   ✅ Trúng ${r.soTrung.map((x) => `${x.so}${x.nhay > 1 ? ` (${x.nhay} nháy)` : ''}`).join(', ')}: ${fmt(r.trung)}`);
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

    // ═════════ định nghĩa cú pháp cược ═════════
    nhanNhom(n) { return NHOM_CP.find((x) => x[0] === n)?.[1] || n; },
    get cpDemNhom() { const d = {}; for (const x of this.danSo) d[x.nhom] = (d[x.nhom] || 0) + 1; return d; },
    get cpBangLoc() {
      const q = boDau(this.cpTim.trim());
      return this.danSo.filter((d) => (!this.cpLocNhom || d.nhom === this.cpLocNhom)
        && (!q || chuoiTimDan(d).includes(q) || d.so.includes(z2(q))));
    },
    get cpDanChon() { return this.danSo.find((d) => d.id === this.cpForm.id) || null; },
    get cpChiDe() { return HO_CHI_DE.includes(this.cpForm.nhom); },
    cpChonDan(d) {
      this.cpLoiForm = [];
      this.cpForm = { id: d.id, ten: d.ten, nhom: d.nhom, de: d.ap.includes('DE'), lo: d.ap.includes('LO'), bat: d.bat, khoaText: d.khoa.join('\n'), soText: d.so.join(' ') };
    },
    cpDanMoi() {
      this.cpLoiForm = [];
      this.cpForm = CP_RONG();
      this.$nextTick(() => this.$refs.cpTen?.focus());
    },
    cpApLuatNhom() { if (this.cpChiDe) { this.cpForm.de = true; this.cpForm.lo = false; } },
    tachSo(text) {
      const so = []; const loi = [];
      for (const t of text.split(/[\s,.;\-]+/).filter(Boolean)) {
        if (/^\d{1,2}$/.test(t)) so.push(t.padStart(2, '0')); else loi.push(`“${t}” không phải số 2 chữ số`);
      }
      return { so, loi };
    },
    get cpSoForm() {
      const text = this.cpForm.soText;
      if (_soCache.text !== text) {
        const v = this.tachSo(text);
        _soCache.text = text;
        _soCache.v = { ...v, set: new Set(v.so) };
      }
      return _soCache.v;
    },
    get cpSoTrung() { return this.cpSoForm.so.length - this.cpSoForm.set.size; },
    cpCoSo(x) { return this.cpSoForm.set.has(x); },
    cpBatTatSo(x) {
      const set = new Set(this.cpSoForm.so);
      if (set.has(x)) set.delete(x); else set.add(x);
      this.cpForm.soText = [...set].sort().join(' ');
    },
    cpLocTrung() {
      const { so, loi } = this.cpSoForm;
      const sach = [...new Set(so)].sort();
      this.cpForm.soText = sach.join(' ');
      this.bao(`Đã lọc trùng & sắp xếp ${sach.length} số${loi.length ? ` · bỏ qua: ${loi[0]}` : ''}`, loi.length ? 'warn' : 'ok', 'fa-broom');
    },
    cpDocForm() {
      const f = this.cpForm; const loi = [];
      const khoa = [...new Set(f.khoaText.split('\n').map((k) => k.trim().toLowerCase()).filter(Boolean))];
      const { so, loi: loiSo } = this.cpSoForm;
      if (!f.ten.trim()) loi.push('Chưa đặt tên dàn');
      if (!f.de && !f.lo) loi.push('Phải áp cho ít nhất ĐỀ hoặc LÔ');
      if (!khoa.length) loi.push('Cần ít nhất 1 từ khoá để app nhận diện');
      if (!so.length) loi.push('Danh sách số đang trống');
      loi.push(...loiSo);
      for (const k of khoa) {
        if (KHOA_CHUAN.includes(boDau(k))) loi.push(`Từ khoá “${k}” trùng từ khoá chuẩn của app`);
        if (/^\d+$/.test(k)) loi.push(`Từ khoá “${k}” chỉ gồm số — sẽ nhầm với số cược`);
        const dung = this.danSo.find((d) => d.id !== f.id && d.bat && d.khoa.some((x) => boDau(x) === boDau(k)));
        if (dung) loi.push(`Từ khoá “${k}” đang thuộc dàn “${dung.ten}”`);
      }
      const goc = this.danSo.find((d) => d.id === f.id);
      const dan = { id: f.id ?? idMoi(this.danSo), ten: f.ten.trim(), nhom: f.nhom,
        ap: [f.de && 'DE', f.lo && 'LO'].filter(Boolean), khoa, so: [...new Set(so)].sort(), nguon: goc?.nguon === 'goc' ? 'sua' : (goc?.nguon || 'user'), bat: f.bat };
      return { dan, loi };
    },
    /** ql.anh_huong: chạy tin chuẩn + tin thật gần nhất qua bảng cũ & bảng mới, trả các tin đổi tiền. */
    anhHuong(bangCu, bangMoi) {
      const tinThat = [...this.daLuu.map((x) => x.text), ...this.tg.map((x) => x.text)];
      const ds = [...M.TIN_CHUAN_CP.map((t) => [t, false]), ...[...new Set(tinThat)].map((t) => [t, true])];
      const out = [];
      for (const [tin, that] of ds) {
        const a = tomTatKQ(phanTichMoi(tin, this.tuHoc, bangCu));
        const b = tomTatKQ(phanTichMoi(tin, this.tuHoc, bangMoi));
        if (a.key === b.key) continue;
        const coA = new Set(a.so); const coB = new Set(b.so);
        out.push({ tin, that, truoc: a, sau: b, them: b.so.filter((x) => !coA.has(x)), bot: a.so.filter((x) => !coB.has(x)) });
      }
      return out;
    },
    cpLuu() {
      const { dan, loi } = this.cpDocForm();
      this.cpLoiForm = loi;
      if (loi.length) { this.bao(`Dàn chưa hợp lệ — ${loi.length} lỗi`, 'err', 'fa-triangle-exclamation'); return; }
      const bangMoi = this.danSo.some((d) => d.id === dan.id) ? this.danSo.map((d) => (d.id === dan.id ? dan : d)) : [...this.danSo, dan];
      this.cpXacNhan = { tieuDe: this.cpForm.id ? `Lưu dàn “${dan.ten}”` : `Tạo dàn mới “${dan.ten}”`, icon: 'fa-floppy-disk',
        ds: this.anhHuong(this.danSo, bangMoi), bangMoi, chonId: dan.id, nut: 'Xác nhận lưu', nguy: false,
        thongBao: `Đã lưu dàn “${dan.ten}” (${dan.so.length} số) — áp dụng ngay cho tin tiếp theo` };
      this.modal = 'cp_anh_huong';
    },
    cpXoa() {
      const d = this.cpDanChon;
      if (!d) { this.bao('Chọn một dàn trong bảng trước', 'warn', 'fa-hand-pointer'); return; }
      const bangMoi = this.danSo.filter((x) => x.id !== d.id);
      this.cpXacNhan = { tieuDe: `Xoá dàn “${d.ten}”`, icon: 'fa-trash', ds: this.anhHuong(this.danSo, bangMoi), bangMoi, chonId: bangMoi[0]?.id,
        nut: 'Xoá dàn', nguy: true, thongBao: `Đã xoá dàn “${d.ten}”` };
      this.modal = 'cp_anh_huong';
    },
    cpGhi() {
      const x = this.cpXacNhan;
      this.danSo = x.bangMoi;
      const d = this.danSo.find((v) => v.id === x.chonId);
      if (d) this.cpChonDan(d); else this.cpDanMoi();
      this.modal = null; this.cpXacNhan = null;
      this.bao(x.thongBao, 'ok', 'fa-circle-check');
    },
    cpKhoiPhuc() {
      this.danSo = JSON.parse(JSON.stringify(M.DAN_SO.filter((d) => d.nguon === 'goc')));
      this.cpChonDan(this.danSo[0]); this.cpLocNhom = ''; this.modal = null;
      this.bao(`Đã khôi phục ${M.SO_DAN_GOC} dàn mặc định — mọi dàn tự thêm/sửa đã bị xoá`, 'warn', 'fa-rotate-left');
    },

    // ═════════ tiện ích hiển thị ═════════
    fmt,
    z2,
    bangTongHop,
    veBangTongHop,
    ketLuanTien,
    badge(loai) { return { DE: 'b-de', LO: 'b-lo', '3C': 'b-3c', LX2: 'b-xien', LX3: 'b-xien', LX4: 'b-xien' }[loai]; },
    soHienThi(so) { return this.chi2So ? so.slice(-2) : so; },
  };
}
