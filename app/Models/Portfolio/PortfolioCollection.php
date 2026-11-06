<?php

namespace App\Models\Portfolio;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** コレクション（フォルダ）。削除しても作品は消さず「未分類」に戻る（FK nullOnDelete） */
class PortfolioCollection extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'cover_item_id', 'sort_order'];

    public function items(): HasMany
    {
        return $this->hasMany(PortfolioItem::class, 'collection_id');
    }
}
