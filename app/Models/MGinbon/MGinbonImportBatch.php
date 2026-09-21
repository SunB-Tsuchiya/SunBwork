<?php

namespace App\Models\MGinbon;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MGinbonImportBatch extends MGinbonModel
{
    protected $table = 'mginbon_import_batches';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'summary_json' => 'array',
            'previewed_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(MGinbonProject::class, 'mginbon_project_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(MGinbonImportRow::class, 'mginbon_import_batch_id');
    }
}
