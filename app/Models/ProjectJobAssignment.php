<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ProjectJobAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_job_id',
        'user_id',
        'estimated_hours',
        'title',
        'detail',
        'desired_at',
        'desired_end_date',
        'desired_time',
        'assigned',
        'completed',
        'accepted',
        // lookup fields migrated from WorkItem
        'size_id',
        'work_item_type_id',
        'stage_id',
        'status_id',
        'company_id',
        'department_id',
        // quantity fields
        'amounts',
        'amounts_unit',
        'difficulty_id',
        // starts_at/ends_at removed from fillable
        'sender_id',
        'read_at',
        'scheduled',
        'scheduled_at',
        'source_assignment_id',
        'supersedes_assignment_id',
        'start_time',
        'subcontractor_id',
        'proof_dispatcher_id',
        'job_type',
        'file_info',
    ];

    protected $casts = [
        'desired_at' => 'datetime',
        // serialize dates as plain Y-m-d to avoid ISO timestamp with T and timezone
        'desired_end_date' => 'date:Y-m-d',
        'desired_time' => 'string',
        // estimated_hours stores fractional hours, e.g. 1.5 == 1 hour 30 minutes
        'estimated_hours' => 'float',
        'assigned' => 'boolean',
        'completed' => 'boolean',
        'accepted' => 'boolean',
        'read_at' => 'datetime',
        // starts_at/ends_at removed from casts
        'sender_id' => 'integer',
        'scheduled' => 'boolean',
        'scheduled_at' => 'datetime',
        'source_assignment_id' => 'integer',
        'supersedes_assignment_id' => 'integer',
        'file_info' => 'array',
    ];

    protected $dates = [
        'desired_end_date',
        'desired_at',
        'read_at',
        // removed desired_start_date, starts_at, ends_at from dates
    ];

    // sender_id = user_id → 自己割当（旧 project_job_assignment_by_myself）
    public function scopeSelfAssigned($query)
    {
        return $query->whereColumn('sender_id', 'user_id');
    }

    // sender_id ≠ user_id または sender_id IS NULL → Coordinator 割当
    public function scopeCoordinatorAssigned($query)
    {
        return $query->where(function ($q) {
            $q->whereColumn('sender_id', '!=', 'user_id')
              ->orWhereNull('sender_id');
        });
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // 元ジョブ（続き元）
    public function sourceAssignment()
    {
        return $this->belongsTo(self::class, 'source_assignment_id');
    }

    // このジョブを続きとして登録した後続ジョブ一覧
    public function continuations()
    {
        return $this->hasMany(self::class, 'source_assignment_id');
    }

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class, 'project_job_id');
    }

    public function difficultyModel()
    {
        return $this->belongsTo(\App\Models\Difficulty::class, 'difficulty_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relations to lookup models (optional)
    public function workItemType()
    {
        return $this->belongsTo(WorkItemType::class, 'work_item_type_id');
    }

    public function size()
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class, 'stage_id');
    }

    public function statusModel()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function events()
    {
        return $this->hasMany(\App\Models\Event::class, 'project_job_assignment_id');
    }

    public function fileStats()
    {
        return $this->hasOne(\App\Models\AssignmentFileStat::class, 'project_job_assignment_id');
    }

    public function proofDispatcher()
    {
        return $this->belongsTo(ProofDispatcher::class, 'proof_dispatcher_id');
    }

    /**
     * このマイジョブが置き換える依頼ジョブ（supersedes_assignment_id が指す先）
     */
    public function supersedesAssignment()
    {
        return $this->belongsTo(self::class, 'supersedes_assignment_id');
    }

    /**
     * この依頼ジョブを置き換えたマイジョブ一覧（逆引き）
     */
    public function supersededBy()
    {
        return $this->hasMany(self::class, 'supersedes_assignment_id');
    }

    /**
     * Build a standardized array for pre-filling Event creation forms.
     * Includes assignment fields, linked project job fields and client info when available.
     *
     * @return array
     */
    public function toEventPrefill(): array
    {
        // ensure relations are loaded to avoid extra queries in callers
        $this->loadMissing(['projectJob.client', 'user', 'size', 'stage', 'workItemType', 'statusModel']);

        $jobData = [
            'id' => $this->id,
            'project_job_id' => $this->project_job_id,
            'title' => $this->title ?: ($this->projectJob ? ($this->projectJob->name ?? null) : null),
            'details' => $this->detail ?? ($this->projectJob ? ($this->projectJob->detail ?? null) : null),
            'assigned_user_name' => $this->user ? $this->user->name : null,
            'assigned_user_id' => $this->user ? $this->user->id : null,
            'user' => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'project_job_name' => $this->projectJob ? ($this->projectJob->name ?? null) : null,
            'project_job_detail' => $this->projectJob ? ($this->projectJob->detail ?? null) : null,
            'difficulty' => $this->difficulty ?? ($this->projectJob ? ($this->projectJob->difficulty ?? null) : null),
            'desired_end_date' => $this->desired_end_date ? (method_exists($this->desired_end_date, 'format') ? $this->desired_end_date->format('Y-m-d') : (string)$this->desired_end_date) : null,
            'desired_time' => $this->desired_time ?? null,
            'estimated_hours' => $this->estimated_hours ?? null,
            'scheduled' => Schema::hasColumn('project_job_assignments', 'scheduled') ? (bool) ($this->scheduled ?? false) : false,
            'scheduled_at' => Schema::hasColumn('project_job_assignments', 'scheduled_at') && $this->scheduled_at ? (method_exists($this->scheduled_at, 'format') ? $this->scheduled_at->format('Y-m-d H:i:s') : (string)$this->scheduled_at) : null,
            'accepted' => Schema::hasColumn('project_job_assignments', 'accepted') ? (bool) ($this->accepted ?? false) : false,
            'completed' => Schema::hasColumn('project_job_assignments', 'completed') ? (bool) ($this->completed ?? false) : false,
            'preferred_date' => null,
            'size_label' => $this->size ? ($this->size->name ?? $this->size->label ?? null) : null,
            'stage_label' => $this->stage ? ($this->stage->name ?? $this->stage->label ?? null) : null,
            'type_label' => $this->workItemType ? ($this->workItemType->name ?? $this->workItemType->label ?? null) : null,
            'status_label' => $this->statusModel ? ($this->statusModel->name ?? $this->statusModel->label ?? null) : null,
            'client' => $this->projectJob && isset($this->projectJob->client) ? ['id' => $this->projectJob->client->id ?? null, 'name' => $this->projectJob->client->name ?? null] : null,
            'client_name' => $this->projectJob && isset($this->projectJob->client) ? ($this->projectJob->client->name ?? null) : null,
        ];

        return $jobData;
    }
}
