<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementRecipient extends Model
{
    protected $fillable = ['announcement_id', 'user_id', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /** Only recipients of announcements that have actually been sent. */
    public function scopeForSentAnnouncements(Builder $query): Builder
    {
        return $query->whereHas(
            'announcement',
            fn (Builder $announcementQuery) => $announcementQuery->where('status', 'sent')
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
