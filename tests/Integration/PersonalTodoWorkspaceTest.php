<?php
namespace Tests\Integration;
use App\Models\PersonalCalendarItem;
use App\Models\PersonalTodoList;
use App\Models\User;
use App\Services\PersonalMonthCalendar;
use App\Services\PersonalTodoWorkspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
require_once __DIR__.'/UnifiedMyJobCreateTest.php';
class PersonalTodoWorkspaceTest extends UnifiedMyJobCreateTest
{
    protected function setUp():void {
        parent::setUp();Carbon::setTestNow(Carbon::parse('2026-11-17 10:00:00','Asia/Tokyo'));
        (require dirname(__DIR__,2).'/database/migrations/2026_11_16_180001_create_personal_calendar_items.php')->up();
        (require dirname(__DIR__,2).'/database/migrations/2026_11_17_160001_add_personal_todo_workspace.php')->up();
    }
    protected function tearDown():void {Carbon::setTestNow();parent::tearDown();}
    private function actor(array $extra=[]):User {return (new User)->forceFill($extra+['id'=>1,'company_id'=>1,'department_id'=>1,'user_role'=>'superadmin']);}
    private function svc():PersonalTodoWorkspace {return app(PersonalTodoWorkspace::class);}
    private function calendar():PersonalMonthCalendar {return app(PersonalMonthCalendar::class);}
    private function todoPayload(array $extra=[]):array {return $extra+['item_type'=>'todo','title'=>'旧入力のTODO','all_day'=>true,'color_key'=>'indigo','request_key'=>(string)Str::uuid()];}
    private function task(array $extra=[]):array {return $this->calendar()->create($this->actor(),1,$this->todoPayload($extra))['item'];}
    private function list(array $extra=[],?User $user=null):array {$r=$this->svc()->saveList($user??$this->actor(),1,$extra+['name'=>'個人リスト','color_key'=>'blue','request_key'=>(string)Str::uuid()]);return collect($r['lists'])->firstWhere('id',$r['id']);}
    public function test_workspace_keeps_old_create_hash_and_legacy_update_preserves_metadata():void {
        $payload=$this->todoPayload();$row=$this->calendar()->create($this->actor(),1,$payload)['item'];
        $old=['note'=>null,'start_date'=>null,'end_date'=>null,'start_time'=>null,'end_time'=>null,'item_type'=>'todo','title'=>'旧入力のTODO','all_day'=>true,'color_key'=>'indigo'];ksort($old);
        self::assertSame(hash('sha256',json_encode($old,JSON_THROW_ON_ERROR)),PersonalCalendarItem::find($row['id'])->request_hash);
        $list=$this->list();$step=['id'=>(string)Str::uuid(),'title'=>'チェック','completed'=>false];
        $updated=$this->svc()->meta($this->actor(),1,$row['id'],['version'=>1,'today'=>true,'is_important'=>true,'todo_list_id'=>$list['id'],'steps'=>[$step]]);
        self::assertTrue($updated['is_important']);self::assertTrue($updated['is_my_day']);self::assertSame('2026-11-17',$updated['focus_date']);self::assertNull($updated['start_date']);
        $saved=$this->calendar()->mutate($this->actor(),1,$row['id'],$this->todoPayload(['title'=>'旧画面から編集','version'=>2]),'update');
        self::assertSame([$step],$saved['steps']);self::assertTrue($saved['is_important']);self::assertSame($list['id'],$saved['todo_list_id']);self::assertSame('2026-11-17',$saved['focus_date']);
        self::assertTrue($this->calendar()->create($this->actor(),1,$payload)['replayed']);self::assertSame(1,PersonalCalendarItem::count());
    }
    public function test_workspace_today_deadline_and_parent_step_completion_are_independent():void {
        $row=$this->task(['start_date'=>'2026-11-20']);$step=['id'=>(string)Str::uuid(),'title'=>'提出','completed'=>true];
        $meta=$this->svc()->meta($this->actor(),1,$row['id'],['version'=>1,'today'=>true,'steps'=>[$step]]);
        self::assertSame('2026-11-20',$meta['start_date']);self::assertFalse($meta['completed']);self::assertSame(1,$meta['steps_done']);
        self::assertSame(1,$this->svc()->workspace($this->actor(),1,['view'=>'today'])['total']);
        Carbon::setTestNow(Carbon::parse('2026-11-18 00:01:00','Asia/Tokyo'));
        self::assertSame(0,$this->svc()->workspace($this->actor(),1,['view'=>'today'])['total']);self::assertSame(1,$this->svc()->workspace($this->actor(),1,['view'=>'all'])['total']);
        $done=$this->calendar()->mutate($this->actor(),1,$row['id'],['version'=>2,'completed'=>true],'complete');self::assertSame([$step],$done['steps']);
    }
    public function test_workspace_smart_search_and_sidebar_keep_all_tasks_reachable():void {
        $this->task(['title'=>'100% literal','start_date'=>'2026-11-16']);$this->task(['title'=>'100percent','start_date'=>'2026-11-19']);$this->task(['title'=>'期限未定']);$this->task(['title'=>'今日作業','focus_date'=>'2026-11-17']);
        self::assertSame(1,$this->svc()->workspace($this->actor(),1,['q'=>'%'])['total']);
        self::assertSame(1,$this->svc()->workspace($this->actor(),1,['view'=>'overdue'])['total']);self::assertSame(1,$this->svc()->workspace($this->actor(),1,['view'=>'upcoming'])['total']);self::assertSame(2,$this->svc()->workspace($this->actor(),1,['view'=>'undated'])['total']);
        self::assertSame('all',$this->svc()->workspace($this->actor(),1,['view'=>'bad-value'])['view']);
        $sidebar=$this->svc()->sidebar($this->actor(),1);self::assertCount(3,$sidebar['todos']);self::assertSame(4,$this->svc()->workspace($this->actor(),1,[])['total']);
        $range=app(PersonalMonthCalendar::class)->index($this->actor(),1,['start'=>'2026-11-01','end'=>'2026-11-30','sidebar'=>true]);self::assertSame(3,$range['todo_total']);self::assertArrayHasKey('settings',$range);
    }
    public function test_workspace_lists_archive_restore_and_versions_do_not_remove_tasks():void {
        $list=$this->list();$row=$this->task(['todo_list_id'=>$list['id']]);
        $r=$this->svc()->saveList($this->actor(),1,['name'=>$list['name'],'color_key'=>'blue','version'=>1,'archived'=>true],$list['id']);self::assertTrue($r['lists'][0]['archived']);
        self::assertSame(1,$this->svc()->workspace($this->actor(),1,['view'=>'all'])['total']);
        $saved=$this->calendar()->mutate($this->actor(),1,$row['id'],$this->todoPayload(['version'=>1,'title'=>'所属は保持']),'update');self::assertSame($list['id'],$saved['todo_list_id']);
        try{$this->task(['todo_list_id'=>$list['id']]);self::fail();}catch(ValidationException $e){self::assertArrayHasKey('todo_list_id',$e->errors());}
        try{$this->svc()->saveList($this->actor(),1,['name'=>'古い編集','color_key'=>'blue','version'=>1],$list['id']);self::fail();}catch(HttpException $e){self::assertSame(409,$e->getStatusCode());}
        $r=$this->svc()->saveList($this->actor(),1,['name'=>$list['name'],'color_key'=>'blue','version'=>2,'archived'=>false],$list['id']);self::assertFalse($r['lists'][0]['archived']);
        $updated=$this->svc()->meta($this->actor(),1,$row['id'],['version'=>2,'todo_list_id'=>null]);self::assertNull($updated['todo_list_id']);
    }
    public function test_workspace_list_retry_duplicate_and_full_reorder_are_atomic():void {
        $key=(string)Str::uuid();$one=$this->list(['name'=>'一','request_key'=>$key]);$replay=$this->svc()->saveList($this->actor(),1,['name'=>'一','color_key'=>'blue','request_key'=>$key]);self::assertSame($one['id'],$replay['id']);
        try{$this->list(['name'=>'一']);self::fail();}catch(ValidationException $e){self::assertArrayHasKey('name',$e->errors());}
        $two=$this->list(['name'=>'二']);$ordered=$this->svc()->reorder($this->actor(),1,['items'=>[['id'=>$two['id'],'version'=>1],['id'=>$one['id'],'version'=>1]]]);self::assertSame([$two['id'],$one['id']],array_column($ordered,'id'));
        $before=PersonalTodoList::orderBy('id')->get()->toArray();
        try{$this->svc()->reorder($this->actor(),1,['items'=>[['id'=>$one['id'],'version'=>1]]]);self::fail();}catch(HttpException $e){self::assertSame(409,$e->getStatusCode());}
        self::assertSame($before,PersonalTodoList::orderBy('id')->get()->toArray());
    }
    public function test_workspace_owner_context_and_trial_gate_cover_all_new_resources():void {
        $list=$this->list();$row=$this->task();$other=$this->actor(['id'=>2]);
        self::assertSame([],$this->svc()->lists($other,1));self::assertSame(0,$this->svc()->workspace($other,1,[])['total']);
        foreach(['detail','meta'] as $method){try{$args=[$other,1,$row['id']];if($method==='meta')$args[]=['version'=>1,'is_important'=>true];$this->svc()->$method(...$args);self::fail();}catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){self::assertNotNull($e);}}
        try{$this->calendar()->create($other,1,$this->todoPayload(['todo_list_id'=>$list['id']]));self::fail();}catch(ValidationException $e){self::assertArrayHasKey('todo_list_id',$e->errors());}
        foreach([$this->actor(['user_role'=>'user']),$this->actor(['department_id'=>2]),$this->actor(['company_id'=>2,'department_id'=>3])] as $denied){
            foreach(['workspace','lists','settings'] as $method){try{$this->svc()->$method($denied,1,[]);self::fail();}catch(HttpException $e){self::assertSame(403,$e->getStatusCode());}}
        }
        try{$this->svc()->settings($this->actor(),2);self::fail();}catch(HttpException $e){self::assertSame(403,$e->getStatusCode());}
    }
    public function test_workspace_settings_and_metadata_conflicts_preserve_the_latest_state():void {
        $settings=$this->svc()->settings($this->actor(),1);self::assertSame(0,$settings['version']);self::assertSame('upcoming',$settings['sidebar_view']);
        $settings=$this->svc()->saveSettings($this->actor(),1,array_replace($settings,['sidebar_view'=>'all','initial_view'=>'important']));self::assertSame(1,$settings['version']);
        try{$this->svc()->saveSettings($this->actor(),1,array_replace($settings,['version'=>0]));self::fail();}catch(HttpException $e){self::assertSame(409,$e->getStatusCode());}
        self::assertSame('important',$this->svc()->workspace($this->actor(),1,[])['view']);
        $row=$this->task();$this->svc()->meta($this->actor(),1,$row['id'],['version'=>1,'is_important'=>true]);
        try{$this->svc()->meta($this->actor(),1,$row['id'],['version'=>1,'today'=>true]);self::fail();}catch(HttpException $e){self::assertSame(409,$e->getStatusCode());}
        self::assertNull(PersonalCalendarItem::find($row['id'])->focus_date);
    }
    public function test_workspace_steps_and_event_conversion_do_not_silently_discard_metadata():void {
        $row=$this->task(['is_important'=>true]);
        try{$this->calendar()->mutate($this->actor(),1,$row['id'],$this->todoPayload(['version'=>1,'item_type'=>'event','start_date'=>'2026-11-20']),'update');self::fail();}catch(ValidationException $e){self::assertArrayHasKey('item_type',$e->errors());}
        foreach([[['id'=>'bad','title'=>'項目','completed'=>false]],[['id'=>(string)Str::uuid(),'title'=>'　 ','completed'=>false]],array_fill(0,51,['id'=>(string)Str::uuid(),'title'=>'項目','completed'=>false])] as $steps){try{$this->svc()->meta($this->actor(),1,$row['id'],['version'=>1,'steps'=>$steps]);self::fail();}catch(ValidationException $e){self::assertNotEmpty($e->errors());}}
        self::assertSame(1,PersonalCalendarItem::find($row['id'])->version);
    }
}
