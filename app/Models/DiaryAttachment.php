<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiaryAttachment extends Model
{
    protected $fillable = [
        'diary_id',
        'path',
        'original_name',
        'mime_type'
    ];
}
