<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'client_code',
        'notes',
        'detail',
        'fromSB',
        'company_id',
        'is_dormant',
    ];

    protected $casts = [
        'fromSB'     => 'boolean',
        'is_dormant' => 'boolean',
    ];

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'client_departments');
    }

    public function projectJobs()
    {
        return $this->hasMany(ProjectJob::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope a query to only include clients belonging to a given company id.
     */
    public function scopeForCompany($query, $companyId)
    {
        if (empty($companyId)) return $query->whereNull('company_id');
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to only include active (non-dormant) clients.
     */
    public function scopeActive($query)
    {
        return $query->where('is_dormant', false);
    }

    /**
     * Scope a query to only include dormant clients.
     */
    public function scopeDormant($query)
    {
        return $query->where('is_dormant', true);
    }
}
