<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClerkWeekPost extends Model
{
    protected $fillable = ['company_id', 'year', 'week', 'user_id', 'body', 'parent_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(ClerkWeekPost::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(ClerkWeekPost::class, 'parent_id');
    }
}
