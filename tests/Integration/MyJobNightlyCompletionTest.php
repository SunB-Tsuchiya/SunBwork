<?php
namespace Tests\Integration;
use App\Console\Commands\AutoCompleteMyJobs;
use App\Models\{PersonalWorkEntry,User};
use App\Services\{PersonalWorkSchedule,UnifiedMyJobBox};
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Artisan,DB,Schema};
require_once __DIR__.'/UnifiedMyJobCreateTest.php';
class MyJobNightlyCompletionTest extends UnifiedMyJobCreateTest
{
    protected function setUp():void {parent::setUp();if(str_starts_with($this->name(),'test_nightly_')){Carbon::setTestNow(Carbon::parse('2026-11-19 00:05:00','Asia/Tokyo'));Schema::table('project_job_assignments',function(Blueprint $t){$t->dateTime('scheduled_at')->nullable();});}}
    protected function tearDown():void {Carbon::setTestNow();parent::tearDown();}
    private function entry(array $d=[]):int {return DB::table('personal_work_entries')->insertGetId($d+['user_id'=>1,'company_id'=>1,'department_id'=>1,'work_date'=>'2026-11-18','work_category'=>'other','plan_slot'=>'hour','planned_start_time'=>'08:00','planned_end_time'=>'09:00','entry_visibility'=>'department','title'=>'新マイジョブ','actual_minutes'=>60,'created_at'=>'2026-11-18 08:00:00','updated_at'=>'2026-11-18 08:00:00']);}
    private function old(array $d=[]):int {return DB::table('project_job_assignments')->insertGetId($d+['user_id'=>1,'sender_id'=>1,'project_job_id'=>1,'company_id'=>1,'department_id'=>1,'completed'=>false,'title'=>'旧','scheduled_at'=>'2026-11-18 08:00:00','created_at'=>'2026-11-18 08:00:00','updated_at'=>'2026-11-18 08:00:00']);}
    private function runBatch(bool $dry=false):string {self::assertSame('sqlite',DB::connection()->getDriverName());self::assertSame(':memory:',DB::connection()->getDatabaseName());$status=Artisan::call(AutoCompleteMyJobs::class,$dry?['--dry-run'=>true]:[]);self::assertSame(0,$status);return Artisan::output();}
    public function test_nightly_new_only_is_completed_and_displays_in_box_and_calendar():void {
        $id=$this->entry(['actual_quantity'=>12,'actual_unit'=>'ページ','result_note'=>'保持']);$this->runBatch();$e=PersonalWorkEntry::findOrFail($id);self::assertNotNull($e->completed_at);self::assertSame('2026-11-19 00:05:00',$e->completed_at->format('Y-m-d H:i:s'));self::assertSame('12.00',$e->actual_quantity);self::assertSame(60,$e->actual_minutes);self::assertSame('保持',$e->result_note);
        self::assertStringStartsWith('【完了】',app(PersonalWorkSchedule::class)->entryCalendarTitle($e,true));$u=(new User)->forceFill(['id'=>1,'company_id'=>1,'department_id'=>1,'user_role'=>'user']);$row=collect(app(UnifiedMyJobBox::class)->rows($u,1))->firstWhere('key','entry:'.$id);self::assertTrue($row['completed']);
        self::assertSame(0,DB::table('job_notifications')->count());
    }
    public function test_nightly_excludes_today_future_completed_event_sources_and_other_scopes():void {
        $past=$this->entry();$ids=[];foreach([['work_date'=>'2026-11-19'],['work_date'=>'2026-11-20'],['source_event_id'=>99],['company_id'=>2,'department_id'=>3],['department_id'=>2],['user_id'=>999],['user_id'=>2,'company_id'=>2,'department_id'=>3]] as $d)$ids[]=$this->entry($d);
        $done=$this->entry(['completed_at'=>'2026-11-18 12:00:00']);$this->runBatch();self::assertNotNull(PersonalWorkEntry::findOrFail($past)->completed_at);foreach($ids as $id)self::assertNull(PersonalWorkEntry::findOrFail($id)->completed_at);self::assertSame('2026-11-18 12:00:00',PersonalWorkEntry::findOrFail($done)->completed_at->format('Y-m-d H:i:s'));
    }
    public function test_nightly_dry_run_never_changes_new_old_or_titles_and_repeat_is_idempotent():void {
        $new=$this->entry();$old=$this->old();$ev=DB::table('events')->insertGetId(['user_id'=>1,'project_job_assignment_id'=>$old,'title'=>'旧作業']);$snapshot=DB::table('personal_work_entries')->where('id',$new)->first();$text=$this->runBatch(true);self::assertStringContainsString('dry-run',$text);self::assertEquals($snapshot,DB::table('personal_work_entries')->where('id',$new)->first());self::assertFalse((bool)DB::table('project_job_assignments')->where('id',$old)->value('completed'));self::assertSame('旧作業',DB::table('events')->where('id',$ev)->value('title'));
        $this->runBatch();$first=DB::table('personal_work_entries')->where('id',$new)->first();Carbon::setTestNow(Carbon::parse('2026-11-19 00:10:00','Asia/Tokyo'));$this->runBatch();self::assertEquals($first,DB::table('personal_work_entries')->where('id',$new)->first());self::assertSame('【完了】旧作業',DB::table('events')->where('id',$ev)->value('title'));
    }
    public function test_nightly_legacy_priority_fallback_and_requested_jobs_are_preserved():void {
        $a=$this->old();$b=$this->old(['scheduled_at'=>null,'desired_end_date'=>'2026-11-18']);$c=$this->old(['scheduled_at'=>'2026-11-20 08:00:00','desired_end_date'=>'2026-11-18']);$d=$this->old(['scheduled_at'=>null,'desired_end_date'=>null]);$e=$this->old(['sender_id'=>null]);$f=$this->old(['sender_id'=>2]);$this->runBatch();foreach([$a,$b]as$id){self::assertTrue((bool)DB::table('project_job_assignments')->where('id',$id)->value('completed'));self::assertSame(3,DB::table('project_job_assignments')->where('id',$id)->value('status_id'));}foreach([$c,$d,$e,$f]as$id)self::assertFalse((bool)DB::table('project_job_assignments')->where('id',$id)->value('completed'));
    }
    public function test_nightly_continuations_are_independent_and_utc_clock_uses_jst_date():void {
        Carbon::setTestNow(Carbon::parse('2026-11-18 15:05:00','UTC'));$root=$this->entry();$child=$this->entry(['work_date'=>'2026-11-19','source_entry_id'=>$root]);$this->runBatch();self::assertNotNull(PersonalWorkEntry::findOrFail($root)->completed_at);self::assertNull(PersonalWorkEntry::findOrFail($child)->completed_at);
    }
    public function test_nightly_rechecks_rows_rescheduled_or_manually_completed_after_count():void {
        foreach(['future','completed'] as $mode){
            $id=$this->entry();$state=(object)['changed'=>false];
            DB::listen(function($query)use($id,$mode,$state){
                if(!$state->changed&&str_contains($query->sql,'count(*)')&&str_contains($query->sql,'personal_work_entries')){
                    $state->changed=true;DB::table('personal_work_entries')->where('id',$id)->update($mode==='future'?['work_date'=>'2026-11-20']:['completed_at'=>'2026-11-19 00:01:00']);
                }
            });
            $this->runBatch();self::assertTrue($state->changed);$entry=PersonalWorkEntry::findOrFail($id);
            if($mode==='future')self::assertNull($entry->completed_at);else self::assertSame('2026-11-19 00:01:00',$entry->completed_at->format('Y-m-d H:i:s'));
        }
    }
    public function test_nightly_imported_request_reference_does_not_complete_original_or_notify():void {
        $original=$this->old(['sender_id'=>2,'job_type'=>'proof']);$id=$this->entry(['source_assignment_id'=>$original,'project_job_assignment_id'=>$original]);
        $this->runBatch();self::assertNotNull(PersonalWorkEntry::findOrFail($id)->completed_at);self::assertFalse((bool)DB::table('project_job_assignments')->where('id',$original)->value('completed'));self::assertSame(0,DB::table('job_notifications')->count());self::assertSame(0,DB::table('work_job_completion_syncs')->count());
    }

}
