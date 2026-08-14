<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\ScheduleNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendScheduleNotifications extends Command
{
    use \App\Http\Controllers\Concerns\CalculatesEventTime;

    protected $signature   = 'schedule:send-notifications {--date= : 対象日付(YYYY-MM-DD)。省略時は今日}';
    protected $description = '当日の予定を朝まとめ通知として schedule_notifications に登録する';

    public function handle(): int
    {
        $targetDate = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $this->info("対象日: {$targetDate->toDateString()}");

        // 当日に starts_at が含まれるイベントをすべて取得
        // events は proof=UTC / 通常=JST の混在保存のため DB 側では絞り切れない
        //（JST 09:00 より前に始まる校正予定が前日扱いで漏れる）。
        // ±9時間のバッファで広く取得し、JST 変換後に当日分だけへ絞る。
        $dayStart = $targetDate->copy()->startOfDay();
        $dayEnd   = $targetDate->copy()->endOfDay();

        $events = Event::whereBetween('starts_at', [
                $dayStart->copy()->subHours(9),
                $dayEnd->copy()->addHours(9),
            ])
            ->with(['attendees:id,event_id,user_id', 'projectJobAssignment:id,job_type'])
            ->get(['id', 'user_id', 'starts_at', 'project_job_assignment_id'])
            ->filter(function ($e) use ($dayStart, $dayEnd) {
                $jstStart = $this->resolveJstCarbon($e, 'starts_at');
                return $jstStart && $jstStart->gte($dayStart) && $jstStart->lte($dayEnd);
            });

        $count = 0;

        foreach ($events as $event) {
            // 参加者: 作成者 + schedule_attendees のユーザー
            $userIds = collect([$event->user_id])
                ->merge($event->attendees->pluck('user_id'))
                ->unique()
                ->filter()
                ->values();

            foreach ($userIds as $userId) {
                // 同日・同ユーザー・同イベントの morning_summary が既にあればスキップ
                $alreadyExists = ScheduleNotification::where('event_id', $event->id)
                    ->where('user_id', $userId)
                    ->where('type', 'morning_summary')
                    ->whereDate('scheduled_at', $targetDate->toDateString())
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                ScheduleNotification::create([
                    'event_id'     => $event->id,
                    'user_id'      => $userId,
                    'type'         => 'morning_summary',
                    'scheduled_at' => $targetDate->copy()->setTime(8, 0),
                    'notified_at'  => now(),
                ]);
                $count++;
            }
        }

        $this->info("通知登録完了: {$count} 件");
        return Command::SUCCESS;
    }
}
