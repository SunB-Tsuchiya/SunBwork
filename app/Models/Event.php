<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'event_item_type_id',
        // DB has 'body', controllers use 'description'
        'body',
        // DB has 'starts_at'/'ends_at', controllers prefer 'start'/'end'
        'starts_at',
        'ends_at',
        // 'project_job_assignment_by_myself_id' removed via migration
        // aliases accepted for mass assignment
        'start',
        'end',
        'description',
    ];

    // Ensure accessor-backed attributes are included when the model is converted to array/JSON
    protected $appends = ['start', 'end', 'description', 'date'];

    // Cast DB timestamp columns to DateTime objects
    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function eventItemType()
    {
        return $this->belongsTo(EventItemType::class);
    }

    public function attachments()
    {
        return $this->morphToMany(Attachment::class, 'attachable', 'attachmentables')
            ->withPivot(['role', 'order'])
            ->withTimestamps();
    }

    /**
     * Optional relation to ProjectJobAssignment so events created from a job
     * can be linked back to the assignment.
     */
    public function projectJobAssignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class, 'project_job_assignment_id');
    }

    public function projectJobAssignmentByMyself()
    {
        // column removed: keep method stub removed to avoid accidental usage
        return null;
    }

    // Provide a virtual date attribute derived from start datetime
    public function getDateAttribute()
    {
        // Prefer the casted starts_at when available to ensure correct date format
        if (!empty($this->starts_at)) {
            try {
                return $this->starts_at->toDateString();
            } catch (\Exception $e) {
                // fallthrough to parsing raw start
            }
        }
        if (empty($this->start)) return null;
        try {
            return \Carbon\Carbon::parse($this->start)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    // Backwards-compatible accessors / mutators
    // description <-> body
    public function getDescriptionAttribute()
    {
        return $this->attributes['body'] ?? ($this->body ?? null);
    }

    public function setDescriptionAttribute($value)
    {
        $this->attributes['body'] = $value;
    }

    // start <-> starts_at
    public function getStartAttribute()
    {
        // Use the casted starts_at (Carbon) when available so JSON contains a proper datetime string
        if (isset($this->attributes['starts_at'])) {
            // When model is hydrated with starts_at, Eloquent will cast it; access via property to get the cast
            try {
                $val = $this->starts_at;
                if ($val instanceof \Carbon\CarbonInterface) {
                    return $val->toIso8601String();
                }
                return $val;
            } catch (\Exception $e) {
                // fallback to raw attribute
                return $this->attributes['starts_at'];
            }
        }
        return $this->attributes['start'] ?? null;
    }

    public function setStartAttribute($value)
    {
        $this->attributes['starts_at'] = $value;
    }

    // end <-> ends_at
    public function getEndAttribute()
    {
        if (isset($this->attributes['ends_at'])) {
            try {
                $val = $this->ends_at;
                if ($val instanceof \Carbon\CarbonInterface) {
                    return $val->toIso8601String();
                }
                return $val;
            } catch (\Exception $e) {
                return $this->attributes['ends_at'];
            }
        }
        return $this->attributes['end'] ?? null;
    }

    public function setEndAttribute($value)
    {
        $this->attributes['ends_at'] = $value;
    }
}
