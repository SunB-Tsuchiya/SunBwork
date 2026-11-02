<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadProjectFavorite extends Model
{
    protected $fillable = ['user_id', 'broad_project_id'];

    public function broadProject()
    {
        return $this->belongsTo(BroadProject::class);
    }
}
