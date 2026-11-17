<?php
namespace App\Services;

use App\Models\PersonalCalendarItem;
use App\Models\PersonalTodoList;
use App\Models\PersonalTodoSetting;
use App\Models\User;
use App\Support\CalendarColors;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonalTodoWorkspace
{
    public const DEFAULTS = ['initial_view'=>'all','default_sort'=>'due','sidebar_view'=>'upcoming','sidebar_limit'=>10,'show_completed'=>false];
    public const VIEWS = ['all','today','important','overdue','upcoming','undated','completed','inbox','list'];
    public function __construct(private PersonalMonthCalendar $calendar) {}
    private function authorize(User $user,?int $context):void {$this->calendar->authorize($user,$context);}
    private function tasks(User $user) {return PersonalCalendarItem::forOwner($user)->whereIn('item_type',['todo','deadline']);}
    private function item(User $user,int $id):PersonalCalendarItem {return $this->tasks($user)->whereKey($id)->firstOrFail();}
    public function settings(User $user,?int $context):array {
        $this->authorize($user,$context);
        $row=PersonalTodoSetting::forOwner($user)->first();
        return $row ? $row->only([...array_keys(self::DEFAULTS),'version']) : self::DEFAULTS+['version'=>0];
    }
    public function lists(User $user,?int $context):array {
        $this->authorize($user,$context);
        return PersonalTodoList::forOwner($user)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn($list)=>['id'=>$list->id,'name'=>$list->name,'color_key'=>$list->color_key,'sort_order'=>$list->sort_order,'archived'=>(bool)$list->archived_at,'version'=>$list->version])->all();
    }
    private function filter($query,string $view):void {
        $today=Carbon::now('Asia/Tokyo')->toDateString();
        if($view==='today')$query->whereDate('focus_date',$today);
        if($view==='important')$query->where('is_important',true);
        if($view==='undated')$query->whereNull('start_date');
        if($view==='inbox')$query->whereNull('todo_list_id');
        if($view==='upcoming')$query->whereBetween('start_date',[$today,Carbon::parse($today,'Asia/Tokyo')->addDays(6)->toDateString()]);
        if($view==='overdue')$query->where(function($q)use($today){
            $q->where('start_date','<',$today)->orWhere(fn($q)=>$q->where('start_date',$today)->where('all_day',false)->where('start_time','<',Carbon::now('Asia/Tokyo')->format('H:i:s')));
        });
    }
    private function sorted($q,string $sort) {
        if($sort==='important')$q->orderByDesc('is_important');
        if($sort==='created')return $q->orderByDesc('id');
        return $q->orderByRaw('start_date IS NULL')->orderBy('start_date')->orderByRaw("COALESCE(start_time, '23:59:59')")->orderBy('id');
    }
    public function workspace(User $user,?int $context,array $input):array {
        $this->authorize($user,$context);$settings=$this->settings($user,$context);
        $d=Validator::make($input,['view'=>['nullable','string','max:30'],'q'=>['nullable','string','max:100'],'sort'=>['nullable','in:due,important,created'],'list_id'=>['nullable','integer','min:1'],'page'=>['sometimes','integer','min:1','max:10000'],'show_completed'=>['sometimes','boolean']])->validate();
        $view=$d['view']??$settings['initial_view'];if(!in_array($view,self::VIEWS,true))$view='all';
        $q=$this->tasks($user);
        if($view==='completed')$q->whereNotNull('completed_at');elseif(!($d['show_completed']??$settings['show_completed']))$q->whereNull('completed_at');
        if($view==='list'){
            $list=PersonalTodoList::forOwner($user)->whereKey($d['list_id']??0)->firstOrFail();$q->where('todo_list_id',$list->id);
        }
        $this->filter($q,$view);
        $term=trim($d['q']??'');
        if($term!==''){$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$term).'%';$q->where(fn($q)=>$q->whereRaw("title LIKE ? ESCAPE '!'",[$like])->orWhereRaw("note LIKE ? ESCAPE '!'",[$like]));}
        $sort=$d['sort']??$settings['default_sort'];$rows=$this->sorted($q,$sort)->paginate(30,['*'],'page',$d['page']??1);
        $counts=[];foreach(['all','today','important','overdue','upcoming','undated','inbox','completed'] as $key){$base=$this->tasks($user);$key==='completed'?$base->whereNotNull('completed_at'):$base->whereNull('completed_at');$this->filter($base,$key);$counts[$key]=$base->count();}
        return ['items'=>array_map(fn($i)=>$this->calendar->row($i),$rows->items()),'total'=>$rows->total(),'page'=>$rows->currentPage(),'last_page'=>$rows->lastPage(),
            'view'=>$view,'q'=>$term,'sort'=>$sort,'list_id'=>$d['list_id']??null,'show_completed'=>(bool)($d['show_completed']??$settings['show_completed']),
            'counts'=>$counts,'lists'=>$this->lists($user,$context),'settings'=>$settings,'today'=>Carbon::now('Asia/Tokyo')->toDateString()];
    }
    public function detail(User $user,?int $context,int $id):array {$this->authorize($user,$context);return $this->calendar->row($this->item($user,$id));}
    public function meta(User $user,?int $context,int $id,array $input):array {
        $this->authorize($user,$context);$row=$this->calendar->row($this->item($user,$id));
        $d=Validator::make($input,['version'=>['required','integer','min:1'],'today'=>['sometimes','boolean'],'is_important'=>['sometimes','boolean'],'todo_list_id'=>['sometimes','nullable','integer','min:1'],'steps'=>['sometimes','array','list','max:50']])->validate();
        abort_unless(count($d)>1,422,'変更する項目を指定してください。');
        if(array_key_exists('today',$d)){$d['focus_date']=$d['today']?Carbon::now('Asia/Tokyo')->toDateString():null;unset($d['today']);}
        return $this->calendar->mutate($user,$context,$id,array_replace($row,$d),'update');
    }
    public function sidebar(User $user,?int $context,bool $showCompleted=false):array {
        $settings=$this->settings($user,$context);$q=$this->tasks($user)->when(!$showCompleted,fn($q)=>$q->whereNull('completed_at'));
        if($settings['sidebar_view']==='today')$this->filter($q,'today');
        if($settings['sidebar_view']==='upcoming')$q->where(fn($q)=>$q->whereDate('focus_date',Carbon::now('Asia/Tokyo')->toDateString())->orWhere('start_date','<=',Carbon::now('Asia/Tokyo')->addDays(6)->toDateString()));
        $total=(clone $q)->count();$rows=$this->sorted($q,$settings['default_sort'])->limit($settings['sidebar_limit'])->get();
        return ['todos'=>$rows->map(fn($i)=>$this->calendar->row($i))->all(),'todo_total'=>$total,'todo_page'=>1,'todo_last_page'=>1,'settings'=>$settings];
    }
    public function saveSettings(User $user,?int $context,array $input):array {
        $this->authorize($user,$context);
        $d=Validator::make($input,['version'=>['required','integer','min:0'],'initial_view'=>['required','in:all,today,important,upcoming,undated'],'default_sort'=>['required','in:due,important,created'],'sidebar_view'=>['required','in:upcoming,today,all'],'sidebar_limit'=>['required',Rule::in([5,10,20,30])],'show_completed'=>['required','boolean']])->validate();
        return DB::transaction(function()use($user,$context,$d){
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();$row=PersonalTodoSetting::forOwner($user)->lockForUpdate()->first();
            abort_unless(($row?->version??0)===(int)$d['version'],409,'表示設定が更新されています。再読み込みしてください。');
            $values=array_diff_key($d,['version'=>true]);$row??=new PersonalTodoSetting(['user_id'=>$user->id,'company_id'=>$user->company_id,'version'=>0]);$row->fill($values);$row->version++;$row->save();return $this->settings($user,$context);
        },3);
    }
    public function saveList(User $user,?int $context,array $input,?int $id=null):array {
        $this->authorize($user,$context);
        $d=Validator::make($input,['name'=>['required','string','max:80'],'color_key'=>['required',Rule::in(CalendarColors::KEYS)],'version'=>[$id?'required':'nullable','integer','min:1'],'archived'=>['sometimes','boolean'],'request_key'=>[$id?'nullable':'required','uuid']])->validate();
        $d['name']=trim(preg_replace('/[\s\x{3000}]+/u',' ',$d['name']));if($d['name']==='')throw ValidationException::withMessages(['name'=>'リスト名を入力してください。']);
        return DB::transaction(function()use($user,$context,$d,$id){
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();$hash=hash('sha256',json_encode([$d['name'],$d['color_key']],JSON_THROW_ON_ERROR));
            if(!$id && ($old=PersonalTodoList::forOwner($user)->where('request_key',$d['request_key'])->first())){abort_unless(hash_equals($old->request_hash,$hash),409,'同じ保存キーの内容が変わっています。');return ['lists'=>$this->lists($user,$context),'id'=>$old->id];}
            $row=$id?PersonalTodoList::forOwner($user)->whereKey($id)->lockForUpdate()->firstOrFail():new PersonalTodoList;
            if($id)abort_unless($row->version===(int)$d['version'],409,'リストが更新されています。再読み込みしてください。');
            $archived=$d['archived']??(bool)$row->archived_at;
            if(!$archived && PersonalTodoList::forOwner($user)->whereNull('archived_at')->where('name',$d['name'])->when($id,fn($q)=>$q->whereKeyNot($id))->exists())throw ValidationException::withMessages(['name'=>'同名の有効なリストがあります。']);
            if(!$id){abort_if(PersonalTodoList::forOwner($user)->count()>=100,422,'リストは100件までです。');$row->fill(['user_id'=>$user->id,'company_id'=>$user->company_id,'sort_order'=>((int)PersonalTodoList::forOwner($user)->max('sort_order'))+10,'version'=>0,'request_key'=>$d['request_key'],'request_hash'=>$hash]);}
            $row->name=$d['name'];$row->color_key=$d['color_key'];$row->archived_at=$archived?($row->archived_at??Carbon::now('Asia/Tokyo')):null;$row->version++;$row->save();return ['lists'=>$this->lists($user,$context),'id'=>$row->id];
        },3);
    }
    public function reorder(User $user,?int $context,array $input):array {
        $this->authorize($user,$context);$d=Validator::make($input,['items'=>['required','array','list','min:1','max:100'],'items.*.id'=>['required','integer','distinct'],'items.*.version'=>['required','integer','min:1']])->validate();
        return DB::transaction(function()use($user,$context,$d){
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();$rows=PersonalTodoList::forOwner($user)->whereNull('archived_at')->lockForUpdate()->get()->keyBy('id');
            $ids=array_column($d['items'],'id');$expected=$rows->keys()->all();sort($ids);sort($expected);abort_unless($ids===$expected,409,'リスト構成が変わっています。再読み込みしてください。');
            foreach($d['items'] as $item)abort_unless($rows[$item['id']]->version===(int)$item['version'],409,'リストが更新されています。再読み込みしてください。');
            foreach($d['items'] as $i=>$item){$row=$rows[$item['id']];if($row->sort_order!==($i+1)*10){$row->sort_order=($i+1)*10;$row->version++;$row->save();}}
            return $this->lists($user,$context);
        },3);
    }
}
