<?php
declare(strict_types=1);

// Bộ quét + an toàn hoá SQL tự viết của TenantRepo::truyVan() (kế hoạch tổng §5.4, Q-P02-8 "bảng lọc sẵn").
// Mỗi bảng tenant phải viết {tenant:ten_bang alias} (hoặc {tenant_ca_xoa:ten_bang alias}) ở vị trí tên bảng sau FROM/JOIN;
// được thay bằng bảng dẫn xuất đã lọc: (SELECT * FROM ten_bang WHERE user_id = <id> AND deleted_at IS NULL) alias.
// Điều kiện nằm TRONG bảng dẫn xuất → OR / LEFT JOIN ở câu ngoài không thể vô hiệu. Fail-closed: tên bảng tenant (hoặc
// bảng cấm — vd bảng thuê bao) xuất hiện "trần" ở bất cứ đâu (FROM, JOIN, subquery, tiền tố cột, kèm tên DB) hoặc cú pháp
// không hỗ trợ → LoiCoLapTenant.

require_once __DIR__ . '/LoiCoLapTenant.php';

final class BoQuetSqlTenant
{
    private const MAU_CHO = '/\{(tenant|tenant_ca_xoa):\s*([A-Za-z_][A-Za-z0-9_]*)\s+(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*\}/i';

    /**
     * Bảng đầu tiên trong $bang được nhắc như một tên riêng trong $chu (không tính khi là một phần của tên khác), null nếu
     * không có. Bộ khớp DUY NHẤT về "nhắc tên bảng" — dùng chung cho truyVan, SharedRepo và tests/tenant_scan_test.php.
     */
    public static function nhacBang(string $chu, array $bang): ?string
    {
        static $mau = [];
        if ($bang === []) {
            return null;
        }
        $khoa = implode(',', $bang);
        $mau[$khoa] ??= '/(?<![A-Za-z0-9_$])(' . implode('|', array_map(static fn (string $b): string => preg_quote($b, '/'), $bang)) . ')(?![A-Za-z0-9_$])/i';
        return preg_match($mau[$khoa], $chu, $m) ? strtolower($m[1]) : null;
    }

    /**
     * @param array<string, bool> $danhSach bảng tenant => có xoá mềm
     * @param list<string> $cam bảng có user_id nhưng chỉ truy cập qua repo riêng (không bao giờ được nhắc trong truyVan)
     * @return string SQL đã thay placeholder (giữ nguyên tham số `?`/`:ten` của người viết)
     */
    public static function anToanHoa(string $sql, int $id, array $danhSach, array $cam = []): string
    {
        static $daQuet = []; // SQL của repo hầu như là hằng → quét 1 lần / request; câu lỗi ném trước khi được lưu
        $khoa = $id . "\0" . implode(',', array_keys($danhSach)) . "\0" . implode(',', $cam) . "\0" . $sql;
        return $daQuet[$khoa] ??= self::quet($sql, $id, $danhSach, $cam);
    }

    private static function quet(string $sql, int $id, array $danhSach, array $cam): string
    {
        if ($id <= 0) {
            throw new LoiCoLapTenant('truyVan: ngữ cảnh không thuộc đại lý nào');
        }
        $che = self::cheChuoi($sql);
        if (!preg_match('/^\s*SELECT\b/i', $che)) {
            throw new LoiCoLapTenant('truyVan chỉ nhận một câu SELECT (ghi dữ liệu dùng them/sua/xoaMem)');
        }
        if (str_contains($che, ';')) {
            throw new LoiCoLapTenant('truyVan không nhận nhiều câu lệnh');
        }
        if (preg_match('/\b(UNION|WITH|INTO)\b/i', $che, $m)) {
            throw new LoiCoLapTenant("truyVan không hỗ trợ $m[1] — tách thành câu đơn giản hơn");
        }

        preg_match_all(self::MAU_CHO, $che, $cho, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $conLai = $che;
        foreach ($cho as $c) {
            [$toanBo, $viTri] = $c[0];
            $bang = strtolower($c[2][0]);
            $alias = strtolower($c[3][0]);
            if (!array_key_exists($bang, $danhSach)) {
                throw new LoiCoLapTenant("truyVan: `$bang` không phải bảng tenant — không dùng {tenant:...}");
            }
            if (array_key_exists($alias, $danhSach)) {
                throw new LoiCoLapTenant("truyVan: alias `$alias` trùng tên bảng tenant");
            }
            if (!preg_match('/(?:\bFROM|\bJOIN|,)\s*$/i', substr($che, 0, $viTri))) {
                throw new LoiCoLapTenant("truyVan: {tenant:$bang $alias} phải đứng ở vị trí tên bảng (sau FROM / JOIN)");
            }
            $conLai = substr_replace($conLai, str_repeat(' ', strlen($toanBo)), $viTri, strlen($toanBo));
        }
        if (str_contains($conLai, '{') || str_contains($conLai, '}')) {
            throw new LoiCoLapTenant('truyVan: placeholder sai cú pháp — dùng {tenant:ten_bang alias}');
        }
        $tran = self::nhacBang($conLai, [...array_keys($danhSach), ...$cam]);
        if ($tran !== null) {
            throw new LoiCoLapTenant(in_array($tran, $cam, true)
                ? "truyVan: bảng `$tran` chỉ được truy cập qua repo riêng"
                : "truyVan: bảng tenant `$tran` phải viết {tenant:$tran alias}");
        }

        foreach (array_reverse($cho) as $c) {
            [$toanBo, $viTri] = $c[0];
            $bang = strtolower($c[2][0]);
            $locXoa = strtolower($c[1][0]) === 'tenant' && $danhSach[$bang] ? ' AND deleted_at IS NULL' : '';
            $sql = substr_replace($sql, "(SELECT * FROM `$bang` WHERE user_id = $id$locXoa) " . strtolower($c[3][0]), $viTri, strlen($toanBo));
        }
        return $sql;
    }

    /** Cùng độ dài byte với $sql, nội dung chuỗi '...' / "..." thay bằng khoảng trắng. Chú thích / chuỗi chưa đóng → lỗi. */
    private static function cheChuoi(string $sql): string
    {
        $ra = $sql;
        $n = strlen($sql);
        for ($i = 0; $i < $n; $i++) {
            $c = $sql[$i];
            if ($c === "'" || $c === '"') {
                $j = $i + 1;
                while (true) {
                    if ($j >= $n) {
                        throw new LoiCoLapTenant('truyVan: chuỗi ký tự chưa đóng');
                    }
                    if ($sql[$j] === '\\') {
                        $j += 2;
                        continue;
                    }
                    if ($sql[$j] === $c) {
                        if (($sql[$j + 1] ?? '') !== $c) {
                            break;
                        }
                        $j++; // '' hoặc "" = dấu nháy trong chuỗi
                    }
                    $j++;
                }
                $ra = substr_replace($ra, str_repeat(' ', $j - $i - 1), $i + 1, $j - $i - 1);
                $i = $j;
            } elseif ($c === '`') {
                $j = strpos($sql, '`', $i + 1);
                if ($j === false) {
                    throw new LoiCoLapTenant('truyVan: tên trong dấu ` chưa đóng');
                }
                $i = $j;
            } elseif ($c === '#' || ($c === '-' && ($sql[$i + 1] ?? '') === '-') || ($c === '/' && ($sql[$i + 1] ?? '') === '*')) {
                throw new LoiCoLapTenant('truyVan không nhận chú thích trong SQL');
            }
        }
        return $ra;
    }
}
