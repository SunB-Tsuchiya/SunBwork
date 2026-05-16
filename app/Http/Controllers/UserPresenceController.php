<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Models\Event;
use App\Models\IrukaStatusOrder;
use App\Models\User;
use App\Models\UserPresenceStatus;
use App\Models\UserMonthlySchedule;
use App\Models\WorkRecord;
use App\Models\Worktype;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserPresenceController extends Controller
{
    /**
     * 全ユーザーの在席ステータス一覧を返す（ポーリング用）
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();

        // カレンダーイベントに基づいて自分のステータスを自動同期
        $this->syncCalendarStatus($authUser);

        $users = User::with(['department', 'presenceStatus'])
            ->where('company_id', $authUser->company_id)
            ->where('is_ghost', false)
            ->whereNull('ghost_owner_id')
            ->get();

        // is_hidden のユーザーを除外し、sort_order → department_id → name の順でソート
        $data = $users
            ->filter(fn(User $u) => !($u->presenceStatus?->is_hidden ?? false))
            ->sortBy([
                fn($u) => $u->presenceStatus?->sort_order ?? 9999,
                fn($u) => $u->department_id ?? 9999,
                fn($u) => $u->name,
            ])
            ->values()
            ->map(function (User $u) {
                $ps = $u->presenceStatus;
                return [
                    'id'            => $u->id,
                    'name'          => $u->name,
                    'department_id' => $u->department_id,
                    'department'    => $u->department?->name ?? '未所属',
                    'status'        => $ps?->status ?? 'left',
                    'comment'       => $ps?->comment ?? '',
                    'updated_at'    => $ps?->updated_at?->toDateTimeString() ?? null,
                    'updated_by_id' => $ps?->updated_by_id ?? null,
                ];
            });

        return response()->json($data);
    }

    /**
     * 指定ユーザーのステータスを更新
     * 自分以外はステータスのみ変更可（comment は無視）
     */
    public function update(Request $request, User $user)
    {
        $authUser = Auth::user();
        $isSelf   = ($authUser->id === $user->id);

        $validated = $request->validate([
            'status'  => 'required|string|max:50',
            'comment' => 'nullable|string|max:200',
        ]);

        $data = [
            'status'         => $validated['status'],
            'updated_by_id'  => $authUser->id,
            'status_source'  => 'manual',
        ];

        if ($isSelf) {
            $data['comment'] = $validated['comment'] ?? '';
        }

        UserPresenceStatus::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        // 退社ステータスなら自動日報チェック
        if ($validated['status'] === 'left') {
            $this->maybeAutoCreateDiary($user);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * 自分のコメントを削除（ステータスは left に戻す）
     */
    public function clearSelf(Request $request)
    {
        $user = Auth::user();

        UserPresenceStatus::updateOrCreate(
            ['user_id' => $user->id],
            [
                'status'        => 'left',
                'comment'       => '',
                'updated_by_id' => $user->id,
                'status_source' => 'manual',
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * 会社ごとのステータス順序を返す（モーダル用）
     */
    public function statuses()
    {
        $companyId = Auth::user()->company_id;
        $orders    = IrukaStatusOrder::getOrCreateForCompany($companyId);

        return response()->json(
            $orders->where('is_active', true)
                   ->map(fn ($o) => [
                       'slug'         => $o->slug,
                       'sort_order'   => $o->sort_order,
                       'custom_label' => $o->custom_label,
                       'custom_color' => $o->custom_color,
                   ])
                   ->values()
        );
    }

    /**
     * カレンダーイベントに基づいてステータスを自動同期する（ポーリング毎）
     */
    private function syncCalendarStatus(User $user): void
    {
        try {
            // 非proofイベントはJST文字列保存のためJSTで比較
            $nowJST = Carbon::now('Asia/Tokyo')->format('Y-m-d H:i:s');

            $event = Event::where('user_id', $user->id)
                ->whereNotNull('event_item_type_id')
                ->where('starts_at', '<=', $nowJST)
                ->where('ends_at', '>=', $nowJST)
                ->with('eventItemType')
                ->latest('starts_at')
                ->first();

            $presence = UserPresenceStatus::where('user_id', $user->id)->first();

            if (!$event) {
                // イベントなし → calendar設定のままなら在席に戻す
                if ($presence && $presence->status_source === 'calendar') {
                    $presence->update(['status' => 'present', 'status_source' => 'manual']);
                }
                return;
            }

            static $map = [
                'conference'       => 'meeting',
                'meeting_internal' => 'discussion',
                'meeting_client'   => 'discussion',
                'customer_visit'   => 'out',
                'client_visit'     => 'client_reception',
                'outing'           => 'out',
            ];

            $slug         = $event->eventItemType?->slug;
            $targetStatus = $map[$slug] ?? null;

            if (!$targetStatus) {
                return;
            }

            // 手動変更済みは上書きしない
            if (!$presence || $presence->status_source !== 'manual') {
                UserPresenceStatus::updateOrCreate(
                    ['user_id' => $user->id],
                    ['status' => $targetStatus, 'status_source' => 'calendar', 'updated_by_id' => $user->id]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("syncCalendarStatus failed for user {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * 退社時に当日の日報がなければ自動作成する
     */
    private function maybeAutoCreateDiary(User $user): void
    {
        $today = Carbon::now()->toDateString();

        // 既存の日報チェック
        $exists = Diary::where('user_id', $user->id)->where('date', $today)->exists();
        if ($exists) {
            return;
        }

        try {
            // 当日の勤務形態を user_monthly_schedules から取得
            $worktypeId = UserMonthlySchedule::worktypeIdForDate($user->id, $today);

            // worktype が見つからなければ会社のデフォルト（sort_order=1）を使用
            $worktype = $worktypeId
                ? Worktype::where('company_id', $user->company_id)->find($worktypeId)
                : Worktype::where('company_id', $user->company_id)->orderBy('sort_order')->first();

            // 現在時刻（退社時刻）
            $endTime = Carbon::now()->format('H:i');

            // 始業時間: worktype の定時
            $startTime = $worktype ? substr($worktype->start_time, 0, 5) : null;

            // Diary 作成
            Diary::create([
                'user_id' => $user->id,
                'date'    => $today,
                'content' => '',
            ]);

            // WorkRecord 作成（既存がなければ）
            $workRecordExists = WorkRecord::where('user_id', $user->id)->where('date', $today)->exists();
            if (!$workRecordExists && $startTime) {
                $scheduledStart = $worktype ? substr($worktype->start_time, 0, 5) : null;
                $scheduledEnd   = $worktype ? substr($worktype->end_time,   0, 5) : null;

                $record = new WorkRecord();
                $record->start_time      = $startTime . ':00';
                $record->end_time        = $endTime . ':00';
                $record->scheduled_start = $scheduledStart ? $scheduledStart . ':00' : null;
                $record->scheduled_end   = $scheduledEnd   ? $scheduledEnd   . ':00' : null;
                $record->calcOvertime();

                WorkRecord::create([
                    'user_id'             => $user->id,
                    'company_id'          => $user->company_id,
                    'department_id'       => $user->department_id,
                    'worktype_id'         => $worktype?->id,
                    'date'                => $today,
                    'start_time'          => $record->start_time,
                    'end_time'            => $record->end_time,
                    'scheduled_start'     => $record->scheduled_start,
                    'scheduled_end'       => $record->scheduled_end,
                    'overtime_minutes'    => $record->overtime_minutes,
                    'early_leave_minutes' => $record->early_leave_minutes,
                ]);
            }

            Log::info("UserPresenceController: auto-created diary for user {$user->id} on {$today}");
        } catch (\Throwable $e) {
            Log::warning("UserPresenceController: maybeAutoCreateDiary failed for user {$user->id}: " . $e->getMessage());
        }
    }
}
