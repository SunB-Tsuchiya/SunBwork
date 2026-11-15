<?php
namespace Tests\Integration;

use App\Models\Event;
use App\Models\EventItemType;
use App\Services\DiaryRecordAdvice;
use App\Services\PersonalWorkSchedule;
use Illuminate\Support\Collection;
require_once __DIR__.'/UnifiedMyJobCreateTest.php';

class DiaryRecordAdviceTest extends UnifiedMyJobCreateTest
{
    private function event(string $slug='other', array $values=[]): Event
    {
        return (new Event)->forceFill($values+['id'=>101,'user_id'=>1,'starts_at'=>'2026-12-01 09:00:00','ends_at'=>'2026-12-01 17:00:00'])
            ->setRelation('eventItemType',(new EventItemType)->forceFill(['slug'=>$slug]))
            ->setRelation('projectJobAssignment',null)->syncOriginal();
    }
    private function advice(array $events, bool $allowed=true): array
    {
        $schedule=$this->createMock(PersonalWorkSchedule::class);
        $schedule->method('personalAvailable')->willReturn($allowed);
        $schedule->method('eventsForUsers')->willReturn(collect($events));
        return (new DiaryRecordAdvice($schedule))->issues($this->request()->user(),'2026-12-01');
    }
    public function test_advice_only_other_and_other_with_outing_warn_without_assessing_performance(): void
    {
        foreach (['other','personal_other'] as $slug) {
            $rows=$this->advice([$this->event($slug),$this->event('outing',['id'=>102])]);
            self::assertCount(1,$rows);
            self::assertSame('other_without_myjob',$rows[0]['code']);
            self::assertStringContainsString('私用の予定は変更不要',$rows[0]['message']);
            self::assertStringNotContainsString('不利',$rows[0]['message']);
        }
    }
    public function test_advice_new_myjob_with_other_category_and_legacy_job_prevent_warning(): void
    {
        self::assertSame([],$this->advice([$this->event(),$this->event('other',['id'=>102,'project_job_assignment_id'=>10])]));
        $this->save($this->request($this->payload(['work_category'=>'other'])));
        self::assertSame([],$this->advice([$this->event()]));
    }
    public function test_advice_ignores_other_people_cancelled_events_and_meetings_only(): void
    {
        self::assertSame([],$this->advice([$this->event('other',['user_id'=>2])]));
        self::assertSame([],$this->advice([$this->event('other',['cancelled_at'=>'2026-12-01 08:00:00'])]));
        self::assertSame([],$this->advice([$this->event('meeting_internal')]));
        self::assertSame([],$this->advice([$this->event()],false));
        self::assertSame([],$this->advice([$this->event('other',['starts_at'=>'2026-11-30 20:00:00','ends_at'=>'2026-12-01 00:00:00'])]));
    }
    public function test_advice_api_uses_owner_only_and_changed_context_returns_no_advice(): void
    {
        $schedule=$this->createMock(PersonalWorkSchedule::class);
        $schedule->method('personalAvailable')->willReturn(true);
        $schedule->method('eventsForUsers')->willReturn(collect([$this->event()]));
        $schedule->method('workWindow')->willReturn(null);
        app()->instance(PersonalWorkSchedule::class,$schedule);
        $controller=app(\App\Http\Controllers\DiaryController::class);
        $r=$controller->workReminderApi($this->request(['date'=>'2026-12-01','user_id'=>2]));
        self::assertCount(1,$r->getData(true)['reminder']['issues']);
        self::assertStringContainsString('no-store',$r->headers->get('Cache-Control'));
        self::assertNull($controller->workReminderApi($this->request(['date'=>'2026-12-01'],null,2))->getData(true)['reminder']);
        self::assertNull($controller->workReminderApi($this->request(['date'=>'2026-12-02']))->getData(true)['reminder']);
    }
    public function test_advice_shortage_uses_v3_and_does_not_count_future_work_hours(): void
    {
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-12-01 10:00:00','Asia/Tokyo'));
        try {
            $schedule=$this->createMock(PersonalWorkSchedule::class);
            $schedule->method('personalAvailable')->willReturn(true);
            $schedule->method('eventsForUsers')->willReturn(collect());
            $schedule->method('workWindow')->willReturn(['start'=>540,'end'=>1020,'break_start'=>720,'break_end'=>780]);
            app()->instance(PersonalWorkSchedule::class,$schedule);
            $analysis=$this->createMock(\App\Services\WorkloadTrialAnalysis::class);
            $analysis->expects(self::once())->method('analyze')->with(self::anything(),self::anything(),self::anything(),self::anything(),false,'trial_time_v3')->willReturn([['scheduled_covered_minutes'=>30]]);
            app()->instance(\App\Services\WorkloadTrialAnalysis::class,$analysis);
            $data=app(\App\Http\Controllers\DiaryController::class)->workReminderApi($this->request(['date'=>'2026-12-01']))->getData(true)['reminder'];
            self::assertSame(60,$data['scheduled_minutes']);
            self::assertSame(30,$data['missing_minutes']);
        } finally { \Carbon\Carbon::setTestNow(); }
    }
}
