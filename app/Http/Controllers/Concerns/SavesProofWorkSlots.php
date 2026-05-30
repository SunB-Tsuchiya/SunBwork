<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Event;
use App\Models\ProjectJobAssignment;
use App\Models\ProofRequest;
use App\Models\ProofSchedule;
use Illuminate\Support\Facades\Log;

trait SavesProofWorkSlots
{
    /**
     * work_slots から ProofSchedule と Event（pja101）を作成・更新する。
     *
     * @param ProofRequest $proofRequest  proof_coordinator_id・proofreader_id が設定済みのもの
     * @param array        $slots         [['date','startHour','startMinute','endHour','endMinute'], ...]
     * @param bool         $replace       true = 既存エントリを削除してから再作成
     */
    protected function saveWorkSlots(ProofRequest $proofRequest, array $slots, bool $replace = false): void
    {
        if (empty($slots)) return;

        if ($replace) {
            ProofSchedule::where('proof_request_id', $proofRequest->id)->delete();
        }

        // pja100（coordinator が校正員に割り当てたジョブ）を特定してEventを直接作成する。
        // 新フローでは pja101（作業スロット用の中間ジョブ）は作成しない。
        // pja100 の Events + 校正者がマイジョブにした場合の pja101(supersedes) の Events
        // の両方が進行表工数集計に含まれるため、pja100 直接で問題ない。
        $pja100 = ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
            ->where('user_id', $proofRequest->proofreader_id)
            ->where('sender_id', $proofRequest->proof_coordinator_id)
            ->latest()->first();

        if ($replace && $pja100) {
            // coordinator がセットしたイベントのみ削除（プロの追加イベントは残す）
            Event::where('project_job_assignment_id', $pja100->id)->delete();
        }

        foreach ($slots as $slot) {
            if (empty($slot['date'])) continue;

            $date = $slot['date'];
            $sH   = str_pad($slot['startHour'],   2, '0', STR_PAD_LEFT);
            $sM   = str_pad($slot['startMinute'], 2, '0', STR_PAD_LEFT);
            $eH   = str_pad($slot['endHour'],     2, '0', STR_PAD_LEFT);
            $eM   = str_pad($slot['endMinute'],   2, '0', STR_PAD_LEFT);

            $startsAt = \Carbon\Carbon::parse("{$date} {$sH}:{$sM}:00", 'Asia/Tokyo')->utc();
            $endsAt   = \Carbon\Carbon::parse("{$date} {$eH}:{$eM}:00", 'Asia/Tokyo')->utc();

            try {
                ProofSchedule::create([
                    'proof_request_id' => $proofRequest->id,
                    'user_id'          => $proofRequest->proofreader_id,
                    'starts_at'        => $startsAt,
                    'ends_at'          => $endsAt,
                ]);
            } catch (\Throwable $e) {
                Log::warning('saveWorkSlots: failed to create ProofSchedule', [
                    'error'            => $e->getMessage(),
                    'proof_request_id' => $proofRequest->id,
                ]);
            }

            // pja100 に直接 Event を作成する（校正者のカレンダーに反映）
            if ($pja100) {
                try {
                    Event::create([
                        'user_id'                   => $proofRequest->proofreader_id,
                        'project_job_assignment_id' => $pja100->id,
                        'date'                      => $date,
                        'start'                     => "{$date} {$sH}:{$sM}:00",
                        'end'                       => "{$date} {$eH}:{$eM}:00",
                        'starts_at'                 => $startsAt,
                        'ends_at'                   => $endsAt,
                        'title'                     => $proofRequest->title,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('saveWorkSlots: failed to create Event on pja100', [
                        'error'  => $e->getMessage(),
                        'pja100' => $pja100->id ?? null,
                    ]);
                }
            }
        }
    }
}
