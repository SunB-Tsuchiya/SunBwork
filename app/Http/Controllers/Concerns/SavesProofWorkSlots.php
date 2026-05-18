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

        // pja100（coordinator が校正員に割り当てたジョブ）を特定
        $pja100 = ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
            ->where('user_id', $proofRequest->proofreader_id)
            ->where('sender_id', $proofRequest->proof_coordinator_id)
            ->latest()->first();

        $pja101 = null;
        if ($pja100) {
            // 校正員自身が作成した自己割当ジョブ（pja101）を探す
            $pja101 = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($pja100) {
                    $q->where('coordinator_assignment_id', $pja100->id)
                      ->orWhere('supersedes_assignment_id', $pja100->id);
                })->latest()->first();

            // 存在しなければ作成
            if (! $pja101 && $proofRequest->proofreader_id) {
                $pja101 = ProjectJobAssignment::create([
                    'project_job_id'            => $proofRequest->project_job_id,
                    'user_id'                   => $proofRequest->proofreader_id,
                    'sender_id'                 => $proofRequest->proofreader_id,
                    'coordinator_assignment_id' => $pja100->id,
                    'job_type'                  => 'proof',
                    'title'                     => $proofRequest->title,
                    'scheduled'                 => true,
                    'scheduled_at'              => now(),
                ]);
            }

            if ($replace && $pja101) {
                Event::where('project_job_assignment_id', $pja101->id)->delete();
            }
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

            if ($pja101) {
                try {
                    Event::create([
                        'user_id'                   => $proofRequest->proofreader_id,
                        'project_job_assignment_id' => $pja101->id,
                        'date'                      => $date,
                        'start'                     => "{$date} {$sH}:{$sM}:00",
                        'end'                       => "{$date} {$eH}:{$eM}:00",
                        'starts_at'                 => $startsAt,
                        'ends_at'                   => $endsAt,
                        'title'                     => $proofRequest->title,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('saveWorkSlots: failed to create Event', [
                        'error'   => $e->getMessage(),
                        'pja101'  => $pja101->id ?? null,
                    ]);
                }
            }
        }
    }
}
