<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoPageEmail extends Model
{
    protected $fillable = ['demo_page_id', 'email', 'label'];

    public function demoPage(): BelongsTo
    {
        return $this->belongsTo(DemoPage::class);
    }
}
