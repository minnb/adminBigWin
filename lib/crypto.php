<?php
declare(strict_types=1);

// Mã hoá khi lưu bản backup (secretbox) — dùng chung cho api/ và admin/.
// Mất file khoá `backup_key_file` = mọi bản backup không đọc được nữa → SAO LƯU cùng khoá ký.

require_once __DIR__ . '/bootstrap.php';

function khoa_backup(): string
{
    static $k = null;
    if ($k === null) {
        $doc = @file_get_contents((string)cfg('backup_key_file'));
        if ($doc === false || strlen($doc) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('backup key missing/invalid');
        }
        $k = $doc;
    }
    return $k;
}

function ma_hoa(string $plain): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return $nonce . sodium_crypto_secretbox($plain, $nonce, khoa_backup());
}

function giai_ma(string $blob): string
{
    $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, khoa_backup());
    if ($plain === false) {
        throw new RuntimeException('backup decrypt failed');
    }
    return $plain;
}
