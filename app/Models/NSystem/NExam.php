<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NExam extends Model
{
    protected $table = 'n_exams';

    protected $fillable = ['exam_series_id', 'admission_year', 'n_code', 'exam_label', 'source_notes'];

    public function examSeries(): BelongsTo
    {
        return $this->belongsTo(NExamSeries::class, 'exam_series_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(NExamDocument::class, 'exam_id');
    }

    public function publicationEntries(): HasMany
    {
        return $this->hasMany(NPublicationEntry::class, 'exam_id');
    }
}
