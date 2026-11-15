<?php
namespace App\Services;

use App\Models\PersonalWorkEntry;
use App\Models\User;

/** Advice about the owner's input method, never an assessment of performance. */
class DiaryRecordAdvice
{
    use \App\Http\Controllers\Concerns\CalculatesEventTime;

    private const LONG_WORK_MINUTES = 180;

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
            'title' => '業務の予定はマイジョブで記録してください',
            'message' => 'この日はマイジョブの登録がなく、「その他」の予定が'.$others.'件あります。業務を登録した予定であれば、作業内容に合うマイジョブとして記録してください。私用の予定は変更不要です。',
            'hint' => '会議・外出はそれぞれの予定で登録し、作業時間と分けてください。長い一本の予定にまとめると仕事内容が伝わりにくくなります。重複時間は両方へそのまま加算されず、「その他（私用）」は作業時間の集計対象外です。',
        ];
        if ($longOverlaps) $issues[] = [
            'code' => 'long_work_overlaps_appointment',
            'title' => '長い作業枠と会議・外出の時間が重なっています',
            'message' => 'この日は、3時間以上の作業枠と会議・外出が重なる予定が'.$longOverlaps.'件あります。業務を一本の枠にまとめている場合は、会議・外出の時間に合わせて作業枠を分け、実際の仕事内容で記録してください。',
            'hint' => '重複時間は両方にそのまま加算されません。予定の長さだけで成果や評価が決まるわけではありません。私用や目印だけの予定なら変更は不要です。',
        ];
        return $issues;
    }
}
