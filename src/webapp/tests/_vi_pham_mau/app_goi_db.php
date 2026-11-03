<?php
// gia_lap: app/ajax/bao_cao.php
// mong_doi: goi_db
// MẪU VI PHẠM có chủ đích cho P02-T16 — không bao giờ được nạp/chạy.

$ctx = ajax_khoi_dong('doc');
$st = db()->prepare('SELECT 1');
$st->execute();
