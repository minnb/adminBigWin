<?php
// PostToolUse hook (Edit|Write): chạy `php -l` trên tệp .php vừa sửa.
// Lỗi cú pháp → exit 2 + stderr để Claude tự sửa ngay.
$in = json_decode((string)stream_get_contents(STDIN), true);
$file = $in['tool_input']['file_path'] ?? '';
if (!is_string($file) || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php' || !is_file($file)) {
    exit(0);
}
exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "php -l thất bại cho $file:\n" . implode("\n", $out) . "\n");
    exit(2);
}
exit(0);
