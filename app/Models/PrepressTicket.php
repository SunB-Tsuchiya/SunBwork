<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class PrepressTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_job_id',
        'client_id',
        'jobcode',
        'title',
        'project_name',
        'client_name',
        'memo',
        'status',
        'image_path',
        'original_filename',
    ];

    protected $appends = ['image_url'];

    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_SUBMITTING  = 'submitting';
    const STATUS_COMPLETED   = 'completed';

    const STATUS_LABELS = [
        'pending'     => '準備',
        'in_progress' => '作業中',
        'submitting'  => '入稿予定',
        'completed'   => '完了',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
