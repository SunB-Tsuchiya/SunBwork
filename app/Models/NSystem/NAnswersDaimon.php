<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NAnswersDaimon extends Model
{
    protected $table = 'n_answers_daimon';

    protected $fillable = ['school_id', 'subject', 'daimon_index', 'body_html', 'body_text'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(NSchool::class, 'school_id');
    }
}
