<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_job_id',
        'user_id',
    ];

    /**
     * @return BelongsTo
     */
    public function projectJob(): BelongsTo
    {
        return $this->belongsTo(ProjectJob::class);
    }

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
