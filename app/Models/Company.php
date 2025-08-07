<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

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
