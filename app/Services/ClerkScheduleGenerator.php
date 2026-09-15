<?php

namespace App\Services;

use App\Models\ClerkEvent;
use App\Models\ClerkScheduleOccurrence;
use App\Models\ClerkScheduleRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ClerkScheduleGenerator
{
    public function __construct(private ClerkScheduleDates $dates) {}

    public function generateCompany(int $companyId, ?int $year = null): void
    {
        ClerkScheduleRule::where('company_id', $companyId)->where('is_active', true)->eachById(function ($rule) use ($year) {
            $this->generate($rule->id);
            if ($year !== null) {
                $this->generate($rule->id, CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'Asia/Tokyo')->subDays(42)->toDateString(),
                    CarbonImmutable::create($year + 1, 1, 1, 0, 0, 0, 'Asia/Tokyo')->addDays(42)->toDateString());
            }
        });
    }

    public function generate(int $ruleId, ?string $from = null, ?string $to = null): void
    {
        DB::transaction(function () use ($ruleId, $from, $to) {
            $rule = ClerkScheduleRule::whereKey($ruleId)->lockForUpdate()->first();
            if (! $rule || ! $rule->is_active) {
                return;
            }
            $from ??= max(now('Asia/Tokyo')->toDateString(), $rule->starts_on->format('Y-m-d'));
            $to ??= $rule->recurrence === 'custom_dates'
                ? max($rule->custom_dates ?: [$from])
                : CarbonImmutable::parse($from, 'Asia/Tokyo')->addMonthsNoOverflow(18)->toDateString();
            $this->fillRange($rule, $from, $to);
        }, 3);
    }

    private function fillRange(ClerkScheduleRule $rule, string $from, string $to): void
    {
        foreach ($this->dates->between($rule, $from, $to) as $date) {
            $occurrence = ClerkScheduleOccurrence::firstOrCreate(['rule_id' => $rule->id, 'nominal_date' => $date], ['state' => 'retired']);
            if ($occurrence->state !== 'retired') {
                continue;
            }
            // ルール変更で取り除いた過去の回を復活させない。新規の過去日指定は生成可能。
            if (! $occurrence->wasRecentlyCreated && $date < now('Asia/Tokyo')->toDateString()) {
                continue;
            }
            $event = ClerkEvent::create($this->eventData($rule, $date) + ['company_id' => $rule->company_id, 'user_id' => $rule->created_by]);
            $occurrence->update(['clerk_event_id' => $event->id, 'state' => 'generated']);
        }
    }

    private function eventData(ClerkScheduleRule $rule, string $date): array
    {
        return ['title' => $rule->title, 'description' => $rule->description, 'color_key' => $rule->color_key,
            'starts_at' => $date.' 00:00:00', 'ends_at' => $date.' 00:00:00', 'all_day' => true];
    }

    public function changeRule(ClerkScheduleRule $rule, array $data, bool $delete = false): void
    {
        DB::transaction(function () use ($rule, $data, $delete) {
            $locked = ClerkScheduleRule::whereKey($rule->id)->lockForUpdate()->firstOrFail();
            $locked->fill($data);
            if ($delete) {
                $locked->is_active = false;
            }
            $locked->save();
            $today = now('Asia/Tokyo')->toDateString();
            // 個別に触っていない今後の回だけを同期する。
            foreach ($locked->occurrences()->where('state', 'generated')->where('nominal_date', '>=', $today)->lockForUpdate()->get() as $occurrence) {
                $event = ClerkEvent::whereKey($occurrence->clerk_event_id)->lockForUpdate()->first();
                if (! $event || $event->completed_at || $event->starts_at->format('Y-m-d') < $today) {
                    continue;
                }
                $date = $occurrence->nominal_date->format('Y-m-d');
                if ($locked->is_active && $this->dates->between($locked, $date, $date)) {
                    $event->update($this->eventData($locked, $date));
                } else {
                    $occurrence->update(['clerk_event_id' => null, 'state' => 'retired']);
                    $event->delete();
                }
            }
            if ($delete) {
                $locked->delete();
            } elseif ($locked->is_active) {
                $this->fillRange(
                    $locked,
                    max($today, $locked->starts_on->format('Y-m-d')),
                    $locked->recurrence === 'custom_dates'
                        ? max($locked->custom_dates ?: [$today])
                        : CarbonImmutable::parse($today, 'Asia/Tokyo')->addMonthsNoOverflow(18)->toDateString()
                );
            }
        }, 3);
    }

    // 生成処理と同じ「ルール→履歴→予定」の順にロックする。
    public function changeEvent(ClerkEvent $event, array $data = [], string $action = 'update'): ClerkEvent
    {
        return DB::transaction(function () use ($event, $data, $action) {
            $occurrence = ClerkScheduleOccurrence::where('clerk_event_id', $event->id)->first();
            if ($occurrence) {
                ClerkScheduleRule::withTrashed()->whereKey($occurrence->rule_id)->lockForUpdate()->firstOrFail();
                $occurrence = ClerkScheduleOccurrence::whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            }
            $locked = ClerkEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($action === 'delete') {
                $occurrence?->update(['clerk_event_id' => null, 'state' => 'cancelled']);
                $locked->delete();
            } elseif ($action === 'complete') {
                $locked->update(['completed_at' => $locked->completed_at ? null : now()]);
                $occurrence?->update(['state' => 'customized']);
            } else {
                $locked->fill($data);
                if ($locked->isDirty()) {
                    $occurrence?->update(['state' => 'customized']);
                }
                $locked->save();
            }

            return $locked;
        }, 3);
    }
}
