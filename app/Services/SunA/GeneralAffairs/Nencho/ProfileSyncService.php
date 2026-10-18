<?php

namespace App\Services\SunA\GeneralAffairs\Nencho;

use App\Models\SunA\GeneralAffairs\Nencho\EmployeeProfile;
use App\Models\SunA\GeneralAffairs\Nencho\Submission;
use App\Models\User;
use App\Support\SunA\GeneralAffairs\Nencho\ProfileDiff;
use Illuminate\Support\Facades\DB;

/**
 * 申告と社員情報の差異に対する、総務の対応。グループごとに「申告に合わせて社員情報を更新（update）」か「社員情報のまま（keep）」を選ぶ。
 * 対応した記録は申告（profile_sync）と履歴に残し、内容が変わらない限り再び差異として出さない。
 */
class ProfileSyncService
{
    /**
     * @param  array<string, string>  $decisions  グループ名 => update | keep（差異のあるグループすべてについて選ぶ）
     */
    public function apply(Submission $s, User $actor, array $decisions, SubmissionService $submissions): void
    {
        DB::transaction(function () use ($s, $actor, $decisions, $submissions) {
            $profile = EmployeeProfile::firstOrNew(['user_id' => $s->user_id]);
            $updated = [];
            foreach ($decisions as $group => $decision) {
                if ($decision !== 'update' || ! isset(ProfileDiff::GROUPS[$group])) {
                    continue;
                }
                foreach (array_keys(ProfileDiff::GROUPS[$group]['fields']) as $f) {
                    $profile->{$f} = $s->{$f};
                }
                $updated[] = ProfileDiff::GROUPS[$group]['label'];
            }
            if ($updated) {
                $profile->company_id = $profile->company_id ?: $s->company_id;
                $profile->save();
            }
            // 対応後の状態の署名を保存（以後、内容が変わらなければ「対応済み」として扱う）
            // 更新した場合は保存後の社員情報で、更新しなかった場合は現在の社員情報（未登録なら null）で比較する
            $current = $profile->exists ? $profile->fresh() : null;
            $diff = ProfileDiff::compute($s, $current);
            $s->update(['profile_sync' => [
                'resolved_at' => now('Asia/Tokyo')->format('Y-m-d H:i'),
                'resolved_by' => $actor->name,
                'decisions' => $decisions,
                'signature' => $diff['signature'],
            ]]);
            $labels = [];
            foreach ($decisions as $g => $d) {
                $labels[] = (ProfileDiff::GROUPS[$g]['label'] ?? $g) . '＝' . ($d === 'update' ? '申告に合わせて更新' : '社員情報のまま');
            }
            $submissions->log($s, 'profile_synced', $actor, implode('／', $labels), ['decisions' => $decisions]);
        });
    }
}
