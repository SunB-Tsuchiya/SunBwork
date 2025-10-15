<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Attachment;

class Message extends Model
{
    protected $fillable = [
        'from_user_id',
        'subject',
        'body',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(MessageRecipient::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function attachments()
    {
        return $this->morphToMany(Attachment::class, 'attachable', 'attachmentables')
            ->withPivot(['role', 'order'])
            ->withTimestamps();
    }
}
