<?php
namespace App\Http\Controllers\User;
use App\Http\Controllers\Controller;
use App\Services\PersonalTodoWorkspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
class PersonalTodoController extends Controller {
    public function __construct(private PersonalTodoWorkspace $workspace) {}
    private function context(Request $r):?int {$id=$r->session()->get('superadmin_context.company_id');return $id===null?null:(int)$id;}
    private function page(Request $r,string $tab) {
        $back=$r->validate(['date'=>['nullable','date_format:Y-m-d'],'calendar_view'=>['nullable','in:month,week,day'],'item'=>['nullable','integer','min:1']]);
        $data=$this->workspace->workspace($r->user(),$this->context($r),$r->all());
        return Inertia::render('PersonalTodo/Index',['workspace'=>$data,'tab'=>$tab,'back'=>['date'=>$back['date']??null,'view'=>$back['calendar_view']??'month'],
            'selected'=>isset($back['item'])?$this->workspace->detail($r->user(),$this->context($r),$back['item']):null]);
    }
    public function index(Request $r){return $this->page($r,'todo');}
    public function lists(Request $r){return $this->page($r,'lists');}
    public function settings(Request $r){return $this->page($r,'settings');}
    public function data(Request $r){return response()->json($this->workspace->workspace($r->user(),$this->context($r),$r->all()));}
    public function show(Request $r,int $item){return response()->json(['item'=>$this->workspace->detail($r->user(),$this->context($r),$item)]);}
    public function meta(Request $r,int $item){return response()->json(['item'=>$this->workspace->meta($r->user(),$this->context($r),$item,$r->all())]);}
    public function storeList(Request $r){return response()->json($this->workspace->saveList($r->user(),$this->context($r),$r->all()),201);}
    public function updateList(Request $r,int $list){return response()->json($this->workspace->saveList($r->user(),$this->context($r),$r->all(),$list));}
    public function reorder(Request $r){return response()->json(['lists'=>$this->workspace->reorder($r->user(),$this->context($r),$r->all())]);}
    public function updateSettings(Request $r){return response()->json(['settings'=>$this->workspace->saveSettings($r->user(),$this->context($r),$r->all())]);}
}
