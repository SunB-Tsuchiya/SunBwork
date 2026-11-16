<?php
namespace Tests\Integration;
use App\Models\User;
use App\Models\PersonalCalendarItem;
use App\Services\PersonalMonthCalendar;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
require_once __DIR__.'/UnifiedMyJobCreateTest.php';
class PersonalMonthCalendarTest extends UnifiedMyJobCreateTest
{
    protected function setUp():void {parent::setUp();(require dirname(__DIR__,2).'/database/migrations/2026_11_16_180001_create_personal_calendar_items.php')->up();Carbon::setTestNow(Carbon::parse('2026-11-16 10:00:00','Asia/Tokyo'));}
    protected function tearDown():void {Carbon::setTestNow();parent::tearDown();}
    private function actor(array $extra=[]):User {return (new User)->forceFill($extra+['id'=>1,'company_id'=>1,'department_id'=>1,'user_role'=>'superadmin']);}
    private function svc():PersonalMonthCalendar{return app(PersonalMonthCalendar::class);}
    private function itemData(array $extra=[]):array{return $extra+['item_type'=>'event','title'=>'本人予定','start_date'=>'2026-11-16','end_date'=>null,'start_time'=>null,'end_time'=>null,'all_day'=>true,'color_key'=>'blue','request_key'=>(string)Str::uuid()];}
    private function range(array $extra=[]):array {return $extra+['start'=>'2026-11-01','end'=>'2026-11-30'];}
    public function test_create_retries_do_not_duplicate_and_old_payload_cannot_overwrite():void {
        $data=$this->itemData(['user_id'=>2,'company_id'=>2]);$svc=$this->svc();$first=$svc->create($this->actor(),1,$data);
        self::assertFalse($first['replayed']);self::assertSame('2026-11-16',$first['item']['start_date']);self::assertTrue($first['item']['all_day']);
        self::assertTrue($svc->create($this->actor(),1,$data)['replayed']);self::assertSame(1,PersonalCalendarItem::count());self::assertSame(1,(int)PersonalCalendarItem::first()->user_id);self::assertSame(1,(int)PersonalCalendarItem::first()->company_id);
        try{$svc->create($this->actor(),1,array_replace($data,['title'=>'違う内容']));self::fail();}catch(HttpException $e){self::assertSame(409,$e->getStatusCode());}
        self::assertStringNotContainsString('request_',json_encode($first['item']));self::assertSame(0,DB::table('personal_work_entries')->count());self::assertSame(1,DB::table('events')->count());
    }
    public function test_private_cohort_scope_and_superadmin_pilot_apply_to_every_operation():void {
        $svc=$this->svc();$id=$svc->create($this->actor(),1,$this->itemData())['item']['id'];
        foreach([$this->actor(['user_role'=>'user']),$this->actor(['company_id'=>2,'department_id'=>3]),$this->actor(['department_id'=>2])] as $actor){
            foreach(['read','create','update','complete','delete'] as $op){try{
                if($op==='read')$svc->index($actor,null,$this->range());elseif($op==='create')$svc->create($actor,null,$this->itemData());else $svc->mutate($actor,null,$id,$this->itemData(['version'=>1,'completed'=>true]),$op);
                self::fail('scope');}catch(HttpException $e){self::assertSame(403,$e->getStatusCode());}}
        }
        self::assertSame([],$svc->index($this->actor(['id'=>2]),1,$this->range())['items']);
        foreach(['update','complete','delete'] as $op){try{$svc->mutate($this->actor(['id'=>2]),1,$id,$this->itemData(['version'=>1,'completed'=>true]),$op);self::fail();}catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){self::assertNotNull($e);}}
        try{$svc->index($this->actor(),2,$this->range());self::fail();}catch(HttpException $e){self::assertSame(403,$e->getStatusCode());}
    }
    public function test_todo_list_is_not_limited_to_month_and_overdue_is_jst_end_of_day():void {
        $svc=$this->svc();$svc->create($this->actor(),1,$this->itemData(['item_type'=>'todo','start_date'=>null,'title'=>'期限未定']));
        $svc->create($this->actor(),1,$this->itemData(['item_type'=>'deadline','start_date'=>'2026-10-01','title'=>'過去期限']));
        $today=$svc->create($this->actor(),1,$this->itemData(['item_type'=>'todo','title'=>'当日期限']));
        $timed=$svc->create($this->actor(),1,$this->itemData(['item_type'=>'todo','all_day'=>false,'start_time'=>'09:00','title'=>'当日時刻']));
        self::assertFalse($today['item']['overdue']);self::assertTrue($timed['item']['overdue']);
        $data=$svc->index($this->actor(),1,$this->range());self::assertCount(2,$data['items']);self::assertCount(4,$data['todos']);self::assertSame(['過去期限','当日時刻','当日期限','期限未定'],array_column($data['todos'],'title'));
        self::assertTrue($data['todos'][0]['overdue']);self::assertFalse($data['todos'][3]['overdue']);
        $done=$svc->mutate($this->actor(),1,$today['item']['id'],['version'=>1,'completed'=>true],'complete');self::assertTrue($done['completed']);
        self::assertSame(3,$svc->index($this->actor(),1,$this->range())['todo_total']);self::assertSame(4,$svc->index($this->actor(),1,$this->range(['show_completed'=>true]))['todo_total']);
    }
    public function test_version_guard_covers_update_complete_and_delete_and_deleted_retry_is_not_recreated():void {
        $svc=$this->svc();$data=$this->itemData();$first=$svc->create($this->actor(),1,$data)['item'];$id=$first['id'];
        $updated=$svc->mutate($this->actor(),1,$id,$this->itemData(['version'=>1,'title'=>'更新']),'update');self::assertSame(2,$updated['version']);
        foreach(['update','complete','delete'] as $op){try{$svc->mutate($this->actor(),1,$id,$this->itemData(['version'=>1,'completed'=>true]),$op);self::fail();}catch(HttpException $e){self::assertSame(409,$e->getStatusCode());}}
        $complete=$svc->mutate($this->actor(),1,$id,['version'=>2,'completed'=>true],'complete');self::assertSame(3,$complete['version']);
        $restored=$svc->mutate($this->actor(),1,$id,['version'=>3,'completed'=>false],'complete');self::assertFalse($restored['completed']);
        $svc->mutate($this->actor(),1,$id,['version'=>4],'delete');self::assertSame(0,PersonalCalendarItem::count());
        try{$svc->create($this->actor(),1,$data);self::fail();}catch(HttpException $e){self::assertSame(410,$e->getStatusCode());}self::assertSame(1,PersonalCalendarItem::withTrashed()->count());
    }
    public function test_periods_date_validation_and_plain_text_roundtrip():void {
        $svc=$this->svc();$row=$svc->create($this->actor(),1,$this->itemData(['start_date'=>'2026-10-30','end_date'=>'2026-11-02','title'=>'<script>alert(1)</script>','note'=>'<b>メモ</b>']))['item'];
        self::assertSame('2026-10-30',$row['start_date']);self::assertCount(1,$svc->index($this->actor(),1,$this->range())['items']);self::assertSame('<b>メモ</b>',$row['note']);
        foreach([
            ['start_date'=>'2026-02-30'],['title'=>'　 '],['start_date'=>'2026-01-01','end_date'=>'2027-12-01'],
            ['item_type'=>'todo','start_date'=>null,'start_time'=>'09:00','all_day'=>false],['item_type'=>'deadline','end_date'=>'2026-11-20'],
            ['all_day'=>true,'start_time'=>'09:00'],['all_day'=>false,'start_time'=>'10:00','end_time'=>'09:00'],['color_key'=>'invalid']
        ] as $bad){try{$svc->create($this->actor(),1,$this->itemData($bad));self::fail(json_encode($bad));}catch(ValidationException $e){self::assertNotEmpty($e->errors());}}
        try{$svc->index($this->actor(),1,['start'=>'2026-01-01','end'=>'2026-12-31']);self::fail();}catch(HttpException $e){self::assertSame(422,$e->getStatusCode());}
    }
    public function test_todo_pagination_preserves_undated_items():void {
        for($i=0;$i<32;$i++)$this->svc()->create($this->actor(),1,$this->itemData(['item_type'=>'todo','start_date'=>null,'title'=>'TODO '.$i]));
        $first=$this->svc()->index($this->actor(),1,$this->range());$second=$this->svc()->index($this->actor(),1,$this->range(['todo_page'=>2]));
        self::assertSame(32,$first['todo_total']);self::assertCount(30,$first['todos']);self::assertCount(2,$second['todos']);self::assertSame([],array_intersect(array_column($first['todos'],'id'),array_column($second['todos'],'id')));
    }
}
