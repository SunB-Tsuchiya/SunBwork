<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProofRequest extends Model
{
    protected $fillable = [
        'source_personal_work_entry_id',
        'project_job_assignment_id',
        'project_job_id',
        'proof_cell_id',
        'workflow_cell_id',
        'requester_id',
        'proof_coordinator_id',
        'proofreader_id',
        'title',
        'deadline',
        'status',
        'note',
        'completed_at',
    ];

    protected $appends = ['quick_work_source'];

    protected $hidden = ['sourcePersonalWorkEntry'];

    protected static function booted(): void
    {
        // Legacy requests retain their existing visibility. Trial sources stay in their real scope.
        static::addGlobalScope('quick_work_scope', function ($query) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (! $user) return; // Trusted background processing has no interactive viewer.
            $request = request();
            if ($request->hasSession() && ($context = $request->session()->get('superadmin_context.company_id')) !== null
                && (int) $context !== (int) $user->company_id) {
                $query->whereNull('proof_requests.source_personal_work_entry_id');
                return;
            }
            $query->where(function ($q) use ($user) {
                $q->whereNull('proof_requests.source_personal_work_entry_id')
                    ->orWhereHas('sourcePersonalWorkEntry', fn ($source) => $source
                        ->where('company_id', $user->company_id)->where('department_id', $user->department_id));
            });
        });
    }

    public function sourcePersonalWorkEntry(): BelongsTo
    {
        return $this->belongsTo(PersonalWorkEntry::class, 'source_personal_work_entry_id');
    }

    public function getQuickWorkSourceAttribute(): ?array
    {
        if (! $this->source_personal_work_entry_id) return null;
        $user = \Illuminate\Support\Facades\Auth::user();
        if (! $user || ((int) $this->requester_id !== (int) $user->id
            && ! in_array($user->user_role, ['superadmin', 'admin', 'proof_coordinator'], true))) return null;
        $request = request();
        $context = $request->hasSession() ? $request->session()->get('superadmin_context.company_id') : null;
        if ($context !== null && (int) $context !== (int) $user->company_id) return null;
        $source = $this->sourcePersonalWorkEntry;
        if (! $source || (int) $source->company_id !== (int) $user->company_id
            || (int) $source->department_id !== (int) $user->department_id) return null;
        // Only handoff labels, never private result text, files, quantities or schedule details.
        $source->loadMissing('broadProject.parent');
        $broad = $source->broadProject;
        $classification = null;
        if ($broad && (int) $broad->company_id === (int) $user->company_id && (int) $broad->department_id === (int) $user->department_id
            && (! $broad->parent || ((int) $broad->parent->company_id === (int) $user->company_id && (int) $broad->parent->department_id === (int) $user->department_id))) {
            $classification = $broad->pathLabel();
        }
        return ['title' => $source->title ?: '（作業名なし）', 'classification' => $classification,
            'projectName' => $source->project_name_text,
            'entryId' => (int) $this->requester_id === (int) $user->id && app(\App\Services\QuickWorkProofRequest::class)->available($user, $context === null ? null : (int) $context) ? $source->id : null];
    }

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * DB の deadline カラムは UTC 文字列で保存されている。
     * APP_TIMEZONE=Asia/Tokyo 環境でも正しく UTC として読んで JST Carbon を返す。
     */
    protected function deadline(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $raw = $attributes['deadline'] ?? null;
                if (! $raw) return null;
                return Carbon::createFromFormat('Y-m-d H:i:s', $raw, 'UTC')
                             ->setTimezone('Asia/Tokyo');
            },
            set: function ($value) {
                if (! $value) return ['deadline' => null];
                $carbon = $value instanceof Carbon
                    ? $value
                    : Carbon::parse($value);
                // UTC 文字列として保存
                return ['deadline' => $carbon->utc()->format('Y-m-d H:i:s')];
            },
        );
    }

    // ---- リレーション ----

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function proofCoordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proof_coordinator_id');
    }

    public function proofreader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proofreader_id');
    }

    public function projectJob(): BelongsTo
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function projectJobAssignment(): BelongsTo
    {
        return $this->belongsTo(ProjectJobAssignment::class);
    }

    public function proofCell(): BelongsTo
    {
        return $this->belongsTo(ProgressCell::class, 'proof_cell_id');
    }

    // ---- ステータスヘルパー ----

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    // ---- スコープ ----

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['assigned', 'in_progress']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
