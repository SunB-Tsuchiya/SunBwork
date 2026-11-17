<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PersonalTodoList extends Model {
    protected $guarded=['id'];
    protected $hidden=['request_key','request_hash'];
    protected function casts():array {return ['archived_at'=>'datetime','version'=>'integer','sort_order'=>'integer'];}
    public function scopeForOwner(\Illuminate\Database\Eloquent\Builder $query,User $user):\Illuminate\Database\Eloquent\Builder {return $query->where('user_id',$user->id)->where('company_id',$user->company_id);}
}
