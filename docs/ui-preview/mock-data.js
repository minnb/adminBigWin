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
  const COT_HE_SO = [
    { nhom: 'Trả thưởng (×)', cot: [
      ['he_so_de', 'Đề'], ['he_so_lo', 'Lô /điểm'], ['he_so_3c', '3 Càng'],
      ['he_so_lx2', 'Xiên 2'], ['he_so_lx3', 'Xiên 3'], ['he_so_lx4', 'Xiên 4'],
    ] },
    { nhom: 'Giá ghi', cot: [['he_so_ghi_lo', 'Lô /điểm'], ['he_so_ghi_de', 'Đề'], ['he_so_ghi_3c', '3 Càng']] },
    { nhom: 'Chiết khấu (%)', cot: [['ti_le_chiet_khau_de', 'Đề'], ['ti_le_chiet_khau_lo', 'Lô'], ['ti_le_chiet_khau_lx2', 'Xiên']] },
  ];

  const KHACH_HANG = [
    { id: 1, ten: 'Anh Ba — Chợ Lớn', zalo: '0903 112 445', telegram: '@anhba_cl', he_so: { ti_le_chiet_khau_de: 25 } },
    { id: 2, ten: 'Chị Tư Bến Thành', zalo: '0918 223 778', telegram: '@chitu_bt', he_so: { he_so_ghi_lo: 22500 } },
    { id: 3, ten: 'Cô Năm Q.5', zalo: '0937 654 120', telegram: '', he_so: {} },
    { id: 4, ten: 'Út Hiền Gò Vấp', zalo: '', telegram: '@uthien_gv', he_so: { he_so_de: 75, ti_le_chiet_khau_de: 20 } },
    { id: 5, ten: 'Bác Sáu Thủ Đức', zalo: '0909 887 001', telegram: '@bacsau', he_so: { he_so_lo: 78000, he_so_ghi_lo: 23500 } },
    { id: 6, ten: 'Tám Xe Ôm', zalo: '0388 456 999', telegram: '', he_so: {} },
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
    { id: 9101, khach_id: 1, gio: '17:12', text: 'de 81 18 x50k lo 27 63 x10', trang_thai: 'ok', da_luu: true },
    { id: 9102, khach_id: 2, gio: '17:25', text: 'đê 45 54 x 2 lít\nlô 63 x5 điểm', trang_thai: 'ok', da_luu: false },
    { id: 9103, khach_id: 4, gio: '17:31', text: 'de 27 x30k lo 04 81 x20\nsien 27 81 x10k', trang_thai: 'err', da_luu: false },
    { id: 9104, khach_id: 5, gio: '17:38', text: 'lo 81 x 50 diem\nde kep x10k', trang_thai: 'err', da_luu: false },
    { id: 9105, khach_id: 1, gio: '17:42', text: 'xq 81 27 63 x 1 chục', trang_thai: 'warn', da_luu: true, sua_tren_tg: true },
    { id: 9106, khach_id: 3, gio: '17:47', text: '3c 281 x20k de 18 x100k', trang_thai: 'ok', da_luu: false },
    { id: 9107, khach_id: 6, gio: '17:53', text: 'de tong 5 x10k lo 63.27 x15', trang_thai: 'ok', da_luu: false },
    { id: 9108, khach_id: 2, gio: '18:02', text: 'lo 18 81 x 3 củ', trang_thai: 'err', da_luu: false },
  ];

  const TU_HOC = [
    { goc: 'lít', moi: '00k', hit: 412, dung_cuoi: '30-10 17:52', nguon: 'tự học' },
    { goc: 'chục', moi: '0k', hit: 288, dung_cuoi: '30-10 17:40', nguon: 'tự học' },
    { goc: 'điểm', moi: 'd', hit: 251, dung_cuoi: '30-10 17:38', nguon: 'tự học' },
    { goc: 'xjen', moi: 'xien', hit: 37, dung_cuoi: '29-10 18:11', nguon: 'tự học' },
    { goc: 'dem', moi: 'd', hit: 19, dung_cuoi: '30-10 17:38', nguon: 'thêm tay' },
    { goc: 'đê', moi: 'de', hit: 640, dung_cuoi: '30-10 18:01', nguon: 'tự học' },
    { goc: 'x quay', moi: 'xq', hit: 12, dung_cuoi: '27-10 17:55', nguon: 'thêm tay' },
  ];

  const DAN_SO = [
    { ten: 'Kép bằng', khoa: 'kep', so: ['00', '11', '22', '33', '44', '55', '66', '77', '88', '99'], nguon: 'mặc định', bat: true },
    { ten: 'Kép lệch', khoa: 'kl, kep lech', so: ['05', '50', '16', '61', '27', '72', '38', '83', '49', '94'], nguon: 'mặc định', bat: true },
    { ten: 'Sát kép', khoa: 'sk, sat kep', so: ['01', '10', '12', '21', '23', '32', '34', '43', '45', '54', '56', '65', '67', '76', '78', '87', '89', '98'], nguon: 'mặc định', bat: true },
    { ten: 'Dàn đẹp Anh Ba', khoa: 'dan ab', so: ['18', '81', '27', '72', '36', '63'], nguon: 'của tôi', bat: true },
    { ten: 'Bóng âm', khoa: 'bong am', so: ['07', '70', '14', '41', '29', '92', '36', '63', '58', '85'], nguon: 'của tôi', bat: false },
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
    HE_SO_MAC_DINH, COT_HE_SO, KHACH_HANG, MAU_TIN, TELEGRAM_TIN, TU_HOC, DAN_SO, DA_LUU,
  };
})();
