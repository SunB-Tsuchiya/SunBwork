<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diary extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'content',
    ];

    protected $casts = [
        'date' => 'date',
        'read_by' => 'array',
        'admin_comments' => 'array',
    ];

    /**
     * 派生元ユーザー
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 添付ファイル
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'diary_id');
    }
}
