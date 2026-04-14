<?php

namespace App\Services;

use App\Models\JobNotification;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\ProofRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class JobNotificationService
{
    /**
     * 案件のリーダー・副リーダーのIDリストを返す（指定ユーザーは除く）
     */
    public static function getLeaderIds(ProjectJob $projectJob, int $excludeUserId): array
    {
        $ids = [];
        if ($projectJob->user_id) {
            $ids[] = $projectJob->user_id;
        }
        $subCoIds = $projectJob->coordinators()->pluck('users.id')->toArray();
        $ids = array_unique(array_merge($ids, $subCoIds));

        return array_values(array_filter($ids, fn($id) => $id !== $excludeUserId));
    }

    /**
     * ① 新規ジョブ依頼通知
     * - 依頼相手: 「〇〇さんから「案件名」の新しいジョブが依頼されました」
     * - リーダー・副リーダー（依頼主・依頼相手を除く）:
     *   「〇〇さんが△△さんに「案件名」のジョブを依頼しました」
     */
    public static function notifyNewJob(
        User $sender,
        int $recipientId,
        ProjectJob $projectJob,
        ProjectJobAssignment $assignment
    ): void {
        try {
            $jobTitle      = $projectJob->title ?? '案件';
            $recipientUser = User::find($recipientId);
            $recipientName = $recipientUser?->name ?? 'ユーザー';

            // 依頼相手への通知
            JobNotification::create([
                'type'           => 'new_job',
                'sender_id'      => $sender->id,
                'recipient_id'   => $recipientId,
                'project_job_id' => $projectJob->id,
                'assignment_id'  => $assignment->id,
                'message'        => "{$sender->name}さんから「{$jobTitle}」の新しいジョブが依頼されました",
            ]);

            // リーダー・副リーダーへの情報通知（依頼主・依頼相手を除く）
            $leaderIds = self::getLeaderIds($projectJob, $sender->id);
            $leaderIds = array_values(array_filter($leaderIds, fn($id) => $id !== $recipientId));
            foreach ($leaderIds as $leaderId) {
                JobNotification::create([
                    'type'           => 'new_job_info',
                    'sender_id'      => $sender->id,
                    'recipient_id'   => $leaderId,
                    'project_job_id' => $projectJob->id,
                    'assignment_id'  => $assignment->id,
                    'message'        => "{$sender->name}さんが{$recipientName}さんに「{$jobTitle}」のジョブを依頼しました",
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (new_job)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ② ジョブ完了通知（Coordinator依頼分のみ）
     * sender_id === user_id（自己割当）の場合は通知しない。
     * - 依頼主: 「〇〇さんが「ジョブ名」を完了しました」
     * - リーダー・副リーダー（自分・依頼主を除く）: 同じメッセージ
     */
    public static function notifyCompleted(
        User $user,
        ProjectJobAssignment $assignment,
        ProjectJob $projectJob
    ): void {
        if (!$assignment->sender_id || $assignment->sender_id === $user->id) {
            return;
        }

        try {
            $assignmentTitle = $assignment->title ?? 'ジョブ';
            $message         = "{$user->name}さんが「{$assignmentTitle}」を完了しました";

            // 依頼主への通知
            JobNotification::create([
                'type'           => 'completed',
                'sender_id'      => $user->id,
                'recipient_id'   => $assignment->sender_id,
                'project_job_id' => $projectJob->id,
                'assignment_id'  => $assignment->id,
                'message'        => $message,
            ]);

            // リーダー・副リーダーへの情報通知（自分・依頼主を除く）
            $leaderIds = self::getLeaderIds($projectJob, $user->id);
            $leaderIds = array_values(array_filter($leaderIds, fn($id) => $id !== $assignment->sender_id));
            foreach ($leaderIds as $leaderId) {
                JobNotification::create([
                    'type'           => 'completed_info',
                    'sender_id'      => $user->id,
                    'recipient_id'   => $leaderId,
                    'project_job_id' => $projectJob->id,
                    'assignment_id'  => $assignment->id,
                    'message'        => $message,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (completed)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ③ 進行管理表からのジョブ登録通知
     * リーダー・副リーダー（自分以外）へ:
     * 「〇〇さんが「案件名」の進行管理表でジョブを登録しました」
     */
    public static function notifyProgressRegistered(
        User $user,
        ProjectJob $projectJob,
        ProjectJobAssignment $assignment
    ): void {
        try {
            $jobTitle = $projectJob->title ?? '案件';
            $message  = "{$user->name}さんが「{$jobTitle}」の進行管理表でジョブを登録しました";

            $leaderIds = self::getLeaderIds($projectJob, $user->id);
            foreach ($leaderIds as $leaderId) {
                JobNotification::create([
                    'type'           => 'progress_registered',
                    'sender_id'      => $user->id,
                    'recipient_id'   => $leaderId,
                    'project_job_id' => $projectJob->id,
                    'assignment_id'  => $assignment->id,
                    'message'        => $message,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (progress_registered)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ④ 進行管理表からのジョブ完了通知
     * リーダー・副リーダー（自分以外）へ:
     * 「〇〇さんが「案件名」の進行管理表のジョブを完了しました」
     */
    public static function notifyProgressCompleted(
        User $user,
        ProjectJob $projectJob,
        ProjectJobAssignment $assignment
    ): void {
        try {
            $jobTitle = $projectJob->title ?? '案件';
            $message  = "{$user->name}さんが「{$jobTitle}」の進行管理表のジョブを完了しました";

            $leaderIds = self::getLeaderIds($projectJob, $user->id);
            foreach ($leaderIds as $leaderId) {
                JobNotification::create([
                    'type'           => 'progress_completed',
                    'sender_id'      => $user->id,
                    'recipient_id'   => $leaderId,
                    'project_job_id' => $projectJob->id,
                    'assignment_id'  => $assignment->id,
                    'message'        => $message,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (progress_completed)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ⑤ 校正依頼通知
     * proof_coordinator ロールの全ユーザーへ:
     * 「〇〇さんから「タイトル」の校正依頼が届きました」
     */
    public static function notifyProofRequested(User $requester, ProofRequest $proofRequest): void
    {
        try {
            $proofCoordinators = User::where('user_role', 'proof_coordinator')->get();
            foreach ($proofCoordinators as $pc) {
                JobNotification::create([
                    'type'           => 'proof_requested',
                    'sender_id'      => $requester->id,
                    'recipient_id'   => $pc->id,
                    'project_job_id' => $proofRequest->project_job_id,
                    'assignment_id'  => $proofRequest->project_job_assignment_id,
                    'message'        => "{$requester->name}さんから「{$proofRequest->title}」の校正依頼が届きました",
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (proof_requested)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ⑥ 校正員割り当て通知（PCあり校正員へ）
     * 「「タイトル」の校正が割り当てられました」
     */
    public static function notifyProofAssigned(User $proofCoordinator, ProofRequest $proofRequest): void
    {
        if (! $proofRequest->proofreader_id) {
            return;
        }

        try {
            JobNotification::create([
                'type'           => 'proof_assigned',
                'sender_id'      => $proofCoordinator->id,
                'recipient_id'   => $proofRequest->proofreader_id,
                'project_job_id' => $proofRequest->project_job_id,
                'assignment_id'  => $proofRequest->project_job_assignment_id,
                'message'        => "「{$proofRequest->title}」の校正が割り当てられました",
            ]);
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (proof_assigned)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ⑦ 校正完了通知（依頼者へ）
     * 「「タイトル」の校正が完了しました」
     */
    public static function notifyProofCompleted(User $completedBy, ProofRequest $proofRequest): void
    {
        try {
            JobNotification::create([
                'type'           => 'proof_completed',
                'sender_id'      => $completedBy->id,
                'recipient_id'   => $proofRequest->requester_id,
                'project_job_id' => $proofRequest->project_job_id,
                'assignment_id'  => $proofRequest->project_job_assignment_id,
                'message'        => "「{$proofRequest->title}」の校正が完了しました",
            ]);
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (proof_completed)', ['error' => $e->getMessage()]);
        }
    }
}
