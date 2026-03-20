<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventItemType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'coefficient', 'sort_order'];
}
