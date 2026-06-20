<?php

namespace App\Models\NSystem;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NPublicationEdition extends Model
{
    protected $table = 'n_publication_editions';

    protected $fillable = ['admission_year', 'title', 'source_filename'];

    public function publicationEntries(): HasMany
    {
        return $this->hasMany(NPublicationEntry::class, 'publication_edition_id');
    }
}
