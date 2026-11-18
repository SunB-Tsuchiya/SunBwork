<?php
namespace Tests\Integration;
use App\Models\{PersonalAbsencePlan,User,UserPresenceStatus};
use App\Services\PersonalAbsencePlans;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
require_once __DIR__.'/UnifiedMyJobCreateTest.php';
class PersonalAbsencePlansTest extends UnifiedMyJobCreateTest
{
    protected function setUp():void {
        parent::setUp();Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00','Asia/Tokyo'));
        (require dirname(__DIR__,2).'/database/migrations/2026_11_18_120001_create_personal_absence_plans.php')->up();
        Schema::create('iruka_status_orders',function(Blueprint $t){$t->id();$t->integer('company_id');$t->string('slug');$t->integer('sort_order')->default(0);$t->boolean('is_active')->default(true);$t->string('custom_label')->nullable();$t->string('custom_color')->nullable();$t->timestamps();});
        Schema::create('user_presence_statuses',function(Blueprint $t){$t->id();$t->integer('user_id');$t->string('status');$t->string('comment')->default('');$t->string('status_source')->default('manual');$t->dateTime('status_changed_at')->nullable();$t->integer('updated_by_id')->nullable();$t->integer('sort_order')->nullable();$t->boolean('is_hidden')->default(false);$t->timestamps();});
        Schema::create('user_settings',function(Blueprint $t){$t->id();$t->integer('user_id');$t->integer('worktype_id')->nullable();$t->string('lunch_start')->nullable();$t->string('lunch_end')->nullable();});
        Schema::create('worktypes',function(Blueprint $t){$t->id();$t->integer('company_id');$t->string('name');$t->string('start_time');$t->string('end_time');$t->integer('sort_order')->default(0);});
    }
    protected function tearDown():void {Carbon::setTestNow();parent::tearDown();}
    private function svc():PersonalAbsencePlans{return app(PersonalAbsencePlans::class);}
    private function actor(array $d=[]):User{return (new User)->forceFill($d+['id'=>1,'company_id'=>1,'department_id'=>1,'user_role'=>'superadmin']);}
    private function data(array $d=[]):array{return $d+['date'=>'2026-11-18','kind'=>'paid_leave','request_key'=>(string)Str::uuid()];}
    private function makePlan(array $d=[]):array{return $this->svc()->save($this->actor(),1,$this->data($d))['item'];}
    private function viewerUsers(?UserPresenceStatus $p=null,int $company=1):\Illuminate\Support\Collection{$u=$this->actor(['company_id'=>$company]);$u->setRelation('presenceStatus',$p);return collect([$u]);}
    private function projection(?UserPresenceStatus $p=null):array{return $this->svc()->presenceFor(1,$this->viewerUsers($p));}
    private function expectStatus(int $status,callable $fn):void {try{$fn();self::fail('expected status');}catch(HttpException $e){self::assertSame($status,$e->getStatusCode());}}
    public function test_absence_trial_company_owner_and_context_boundaries():void {
        foreach([['user_role'=>'user'],['department_id'=>2],['company_id'=>2,'department_id'=>3]] as $d){self::assertFalse($this->svc()->available($this->actor($d),1));$this->expectStatus(403,fn()=>$this->svc()->save($this->actor($d),1,$this->data()));}
        $this->expectStatus(403,fn()=>$this->svc()->range($this->actor(),2,['start'=>'2026-11-18','end'=>'2026-11-18']));
        $r=$this->makePlan(['user_id'=>999,'company_id'=>2]);self::assertSame(1,PersonalAbsencePlan::findOrFail($r['id'])->user_id);
        try{$this->svc()->remove($this->actor(['id'=>2]),1,$r['id'],['version'=>1]);self::fail();}catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){self::assertSame(1,PersonalAbsencePlan::count());}
        self::assertSame([],$this->svc()->presenceFor(2,$this->viewerUsers()));self::assertSame([],$this->svc()->presenceFor(1,$this->viewerUsers(null,2)));
    }
    public function test_absence_retry_version_and_deleted_retry_protection():void {
        $input=$this->data();$r=$this->svc()->save($this->actor(),1,$input);self::assertFalse($r['replayed']);self::assertTrue($this->svc()->save($this->actor(),1,$input)['replayed']);self::assertSame(1,PersonalAbsencePlan::count());
        $this->expectStatus(409,fn()=>$this->svc()->save($this->actor(),1,array_replace($input,['date'=>'2026-11-19'])));
        $edited=$this->svc()->save($this->actor(),1,['version'=>1,'date'=>'2026-11-19','kind'=>'paid_leave'],$r['item']['id']);self::assertSame(2,$edited['item']['version']);
        $this->expectStatus(409,fn()=>$this->svc()->remove($this->actor(),1,$r['item']['id'],['version'=>1]));$this->svc()->remove($this->actor(),1,$r['item']['id'],['version'=>2]);
        $this->expectStatus(410,fn()=>$this->svc()->save($this->actor(),1,$input));self::assertSame(0,PersonalAbsencePlan::count());
    }
    public function test_absence_time_validation_overlap_and_adjacent_half_days():void {
        foreach([['date'=>'2026-02-30'],['kind'=>'bad'],['kind'=>'paid_leave','start_time'=>'08:00'],['kind'=>'late'],['kind'=>'half_am','start_time'=>'13:00','end_time'=>'08:00'],['kind'=>'early_leave','start_time'=>'12:00','end_time'=>'12:00']] as $bad){try{$this->makePlan($bad);self::fail();}catch(ValidationException $e){self::assertNotEmpty($e->errors());}}
        $a=$this->makePlan(['kind'=>'half_am','start_time'=>'08:00','end_time'=>'13:00']);$b=$this->makePlan(['kind'=>'half_pm','start_time'=>'13:00','end_time'=>'17:00']);self::assertSame(2,PersonalAbsencePlan::count());
        try{$this->makePlan(['kind'=>'late','start_time'=>'08:00','end_time'=>'09:00']);self::fail();}catch(ValidationException $e){self::assertArrayHasKey('date',$e->errors());}
        $range=$this->svc()->range($this->actor(),1,['start'=>'2026-11-18','end'=>'2026-11-18']);self::assertCount(2,$range['events']);self::assertSame('absence-plan-'.$a['id'],$range['events'][0]['id']);self::assertSame('absence-plan',$range['events'][0]['_source']);
    }
    public function test_absence_full_day_jst_boundaries_and_future_plan_dont_change_today():void {
        $r=$this->makePlan(['date'=>'2026-11-19']);self::assertSame('2026-11-20 00:00:00',$r['ends_at']);self::assertSame([],$this->projection());
        Carbon::setTestNow(Carbon::parse('2026-11-18 15:00:00','UTC'));self::assertSame('paid_leave',$this->projection()[1]['status']);
        Carbon::setTestNow(Carbon::parse('2026-11-20 00:00:00','Asia/Tokyo'));self::assertSame([],$this->projection());
    }
    public function test_absence_any_viewer_projects_all_users_without_writing_presence_or_diary():void {
        $r=$this->makePlan();$p=new UserPresenceStatus(['status'=>'left','comment'=>'そのまま','status_source'=>'manual','status_changed_at'=>'2026-11-17 18:00:00']);
        $out=$this->projection($p);self::assertSame('paid_leave',$out[1]['status']);self::assertSame('absence_plan',$out[1]['status_source']);self::assertSame('そのまま',$p->comment);self::assertSame('left',$p->status);
        self::assertSame(0,UserPresenceStatus::count());self::assertFalse(Schema::hasTable('diaries'));self::assertFalse(Schema::hasTable('work_records'));self::assertSame(1,DB::table('events')->count());self::assertSame(0,DB::table('personal_work_entries')->count());
        $this->svc()->remove($this->actor(),1,$r['id'],['version'=>1]);self::assertSame([],$this->projection($p));self::assertSame('left',$p->status);
    }
    public function test_absence_later_manual_priority_calendar_source_and_end_boundary():void {
        $r=$this->makePlan(['kind'=>'late','start_time'=>'08:00','end_time'=>'11:00']);$p=new UserPresenceStatus(['status'=>'present','status_source'=>'manual','status_changed_at'=>'2026-11-18 10:01:00']);
        self::assertSame([],$this->projection($p));$p->status_source='calendar';self::assertSame('late',$this->projection($p)[1]['status']);
        Carbon::setTestNow(Carbon::parse('2026-11-18 11:00:00','Asia/Tokyo'));self::assertSame([],$this->projection($p));self::assertSame('present',$p->status);
    }
    public function test_absence_status_disabled_and_default_worktime_resolution():void {
        DB::table('iruka_status_orders')->insert(['company_id'=>1,'slug'=>'paid_leave','is_active'=>false]);
        try{$this->makePlan();self::fail();}catch(ValidationException $e){self::assertArrayHasKey('kind',$e->errors());}
        DB::table('worktypes')->insert([['id'=>1,'company_id'=>1,'name'=>'通常','start_time'=>'08:00','end_time'=>'17:00'],['id'=>2,'company_id'=>1,'name'=>'遅番','start_time'=>'10:00','end_time'=>'19:00'],['id'=>3,'company_id'=>2,'name'=>'別会社','start_time'=>'09:00','end_time'=>'18:00']]);
        DB::table('user_monthly_schedules')->insert(['user_id'=>1,'year_month'=>'2026-11','schedule'=>json_encode(['18'=>2])]);DB::table('user_monthly_breaks')->insert(['user_id'=>1,'year_month'=>'2026-11','schedule'=>json_encode(['18'=>['start'=>'13:00','end'=>'14:00']])]);
        $d=$this->svc()->defaults($this->actor(),1,['date'=>'2026-11-18']);self::assertSame('10:00',$d['start_time']);self::assertSame('14:00',$d['break_end']);
        DB::table('user_settings')->insert(['user_id'=>1,'worktype_id'=>3]);$d=$this->svc()->defaults($this->actor(),1,['date'=>'2026-11-19']);self::assertNull($d['start_time']);
    }
    public function test_absence_presence_controller_applies_for_other_user_and_preserves_meeting_sync():void {
        Schema::table('users',function(Blueprint $t){$t->integer('ghost_owner_id')->nullable();});
        Schema::table('events',function(Blueprint $t){$t->integer('event_item_type_id')->nullable();});
        Schema::create('event_item_types',function(Blueprint $t){$t->id();$t->string('slug');});
        DB::table('event_item_types')->insert(['id'=>1,'slug'=>'conference']);
        DB::table('events')->insert(['user_id'=>2,'event_item_type_id'=>1,'starts_at'=>'2026-11-18 09:00:00','ends_at'=>'2026-11-18 11:00:00']);
        $this->makePlan();UserPresenceStatus::create(['user_id'=>1,'status'=>'left','status_source'=>'manual','status_changed_at'=>'2026-11-17 18:00:00','comment'=>'手動コメント']);
        $viewer=$this->actor(['id'=>2,'user_role'=>'user']);\Illuminate\Support\Facades\Auth::setUser($viewer);
        $request=$this->request([],$viewer,1,'GET');$rows=app(\App\Http\Controllers\UserPresenceController::class)->index($request)->getData(true);
        $first=collect($rows)->firstWhere('id',1);self::assertSame('paid_leave',$first['status']);self::assertSame('手動コメント',$first['comment']);self::assertSame('absence_plan',$first['status_source']);
        self::assertSame('left',UserPresenceStatus::where('user_id',1)->firstOrFail()->status);self::assertSame('meeting',collect($rows)->firstWhere('id',2)['status']);
        self::assertFalse(Schema::hasTable('diaries'));self::assertFalse(Schema::hasTable('work_records'));
    }
    public function test_absence_registered_after_manual_and_plan_edit_reapply_without_overwriting_manual():void {
        $p=new UserPresenceStatus(['status'=>'present','status_source'=>'manual','status_changed_at'=>'2026-11-18 09:00:00']);$r=$this->makePlan();self::assertSame('paid_leave',$this->projection($p)[1]['status']);
        $p->status_changed_at='2026-11-18 10:01:00';self::assertSame([],$this->projection($p));Carbon::setTestNow(Carbon::parse('2026-11-18 10:02:00','Asia/Tokyo'));
        $this->svc()->save($this->actor(),1,['version'=>1,'date'=>'2026-11-18','kind'=>'paid_leave'],$r['id']);self::assertSame('paid_leave',$this->projection($p)[1]['status']);self::assertSame('present',$p->status);
    }

}
