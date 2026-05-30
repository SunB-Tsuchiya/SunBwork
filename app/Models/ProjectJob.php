<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProjectJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'jobcode',
        'title',
        'user_id',
        'client_id',
        'company_id',
        'detail',
        'size_id',
        'page_count',
        'schedule',
        'completed',
        'shared_from_id',
        'image_path',
        'original_filename',
        'sales_rep',
        'sales_rep_id',
        'plate_submission_date',
        'plate_down_date',
    ];

    protected $appends = ['image_url'];

    protected $casts = [
        'schedule' => 'array',
        'completed' => 'boolean',
        'page_count' => 'integer',
        'plate_submission_date' => 'date',
        'plate_down_date' => 'date',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany($query, ?int $companyId)
    {
        if ($companyId === null) {
            return $query;
        }
        return $query->where('company_id', $companyId);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // サブリーダー（リーダー以外の共同管理者）
    public function coordinators()
    {
        return $this->belongsToMany(User::class, 'project_job_coordinators');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    // ProjectTeamMembers relation
    public function teamMembers()
    {
        // eager-load the user relation for display convenience
        return $this->hasMany(ProjectTeamMember::class, 'project_job_id');
    }

    // Schedules for this project job
    public function schedules()
    {
        return $this->hasMany(\App\Models\ProjectSchedule::class, 'project_job_id');
    }

    public function projectJobAssignments()
    {
        return $this->hasMany(\App\Models\ProjectJobAssignment::class, 'project_job_id');
    }

    public function progressSheets()
    {
        return $this->hasMany(\App\Models\ProgressSheet::class, 'project_job_id');
    }

    public function itemEntries()
    {
        return $this->hasMany(\App\Models\ProjectItemEntry::class, 'project_job_id')->orderBy('sort_order');
    }

    public function workflowSheets()
    {
        return $this->hasMany(\App\Models\WorkflowSheet::class, 'project_job_id')->orderBy('sort_order');
    }

    public function items()
    {
        return $this->hasMany(\App\Models\ProjectJobItem::class, 'project_job_id');
    }

    // この案件から共有されて作られた案件一覧
    public function sharedJobs()
    {
        return $this->hasMany(ProjectJob::class, 'shared_from_id');
    }

    // この案件の共有元案件
    public function sharedFrom()
    {
        return $this->belongsTo(ProjectJob::class, 'shared_from_id');
    }
}
