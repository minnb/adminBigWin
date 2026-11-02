// Dữ liệu mẫu cho bản prototype giao diện BigWIN Web — KHÔNG phải dữ liệu thật.
// Hệ số mặc định lấy đúng Database.DEFAULT_HE_SO của BigWIN Desktop.

window.BW_MOCK = (() => {
  const pad = (n) => String(n).padStart(2, '0');
  const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const homNay = new Date();
  const homQua = new Date(homNay.getTime() - 86400000);

  // 27 giải XSMB: [giai_key, tên, các số]
  const KQ_HOM_NAY = [
    ['giaidb', 'Giải ĐB', ['47281']],
    ['giai1', 'Giải Nhất', ['39504']],
    ['giai2', 'Giải Nhì', ['81226', '05917']],
    ['giai3', 'Giải Ba', ['66327', '20475', '93128', '41052', '78869', '12394']],
    ['giai4', 'Giải Tư', ['4581', '7026', '3364', '9175']],
    ['giai5', 'Giải Năm', ['1858', '6243', '2907', '5463', '8312', '0566']],
    ['giai6', 'Giải Sáu', ['384', '742', '926']],
    ['giai7', 'Giải Bảy', ['81', '04', '63', '27']],
  ];
  const KQ_HOM_QUA = [
    ['giaidb', 'Giải ĐB', ['90215']],
    ['giai1', 'Giải Nhất', ['54872']],
    ['giai2', 'Giải Nhì', ['13340', '68091']],
    ['giai3', 'Giải Ba', ['22715', '40983', '77156', '31608', '85529', '06347']],
    ['giai4', 'Giải Tư', ['2214', '8873', '5560', '1099']],
    ['giai5', 'Giải Năm', ['7302', '4415', '9938', '2681', '6650', '3127']],
    ['giai6', 'Giải Sáu', ['158', '603', '771']],
    ['giai7', 'Giải Bảy', ['15', '39', '72', '08']],
  ];

  const HE_SO_MAC_DINH = {
    he_so_de: 80, he_so_lo: 80000, he_so_3c: 400, he_so_xq2: 15,
    he_so_lx2: 10, he_so_lx3: 40, he_so_lx4: 100,
    he_so_ghi_de: 1, he_so_ghi_lo: 23000, he_so_ghi_3c: 1, he_so_ghi_xq: 1,
    he_so_ghi_lx2: 1, he_so_ghi_lx3: 1, he_so_ghi_lx4: 1,
    ti_le_chiet_khau_de: 23, ti_le_chiet_khau_lo: 0, ti_le_chiet_khau_3c: 0,
    ti_le_chiet_khau_xq: 0, ti_le_chiet_khau_lx2: 0, ti_le_chiet_khau_lx3: 0, ti_le_chiet_khau_lx4: 0,
  };

  // Các cột hiển thị trên lưới bảng giá (nhóm → khoá)
  // 21 khoá giá = 3 nhóm × 7 loại cược (core/database.py DEFAULT_HE_SO · customer_forms.py CauHinhDialog)
  const COT_HE_SO = [
    { ma: 'thuong', nhom: 'Trả thưởng (×)', icon: 'fa-trophy', goiY: 'Trúng 1 thì trả bao nhiêu lần tiền cược', cot: [
      ['he_so_de', 'Đề'], ['he_so_lo', 'Lô /điểm'], ['he_so_3c', '3 Càng'], ['he_so_xq2', 'Xiên quay'],
      ['he_so_lx2', 'Xiên 2'], ['he_so_lx3', 'Xiên 3'], ['he_so_lx4', 'Xiên 4'],
    ] },
    { ma: 'ghi', nhom: 'Giá ghi (VNĐ)', icon: 'fa-money-bill-wave', goiY: 'Quy đổi 1 đơn vị cược ra tiền ghi sổ', cot: [
      ['he_so_ghi_de', 'Đề'], ['he_so_ghi_lo', 'Lô /điểm'], ['he_so_ghi_3c', '3 Càng'], ['he_so_ghi_xq', 'Xiên quay'],
      ['he_so_ghi_lx2', 'Xiên 2'], ['he_so_ghi_lx3', 'Xiên 3'], ['he_so_ghi_lx4', 'Xiên 4'],
    ] },
    { ma: 'ck', nhom: 'Chiết khấu (%)', icon: 'fa-tag', goiY: '% trừ trên tiền cược gốc', cot: [
      ['ti_le_chiet_khau_de', 'Đề'], ['ti_le_chiet_khau_lo', 'Lô'], ['ti_le_chiet_khau_3c', '3 Càng'], ['ti_le_chiet_khau_xq', 'Xiên quay'],
      ['ti_le_chiet_khau_lx2', 'Xiên 2'], ['ti_le_chiet_khau_lx3', 'Xiên 3'], ['ti_le_chiet_khau_lx4', 'Xiên 4'],
    ] },
  ];

  const KHACH_HANG = [
    { id: 1, ngay_tao: '12/03/2026', ten: 'Anh Ba — Chợ Lớn', zalo: '0903 112 445', telegram: '@anhba_cl', tele_id: 1101, he_so: { ti_le_chiet_khau_de: 25 } },
    { id: 2, ngay_tao: '12/03/2026', ten: 'Chị Tư Bến Thành', zalo: '0918 223 778', telegram: '@chitu_bt', tele_id: 1102, he_so: { he_so_ghi_lo: 22500 } },
    { id: 3, ngay_tao: '02/05/2026', ten: 'Cô Năm Q.5', zalo: '0937 654 120', telegram: '', tele_id: null, he_so: {} },
    { id: 4, ngay_tao: '18/06/2026', ten: 'Út Hiền Gò Vấp', zalo: '', telegram: '@uthien_gv', tele_id: 1104, he_so: { he_so_de: 75, ti_le_chiet_khau_de: 20 } },
    { id: 5, ngay_tao: '01/08/2026', ten: 'Bác Sáu Thủ Đức', zalo: '0909 887 001', telegram: '@bacsau', tele_id: 1105, he_so: { he_so_lo: 78000, he_so_ghi_lo: 23500 } },
    { id: 6, ngay_tao: '20/10/2026', ten: 'Tám Xe Ôm', zalo: '0388 456 999', telegram: '', tele_id: null, he_so: {} },
  ];

  const MAU_TIN = [
    {
      ten: 'Mẫu 1 · Tin chuẩn', icon: 'fa-circle-check', mau: 'ok',
      text: 'de 18 36 54 72 x50k\nlo 15 36 40 x10\nlo 63 x3\nxien 81 72 x20k\n3c 128 x10k',
    },
    {
      ten: 'Mẫu 2 · Tiếng lóng', icon: 'fa-wand-magic-sparkles', mau: 'warn',
      text: 'đê 81,18 x 1 lít\nlô 27.63 x10 điểm\nxq 81 27 04 x 2 chục',
    },
    {
      ten: 'Mẫu 3 · Có lỗi', icon: 'fa-triangle-exclamation', mau: 'err',
      text: 'de 81 x50k lo 2763 x10\n3c 12345 x5k\nxien 12 x10k\nghi giùm anh nha',
    },
    {
      ten: 'Mẫu 4 · Bộ / Chạm', icon: 'fa-layer-group', mau: 'ok',
      text: 'de bo 12 x10k\nde cham 8 x5k\nlo kep x5',
    },
  ];

  const TELEGRAM_TIN = [
    // Khách 1: Anh Ba (Nhận về)
    { id: 9101, message_id: 10451, khach_id: 1, chieu: 'in', gio: '17:12', text: 'de 81 18 x50k lo 27 63 x10', trang_thai: 'ok', da_luu: false },
    { id: 9102, message_id: 10452, khach_id: 1, chieu: 'in', gio: '17:25', text: 'đê 45 54 x 2 lít\nlô 63 x5 điểm', trang_thai: 'warn', da_luu: false },
    { id: 9103, message_id: 10453, khach_id: 1, chieu: 'in', gio: '17:31', text: 'de 27 x30k lo 04 81 x20\nsien 27 81 x10k', trang_thai: 'err', da_luu: false },
    { id: 9104, message_id: 10454, khach_id: 1, chieu: 'in', gio: '17:42', text: 'xq 81 27 63 x 1 chục', trang_thai: 'warn', da_luu: false },
    { id: 9105, message_id: 10455, khach_id: 1, chieu: 'in', gio: '17:48', text: '3c 128 281 x10k\nde 18 x50k', trang_thai: 'ok', da_luu: false },
    { id: 9107, message_id: 10465, khach_id: 1, chieu: 'in', gio: '17:55', text: 'de bo 12 34 x50k\nde cham 8 9 x30k\nde kep x20k\nlo 18 81 27 72 x10d\nlo 36 63 45 54 x15d\nxien 18 27, 81 72 x50k\nxien 36 45, 63 54 x30k\nxq 18 27 63 x20k\n3c 128 281 363 x10k\n3c 454 545 x10k', trang_thai: 'ok', da_luu: false },
    // Khách 1: Anh Ba (Gửi đi)
    { id: 9106, message_id: 10460, khach_id: 1, chieu: 'out', gio: '17:50', text: 'de 81 18 x50k lo 27 63 x10', trang_thai: 'ok', da_luu: false },

    // Khách 2: Chị Tư Bến Thành (Nhận về)
    { id: 9201, message_id: 10512, khach_id: 2, chieu: 'in', gio: '16:45', text: 'de bo 12 x20k\nde cham 8 x10k', trang_thai: 'ok', da_luu: false },
    { id: 9202, message_id: 10513, khach_id: 2, chieu: 'in', gio: '17:15', text: 'lo 18 81 x 3 củ', trang_thai: 'err', da_luu: false },
    { id: 9203, message_id: 10514, khach_id: 2, chieu: 'in', gio: '17:35', text: 'lo 27 63 x20 diem\nde 81 x100k', trang_thai: 'ok', da_luu: false },

    // Khách 4: Út Hiền Gò Vấp (Nhận về)
    { id: 9401, message_id: 10601, khach_id: 4, chieu: 'in', gio: '17:05', text: 'de 27 72 x50k lo 04 x10', trang_thai: 'ok', da_luu: false },
    { id: 9402, message_id: 10602, khach_id: 4, chieu: 'in', gio: '17:28', text: 'xien 27 81 63 x20k\n3c 456 x10k', trang_thai: 'ok', da_luu: false },

    // Khách 5: Bác Sáu Thủ Đức (Nhận về)
    { id: 9501, message_id: 10701, khach_id: 5, chieu: 'in', gio: '17:38', text: 'lo 81 x 50 diem\nde kep x10k', trang_thai: 'ok', da_luu: false },
    { id: 9502, message_id: 10702, khach_id: 5, chieu: 'in', gio: '17:52', text: 'de 81 x50k lo 2763 x10', trang_thai: 'err', da_luu: false },
  ];

  const USERS_CHATTED_TODAY = [
    { uid: '1101', username: 'anhba_cl', name: 'Anh Ba — Chợ Lớn', time: '17:48', text: '3c 128 281 x10k de 18 x50k' },
    { uid: '1102', username: 'chitu_bt', name: 'Chị Tư Bến Thành', time: '17:35', text: 'lo 27 63 x20 diem de 81 x100k' },
    { uid: '1104', username: 'uthien_gv', name: 'Út Hiền Gò Vấp', time: '17:28', text: 'xien 27 81 63 x20k 3c 456 x10k' },
    { uid: '1105', username: 'bacsau', name: 'Bác Sáu Thủ Đức', time: '17:52', text: 'de 81 x50k lo 2763 x10' },
  ];

  const TG_CONFIG = {
    api_id: '2981726',
    api_hash: 'c8f12a3b90e1f728c89b27182903ab81',
    session_name: 'bigwin_telethon',
    gemini_api_key: 'AIzaSyA8B9123-Kj98234-Mn76231-BigWinKey',
  };

  const TU_HOC = [
    { goc: 'lít', moi: '00k', hit: 412, dung_cuoi: '30-10 17:52', nguon: 'tự học' },
    { goc: 'chục', moi: '0k', hit: 288, dung_cuoi: '30-10 17:40', nguon: 'tự học' },
    { goc: 'điểm', moi: 'd', hit: 251, dung_cuoi: '30-10 17:38', nguon: 'tự học' },
    { goc: 'xjen', moi: 'xien', hit: 37, dung_cuoi: '29-10 18:11', nguon: 'tự học' },
    { goc: 'dem', moi: 'd', hit: 19, dung_cuoi: '30-10 17:38', nguon: 'thêm tay' },
    { goc: 'đê', moi: 'de', hit: 640, dung_cuoi: '30-10 18:01', nguon: 'tự học' },
    { goc: 'x quay', moi: 'xq', hit: 12, dung_cuoi: '27-10 17:55', nguon: 'thêm tay' },
  ];

  // Bảng dàn số (rules_forms.py CuPhapCuocDialog · core/dan_so_mac_dinh.py) — nguon: 'goc' = 115 dàn hệ thống, 'user' = đại lý tự thêm/sửa
  const day = (f) => [...Array(100).keys()].map((i) => String(i).padStart(2, '0')).filter((s) => f(+s[0], +s[1], +s));
  const DAN_SO = [
    { id: 1, ten: 'Kép bằng', nhom: 'dan', ap: ['DE', 'LO'], khoa: ['kep', 'kép', 'kep bang'], so: day((a, b) => a === b), nguon: 'goc', bat: true },
    { id: 2, ten: 'Kép lệch', nhom: 'dan', ap: ['DE', 'LO'], khoa: ['kl', 'kep lech', 'kép lệch'], so: ['05', '16', '27', '38', '49', '50', '61', '72', '83', '94'], nguon: 'goc', bat: true },
    { id: 3, ten: 'Sát kép', nhom: 'dan', ap: ['DE', 'LO'], khoa: ['sk', 'sat kep', 'sát kép'], so: day((a, b) => Math.abs(a - b) === 1), nguon: 'goc', bat: true },
    { id: 4, ten: 'Kép chẵn', nhom: 'dan', ap: ['DE', 'LO'], khoa: ['kep chan', 'kép chẵn'], so: day((a, b) => a === b && a % 2 === 0), nguon: 'goc', bat: true },
    { id: 5, ten: 'Chẵn chẵn', nhom: 'dan', ap: ['DE', 'LO'], khoa: ['chan chan', 'chẵn chẵn', 'cc'], so: day((a, b) => a % 2 === 0 && b % 2 === 0), nguon: 'goc', bat: true },
    { id: 6, ten: 'Bộ 12', nhom: 'bo', ap: ['DE'], khoa: ['bo 12', 'bộ 12', 'bo12'], so: ['12', '17', '21', '26', '62', '67', '71', '76'], nguon: 'goc', bat: true },
    { id: 7, ten: 'Bộ 05', nhom: 'bo', ap: ['DE'], khoa: ['bo 05', 'bộ 05', 'bo05'], so: ['00', '05', '50', '55'], nguon: 'goc', bat: true },
    { id: 8, ten: 'Tổng 5', nhom: 'tong', ap: ['DE'], khoa: ['tong 5', 'tổng 5', 't5'], so: day((a, b) => (a + b) % 10 === 5), nguon: 'goc', bat: true },
    { id: 9, ten: 'Chia 3 dư 0', nhom: 'chia', ap: ['DE'], khoa: ['chia 3', 'chia het 3'], so: day((a, b, n) => n % 3 === 0), nguon: 'goc', bat: true },
    { id: 10, ten: 'Chạm 8', nhom: 'cham', ap: ['DE', 'LO'], khoa: ['cham 8', 'chạm 8', 'c8'], so: day((a, b) => a === 8 || b === 8), nguon: 'goc', bat: true },
    { id: 11, ten: 'Đầu 3', nhom: 'dau', ap: ['DE', 'LO'], khoa: ['dau 3', 'đầu 3'], so: day((a) => a === 3), nguon: 'goc', bat: true },
    { id: 12, ten: 'Đít 7', nhom: 'dit', ap: ['DE', 'LO'], khoa: ['dit 7', 'đít 7', 'duoi 7'], so: day((a, b) => b === 7), nguon: 'goc', bat: true },
    { id: 13, ten: 'Dàn đẹp Anh Ba', nhom: 'user', ap: ['DE', 'LO'], khoa: ['dan ab', 'dàn anh ba'], so: ['18', '27', '36', '63', '72', '81'], nguon: 'user', bat: true },
    { id: 14, ten: 'Bóng âm', nhom: 'user', ap: ['DE', 'LO'], khoa: ['bong am', 'bóng âm'], so: ['07', '14', '29', '36', '41', '58', '63', '70', '85', '92'], nguon: 'user', bat: false },
    { id: 15, ten: 'Kép lệch (sửa)', nhom: 'user', ap: ['LO'], khoa: ['klo'], so: ['05', '50', '16', '61'], nguon: 'user', bat: true },
  ];
  const SO_DAN_GOC = 115; // core/dan_so_mac_dinh.py — preview chỉ giả lập 12 dàn đại diện

  // Tin chuẩn nội bộ dùng để đo ảnh hưởng khi sửa dàn (ql.anh_huong: tin chuẩn + tin thật gần nhất)
  const TIN_CHUAN_CP = [
    'de kep x20k', 'lo kep x5', 'de sk x10k', 'lo sat kep x3', 'de kl x50k', 'de kep chan x10k',
    'de bo 12 x20k', 'de tong 5 x10k', 'de cham 8 x5k', 'lo dau 3 x2', 'de dit 7 x10k', 'de chia 3 x5k',
    'de dan ab x50k', 'lo bong am x3', 'de cc x10k',
  ];

  // Phiên đã lưu trong ngày (hiện ở Lịch sử) — bets đã bung sẵn
  const DA_LUU = [
    { khach_id: 1, gio: '17:12', nguon: 'Telegram', text: 'de 18 36 x50k lo 15 40 x10',
      bets: [['DE', ['18'], 50000], ['DE', ['36'], 50000], ['LO', ['15'], 10], ['LO', ['40'], 10]] },
    { khach_id: 1, gio: '17:42', nguon: 'Telegram', text: 'xq 12 34 56 x 1 chục',
      bets: [['LX2', ['12', '34'], 10000], ['LX2', ['12', '56'], 10000], ['LX2', ['34', '56'], 10000], ['LX3', ['12', '34', '56'], 10000]] },
    { khach_id: 2, gio: '17:20', nguon: 'Nhập tay', text: 'de 45 54 x200k lo 63 x5',
      bets: [['DE', ['45'], 200000], ['DE', ['54'], 200000], ['LO', ['63'], 5]] },
    { khach_id: 3, gio: '17:47', nguon: 'Nhập tay', text: '3c 128 x20k de 18 x100k',
      bets: [['3C', ['128'], 20000], ['DE', ['18'], 100000]] },
    { khach_id: 5, gio: '17:58', nguon: 'Zalo', text: 'lo 19 x50 de 99 x30k',
      bets: [['LO', ['19'], 50], ['DE', ['99'], 30000]] },
    { khach_id: 4, gio: '18:04', nguon: 'Telegram', text: 'de 81 x20k lo 35 x10',
      bets: [['DE', ['81'], 20000], ['LO', ['35'], 10]] },
    { khach_id: 6, gio: '18:10', nguon: 'Nhập tay', text: 'lo 22 33 x20 de 50 x100k',
      bets: [['LO', ['22'], 20], ['LO', ['33'], 20], ['DE', ['50'], 100000]] },
  ];

  return {
    homNay: iso(homNay), homQua: iso(homQua),
    KQ: { [iso(homNay)]: KQ_HOM_NAY, [iso(homQua)]: KQ_HOM_QUA },
    HE_SO_MAC_DINH, COT_HE_SO, KHACH_HANG, MAU_TIN, TELEGRAM_TIN, USERS_CHATTED_TODAY, TG_CONFIG, TU_HOC, DAN_SO, SO_DAN_GOC, TIN_CHUAN_CP, DA_LUU,
  };
})();

