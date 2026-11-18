<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class PersonalAbsencePlan extends Model {
    use SoftDeletes;
    protected $guarded=['id'];
    protected $hidden=['request_key','request_hash'];
    protected function casts():array {return ['date'=>'date:Y-m-d','version'=>'integer'];}
    public function scopeForOwner($q,User $user){return $q->where('company_id',$user->company_id)->where('user_id',$user->id);}
}
