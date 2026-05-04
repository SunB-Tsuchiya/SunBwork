<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoordinatorProgressSheetFavorite extends Model
{
    protected $fillable = ['user_id', 'progress_sheet_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function progressSheet()
    {
        return $this->belongsTo(ProgressSheet::class);
    }
}
