<?php
namespace Tests\Integration;

use App\Models\User;
use App\Models\DepartmentWorkDisplaySetting;
use App\Services\DepartmentWorkDisplaySettings;
use App\Services\PersonalWorkSchedule;
use App\Services\WorkloadTrialAnalysis;
use App\Services\DepartmentWorkRecords;
use App\Services\ClerkCalendarHolidays;
use App\Http\Controllers\Admin\DepartmentWorkDisplayController;
use App\Http\Controllers\Schedule\DepartmentWorkController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/UnifiedMyJobCreateTest.php';
class DepartmentWorkDisplayTest extends UnifiedMyJobCreateTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('users',function(Blueprint $t){ $t->integer('ghost_owner_id')->nullable(); $t->string('employment_type')->nullable(); $t->integer('sort_order')->default(0); $t->integer('assignment_id')->nullable(); });
        foreach (['assignments','user_settings','user_employment_settings'] as $table) Schema::create($table,function(Blueprint $t){$t->id();$t->integer('user_id')->nullable();$t->string('code')->nullable();$t->string('name')->nullable();});
        Schema::create('work_records',function(Blueprint $t){$t->id();$t->integer('user_id');$t->date('date');$t->string('start_time')->nullable();$t->string('end_time')->nullable();});
        Schema::create('schedule_attendees',function(Blueprint $t){$t->id();$t->integer('event_id');$t->integer('user_id');$t->string('status');});
        (require dirname(__DIR__,2).'/database/migrations/2026_11_16_120001_create_department_work_display_settings.php')->up();
    }
    private function actor(string $role='admin',int $company=1): User { return (new User)->forceFill(['id'=>1,'company_id'=>$company,'department_id'=>1,'user_role'=>$role]); }
    private function settings(): DepartmentWorkDisplaySettings { return new DepartmentWorkDisplaySettings; }
    private function change(int $user,bool $schedule,bool $records): array
    {
        return $this->settings()->save($this->actor(),1,[['user_id'=>$user,'show_schedule'=>$schedule,'show_records'=>$records]],$this->settings()->snapshot(1)['version']);
    }
    public function test_display_scope_defaults_independence_audit_and_old_department_setting(): void
    {
        $s=$this->settings();
        self::assertCount(2,$s->snapshot(1)['users']);
        self::assertTrue($s->snapshot(1)['users'][0]['show_schedule']);
        $this->change(2,false,true);
        self::assertSame([1],$s->visible(User::where('company_id',1),'show_schedule')->pluck('id')->all());
        self::assertSame([1,2],$s->visible(User::where('company_id',1),'show_records')->orderBy('id')->pluck('id')->all());
        $row=DepartmentWorkDisplaySetting::first()->getAttributes();
        self::assertSame(0,$this->change(2,false,true)['changed']);
        self::assertSame($row,DepartmentWorkDisplaySetting::first()->getAttributes());
        $this->change(2,true,false);
        self::assertSame([1],$s->visible(User::where('company_id',1),'show_records')->pluck('id')->all());
        DB::table('users')->where('id',2)->update(['department_id'=>2]);
        self::assertSame([1,2],$s->visible(User::where('company_id',1),'show_records')->orderBy('id')->pluck('id')->all());
    }
    public function test_display_management_limits_company_roles_and_ghost_candidates(): void
    {
        $s=$this->settings();
        self::assertSame(1,$s->company($this->actor(),2));
        self::assertSame(1,$s->company($this->actor('superadmin'),1));
        self::assertNull($s->company($this->actor('superadmin'),null));
        foreach (['user','leader','coordinator','clerk'] as $role) {
            try {$s->company($this->actor($role),1);self::fail();}catch(HttpException $e){self::assertSame(403,$e->getStatusCode());}
        }
        try {$s->company($this->actor('superadmin'),2);self::fail();}catch(HttpException $e){self::assertSame(403,$e->getStatusCode());}
        DB::table('users')->where('id',2)->update(['is_ghost'=>true]);
        self::assertCount(1,$s->snapshot(1)['users']);
        DB::table('users')->where('id',2)->update(['is_ghost'=>false,'ghost_owner_id'=>1]);
        self::assertCount(1,$s->snapshot(1)['users']);
    }
    public function test_display_foreign_items_fail_whole_save_and_stale_version_conflicts(): void
    {
        $s=$this->settings();$version=$s->snapshot(1)['version'];
        try {$s->save($this->actor(),1,[['user_id'=>1,'show_schedule'=>false,'show_records'=>false],['user_id'=>999,'show_schedule'=>false,'show_records'=>false]],$version);self::fail();}
        catch(ValidationException $e){self::assertArrayHasKey('items',$e->errors());}
        self::assertSame(0,DepartmentWorkDisplaySetting::count());
        DB::table('users')->insert([['id'=>3,'company_id'=>2,'department_id'=>3],['id'=>4,'company_id'=>1,'department_id'=>2]]);
        foreach ([3,4] as $foreign) {
            try {$s->save($this->actor(),1,[['user_id'=>$foreign,'show_schedule'=>false,'show_records'=>false]],$s->snapshot(1)['version']);self::fail();}
            catch(ValidationException $e){self::assertArrayHasKey('items',$e->errors());}
        }
        self::assertSame(0,DepartmentWorkDisplaySetting::count());

        $this->change(2,false,false);
        try {$s->save($this->actor(),1,[['user_id'=>1,'show_schedule'=>false,'show_records'=>false]],$version);self::fail();}
        catch(HttpException $e){self::assertSame(409,$e->getStatusCode());}
        self::assertTrue($s->snapshot(1)['users'][0]['show_schedule']);
    }
    public function test_display_hidden_schedule_cannot_be_fetched_by_user_id_and_input_stays_enabled(): void
    {
        $schedule=$this->createMock(PersonalWorkSchedule::class);
        $schedule->method('canViewDepartmentWork')->willReturn(true);
        $schedule->method('eventsForUsers')->willReturn(collect());
        $schedule->method('occupancy')->willReturn(['stage'=>'unknown','percent'=>null]);
        $holidays=$this->createMock(ClerkCalendarHolidays::class);$holidays->method('forCompany')->willReturn([]);
        app()->instance(ClerkCalendarHolidays::class,$holidays);
        $controller=new DepartmentWorkController($schedule,$this->createMock(WorkloadTrialAnalysis::class));
        $this->change(2,false,true);
        $data=$controller->index($this->request(['date'=>'2026-12-01']))->getData(true);
        self::assertSame([1],array_column($data['people'],'id'));
        try {$controller->index($this->request(['date'=>'2026-12-01','user_id'=>2]));self::fail();}
        catch(HttpException $e){self::assertSame(404,$e->getStatusCode());}
        $this->change(1,false,false);
        self::assertTrue(app(PersonalWorkSchedule::class)->personalAvailable($this->actor('user')));
        self::assertGreaterThan(0,$this->save($this->request($this->payload()))->getData(true)['id']);
    }
    public function test_display_hidden_own_record_keeps_personal_summary_and_analysis_user_query(): void
    {
        \Carbon\Carbon::setTestNow('2026-11-16 17:00:00');
        try {
            $this->change(1,true,false);
            $schedule=$this->createMock(PersonalWorkSchedule::class);$schedule->method('canViewDepartmentWork')->willReturn(true);
            $analysis=$this->createMock(WorkloadTrialAnalysis::class);
            $analysis->method('analyze')->willReturnCallback(fn ($viewer,$users) => $users->map(fn ($u)=>['id'=>$u->id,'name'=>$u->name??'Synthetic','minutes'=>90,'activities'=>[],'time_breakdown_minutes'=>['quick'=>90]])->all());
            $holidays=$this->createMock(ClerkCalendarHolidays::class);$holidays->method('forCompany')->willReturn([]);
            $service=new DepartmentWorkRecords($schedule,$analysis,$holidays);
            $data=$service->records($this->actor('user'),null,1);
            self::assertSame([2],array_column($data['people'],'id'));
            self::assertSame(90,$data['mine']['minutes']);
            self::assertSame(2,User::where('company_id',1)->where('department_id',1)->count());
            self::assertArrayNotHasKey('points',$data['mine']);
        } finally { \Carbon\Carbon::setTestNow(); }
    }
    public function test_display_controller_rejects_duplicate_targets_and_normal_roles(): void
    {
        $controller=new DepartmentWorkDisplayController;$settings=$this->settings();
        $item=['user_id'=>1,'show_schedule'=>false,'show_records'=>false];
        $request=$this->request(['version'=>$settings->snapshot(1)['version'],'items'=>[$item,$item]],$this->actor());
        try {$controller->update($request,$settings);self::fail();}
        catch(ValidationException $e){self::assertNotEmpty($e->errors());}
        self::assertSame(0,DepartmentWorkDisplaySetting::count());
        try {$controller->index($this->request([],$this->actor('user')),$settings);self::fail();}
        catch(HttpException $e){self::assertSame(403,$e->getStatusCode());}
        try {$controller->update($this->request(['version'=>$settings->snapshot(1)['version'],'items'=>[$item]],$this->actor('superadmin')),$settings);self::fail();}
        catch(HttpException $e){self::assertSame(422,$e->getStatusCode());}
    }
}
