<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'jobcode',
        'name',
        'user_id',
        'detail',
        'teammember',
        'schedule',
    ];

    protected $casts = [
        'detail' => 'array',
        'teammember' => 'array',
        'schedule' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
