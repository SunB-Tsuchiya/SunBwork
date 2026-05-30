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

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_clients');
    }

    /**
     * Scope a query to only include clients registered to a given company via company_clients.
     */
    public function scopeForCompany($query, $companyId)
    {
        if (empty($companyId)) {
            return $query->whereDoesntHave('companies');
        }
        return $query->whereHas('companies', fn($q) => $q->where('companies.id', $companyId));
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
