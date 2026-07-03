<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use App\Models\Event;
use App\Models\IrukaStatusOrder;
use App\Models\User;
use App\Models\UserPresenceStatus;
use App\Models\UserMonthlySchedule;
use App\Models\UserSetting;
use App\Models\WorkRecord;
use App\Models\Worktype;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class UserPresenceController extends Controller
{
    use \App\Http\Controllers\Concerns\ResolvesContextCompany;
    /**
     * モバイル向けステータス更新ページ（Inertia）
     */
    public function statusUpdatePage()
    {
        $user      = Auth::user();
        $companyId = $user->company_id;

        $presence = UserPresenceStatus::where('user_id', $user->id)->first();
        $orders   = IrukaStatusOrder::getOrCreateForCompany($companyId);

        $statuses = $orders->where('is_active', true)
            ->map(fn ($o) => [
                'slug'         => $o->slug,
                'sort_order'   => $o->sort_order,
                'custom_label' => $o->custom_label,
                'custom_color' => $o->custom_color,
            ])
            ->values();

        return Inertia::render('Iruka/StatusUpdate', [
            'userId'         => $user->id,
            'currentStatus'  => $presence?->status  ?? 'left',
            'currentComment' => $presence?->comment ?? '',
            'statuses'       => $statuses,
        ]);
    }

    /**
     * 全ユーザーの在席ステータス一覧を返す（ポーリング用）
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();

        // カレンダーイベントに基づいて自分のステータスを自動同期
        $this->syncCalendarStatus($authUser);

        // SuperAdmin はコンテキスト会社のユーザーを表示。グローバル時は自社にフォールバック
        $presenceCompanyId = $authUser->isSuperAdmin()
            ? ($this->contextCompanyId() ?? $authUser->company_id)
            : $authUser->company_id;

        $users = User::with(['department', 'presenceStatus', 'positionTitle'])
            ->where('company_id', $presenceCompanyId)
            ->where('is_ghost', false)
            ->whereNull('ghost_owner_id')
            ->get();

        // BoardSettings と同じ優先順: sort_order → 役職順 → 雇用形態 → 名前
        $employmentPriority = ['regular' => 1, 'contract' => 2, 'dispatch' => 3, 'outsource' => 4];
        $data = $users
            ->filter(fn(User $u) => !($u->presenceStatus?->is_hidden ?? false))
            ->sortBy([
                fn($a, $b) => ($a->presenceStatus?->sort_order ?? 9999) <=> ($b->presenceStatus?->sort_order ?? 9999),
                fn($a, $b) => ($a->positionTitle?->sort_order ?? 9999) <=> ($b->positionTitle?->sort_order ?? 9999),
                fn($a, $b) => ($employmentPriority[$a->employment_type ?? 'regular'] ?? 99) <=> ($employmentPriority[$b->employment_type ?? 'regular'] ?? 99),
                fn($a, $b) => $a->name <=> $b->name,
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
            'status'            => $validated['status'],
            'updated_by_id'     => $authUser->id,
            'status_source'     => 'manual',
            'status_changed_at' => now(),
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
                'status'            => 'left',
                'comment'           => '',
                'updated_by_id'     => $user->id,
                'status_source'     => 'manual',
                'status_changed_at' => now(),
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

            // この予定の開始後に手動でステータス変更していれば、それを優先し自動上書きしない
            // (status_changed_at は手動変更時のみ更新される。updated_at は board 設定保存等でも
            //  更新されてしまうため、手動変更の判定には使わない)
            if (
                $presence
                && $presence->status_source === 'manual'
                && $presence->status_changed_at
                && $presence->status_changed_at->gte(Carbon::parse($event->starts_at))
            ) {
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

            // カレンダーイベントがある場合は常に更新（手動変更より優先）
            UserPresenceStatus::updateOrCreate(
                ['user_id' => $user->id],
                ['status' => $targetStatus, 'status_source' => 'calendar', 'updated_by_id' => $user->id]
            );
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
            // 当日の勤務形態を user_monthly_schedules → UserSetting → 会社デフォルトの順で取得
            $worktypeId = UserMonthlySchedule::worktypeIdForDate($user->id, $today);

            if (!$worktypeId) {
                $worktypeId = UserSetting::where('user_id', $user->id)->value('worktype_id');
            }

            $worktype = $worktypeId
                ? Worktype::where('company_id', $user->company_id)->find($worktypeId)
                : Worktype::where('company_id', $user->company_id)->orderBy('sort_order')->first();

            // 退社時刻を5分単位（切り捨て）で丸める
            $now     = Carbon::now();
            $rounded = $now->minute - ($now->minute % 5);
            $endTime = $now->copy()->minute($rounded)->second(0)->format('H:i');

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
