<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProofRequest extends Model
{
    protected $fillable = [
        'project_job_assignment_id',
        'project_job_id',
        'requester_id',
        'proof_coordinator_id',
        'proofreader_id',
        'title',
        'deadline',
        'status',
        'note',
        'completed_at',
    ];

    protected $casts = [
        'deadline'     => 'datetime',
        'completed_at' => 'datetime',
    ];

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
