<?php
namespace App\Services;

use App\Models\PersonalCalendarItem;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** All mutations run under the owner's User lock and the item's lock/transaction. */
class PersonalTodoRecurrence
{
    public const LABELS = ['daily'=>'毎日','weekdays'=>'平日のみ（月〜金）','weekly'=>'毎週','monthly'=>'毎月','yearly'=>'毎年'];

    public function prepare(array $data, ?PersonalCalendarItem $item = null): array
    {
        $rule = array_key_exists('repeat_rule',$data) ? $data['repeat_rule'] : $item?->repeat_rule;
        if ($rule) {
            if (($data['item_type'] ?? $item?->item_type) === 'event') $this->invalid('通常予定には繰り返しTODOを設定できません。');
            $date = $data['start_date'] ?? null;
            if (!$date || $date < '1000-01-01') $this->invalid('繰り返す場合は期限日を入力してください。');
            if (($rule['until_date'] ?? null) && $rule['until_date'] < $date) $this->invalid('終了日は期限日以降にしてください。');
            if ($rule['frequency'] === 'weekdays' && Carbon::parse($date,'Asia/Tokyo')->isWeekend()) $this->invalid('平日のみの場合、期限日は月〜金を選択してください。');
            $old = $item?->repeat_rule;
            // Keep the original month/day through clamped February occurrences.
            $anchor = $old && $old['frequency'] === $rule['frequency'] && $date === $item->start_date?->format('Y-m-d')
                ? $old['anchor_date'] : $date;
            $rule = ['frequency'=>$rule['frequency'],'until_date'=>$rule['until_date']??null,'anchor_date'=>$anchor];
        }
        if ($item?->repeat_next_version !== null) {
            $dateChanged = ($data['start_date']??null) !== $item->start_date?->format('Y-m-d');
            $timeChanged = ($data['start_time']??null) !== ($item->start_time ? substr($item->start_time,0,5) : null);
            abort_if($dateChanged || $timeChanged || $rule !== $item->repeat_rule,409,'次回分が発行済みです。繰り返しや期限の変更は次回分で行ってください。');
        }
        // Omitting metadata must not change old create request hashes or old editors.
        if ($rule || array_key_exists('repeat_rule',$data)) $data['repeat_rule'] = $rule;
        return $data;
    }

    private function invalid(string $message): never { throw ValidationException::withMessages(['repeat_rule'=>$message]); }

    public function nextDate(array $rule, string $due, string $today): ?string
    {
        $after = Carbon::parse(max($due,$today),'Asia/Tokyo')->startOfDay();
        $anchor = Carbon::parse($rule['anchor_date'],'Asia/Tokyo')->startOfDay();
        $frequency = $rule['frequency'];
        if ($frequency === 'daily' || $frequency === 'weekdays') {
            $next = $after->copy()->addDay();
            if ($frequency === 'weekdays') while ($next->isWeekend()) $next->addDay();
        } elseif ($frequency === 'weekly') {
            $days = (int)$anchor->diffInDays($after, false);
            $next = $after->copy()->addDays(7 - (($days % 7 + 7) % 7));
        } elseif ($frequency === 'monthly') {
            $next = $after->copy()->startOfMonth();
            $next->day(min($anchor->day,$next->daysInMonth));
            if ($next->lte($after)) {
                $next->startOfMonth()->addMonth();
                $next->day(min($anchor->day,$next->daysInMonth));
            }
        } else {
            $next = $after->copy()->startOfYear()->month($anchor->month);
            $next->day(min($anchor->day,$next->daysInMonth));
            if ($next->lte($after)) {
                $next->startOfYear()->addYear()->month($anchor->month);
                $next->day(min($anchor->day,$next->daysInMonth));
            }
        }
        // Ending the series takes priority over an otherwise unrepresentable next date.
        if (($rule['until_date']??null) && $next->gt(Carbon::parse($rule['until_date'],'Asia/Tokyo'))) return null;
        if ($next->year > 9999) $this->invalid('次回の日付が登録可能な範囲を超えます。繰り返しを解除するか終了日を設定してください。');
        return $next->format('Y-m-d');
    }

    private function successor(PersonalCalendarItem $item): ?PersonalCalendarItem
    {
        return PersonalCalendarItem::withTrashed()->where('company_id',$item->company_id)->where('user_id',$item->user_id)
            ->where('repeat_previous_id',$item->id)->lockForUpdate()->first();
    }

    /** Returns only a small completion receipt; no extra owner data is exposed. */
    public function complete(PersonalCalendarItem $item, bool $completed): ?array
    {
        if (!$item->repeat_rule && $item->repeat_next_version === null) return null;
        if ((bool)$item->completed_at === $completed) return null;
        $next = $this->successor($item);
        if (!$completed) {
            if ($next) {
                abort_if($next->trashed() || $next->completed_at || $next->version !== $item->repeat_next_version,409,'次回分が更新・削除されています。次回分を確認してください。完了は取り消していません。');
                $next->repeat_retracted_at = Carbon::now('Asia/Tokyo');
                $next->version++;
                $next->save();
                $next->delete();
                $item->repeat_next_version = $next->version;
            }
            return ['status'=>'undone','retracted'=>(bool)$next];
        }
        if ($next) {
            abort_unless($next->trashed() && $next->repeat_retracted_at && !$next->completed_at && $next->version === $item->repeat_next_version,409,'次回分が更新・削除されています。重複作成は行いません。次回分を確認してください。');
            $next->repeat_retracted_at = null;
            $next->version++;
            $next->restore();
        } else {
            if (!$item->repeat_rule) return null;
            $date = $this->nextDate($item->repeat_rule,$item->start_date->format('Y-m-d'),Carbon::now('Asia/Tokyo')->toDateString());
            if (!$date) return ['status'=>'ended'];
            $values = $item->only(['company_id','user_id','item_type','title','note','all_day','color_key','start_time','todo_list_id','is_important']);
            $values += ['start_date'=>$date,'end_date'=>null,'end_time'=>null,'focus_date'=>null,'completed_at'=>null,
                'repeat_rule'=>$item->repeat_rule,'repeat_previous_id'=>$item->id,
                'steps'=>array_map(fn($s)=>['id'=>(string)Str::uuid(),'title'=>$s['title'],'completed'=>false],$item->steps??[])];
            $values['request_key']=(string)Str::uuid();
            $values['request_hash']=hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
            $next = PersonalCalendarItem::create($values)->fresh();
        }
        $item->repeat_next_version = $next->version;
        return ['status'=>'next','id'=>$next->id,'start_date'=>$next->start_date->format('Y-m-d')];
    }
}
