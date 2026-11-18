<?php
namespace App\Http\Controllers\User;
use App\Http\Controllers\Controller;
use App\Services\PersonalAbsencePlans;
use Illuminate\Http\Request;
class PersonalAbsencePlanController extends Controller {
    public function __construct(private PersonalAbsencePlans $plans){}
    private function context(Request $r):?int {$id=$r->session()->get('superadmin_context.company_id');return $id===null?null:(int)$id;}
    public function index(Request $r){return response()->json($this->plans->range($r->user(),$this->context($r),$r->all()));}
    public function defaults(Request $r){return response()->json($this->plans->defaults($r->user(),$this->context($r),$r->all()));}
    public function store(Request $r){$result=$this->plans->save($r->user(),$this->context($r),$r->all());return response()->json($result,$result['replayed']?200:201);}
    public function update(Request $r,int $plan){return response()->json($this->plans->save($r->user(),$this->context($r),$r->all(),$plan));}
    public function destroy(Request $r,int $plan){$this->plans->remove($r->user(),$this->context($r),$plan,$r->all());return response()->noContent();}
}
