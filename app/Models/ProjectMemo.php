<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ProjectMemo extends Model
{
    use HasFactory;

    protected $table = 'project_memos';

    protected $fillable = [
        'project_id',
        'user_id',
        'date',
        'body',
        'metadata',
    ];

    protected $casts = [
        'date' => 'date',
        'metadata' => 'array',
    ];

    /**
     * The user who created the memo.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
