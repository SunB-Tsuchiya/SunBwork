<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ProofRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 校正ジョブ専用 UI は廃止し、依頼されたジョブ → マイジョブフローに統一。
 * 既存 URL との後方互換のためリダイレクトのみ提供する。
 */
class ProofJobController extends Controller
{
    public function index(Request $request)
    {
        return redirect()->route('user.jobbox.index')
            ->with('info', '校正ジョブは「依頼されたジョブ」で確認してください。');
    }

    public function show(ProofRequest $proofRequest)
    {
        return redirect()->route('user.jobbox.index');
    }

    public function setPage(ProofRequest $proofRequest)
    {
        return redirect()->route('user.jobbox.index');
    }

    public function set(Request $request, ProofRequest $proofRequest)
    {
        return redirect()->route('user.jobbox.index');
    }

    public function complete(ProofRequest $proofRequest)
    {
        $user = Auth::user();
        abort_if($proofRequest->proofreader_id !== $user->id, 403);

        if ($proofRequest->status === 'completed') {
            return back()->with('error', 'この校正依頼はすでに完了済みです。');
        }

        $proofRequest->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        if ($proofRequest->project_job_assignment_id) {
            \App\Models\ProjectJobAssignment::where('id', $proofRequest->project_job_assignment_id)
                ->whereNull('proof_completed_at')
                ->update(['proof_completed_at' => now()]);
        }

        if ($proofRequest->proofreader_id && $proofRequest->proof_coordinator_id) {
            \App\Models\ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
                ->where('user_id', $proofRequest->proofreader_id)
                ->where('sender_id', $proofRequest->proof_coordinator_id)
                ->update(['completed' => true]);
        }

        \App\Services\JobNotificationService::notifyProofCompleted($user, $proofRequest->fresh());

        return redirect()->route('user.jobbox.index')
            ->with('success', '校正が完了しました。依頼者に通知しました。');
    }
}
