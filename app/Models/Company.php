<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'active',
        'representative_id',
        'representative_leader_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * 代表者（Admin ユーザー）
     */
    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    /**
     * 代表者リーダー（Leader ユーザー）
     */
    public function representativeLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_leader_id');
    }

    /**
     * 会社の部署
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * 会社のチーム
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * アクティブな会社のみ取得
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
