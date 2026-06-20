<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;

class NSourceSchoolRow extends Model
{
    protected $table = 'n_source_school_rows';

    protected $fillable = [
        'import_batch_id', 'source_row_number', 'admission_year', 'raw_mikuni_code',
        'raw_n_code', 'raw_school_name', 'raw_exam_label', 'parsed_json',
        'resolution_status', 'resolution_notes',
    ];

    protected function casts(): array
    {
        return ['parsed_json' => 'array'];
    }
}
