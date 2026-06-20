<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NExamDocument extends Model
{
    protected $table = 'n_exam_documents';

    protected $fillable = ['exam_id', 'subject', 'document_type', 'source_filename'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(NExam::class, 'exam_id');
    }

    public function daimons(): HasMany
    {
        return $this->hasMany(NExamDaimon::class, 'exam_document_id');
    }
}
