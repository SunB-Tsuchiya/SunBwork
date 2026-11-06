<?php

namespace App\Models\Portfolio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** タグ（個人ごと） */
class PortfolioTag extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'name'];

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(PortfolioItem::class, 'portfolio_item_tag', 'tag_id', 'item_id');
    }
}
