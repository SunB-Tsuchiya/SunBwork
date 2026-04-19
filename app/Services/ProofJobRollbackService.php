<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ProjectJobAssignment;
use App\Models\ProofRequest;
use App\Models\ProofSchedule;
use Illuminate\Support\Facades\Log;

/**
 * 校正スロット（pja101）のイベントが全て削除された際の
 * ロールバック処理をまとめたサービスクラス。
 *
 * 呼び出し元:
 *  - EventController::destroy
 *  - ProofRequestController::destroyEvent
 *  - ProofCoordinator/CalendarController::destroy
 */
class ProofJobRollbackService
{
    /**
     * 指定した pja（assignment_id）に紐づくイベントが 0 件になったとき、
     * その pja が校正スロット（pja101）であれば以下を実行する:
     *
     *  1. pja101 を削除
     *  2. 対応する pja100（校正管理者割当）を削除
     *  3. 対応する ProofRequest を pending に戻し proofreader_id をクリア
     *  4. pja100 に紐づく残存 ProofSchedule を削除
     *
     * @param int $assignmentId  削除されたイベントの project_job_assignment_id
     */
    public static function rollbackIfNoEvents(int $assignmentId): void
    {
        try {
            $pja101 = ProjectJobAssignment::find($assignmentId);
            if (! $pja101) return;

            // pja101 の判定: job_type='proof' かつ 自己割当（sender_id = user_id）
            if ($pja101->job_type !== 'proof') return;
            if ((int) $pja101->sender_id !== (int) $pja101->user_id) return;

            // イベントが残っている場合は何もしない
            $remaining = Event::where('project_job_assignment_id', $assignmentId)->count();
            if ($remaining > 0) return;

            // pja100 を特定（coordinator_assignment_id / supersedes_assignment_id どちらか）
            $pja100Id = $pja101->coordinator_assignment_id
                ?? $pja101->supersedes_assignment_id
                ?? null;

            // pja101 を削除
            $pja101->delete();

            Log::info('ProofJobRollbackService: pja101 deleted', [
                'pja101_id' => $assignmentId,
                'pja100_id' => $pja100Id,
            ]);

            if (! $pja100Id) return;

            $pja100 = ProjectJobAssignment::find($pja100Id);
            if (! $pja100) return;

            // ProofRequest を pending に戻す（assigned / in_progress のものが対象）
            $updatedCount = ProofRequest::where('project_job_id', $pja100->project_job_id)
                ->where('proofreader_id', $pja100->user_id)
                ->where('proof_coordinator_id', $pja100->sender_id)
                ->whereIn('status', ['assigned', 'in_progress'])
                ->update([
                    'status'         => 'pending',
                    'proofreader_id' => null,
                ]);

            // pja100 に紐づく ProofSchedule を削除（event_id=NULL の残骸）
            ProofSchedule::where('user_id', $pja100->user_id)
                ->whereNull('event_id')
                ->whereHas('proofRequest', function ($q) use ($pja100) {
                    $q->where('project_job_id', $pja100->project_job_id)
                      ->where('proof_coordinator_id', $pja100->sender_id);
                })
                ->delete();

            // pja100 を削除
            $pja100->delete();

            Log::info('ProofJobRollbackService: pja100 deleted, ProofRequest reverted to pending', [
                'pja100_id'      => $pja100Id,
                'updated_requests' => $updatedCount,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ProofJobRollbackService: rollback failed', [
                'assignment_id' => $assignmentId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
