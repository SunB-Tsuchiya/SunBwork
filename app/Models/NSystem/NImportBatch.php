<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;

class NImportBatch extends Model
{
    protected $table = 'n_import_batches';

    protected $fillable = ['import_type', 'source_filename', 'source_year', 'file_hash', 'imported_at', 'status', 'summary_json'];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime', 'summary_json' => 'array'];
    }
}
