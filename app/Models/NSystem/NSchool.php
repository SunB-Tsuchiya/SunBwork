<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NSchool extends Model
{
    protected $table = 'n_schools';

    protected $fillable = ['n_code_prefix', 'canonical_name', 'prefecture', 'is_active', 'merged_into_id'];

    public function schoolYears(): HasMany
    {
        return $this->hasMany(NSchoolYear::class, 'school_id');
    }

    public function examSeries(): HasMany
    {
        return $this->hasMany(NExamSeries::class, 'school_id');
    }

    public function publicationEntries(): HasMany
    {
        return $this->hasMany(NPublicationEntry::class, 'school_id');
    }
}
