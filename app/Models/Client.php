<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'detail',
        'fromSB',
    ];

    protected $casts = [
        'fromSB' => 'boolean',
    ];

    public function projectJobs()
    {
        return $this->hasMany(ProjectJob::class);
    }
}
