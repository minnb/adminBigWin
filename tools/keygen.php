<?php
declare(strict_types=1);

// Sinh cặp khoá Ed25519 — CHẠY 1 LẦN: php tools/keygen.php
// Ghi secret key vào đường dẫn `secret_key_file` trong config.php, in public key
// (base64) để dán vào LICENSE_PUBLIC_KEY_B64 trong core/license_service.py.
// Sinh lại khoá = MỌI bản app đã phát hành mất hiệu lực → CẤM chạy lại khi đã phát hành.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../lib/bootstrap.php';

// Khoá secretbox cho backup (độc lập với khoá ký — sinh nếu chưa có, KHÔNG BAO GIỜ sinh đè).
$fileBk = (string)cfg('backup_key_file');
if ($fileBk !== '' && !is_file($fileBk)) {
    @mkdir(dirname($fileBk), 0700, true);
    file_put_contents($fileBk, sodium_crypto_secretbox_keygen());
    @chmod($fileBk, 0600);
    fwrite(STDERR, "Da ghi khoa backup: $fileBk\n");
}

$file = (string)cfg('secret_key_file');
if (is_file($file)) {
    $sk = file_get_contents($file);
    fwrite(STDERR, "Da co khoa tai $file — KHONG sinh lai. Public key hien tai:\n");
    echo base64_encode(sodium_crypto_sign_publickey_from_secretkey($sk)), PHP_EOL;
    exit(0);
}

$dir = dirname($file);
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    fwrite(STDERR, "Khong tao duoc thu muc $dir\n");
    exit(1);
}

$kp = sodium_crypto_sign_keypair();
file_put_contents($file, sodium_crypto_sign_secretkey($kp));
@chmod($file, 0600);

fwrite(STDERR, "Da ghi secret key: $file\nPublic key (dan vao core/license_service.py):\n");
echo base64_encode(sodium_crypto_sign_publickey($kp)), PHP_EOL;
