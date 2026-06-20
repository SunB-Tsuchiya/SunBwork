<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NPublicationEntry extends Model
{
    protected $table = 'n_publication_entries';

    protected $fillable = [
        'publication_edition_id', 'school_id', 'exam_id', 'mikuni_code', 'publication_section', 'sort_order',
        'printed_school_name', 'printed_exam_label', 'source_row_number', 'source_notes',
    ];

    public function publicationEdition(): BelongsTo
    {
        return $this->belongsTo(NPublicationEdition::class, 'publication_edition_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(NSchool::class, 'school_id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(NExam::class, 'exam_id');
    }
}
