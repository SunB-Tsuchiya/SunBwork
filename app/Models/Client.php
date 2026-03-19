<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'notes',
        'detail',
        'fromSB',
        'company_id',
    ];

    protected $casts = [
        'fromSB' => 'boolean',
    ];

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
}
