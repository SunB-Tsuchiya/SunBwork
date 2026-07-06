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
        'sales_rep',
        'sales_rep_id',
        'memo',
        'submission_date',
        'sb_delivery_date',
        'indesign_version',
        'illustrator_version',
        'check_memo',
        'status',
        'image_path',
        'original_filename',
        'card_color',
    ];

    protected $casts = [
        'submission_date'  => 'date:Y/m/d',
        'sb_delivery_date' => 'date:Y/m/d',
    ];

    protected $appends = ['image_url'];

    const STATUS_PENDING     = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_SUBMITTING  = 'submitting';
    const STATUS_OUTPUTTING  = 'outputting';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_DELETED     = 'deleted';

    const STATUS_LABELS = [
        'pending'     => '準備',
        'in_progress' => '作業中',
        'submitting'  => '入稿予定',
        'outputting'  => '出稿中',
        'completed'   => '完了',
        'deleted'     => '削除',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class);
    }

    public function salesRepEntry()
    {
        return $this->belongsTo(PrepresSalesRep::class, 'sales_rep_id');
    }

    public function stageChecks()
    {
        return $this->hasMany(PrepressTicketStageCheck::class, 'prepress_ticket_id');
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
