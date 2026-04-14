<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProofDispatcher extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'notes', 'is_active', 'company_id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** 校正ジョブ割当 */
    public function assignments()
    {
        return $this->hasMany(ProjectJobAssignment::class);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
