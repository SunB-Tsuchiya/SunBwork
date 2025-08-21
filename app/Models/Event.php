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
        'description',
        'start',
        'end'
    ];

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'event_id');
    }

    // Provide a virtual date attribute derived from start datetime
    public function getDateAttribute()
    {
        if (empty($this->start)) return null;
        try {
            return \Carbon\Carbon::parse($this->start)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }
}
