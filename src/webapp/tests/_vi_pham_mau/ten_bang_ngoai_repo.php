<?php
// gia_lap: admin/bao_cao_dai_ly.php
// mong_doi: ten_bang_ngoai_repo
// MẪU VI PHẠM có chủ đích cho P02-T16 — không bao giờ được nạp/chạy.

$pdo = db();
$dong = $pdo->query('SELECT COUNT(*) FROM demo_cha')->fetchColumn();
$sql = <<<SQL
    SELECT * FROM demo_con WHERE id = 1
    SQL;
