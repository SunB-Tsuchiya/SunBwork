<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProofRequest extends Model
{
    protected $fillable = [
        'project_job_assignment_id',
        'project_job_id',
        'proof_cell_id',
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
