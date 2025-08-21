<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','model','max_tokens','model_options','default_instructions','system_prompt'];

    protected $casts = [
        'model_options' => 'array'
    ];
}
