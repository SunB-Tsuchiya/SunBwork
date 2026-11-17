<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PersonalCalendarItem extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    public function scopeForOwner(\Illuminate\Database\Eloquent\Builder $query,User $user):\Illuminate\Database\Eloquent\Builder {return $query->where('user_id',$user->id)->where('company_id',$user->company_id);}
    protected $guarded = ['id'];
    protected $hidden = ['request_key','request_hash'];
    protected function casts(): array { return ['start_date'=>'date:Y-m-d','end_date'=>'date:Y-m-d','all_day'=>'boolean','completed_at'=>'datetime','version'=>'integer','is_important'=>'boolean','focus_date'=>'date:Y-m-d','steps'=>'array','repeat_rule'=>'array','repeat_next_version'=>'integer','repeat_retracted_at'=>'datetime']; }
}
