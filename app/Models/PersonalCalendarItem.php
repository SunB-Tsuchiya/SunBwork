<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PersonalCalendarItem extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    protected $guarded = ['id'];
    protected $hidden = ['request_key','request_hash'];
    protected function casts(): array { return ['start_date'=>'date:Y-m-d','end_date'=>'date:Y-m-d','all_day'=>'boolean','completed_at'=>'datetime','version'=>'integer']; }
}
