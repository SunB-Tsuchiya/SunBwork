<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DepartmentWorkDisplaySetting extends Model
{
    protected $fillable = ['user_id','company_id','department_id','show_schedule','show_records','changed_by'];
    protected $casts = ['show_schedule'=>'boolean','show_records'=>'boolean'];
}
