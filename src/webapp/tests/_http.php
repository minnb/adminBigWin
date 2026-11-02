<?php
declare(strict_types=1);

// Hạ tầng test HTTP (P01 S1.0) — CHỈ dùng trong tests/.
// Bật `php -S` một lần cho mỗi lần chạy runner, trỏ cấu hình test (BIGWIN_CONFIG_FILE) → mã thật chạy trên DB test.
// Client cURL có cookie jar theo tên (mô phỏng nhiều trình duyệt), gắn/không gắn CSRF & Origin theo tuỳ chọn.

require_once __DIR__ . '/_db.php';

/** URL gốc của server test; khởi động ở lần gọi đầu, tắt khi runner kết thúc. */
function bw_http_goc(): string
{
    static $goc = null;
    if ($goc !== null) {
        return $goc;
    }
    db_test(); // server dùng chung DB test → phải dựng trước khi nhận request
    $cong = bw_http_cong_trong();
    $nhatKy = sys_get_temp_dir() . "/bigwin_test_server_$cong.log";
    $GLOBALS['bw_http_nhat_ky'] = $nhatKy;
    $tienTrinh = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:$cong", '-t', realpath(BW_WEBAPP_DIR), realpath(__DIR__ . '/_router.php')],
        [0 => ['pipe', 'r'], 1 => ['file', $nhatKy, 'a'], 2 => ['file', $nhatKy, 'a']],
        $ong
    ); // kế thừa môi trường của runner (gồm BIGWIN_CONFIG_FILE → cấu hình test)
    if (!is_resource($tienTrinh)) {
        throw new RuntimeException('Không bật được php -S');
    }
    register_shutdown_function(static function () use ($tienTrinh): void {
        proc_terminate($tienTrinh);
        proc_close($tienTrinh);
    });
    $goc = "http://127.0.0.1:$cong";
    $han = microtime(true) + 5;
    $c = curl_init("$goc/__bw_test__/moi-truong");
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT_MS => 100]);
    while (curl_exec($c) === false) { // curl không phát warning khi server chưa lên
        if (microtime(true) > $han) {
            throw new RuntimeException("php -S không lên ở cổng $cong (xem $nhatKy)");
        }
        usleep(20_000);
    }
    curl_close($c);
    return $goc;
}

/** Tệp nhật ký (stdout/stderr) của php -S — nơi error_log()/ghi_loi() của server test ghi ra. */
function bw_http_nhat_ky(): string
{
    bw_http_goc();
    return $GLOBALS['bw_http_nhat_ky'];
}

/** Cổng trống do hệ điều hành cấp (cổng 0), đóng ngay để php -S dùng. */
function bw_http_cong_trong(): int
{
    $s = stream_socket_server('tcp://127.0.0.1:0', $ma, $loi);
    if ($s === false) {
        throw new RuntimeException("Không xin được cổng trống: $loi");
    }
    $cong = (int) substr(strrchr(stream_socket_get_name($s, false), ':'), 1);
    fclose($s);
    return $cong;
}

function bw_http_tep_cookie(string $jar): string
{
    return sys_get_temp_dir() . '/bigwin_test_cookie_' . getmypid() . "_$jar.txt";
}

/** Xoá cookie của một "trình duyệt" (jar). */
function bw_http_xoa_cookie(string $jar = 'mac_dinh'): void
{
    $tep = bw_http_tep_cookie($jar);
    if (is_file($tep)) {
        unlink($tep);
    }
}

/**
 * Gửi request tới server test.
 * $tuyChon: json (mảng → body JSON) | body (chuỗi thô) | headers (['Tên' => 'giá trị'])
 *           csrf (chuỗi → header X-CSRF-Token) | origin (mặc định Origin cùng host; false = không gửi; chuỗi = Origin đó)
 *           jar (tên cookie jar, mặc định 'mac_dinh')
 * Trả: ['ma' => mã HTTP, 'header' => [tên thường => giá trị cuối], 'cookie' => [tên => giá trị server vừa đặt],
 *       'than' => body, 'json' => body đã giải mã hoặc null]
 */
function bw_http(string $phuongThuc, string $duongDan, array $tuyChon = []): array
{
    $goc = bw_http_goc();
    $header = [];
    foreach ($tuyChon['headers'] ?? [] as $ten => $giaTri) {
        $header[] = "$ten: $giaTri";
    }
    if (isset($tuyChon['csrf'])) {
        $header[] = 'X-CSRF-Token: ' . $tuyChon['csrf'];
    }
    $origin = $tuyChon['origin'] ?? $phuongThuc !== 'GET';
    if ($origin !== false) {
        $header[] = 'Origin: ' . ($origin === true ? $goc : $origin);
    }
    $body = $tuyChon['body'] ?? null;
    if (array_key_exists('json', $tuyChon)) {
        $body = json_encode($tuyChon['json']);
        $header[] = 'Content-Type: application/json';
    }

    $tepCookie = bw_http_tep_cookie($tuyChon['jar'] ?? 'mac_dinh');
    $nhan = ['header' => [], 'cookie' => []];
    $c = curl_init($goc . $duongDan);
    curl_setopt_array($c, [
        CURLOPT_CUSTOMREQUEST => $phuongThuc,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $header,
        CURLOPT_COOKIEFILE => $tepCookie,
        CURLOPT_COOKIEJAR => $tepCookie,
        CURLOPT_HEADERFUNCTION => static function ($c, string $dong) use (&$nhan): int {
            if (str_contains($dong, ':')) {
                [$ten, $giaTri] = array_map('trim', explode(':', $dong, 2));
                $ten = strtolower($ten);
                $nhan['header'][$ten] = $giaTri;
                if ($ten === 'set-cookie' && preg_match('/^([^=]+)=([^;]*)/', $giaTri, $m)) {
                    $nhan['cookie'][$m[1]] = rawurldecode($m[2]); // PHP mã hoá ký tự đặc biệt của session ID (vd ',' → %2C)
                }
            }
            return strlen($dong);
        },
    ]);
    if ($body !== null) {
        curl_setopt($c, CURLOPT_POSTFIELDS, $body);
    }
    $than = curl_exec($c);
    if ($than === false) {
        throw new RuntimeException('Request lỗi: ' . curl_error($c));
    }
    $ma = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    curl_close($c); // ghi cookie jar ra tệp ngay để request sau đọc được
    $json = json_decode($than, true);
    return ['ma' => $ma, 'header' => $nhan['header'], 'cookie' => $nhan['cookie'], 'than' => $than, 'json' => is_array($json) ? $json : null];
}

/** POST JSON tiện dụng (tên theo Action Plan P01): gắn CSRF/Origin theo $tuyChon như bw_http(). */
function http_post_json(string $duongDan, array $duLieu, array $tuyChon = []): array
{
    return bw_http('POST', $duongDan, ['json' => $duLieu] + $tuyChon);
}
