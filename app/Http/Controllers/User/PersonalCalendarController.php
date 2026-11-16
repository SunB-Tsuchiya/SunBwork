<?php
namespace App\Http\Controllers\User;
use App\Http\Controllers\Controller;
use App\Services\PersonalMonthCalendar;
use Illuminate\Http\Request;
class PersonalCalendarController extends Controller {
    public function __construct(private PersonalMonthCalendar $calendar) {}
    private function context(Request $r): ?int { $id=$r->session()->get('superadmin_context.company_id');return $id===null?null:(int)$id; }
    public function index(Request $r){return response()->json($this->calendar->index($r->user(),$this->context($r),$r->all()));}
    public function store(Request $r){$result=$this->calendar->create($r->user(),$this->context($r),$r->all());return response()->json($result,$result['replayed']?200:201);}
    public function update(Request $r,int $item){return response()->json(['item'=>$this->calendar->mutate($r->user(),$this->context($r),$item,$r->all(),'update')]);}
    public function complete(Request $r,int $item){return response()->json(['item'=>$this->calendar->mutate($r->user(),$this->context($r),$item,$r->all(),'complete')]);}
    public function destroy(Request $r,int $item){$this->calendar->mutate($r->user(),$this->context($r),$item,$r->all(),'delete');return response()->noContent();}
}
