<?php

namespace App\Models\Portfolio;

use Illuminate\Database\Eloquent\Model;

/** PDF のページ画像 */
class PortfolioItemPage extends Model
{
    public $timestamps = false;

    protected $fillable = ['item_id', 'page_no', 'path', 'width', 'height'];
}
