<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NExamDaimon extends Model
{
    protected $table = 'n_exam_daimons';

    protected $fillable = ['exam_document_id', 'daimon_index', 'body_html', 'body_text'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(NExamDocument::class, 'exam_document_id');
    }
}
