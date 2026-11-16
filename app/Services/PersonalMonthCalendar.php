<?php
namespace App\Services;
use App\Models\PersonalCalendarItem;
use App\Models\User;
use App\Support\CalendarColors;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PersonalMonthCalendar
{
    public function available(?User $user, ?int $context = null): bool {
        return $user && $user->isSuperAdmin() && app(PersonalWorkSchedule::class)->canViewDepartmentWork($user,$context);
    }
    public function authorize(User $user, ?int $context): void { abort_unless($this->available($user,$context),403); }
    private function owned(User $user): Builder { return PersonalCalendarItem::where('user_id',$user->id)->where('company_id',$user->company_id); }
    private function validate(array $input): array {
        $d=Validator::make($input,[
            'item_type'=>['required','in:event,deadline,todo'],'title'=>['required','string','max:255'],
            'note'=>['nullable','string','max:2000'],'start_date'=>['nullable','date_format:Y-m-d','required_unless:item_type,todo'],
            'end_date'=>['nullable','date_format:Y-m-d','after_or_equal:start_date'],
            'start_time'=>['nullable','date_format:H:i'],'end_time'=>['nullable','date_format:H:i'],
            'all_day'=>['required','boolean'],'color_key'=>['required',Rule::in(CalendarColors::KEYS)],
        ])->validate();
        $d=array_replace(['note'=>null,'start_date'=>null,'end_date'=>null,'start_time'=>null,'end_time'=>null],$d);
        $d['title']=trim(preg_replace('/[\s\x{3000}]+/u',' ',$d['title'])); if($d['title']==='') throw ValidationException::withMessages(['title'=>'名前を入力してください。']);
        if($d['item_type']!=='event' && ($d['end_date'] || $d['end_time'])) throw ValidationException::withMessages(['end_date'=>'TODO・締め切りには終了日を指定できません。']);
        if(!$d['start_date'] && ($d['start_time'] || !$d['all_day'])) throw ValidationException::withMessages(['start_date'=>'時刻を指定する場合は日付も指定してください。']);
        if($d['all_day'] && ($d['start_time'] || $d['end_time'])) throw ValidationException::withMessages(['start_time'=>'終日の場合は時刻を空にしてください。']);
        if(!$d['all_day'] && !$d['start_time']) throw ValidationException::withMessages(['start_time'=>'時刻を入力してください。']);
        if($d['end_date'] && Carbon::parse($d['start_date'])->diffInDays(Carbon::parse($d['end_date']))>365) throw ValidationException::withMessages(['end_date'=>'期間は366日以内で指定してください。']);
        if($d['item_type']==='event' && !$d['all_day']) {
            if(!$d['end_time']) throw ValidationException::withMessages(['end_time'=>'終了時刻を入力してください。']);
            if(($d['end_date'] ?: $d['start_date']).' '.$d['end_time'] <= $d['start_date'].' '.$d['start_time']) throw ValidationException::withMessages(['end_time'=>'終了は開始より後にしてください。']);
        }
        $d['all_day']=(bool)$d['all_day']; ksort($d); return $d;
    }
    public function row(PersonalCalendarItem $item): array {
        $row=$item->only(['id','item_type','title','note','all_day','color_key','version']);
        foreach(['start_date','end_date'] as $key) $row[$key]=$item->$key?->format('Y-m-d');
        foreach(['start_time','end_time'] as $key) $row[$key]=$item->$key ? substr($item->$key,0,5) : null;
        $row['completed']=(bool)$item->completed_at;
        $deadline=$row['start_date'] ? Carbon::parse($row['start_date'].' '.($row['start_time'] ?: '23:59:59'),'Asia/Tokyo') : null;
        $row['overdue']=!$row['completed'] && $item->item_type!=='event' && $deadline && $deadline->lt(Carbon::now('Asia/Tokyo'));
        return $row;
    }
    public function index(User $user,?int $context,array $input): array {
        $this->authorize($user,$context);
        $d=Validator::make($input,['start'=>['required','date_format:Y-m-d'],'end'=>['required','date_format:Y-m-d','after_or_equal:start'],'todo_page'=>['sometimes','integer','min:1','max:10000'],'show_completed'=>['sometimes','boolean']])->validate();
        abort_if(Carbon::parse($d['start'])->diffInDays(Carbon::parse($d['end']))>62,422,'表示範囲は63日以内にしてください。');
        $query=fn()=> $this->owned($user)->when(!($d['show_completed']??false),fn($q)=>$q->whereNull('completed_at'));
        $month=$query()->whereNotNull('start_date')->where('start_date','<=',$d['end'])->where(fn($q)=>$q->where('end_date','>=',$d['start'])->orWhere(fn($q)=>$q->whereNull('end_date')->where('start_date','>=',$d['start'])))->orderBy('start_date')->orderBy('id')->limit(501)->get();
        $todos=$query()->whereIn('item_type',['todo','deadline'])->orderByRaw('start_date IS NULL')->orderBy('start_date')->orderByRaw("COALESCE(start_time, '23:59:59')")->orderBy('id')->paginate(30,['*'],'todo_page',$d['todo_page']??1);
        return ['items'=>$month->take(500)->map(fn($i)=>$this->row($i))->values()->all(),'truncated'=>$month->count()>500,
            'todos'=>array_map(fn($i)=>$this->row($i),$todos->items()),'todo_total'=>$todos->total(),'todo_page'=>$todos->currentPage(),'todo_last_page'=>$todos->lastPage()];
    }
    public function create(User $user,?int $context,array $input): array {
        $this->authorize($user,$context);$d=$this->validate($input);
        $key=Validator::make($input,['request_key'=>['required','uuid']])->validate()['request_key'];
        $hash=hash('sha256',json_encode($d,JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($user,$d,$key,$hash){
            // Serialize only this owner's creates, including retries after lost responses.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $item=$this->owned($user)->withTrashed()->where('request_key',$key)->first();
            if($item){abort_if($item->trashed(),410,'この予定は削除済みです。再送しても作成しません。');abort_unless(hash_equals($item->request_hash,$hash),409,'同じ保存キーで内容が変わっています。再送前の内容を確認してください。');return ['item'=>$this->row($item),'replayed'=>true];}
            $item=PersonalCalendarItem::create($d+['company_id'=>$user->company_id,'user_id'=>$user->id,'request_key'=>$key,'request_hash'=>$hash]);
            return ['item'=>$this->row($item->fresh()),'replayed'=>false];
        },3);
    }
    public function mutate(User $user,?int $context,int $id,array $input,string $operation): ?array {
        $this->authorize($user,$context);
        $v=Validator::make($input,['version'=>['required','integer','min:1']])->validate()['version'];
        $data=$operation==='update'?$this->validate($input):[];
        $completed=$operation==='complete'?Validator::make($input,['completed'=>['required','boolean']])->validate()['completed']:null;
        return DB::transaction(function()use($user,$id,$v,$operation,$data,$completed){
            $item=$this->owned($user)->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($item->version===(int)$v,409,'ほかの画面で更新されています。入力を控えて再読み込みしてください。');
            if($operation==='delete'){$item->delete();return null;}
            if($operation==='complete')$item->completed_at=$completed?Carbon::now('Asia/Tokyo'):null;
            else $item->fill($data);
            $item->version++;$item->save();return $this->row($item);
        },3);
    }
}
