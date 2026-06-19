<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NSchool extends Model
{
    protected $table = 'n_schools';

    protected $fillable = ['code', 'year', 'name', 'category'];

    public function questions(): HasMany
    {
        return $this->hasMany(NQuestionsDaimon::class, 'school_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(NAnswersDaimon::class, 'school_id');
    }
}
