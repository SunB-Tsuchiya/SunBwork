<?php

namespace App\Models\MGinbon;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MGinbonImportRow extends MGinbonModel
{
    protected $table = 'mginbon_import_rows';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_json' => 'array',
            'normalized_json' => 'array',
            'warning_codes' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MGinbonImportBatch::class, 'mginbon_import_batch_id');
    }
}
