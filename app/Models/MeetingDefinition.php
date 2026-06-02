<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MeetingDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'company_id',
        'title',
        'description',
        'recurrence',
        'day_of_week',
        'week_of_month',
        'start_time',
        'end_time',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_definition_members', 'meeting_definition_id', 'user_id')
            ->withTimestamps();
    }
}
