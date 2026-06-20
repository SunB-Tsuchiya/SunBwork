<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NSchoolYear extends Model
{
    protected $table = 'n_school_years';

    protected $fillable = ['school_id', 'admission_year', 'school_name', 'normalized_name', 'gender_type', 'prefecture', 'notes'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(NSchool::class, 'school_id');
    }
}
