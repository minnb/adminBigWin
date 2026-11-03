<?php
// gia_lap: lib/bigwin/DoiSoatService.php
// mong_doi: goi_db
// MẪU VI PHẠM có chủ đích cho P02-T16 — không bao giờ được nạp/chạy.

final class MauDoiSoatService
{
    public function luu(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('SELECT 1');
        \db()->query('SELECT 1');
    }
}
