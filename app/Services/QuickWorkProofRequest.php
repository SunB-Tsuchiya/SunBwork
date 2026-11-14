<?php

namespace App\Services;

use App\Models\JobNotification;
use App\Models\PersonalWorkEntry;
use App\Models\ProofRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Proof handoff never creates an assignment or completes the source work. */
class QuickWorkProofRequest
{
    public function __construct(private WorkJobReader $reader, private UnifiedMyJobBox $box) {}

    public function available(User $user, ?int $context = null): bool
    {
        return $this->box->available($user, $context) && $user->company?->company_type === 'sunbrain';
    }

    public function state(User $user, int $entry, ?int $context = null): array
    {
        if (! $this->available($user, $context)) return ['enabled' => false];
        $source = $this->reader->resolve($user, 'entry', $entry, $context);
        $existing = ProofRequest::where('source_personal_work_entry_id', $entry)->latest('id')->first();
        return ['enabled' => true, 'requested' => (bool) $existing,
            'requestId' => $existing?->id, 'status' => $existing?->status,
            'title' => $source->title ?: ($source->broadProject?->pathLabel() ?: ($source->projectJob?->title ?: ($source->project_name_text ?: '作業名なし')))];
    }

    public function store(Request $request, int $entry, ?int $context = null): array
    {
        $user = $request->user();
        abort_unless($this->available($user, $context), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'deadline' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:1000'],
            'project_job_id' => ['prohibited'], 'project_job_assignment_id' => ['prohibited'],
            'source_personal_work_entry_id' => ['prohibited'], 'requester_id' => ['prohibited'],
            'proof_cell_id' => ['prohibited'], 'proof_row_id' => ['prohibited'], 'proof_col_key' => ['prohibited'],
            'workflow_cell_id' => ['prohibited'], 'workflow_sheet_id' => ['prohibited'], 'workflow_stage_key' => ['prohibited'],
        ]);
        return DB::transaction(function () use ($user, $entry, $context, $data) {
            // Source-first lock also serializes against source deletion and duplicate submissions.
            PersonalWorkEntry::whereKey($entry)->lockForUpdate()->firstOrFail();
            $source = $this->reader->resolve($user, 'entry', $entry, $context);
            $existing = ProofRequest::where('source_personal_work_entry_id', $entry)->lockForUpdate()->latest('id')->first();
            if ($existing) return ['id' => $existing->id, 'replayed' => true];
            if ($source->completed_at) throw ValidationException::withMessages(['title' => '完了済みの作業からは校正依頼できません。']);
            $recipients = User::where('user_role', 'proof_coordinator')
                ->where('company_id', $user->company_id)->where('department_id', $user->department_id)->orderBy('id')->get();
            if ($recipients->isEmpty()) throw ValidationException::withMessages(['title' => '同じ会社・部署の校正窓口が登録されていません。']);
            $proof = ProofRequest::create($data + ['source_personal_work_entry_id' => $source->id,
                'project_job_id' => $source->project_job_id, 'requester_id' => $user->id, 'status' => 'pending']);
            // The existing proof notification is a DB record, with no broadcast side effect.
            foreach ($recipients as $recipient) {
                JobNotification::create(['type' => 'proof_requested', 'sender_id' => $user->id,
                    'recipient_id' => $recipient->id, 'project_job_id' => $proof->project_job_id,
                    'assignment_id' => null, 'message' => "{$user->name}さんから「{$proof->title}」の校正依頼が届きました"]);
            }
            return ['id' => $proof->id, 'replayed' => false];
        });
    }
}
