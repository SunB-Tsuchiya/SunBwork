<?php

namespace App\Models\Portfolio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 作品（画像・PDF・動画）。ファイルは storage の portfolio/{user_id}/ 配下に置く */
class PortfolioItem extends Model
{
    protected $fillable = [
        'user_id', 'collection_id', 'type', 'title', 'description', 'original_name', 'mime', 'size_bytes',
        'file_path', 'thumb_path', 'poster_path', 'width', 'height', 'duration_sec', 'page_count', 'status', 'sort_order',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(PortfolioCollection::class, 'collection_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PortfolioTag::class, 'portfolio_item_tag', 'item_id', 'tag_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(PortfolioItemPage::class, 'item_id')->orderBy('page_no');
    }
}
