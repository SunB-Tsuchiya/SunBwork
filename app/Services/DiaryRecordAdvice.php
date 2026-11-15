<?php
namespace App\Services;

use App\Models\PersonalWorkEntry;
use App\Models\User;

/** Advice about the owner's input method, never an assessment of performance. */
class DiaryRecordAdvice
{
    use \App\Http\Controllers\Concerns\CalculatesEventTime;

    public function __construct(private PersonalWorkSchedule $schedule) {}

    public function issues(User $user, string $date): array
    {
        if (! $this->schedule->personalAvailable($user)) return [];
        if (PersonalWorkEntry::ownedWork($user)->whereDate('work_date', $date)->exists()) return [];
        $hasJob = false;
        $others = 0;
        foreach ($this->schedule->eventsForUsers([$user->id], $date) as $event) {
            if ((int) $event->user_id !== (int) $user->id || $event->cancelled_at) continue;
            $start = $this->resolveJstCarbon($event, 'starts_at');
            $end = $this->resolveJstCarbon($event, 'ends_at');
            if (! $start || ! $end || $end->lte($start)
                || $start->gte(\Carbon\Carbon::parse($date, 'Asia/Tokyo')->addDay()->startOfDay())
                || $end->lte(\Carbon\Carbon::parse($date, 'Asia/Tokyo')->startOfDay())) continue;
            if ($event->project_job_assignment_id) $hasJob = true;
            elseif (in_array($event->eventItemType?->slug, ['other','personal_other'], true)) $others++;
        }
        return ! $hasJob && $others ? [[
            'code' => 'other_without_myjob',
            'title' => '業務の予定はマイジョブで記録してください',
            'message' => 'この日はマイジョブの登録がなく、「その他」の予定が'.$others.'件あります。業務を登録した予定であれば、作業内容に合うマイジョブとして記録してください。私用の予定は変更不要です。',
            'hint' => '会議・外出はそれぞれの予定で登録し、作業時間と分けてください。長い一本の予定にまとめると仕事内容が伝わりにくくなります。重複時間は両方へそのまま加算されず、「その他（私用）」は作業時間の集計対象外です。',
        ]] : [];
    }
}
