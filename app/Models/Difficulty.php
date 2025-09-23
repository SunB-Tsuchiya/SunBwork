<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Difficulty extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'sort_order', 'description', 'company_id', 'department_id', 'coefficient'];

    /**
     * Difficulty の会社
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Difficulty の部署
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
