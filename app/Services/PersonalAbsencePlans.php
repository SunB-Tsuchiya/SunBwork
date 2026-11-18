<?php
namespace App\Services;
use App\Models\{PersonalAbsencePlan,User,IrukaStatusOrder,UserMonthlySchedule,UserMonthlyBreak,UserSetting,Worktype};
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB,Schema,Validator};
use Illuminate\Validation\{Rule,ValidationException};
class PersonalAbsencePlans
{
    public const KINDS=['paid_leave'=>'有休','half_am'=>'午前休','half_pm'=>'午後休','late'=>'遅刻','early_leave'=>'早退'];
    public function available(?User $user,?int $context=null):bool {return app(PersonalMonthCalendar::class)->available($user,$context);}
    private function authorize(User $u,?int $context):void {abort_unless($this->available($u,$context),403);}
    private function invalid(string $field,string $text):never {throw ValidationException::withMessages([$field=>$text]);}
    public function kinds(int $company):array {
        $orders=IrukaStatusOrder::where('company_id',$company)->get()->keyBy('slug');
        return collect(self::KINDS)->map(fn($label,$slug)=>['slug'=>$slug,'label'=>$orders->get($slug)?->custom_label?:$label,'enabled'=>$orders->isEmpty()||($orders->get($slug)?->is_active??false)])->values()->all();
    }
    public function defaults(User $u,?int $context,array $input):array {
        $this->authorize($u,$context);$date=Validator::make($input,['date'=>['required','date_format:Y-m-d']])->validate()['date'];
        $id=UserMonthlySchedule::worktypeIdForDate($u->id,$date);$setting=UserSetting::where('user_id',$u->id)->first();
        $work=Worktype::where('company_id',$u->company_id)->find($id?:$setting?->worktype_id);
        if(!$id&&!$setting?->worktype_id)$work=Worktype::where('company_id',$u->company_id)->orderBy('sort_order')->first();
        $start=$work?->start_time?substr($work->start_time,0,5):null;$end=$work?->end_time?substr($work->end_time,0,5):null;
        if(!$start||!$end||$start>=$end){$start=$end=null;}
        $break=UserMonthlyBreak::breakForDate($u->id,$date)??['start'=>$setting?->lunch_start?:'12:00','end'=>$setting?->lunch_end?:'13:00'];
        return ['date'=>$date,'start_time'=>$start,'end_time'=>$end,'break_start'=>substr($break['start'],0,5),'break_end'=>substr($break['end'],0,5),'kinds'=>$this->kinds((int)$u->company_id)];
    }
    public function row(PersonalAbsencePlan $p):array {
        return ['id'=>$p->id,'date'=>$p->date->format('Y-m-d'),'kind'=>$p->kind,'label'=>self::KINDS[$p->kind],
            'start_time'=>substr($p->starts_at,11,5),'end_time'=>substr($p->ends_at,11,5),'starts_at'=>$p->starts_at,'ends_at'=>$p->ends_at,'version'=>$p->version];
    }
    public function range(User $u,?int $context,array $input):array {
        $this->authorize($u,$context);$d=Validator::make($input,['start'=>['required','date_format:Y-m-d'],'end'=>['required','date_format:Y-m-d','after_or_equal:start']])->validate();
        abort_if(Carbon::parse($d['start'])->diffInDays(Carbon::parse($d['end']))>62,422,'表示範囲は63日以内にしてください。');
        $rows=PersonalAbsencePlan::forOwner($u)->whereBetween('date',[$d['start'],$d['end']])->orderBy('starts_at')->orderBy('id')->get();
        return ['items'=>$rows->map(fn($p)=>$this->row($p))->all(),'events'=>$rows->map(fn($p)=>[
            'id'=>'absence-plan-'.$p->id,'title'=>self::KINDS[$p->kind].'（予定）','starts_at'=>str_replace(' ','T',$p->starts_at),
            'ends_at'=>str_replace(' ','T',$p->ends_at),'all_day'=>$p->kind==='paid_leave','is_own'=>true,'_source'=>'absence-plan',
            '_custom_color'=>'#be185d','_absence_plan'=>$this->row($p),'editable'=>false,
        ])->all()];
    }
    private function data(array $input):array {
        $d=Validator::make($input,['date'=>['required','date_format:Y-m-d','after_or_equal:1000-01-01','before_or_equal:9999-12-30'],'kind'=>['required',Rule::in(array_keys(self::KINDS))],
            'start_time'=>['nullable','date_format:H:i','required_unless:kind,paid_leave'],'end_time'=>['nullable','date_format:H:i','required_unless:kind,paid_leave']])->validate();
        $day=Carbon::parse($d['date'],'Asia/Tokyo');
        if($d['kind']==='paid_leave'){
            if(($d['start_time']??null)||($d['end_time']??null))$this->invalid('start_time','有休は終日です。時刻は空にしてください。');
            return ['date'=>$d['date'],'kind'=>$d['kind'],'starts_at'=>$day->format('Y-m-d 00:00:00'),'ends_at'=>$day->addDay()->format('Y-m-d 00:00:00')];
        }
        if($d['start_time']>=$d['end_time'])$this->invalid('end_time','不在の終了時刻は開始時刻より後にしてください（日をまたぐ設定はできません）。');
        return ['date'=>$d['date'],'kind'=>$d['kind'],'starts_at'=>$d['date'].' '.$d['start_time'].':00','ends_at'=>$d['date'].' '.$d['end_time'].':00'];
    }
    public function save(User $u,?int $context,array $input,?int $id=null):array {
        $this->authorize($u,$context);$d=$this->data($input);
        $meta=Validator::make($input,['request_key'=>[$id?'nullable':'required','uuid'],'version'=>[$id?'required':'nullable','integer','min:1']])->validate();
        $hash=hash('sha256',json_encode($d,JSON_THROW_ON_ERROR));
        return DB::transaction(function()use($u,$id,$meta,$d,$hash){
            User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            if(!$id&&($old=PersonalAbsencePlan::forOwner($u)->withTrashed()->where('request_key',$meta['request_key'])->first())){
                abort_if($old->trashed(),410,'この予定は削除済みです。再送しても復元しません。');abort_unless(hash_equals($old->request_hash,$hash),409,'同じ保存キーの内容が変わっています。登録済みか確認してください。');return ['item'=>$this->row($old),'replayed'=>true];
            }
            $p=$id?PersonalAbsencePlan::forOwner($u)->whereKey($id)->lockForUpdate()->firstOrFail():new PersonalAbsencePlan;
            if($id)abort_unless($p->version===(int)$meta['version'],409,'ほかの画面で更新されています。入力を控えて再読み込みしてください。');
            $enabled=collect($this->kinds((int)$u->company_id))->firstWhere('slug',$d['kind'])['enabled'];
            if(!$enabled&&(!$id||$p->kind!==$d['kind']))$this->invalid('kind','この区分は在席ボードで無効になっています。別の区分を選択してください。');
            $overlap=PersonalAbsencePlan::forOwner($u)->where('starts_at','<',$d['ends_at'])->where('ends_at','>',$d['starts_at'])->when($id,fn($q)=>$q->whereKeyNot($id))->exists();
            if($overlap)$this->invalid('date','同じ時間に休暇・遅刻・早退の予定があります。既存の予定を編集してください。');
            if(!$id)$p->fill(['company_id'=>$u->company_id,'user_id'=>$u->id,'request_key'=>$meta['request_key'],'request_hash'=>$hash,'version'=>0]);
            $p->fill($d);$p->version++;$p->save();return ['item'=>$this->row($p),'replayed'=>false];
        },3);
    }
    public function remove(User $u,?int $context,int $id,array $input):void {
        $this->authorize($u,$context);$v=Validator::make($input,['version'=>['required','integer','min:1']])->validate()['version'];
        DB::transaction(function()use($u,$id,$v){User::whereKey($u->id)->lockForUpdate()->firstOrFail();$p=PersonalAbsencePlan::forOwner($u)->whereKey($id)->lockForUpdate()->firstOrFail();abort_unless($p->version===(int)$v,409,'ほかの画面で更新されています。再読み込みしてください。');$p->delete();},3);
    }
    /** Read-only projection for every visible user in the requested company, not just the viewer. */
    public function presenceFor(int $company,Collection $users):array {
        if($users->isEmpty()||!Schema::hasTable('personal_absence_plans'))return [];
        $now=Carbon::now('Asia/Tokyo');$out=[];$byId=$users->where('company_id',$company)->keyBy('id');
        $plans=PersonalAbsencePlan::where('company_id',$company)->whereIn('user_id',$byId->keys())->where('starts_at','<=',$now->format('Y-m-d H:i:s'))->where('ends_at','>',$now->format('Y-m-d H:i:s'))->orderBy('starts_at')->get();
        foreach($plans as $p){
            $presence=$byId[$p->user_id]->presenceStatus;$start=Carbon::parse($p->starts_at,'Asia/Tokyo');$cutoff=$p->updated_at->gt($start)?$p->updated_at:$start;
            if($presence?->status_source==='manual'&&$presence->status_changed_at?->gt($cutoff))continue;
            $out[$p->user_id]=['status'=>$p->kind,'status_source'=>'absence_plan','scheduled_until'=>$p->ends_at];
        }
        return $out;
    }
}
