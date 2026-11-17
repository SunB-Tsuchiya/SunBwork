<?php
namespace Tests\Integration;
use App\Models\PersonalCalendarItem;
use App\Models\User;
use App\Services\PersonalMonthCalendar;
use App\Services\PersonalTodoRecurrence;
use App\Services\PersonalTodoWorkspace;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
require_once __DIR__.'/PersonalTodoWorkspaceTest.php';
class PersonalTodoRecurrenceTest extends PersonalTodoWorkspaceTest
{
    private function owner():User {return (new User)->forceFill(['id'=>1,'company_id'=>1,'department_id'=>1,'user_role'=>'superadmin']);}
    private function cal():PersonalMonthCalendar {return app(PersonalMonthCalendar::class);}
    private function recurring(array $extra=[]):array {return $this->cal()->create($this->owner(),1,$extra+['item_type'=>'todo','title'=>'定期TODO','start_date'=>'2026-11-17','all_day'=>true,'color_key'=>'blue','request_key'=>(string)Str::uuid(),'repeat_rule'=>['frequency'=>'daily','until_date'=>null]])['item'];}
    private function finish(array $row,bool $done=true):array {return $this->cal()->mutate($this->owner(),1,$row['id'],['version'=>$row['version'],'completed'=>$done],'complete');}
    private function edit(array $row,array $change):array {return $this->cal()->mutate($this->owner(),1,$row['id'],array_replace($row,$change),'update');}
    private function conflict(callable $action):void {try{$action();self::fail('expected conflict');}catch(HttpException $e){self::assertSame(409,$e->getStatusCode());}}
    public function test_repeat_date_boundaries_preserve_original_anchor():void {
        $svc=app(PersonalTodoRecurrence::class);
        foreach([
            ['daily','2026-11-17','2026-11-17','2026-11-17','2026-11-18'],
            ['weekdays','2026-11-20','2026-11-20','2026-11-20','2026-11-23'],
            ['weekly','2026-11-09','2026-11-09','2026-11-17','2026-11-23'],
            ['weekly','2026-11-23','2026-11-23','2026-11-17','2026-11-30'],
            ['monthly','2026-01-31','2026-01-31','2026-01-31','2026-02-28'],
            ['monthly','2026-01-31','2026-02-28','2026-02-28','2026-03-31'],
            ['monthly','2026-01-31','2026-02-28','2026-03-01','2026-03-31'],
            ['yearly','2024-02-29','2024-02-29','2024-02-29','2025-02-28'],
            ['yearly','2024-02-29','2027-02-28','2027-02-28','2028-02-29'],
        ] as [$freq,$anchor,$due,$today,$next]) self::assertSame($next,$svc->nextDate(['frequency'=>$freq,'anchor_date'=>$anchor],$due,$today));
        self::assertNull($svc->nextDate(['frequency'=>'daily','anchor_date'=>'2026-11-17','until_date'=>'2026-11-17'],'2026-11-17','2026-11-17'));
        self::assertNull($svc->nextDate(['frequency'=>'yearly','anchor_date'=>'9999-11-17','until_date'=>'9999-12-31'],'9999-11-17','9999-11-17'));
        try{$svc->nextDate(['frequency'=>'daily','anchor_date'=>'9999-12-31'],'9999-12-31','9999-12-31');self::fail();}catch(ValidationException $e){self::assertArrayHasKey('repeat_rule',$e->errors());}
    }
    public function test_repeat_completion_inherits_content_but_resets_steps_and_focus():void {
        $list=app(PersonalTodoWorkspace::class)->saveList($this->owner(),1,['name'=>'定期','color_key'=>'blue','request_key'=>(string)Str::uuid()]);
        $step=['id'=>(string)Str::uuid(),'title'=>'確認','completed'=>true];
        $row=$this->recurring(['todo_list_id'=>$list['id'],'is_important'=>true,'focus_date'=>'2026-11-17','note'=>'引継ぎメモ','steps'=>[$step]]);
        // Existing archived membership is intentionally retained in the next occurrence.
        app(PersonalTodoWorkspace::class)->saveList($this->owner(),1,['version'=>1,'name'=>'定期','color_key'=>'blue','archived'=>true],$list['id']);
        $done=$this->finish($row);self::assertTrue($done['completed']);self::assertSame('next',$done['repeat_result']['status']);
        $next=PersonalCalendarItem::findOrFail($done['repeat_result']['id']);
        self::assertSame('2026-11-18',$next->start_date->format('Y-m-d'));self::assertNull($next->focus_date);self::assertNull($next->completed_at);self::assertTrue($next->is_important);self::assertSame($list['id'],(int)$next->todo_list_id);self::assertSame('引継ぎメモ',$next->note);self::assertFalse($next->steps[0]['completed']);self::assertNotSame($step['id'],$next->steps[0]['id']);self::assertSame([$step],PersonalCalendarItem::findOrFail($row['id'])->steps);
        $this->conflict(fn()=>$this->finish($row));self::assertSame(2,PersonalCalendarItem::count());
        $again=$this->finish($done);self::assertNull($again['repeat_result']);self::assertSame(2,PersonalCalendarItem::count());
    }
    public function test_repeat_undo_and_redo_restore_same_id_and_protect_stale_screens():void {
        $done=$this->finish($this->recurring());$id=$done['repeat_result']['id'];
        $undone=$this->finish($done,false);self::assertFalse($undone['completed']);self::assertTrue(PersonalCalendarItem::withTrashed()->findOrFail($id)->trashed());
        self::assertSame(2,PersonalCalendarItem::withTrashed()->findOrFail($id)->version);
        $redone=$this->finish($undone);self::assertSame($id,$redone['repeat_result']['id']);self::assertSame(3,PersonalCalendarItem::findOrFail($id)->version);self::assertSame(2,PersonalCalendarItem::withTrashed()->count());
        $this->conflict(fn()=>$this->cal()->mutate($this->owner(),1,$id,['version'=>1,'completed'=>true],'complete'));
        $again=$this->finish($redone,false);self::assertFalse($again['completed']);
    }
    public function test_repeat_undo_rejects_edited_or_deleted_successor_atomically():void {
        $done=$this->finish($this->recurring());$next=$this->cal()->row(PersonalCalendarItem::findOrFail($done['repeat_result']['id']));
        $edited=$this->edit($next,['title'=>'編集済み']);$this->conflict(fn()=>$this->finish($done,false));
        self::assertNotNull(PersonalCalendarItem::findOrFail($done['id'])->completed_at);self::assertSame('編集済み',PersonalCalendarItem::findOrFail($next['id'])->title);
        $done2=$this->finish($this->recurring());$child=PersonalCalendarItem::findOrFail($done2['repeat_result']['id']);
        $this->cal()->mutate($this->owner(),1,$child->id,['version'=>1],'delete');$this->conflict(fn()=>$this->finish($done2,false));
        self::assertNull(PersonalCalendarItem::withTrashed()->findOrFail($child->id)->repeat_retracted_at);
    }
    public function test_repeat_old_editor_preserves_rule_and_monthly_anchor_without_hash_change():void {
        $payload=['item_type'=>'todo','title'=>'既存','start_date'=>'2026-01-31','all_day'=>true,'color_key'=>'blue','request_key'=>(string)Str::uuid(),'repeat_rule'=>['frequency'=>'monthly']];
        $r=$this->cal()->create($this->owner(),1,$payload);$hash=PersonalCalendarItem::findOrFail($r['item']['id'])->request_hash;
        $old=$r['item'];unset($old['repeat_rule']);$old['note']='旧モーダル';$saved=$this->cal()->mutate($this->owner(),1,$old['id'],$old,'update');self::assertSame('monthly',$saved['repeat_rule']['frequency']);
        self::assertTrue($this->cal()->create($this->owner(),1,$payload)['replayed']);self::assertSame($hash,PersonalCalendarItem::findOrFail($old['id'])->request_hash);
        Carbon::setTestNow(Carbon::parse('2026-01-31 23:59:59','Asia/Tokyo'));$done=$this->finish($saved);$child=$this->cal()->row(PersonalCalendarItem::findOrFail($done['repeat_result']['id']));$edited=$this->edit($child,['note'=>'短月']);self::assertSame('2026-01-31',PersonalCalendarItem::findOrFail($child['id'])->repeat_rule['anchor_date']);
        Carbon::setTestNow(Carbon::parse('2026-02-28 10:00:00','Asia/Tokyo'));$next=$this->finish($edited);self::assertSame('2026-03-31',$next['repeat_result']['start_date']);
    }
    public function test_repeat_stop_end_and_late_complete_generate_only_one_future_item():void {
        $r=$this->recurring(['start_date'=>'2026-11-09','repeat_rule'=>['frequency'=>'weekly']]);$done=$this->finish($r);self::assertSame('2026-11-23',$done['repeat_result']['start_date']);
        $this->conflict(fn()=>$this->edit($done,['repeat_rule'=>null]));
        $next=$this->cal()->row(PersonalCalendarItem::findOrFail($done['repeat_result']['id']));$stop=$this->edit($next,['repeat_rule'=>null]);$this->finish($stop);self::assertSame(2,PersonalCalendarItem::count());
        $end=$this->finish($this->recurring(['repeat_rule'=>['frequency'=>'daily','until_date'=>'2026-11-17']]));self::assertSame('ended',$end['repeat_result']['status']);
        $undo=$this->finish($end,false);self::assertFalse($undo['completed']);
    }
    public function test_repeat_invalid_rules_and_type_conversion_are_rejected_without_writes():void {
        foreach([
            ['start_date'=>null],['repeat_rule'=>[]],['repeat_rule'=>['frequency'=>'hourly']],['repeat_rule'=>['frequency'=>'daily','anchor_date'=>'2026-01-01']],['repeat_rule'=>['frequency'=>'daily','until_date'=>'2026-11-16']],['repeat_rule'=>['frequency'=>'weekdays'],'start_date'=>'2026-11-21'],
        ] as $bad){try{$this->recurring($bad);self::fail('invalid rule accepted');}catch(ValidationException $e){self::assertNotEmpty($e->errors());}}
        self::assertSame(0,PersonalCalendarItem::count());$r=$this->recurring();try{$this->edit($r,['item_type'=>'event']);self::fail();}catch(ValidationException $e){self::assertArrayHasKey('repeat_rule',$e->errors());}
        $changed=$this->edit($r,['start_date'=>'2026-11-20']);self::assertSame('2026-11-20',PersonalCalendarItem::findOrFail($r['id'])->repeat_rule['anchor_date']);
    }
    public function test_repeat_deleted_history_keeps_successor_and_does_not_cross_owner_boundary():void {
        $done=$this->finish($this->recurring());$next=$done['repeat_result']['id'];$this->cal()->mutate($this->owner(),1,$done['id'],['version'=>$done['version']],'delete');self::assertNotNull(PersonalCalendarItem::find($next));
        $wrong=(new User)->forceFill(['id'=>2,'company_id'=>1,'department_id'=>1,'user_role'=>'superadmin']);
        try{$this->cal()->mutate($wrong,1,$next,['version'=>1,'completed'=>true],'complete');self::fail();}catch(\Illuminate\Database\Eloquent\ModelNotFoundException $e){self::assertSame(1,PersonalCalendarItem::count());}
    }
    public function test_repeat_failure_rolls_back_completion_and_check_toggle_never_spawns():void {
        $row=$this->recurring(['start_date'=>'9999-12-31']);
        try{$this->finish($row);self::fail();}catch(ValidationException $e){self::assertSame(1,PersonalCalendarItem::count());self::assertNull(PersonalCalendarItem::findOrFail($row['id'])->completed_at);self::assertSame(1,PersonalCalendarItem::findOrFail($row['id'])->version);}
        $r=$this->recurring();$step=['id'=>(string)Str::uuid(),'title'=>'完了チェック','completed'=>true];
        $meta=app(PersonalTodoWorkspace::class)->meta($this->owner(),1,$r['id'],['version'=>1,'steps'=>[$step]]);self::assertFalse($meta['completed']);self::assertSame(2,PersonalCalendarItem::count());
        $done=$this->finish($meta);$child=$this->cal()->row(PersonalCalendarItem::findOrFail($done['repeat_result']['id']));$this->finish($child);$this->conflict(fn()=>$this->finish($done,false));
    }
    public function test_repeat_until_is_inclusive_and_jst_midnight_skips_past_days():void {
        Carbon::setTestNow(Carbon::parse('2026-11-17 15:01:00','UTC'));
        $done=$this->finish($this->recurring());self::assertSame('2026-11-19',$done['repeat_result']['start_date']);
        $r=$this->recurring(['start_date'=>'2026-11-18','repeat_rule'=>['frequency'=>'daily','until_date'=>'2026-11-19']]);$done=$this->finish($r);self::assertSame('2026-11-19',$done['repeat_result']['start_date']);
        $child=$this->cal()->row(PersonalCalendarItem::findOrFail($done['repeat_result']['id']));$last=$this->finish($child);self::assertSame('ended',$last['repeat_result']['status']);
    }

}
