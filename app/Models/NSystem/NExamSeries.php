<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NExamSeries extends Model
{
    protected $table = 'n_exam_series';

    protected $fillable = ['school_id', 'series_key', 'canonical_label', 'is_active'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(NSchool::class, 'school_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(NExam::class, 'exam_series_id');
    }
}
