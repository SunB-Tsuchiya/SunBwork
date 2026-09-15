<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Announcement extends Model
{
    protected $fillable = ['sender_id', 'target_type', 'title', 'content', 'target_company_id', 'status'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Announcements managed from a company's role context. */
    public function scopeManagedInCompanyContext(Builder $query, int $companyId, int $userId): Builder
    {
        return $query->where(function (Builder $managedQuery) use ($companyId, $userId) {
            $managedQuery
                ->whereHas('sender', fn (Builder $senderQuery) => $senderQuery->where('company_id', $companyId))
                ->orWhere('sender_id', $userId);
        });
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(AnnouncementRecipient::class);
    }

    public function attachments(): MorphToMany
    {
        return $this->morphToMany(Attachment::class, 'attachable', 'attachmentables');
    }
}
