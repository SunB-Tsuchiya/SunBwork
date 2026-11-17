<?php
namespace App\Services;

use App\Models\PersonalWorkEntry;
use App\Models\User;

/** Advice about the owner's input method, never an assessment of performance. */
class DiaryRecordAdvice
{
    use \App\Http\Controllers\Concerns\CalculatesEventTime;

    private const LONG_WORK_MINUTES = 420;

    public function __construct(private PersonalWorkSchedule $schedule) {}

    public function issues(User $user, string $date): array
    {
        if (! $this->schedule->personalAvailable($user)) return [];
        $entries = PersonalWorkEntry::ownedWork($user)->whereDate('work_date', $date)->get();
        $hasJob = $entries->isNotEmpty();
        $others = 0;
        $workRanges = [];
        $appointmentRanges = [];
        $day = \Carbon\Carbon::parse($date, 'Asia/Tokyo')->startOfDay();
        $until = $day->copy()->addDay();
        foreach ($this->schedule->eventsForUsers([$user->id], $date) as $event) {
            if ($event->cancelled_at) continue;
            $start = $this->resolveJstCarbon($event, 'starts_at');
            $end = $this->resolveJstCarbon($event, 'ends_at');
            if (! $start || ! $end || $end->lte($start) || $start->gte($until) || $end->lte($day)) continue;
            $range = [max(0, (int) (($start->timestamp - $day->timestamp) / 60)),
                min(1440, (int) (($end->timestamp - $day->timestamp) / 60))];
            $own = (int) $event->user_id === (int) $user->id;
            $slug = $event->eventItemType?->slug;
            $appointment = ! $event->project_job_assignment_id
                && ! in_array($slug, ['other','personal_other'], true)
                && ($event->meeting_definition_id || in_array($slug,
                    ['meeting_internal','meeting_client','conference','customer_visit','client_visit','outing'], true));
            // eventsForUsers includes other people's meetings only when this user accepted the invitation.
            if ($appointment) $appointmentRanges[] = $range;
            if (! $own) continue;
            if ($event->project_job_assignment_id) $hasJob = true;
            elseif (in_array($slug, ['other','personal_other'], true)) $others++;
            if ($event->project_job_assignment_id || in_array($slug, ['other','personal_other','work_schedule'], true)) {
                $workRanges[] = $range;
            }
        }
        $window = $entries->isNotEmpty() ? $this->schedule->workWindow($user, $date) : null;
        foreach ($entries as $entry) {
            if ($entry->plan_slot !== 'hour' && ! $window) continue;
            $range = $this->schedule->slotInterval($entry, $window);
            if (! $range || $range[1] <= $range[0]) continue;
            if (in_array($entry->activity_type, ['meeting','outing','sales_visit'], true)) $appointmentRanges[] = $range;
            else $workRanges[] = $range;
        }
        $longOverlaps = 0;
        foreach ($workRanges as [$from, $to]) {
            if ($to - $from < self::LONG_WORK_MINUTES) continue;
            foreach ($appointmentRanges as [$meetingFrom, $meetingTo]) {
                if (min($to, $meetingTo) > max($from, $meetingFrom)) { $longOverlaps++; break; }
            }
        }
        $issues = [];
        if (! $hasJob && $others) $issues[] = [
            'code' => 'other_without_myjob',
            'title' => '作業の登録方法をご確認ください',
            'message' => 'マイジョブがなく、「その他」の予定が'.$others.'件あります。',
            'hint' => '私用の予定は変更不要です。',
            'bullets' => [
                'マイジョブがなく、「その他」の予定が'.$others.'件あります。',
                '業務はマイジョブ、会議・外出はそれぞれの予定で登録してください。',
                '私用の予定は変更不要です。',
            ],
        ];
        if ($longOverlaps) $issues[] = [
            'code' => 'long_work_overlaps_appointment',
            'title' => '1日をまとめた作業枠をご確認ください',
            'message' => '昼休憩を含む7時間以上の作業枠と、会議・外出が重なる予定が'.$longOverlaps.'件あります。',
            'hint' => '1日の業務をまとめた枠なら、実際の作業ごとに分けてください。',
            'bullets' => [
                '昼休憩を含む7時間以上の作業枠と、会議・外出が重なる予定が'.$longOverlaps.'件あります。',
                '1日の業務をまとめた枠なら、実際の作業ごとに分けてください。',
                '私用や目印だけの予定は変更不要です。',
            ],
        ];
        return $issues;
    }
}
