<?php
// gia_lap: lib/bigwin/repo/KhachHangRepo.php
// mong_doi: user_id_viet_tay, tham_so_user_id
// MẪU VI PHẠM có chủ đích cho P02-T16 — không bao giờ được nạp/chạy.

final class MauKhachHangRepo extends TenantRepo
{
    protected const BANG = 'demo_cha';

    public function cuaUser(int $userId): array
    {
        return $this->tim('user_id = ?', [$userId]);
    }

    public function taoHo(int $id): int
    {
        return $this->them(['user_id' => $id, 'ten' => 'x']);
    }
}
