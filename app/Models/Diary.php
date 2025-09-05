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
    'read_by',
    'comments',
    ];

    protected $casts = [
        'date' => 'date',
        'read_by' => 'array',
    'comments' => 'array',
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

    /**
     * Check whether the diary has been read by the given user id.
     * Accepts stored read_by arrays that may contain strings or integers.
     */
    public function hasBeenReadBy($userId): bool
    {
        $readBy = $this->read_by ?? [];
        if (!is_array($readBy) || empty($readBy)) return false;

        $needle = intval($userId);
        foreach ($readBy as $entry) {
            if (intval($entry) === $needle) return true;
        }
        return false;
    }

    /**
     * Add the given user id to read_by and persist if not already present.
     */
    public function addReadBy($userId): void
    {
        $readBy = $this->read_by ?? [];
        if (!is_array($readBy)) $readBy = [];

        $needle = intval($userId);
        $normalized = array_map('intval', $readBy);
        if (!in_array($needle, $normalized, true)) {
            $normalized[] = $needle;
            $this->read_by = array_values(array_unique($normalized));
            $this->save();
        }
    }

    /**
     * Add a comment to the diary. Comment shape: {user_id, user_name, comment, created_at}
     */
    public function addComment(int $userId, string $userName, string $comment): void
    {
        $comments = $this->comments ?? [];
        if (!is_array($comments)) $comments = [];

        $comments[] = [
            'user_id' => intval($userId),
            'user_name' => $userName,
            'comment' => mb_substr($comment, 0, 1000),
            'created_at' => now()->toDateTimeString(),
        ];

        $this->comments = $comments;
        $this->save();
    }
}
