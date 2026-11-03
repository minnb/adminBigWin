<?php
// gia_lap: app/ajax/xem_ho.php
// mong_doi: tu_tao_ngu_canh
// MẪU VI PHẠM có chủ đích cho P02-T16 — không bao giờ được nạp/chạy.

$ctx = ajax_khoi_dong('doc');
$giaMao = TenantContext::tuPhien(['id' => (int) ($ctx['du_lieu']['uid'] ?? 0), 'role' => 'user']);
$cli = TenantContext::choCli(1, 'lách');
