<?php

namespace App\Http\Controllers\ProofCoordinator;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Models\Event;
use App\Models\ProjectJobAssignment;
use App\Models\ProofRequest;
use App\Models\ProofReservation;
use App\Models\ProofSchedule;
use App\Models\ProofTeamMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    use CalculatesEventTime;

    // ──────────────────────────────────────────────────────
    //  ページ表示
    // ──────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $date = $request->query('date', now()->setTimezone('Asia/Tokyo')->toDateString());

        return Inertia::render('ProofCoordinator/Calendar', [
            'members'     => $this->getMembers(),
            'schedules'   => $this->getSchedulesForDate($date),
            'unassigned'  => $this->getUnassigned(),
            'date'        => $date,
            'monthEvents' => $this->getMonthEvents(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  JSON データ取得（日付切り替え時）
    // ──────────────────────────────────────────────────────

    public function data(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->setTimezone('Asia/Tokyo')->toDateString());

        return response()->json([
            'schedules'  => $this->getSchedulesForDate($date),
            'unassigned' => $this->getUnassigned(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  タイムラインピッカー用データ（アサインページ向け）
    // ──────────────────────────────────────────────────────

    public function pickerData(Request $request): JsonResponse
    {
        $date   = $request->query('date', now()->setTimezone('Asia/Tokyo')->toDateString());
        $userId = $request->query('user_id');

        // メンバー一覧（user_id 指定なら1人、なければ校正チーム全員）
        if ($userId) {
            $members = User::where('id', $userId)
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->toArray();
        } else {
            $members = ProofTeamMember::with('user:id,name')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->filter(fn ($m) => $m->user !== null)
                ->map(fn ($m) => ['id' => $m->user->id, 'name' => $m->user->name])
                ->values()
                ->toArray();
        }

        // 既存 ProofSchedule（日付フィルタ済み）
        $schedules = $this->getSchedulesForDate($date);
        if ($userId) {
            $schedules = array_values(
                array_filter($schedules, fn ($s) => (string) ($s['user_id'] ?? '') === (string) $userId)
            );
        }

        // 通常イベント（コンテキスト表示用 — 色のみ、タイトルなし）
        $memberIds = array_column($members, 'id');

        // 日境界は JST で持つ。events は proof ジョブ = UTC 保存 / 通常イベント = JST 保存の
        // 混在形式のため、DB 側の比較だけでは絞り切れない（UTC 境界で比較すると
        // 通常イベントの JST 15:00 以降が当日から漏れる）。
        // ±9時間のバッファで広めに取得し、JST 変換後にその日と重なるかで判定する。
        $dayStartJst = Carbon::parse($date . ' 00:00:00', 'Asia/Tokyo');
        $dayEndJst   = Carbon::parse($date . ' 23:59:59', 'Asia/Tokyo');
        $bufferStart = $dayStartJst->copy()->subHours(9)->toDateTimeString();
        $bufferEnd   = $dayEndJst->copy()->addHours(9)->toDateTimeString();

        $eventModels = Event::whereIn('user_id', $memberIds)
            ->where(function ($q) use ($bufferStart, $bufferEnd) {
                $q->whereBetween('starts_at', [$bufferStart, $bufferEnd])
                  ->orWhereBetween('ends_at',   [$bufferStart, $bufferEnd])
                  ->orWhere(fn ($q2) => $q2->where('starts_at', '<=', $bufferStart)->where('ends_at', '>=', $bufferEnd));
            })
            ->with('projectJobAssignment:id,job_type')
            ->get()
            ->filter(function ($e) use ($dayStartJst, $dayEndJst) {
                $jstStart = $this->resolveJstCarbon($e, 'starts_at');
                $jstEnd   = $this->resolveJstCarbon($e, 'ends_at');
                if (! $jstStart || ! $jstEnd) return false;
                return $jstStart->lte($dayEndJst) && $jstEnd->gte($dayStartJst);
            })
            ->values();

        // pja ID を一括取得してN+1回避
        $pjaIds = $eventModels->pluck('project_job_assignment_id')->filter()->unique()->values()->all();

        $senderMap = $pjaIds
            ? ProjectJobAssignment::whereIn('id', $pjaIds)->pluck('sender_id', 'id')->toArray()
            : [];

        $progressLinkedIds = $pjaIds
            ? \App\Models\ProgressCell::whereIn('assignment_id', $pjaIds)
                ->pluck('assignment_id')
                ->map(fn ($v) => (int) $v)
                ->all()
            : [];

        $contextEvents = $eventModels->map(function ($e) use ($senderMap, $progressLinkedIds) {
            $pjaId = $e->project_job_assignment_id;
            if (! $pjaId) {
                $color = '#1fb6b3'; // 予定（ティール）
            } elseif (in_array((int) $pjaId, $progressLinkedIds, true)) {
                $color = '#7C3AED'; // 進行表から（紫）
            } elseif (
                isset($senderMap[$pjaId]) &&
                $senderMap[$pjaId] !== null &&
                (int) $senderMap[$pjaId] === (int) $e->user_id
            ) {
                $color = '#4F46E5'; // 独自（インディゴ）
            } else {
                $color = '#059669'; // Coordinator割当（グリーン）
            }

            // events は proof=UTC / 通常=JST の混在保存。datetime キャストは常に JST として
            // 解釈するため、proof を素の ->utc() に通すと二重に 9 時間ずれる。
            // resolveJstCarbon() で正しい JST を得てから UTC ISO に変換する。
            $jstStart = $this->resolveJstCarbon($e, 'starts_at');
            $jstEnd   = $this->resolveJstCarbon($e, 'ends_at');

            return [
                'id'        => $e->id,
                'user_id'   => $e->user_id,
                'starts_at' => $jstStart ? $jstStart->copy()->utc()->toIso8601String() : null,
                'ends_at'   => $jstEnd   ? $jstEnd->copy()->utc()->toIso8601String()   : null,
                'color'     => $color,
            ];
        })->all();

        return response()->json([
            'members'   => $members,
            'schedules' => $schedules,
            'events'    => $contextEvents,
            'date'      => $date,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  スケジュール作成
    // ──────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'proof_request_id' => 'required|exists:proof_requests,id',
            'user_id'          => 'required|exists:users,id',
            'starts_at'        => 'required|date',
            'ends_at'          => 'required|date|after:starts_at',
        ]);

        $schedule = ProofSchedule::create($data);
        $schedule->load(['proofRequest.projectJob', 'user']);

        // ユーザーの Event にも反映
        $event = $this->syncEventFromSchedule($schedule);
        if ($event) {
            $schedule->update(['event_id' => $event->id]);
            $schedule->event_id = $event->id;
        }

        return response()->json($this->formatSchedule($schedule), 201);
    }

    // ──────────────────────────────────────────────────────
    //  スケジュール更新（ドラッグ移動・リサイズ・メンバー変更）
    // ──────────────────────────────────────────────────────

    public function update(Request $request, ProofSchedule $proofSchedule): JsonResponse
    {
        $data = $request->validate([
            'user_id'   => 'sometimes|exists:users,id',
            'starts_at' => 'sometimes|date',
            'ends_at'   => 'sometimes|date',
        ]);

        $proofSchedule->update($data);
        $proofSchedule->load(['proofRequest.projectJob', 'user']);

        // 対応する Event を更新（event_id が紐づいていれば）
        if ($proofSchedule->event_id) {
            $event = Event::find($proofSchedule->event_id);
            if ($event) {
                // ProofSchedule.starts_at は DATETIME カラムで UTC 格納。
                // datetime キャストは PHP tz(Asia/Tokyo) で解釈するため UTC 値を JST と誤認識する。
                // getRawOriginal() で UTC 文字列を取得し、正しく JST に変換する。
                $rawStart = $proofSchedule->getRawOriginal('starts_at');
                $rawEnd   = $proofSchedule->getRawOriginal('ends_at');
                $jstStart = Carbon::createFromFormat('Y-m-d H:i:s', $rawStart, 'UTC')->setTimezone('Asia/Tokyo');
                $jstEnd   = Carbon::createFromFormat('Y-m-d H:i:s', $rawEnd,   'UTC')->setTimezone('Asia/Tokyo');
                $event->update([
                    'date'      => $jstStart->toDateString(),
                    'start'     => $jstStart->format('Y-m-d H:i:s'),
                    'end'       => $jstEnd->format('Y-m-d H:i:s'),
                    'starts_at' => $rawStart,
                    'ends_at'   => $rawEnd,
                ]);
            }
        } else {
            // event_id が未設定の場合は新規作成を試みる
            $event = $this->syncEventFromSchedule($proofSchedule);
            if ($event) {
                $proofSchedule->update(['event_id' => $event->id]);
            }
        }

        return response()->json($this->formatSchedule($proofSchedule));
    }

    // ──────────────────────────────────────────────────────
    //  スケジュール削除
    // ──────────────────────────────────────────────────────

    public function destroy(ProofSchedule $proofSchedule): JsonResponse
    {
        // 対応する Event も削除
        $eventAssignmentId = null;
        if ($proofSchedule->event_id) {
            $ev = Event::find($proofSchedule->event_id);
            $eventAssignmentId = $ev?->project_job_assignment_id;
            $ev?->delete();
        }

        $proofSchedule->delete();

        // 校正スロット（pja101）の最後のイベント削除時: pja101/pja100 削除 + ProofRequest を pending に戻す
        if ($eventAssignmentId) {
            \App\Services\ProofJobRollbackService::rollbackIfNoEvents($eventAssignmentId);
        }

        return response()->json(['ok' => true]);
    }

    // ──────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────

    private function getMembers(): array
    {
        return ProofTeamMember::with('user:id,name')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn ($m) => $m->user !== null)
            ->map(fn ($m) => ['id' => $m->user->id, 'name' => $m->user->name])
            ->values()
            ->toArray();
    }

    private function getSchedulesForDate(string $date): array
    {
        // ProofSchedule は UTC 保存なので、JST の日付範囲を UTC に変換して比較する
        $dayStart = Carbon::parse($date . ' 00:00:00', 'Asia/Tokyo')->utc();
        $dayEnd   = Carbon::parse($date . ' 23:59:59', 'Asia/Tokyo')->utc();

        // events は proof=UTC / 通常=JST の混在保存のため、UTC 境界では正しく絞れない。
        // JST の日境界と ±9 時間のバッファを別途用意する（後段のイベント取得で使用）
        $dayStartJst = Carbon::parse($date . ' 00:00:00', 'Asia/Tokyo');
        $dayEndJst   = Carbon::parse($date . ' 23:59:59', 'Asia/Tokyo');
        $bufferStart = $dayStartJst->copy()->subHours(9)->toDateTimeString();
        $bufferEnd   = $dayEndJst->copy()->addHours(9)->toDateTimeString();

        // ① ProofSchedule（手動登録）
        $manual = ProofSchedule::with(['proofRequest.projectJob', 'user'])
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('starts_at', [$dayStart, $dayEnd])
                  ->orWhereBetween('ends_at', [$dayStart, $dayEnd])
                  ->orWhere(function ($q2) use ($dayStart, $dayEnd) {
                      $q2->where('starts_at', '<=', $dayStart)
                         ->where('ends_at', '>=', $dayEnd);
                  });
            })
            ->orderBy('starts_at')
            ->get()
            ->map(fn ($s) => $this->formatSchedule($s))
            ->all();

        // ② 校正員が自分でセットしたイベント（pja101）から自動生成
        // ProofRequest → coordinator assignment → self-assigned job → events
        $manualProofRequestIds = collect($manual)->pluck('proof_request_id')->filter()->unique();

        $activeRequests = ProofRequest::with(['proofCoordinator'])
            ->whereIn('status', ['assigned', 'in_progress'])
            ->whereNotIn('id', $manualProofRequestIds) // 手動登録済みは除外
            ->get();

        $fromEvents = [];
        foreach ($activeRequests as $pr) {
            if (! $pr->proofreader_id || ! $pr->proof_coordinator_id) continue;

            // コーディネーター割当（pja100）を特定
            $coordAssignment = ProjectJobAssignment::where('project_job_id', $pr->project_job_id)
                ->where('user_id', $pr->proofreader_id)
                ->where('sender_id', $pr->proof_coordinator_id)
                ->latest()
                ->first();
            if (! $coordAssignment) continue;

            // 自己割当（pja101）を特定
            $selfJob = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($coordAssignment) {
                    $q->where('coordinator_assignment_id', $coordAssignment->id)
                      ->orWhere('supersedes_assignment_id', $coordAssignment->id);
                })
                ->latest()
                ->first();
            if (! $selfJob) continue;

            // 該当日のイベントを取得
            // events は proof=UTC / 通常=JST の混在保存で DB 側では絞り切れないため、
            // ±9 時間のバッファで広く取得し、JST 変換後にその日と重なるかで判定する
            $events = Event::where('project_job_assignment_id', $selfJob->id)
                ->where(function ($q) use ($bufferStart, $bufferEnd) {
                    $q->whereBetween('starts_at', [$bufferStart, $bufferEnd])
                      ->orWhereBetween('ends_at', [$bufferStart, $bufferEnd]);
                })
                ->with('projectJobAssignment:id,job_type')
                ->orderBy('starts_at')
                ->get()
                ->filter(function ($e) use ($dayStartJst, $dayEndJst) {
                    $jstStart = $this->resolveJstCarbon($e, 'starts_at');
                    $jstEnd   = $this->resolveJstCarbon($e, 'ends_at');
                    if (! $jstStart || ! $jstEnd) return false;
                    return $jstStart->lte($dayEndJst) && $jstEnd->gte($dayStartJst);
                });

            foreach ($events as $ev) {
                // pja101 には job_type=proof（UTC 保存）と NULL（JST 保存）が混在する。
                // datetime キャストは常に JST として解釈するため、素の ->utc() では
                // proof 側が二重変換で 9 時間ずれる。resolveJstCarbon() を通してから UTC 化する。
                $jstStart = $this->resolveJstCarbon($ev, 'starts_at');
                $jstEnd   = $this->resolveJstCarbon($ev, 'ends_at');

                $fromEvents[] = [
                    'id'               => 'ev_' . $ev->id,
                    'proof_request_id' => $pr->id,
                    'user_id'          => $selfJob->user_id,
                    'starts_at'        => $jstStart ? $jstStart->copy()->utc()->toIso8601String() : null,
                    'ends_at'          => $jstEnd   ? $jstEnd->copy()->utc()->toIso8601String()   : null,
                    'title'            => $pr->title,
                    'job_title'        => $pr->projectJob?->title ?? null,
                    'status'           => $pr->status,
                    'deadline'         => $this->toUtcIso($pr->getRawOriginal('deadline')),
                    'user_name'        => $selfJob->user?->name ?? '—',
                    'event_id'         => $ev->id,
                    'from_event'       => true, // イベント由来フラグ
                ];
            }
        }

        return array_merge($manual, $fromEvents);
    }

    private function getUnassigned(): array
    {
        return ProofRequest::with(['requester', 'projectJob'])
            ->whereIn('status', ['assigned', 'in_progress'])
            ->orderBy('deadline')
            ->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'title'          => $r->title,
                'deadline'       => $this->toUtcIso($r->getRawOriginal('deadline')),
                'status'         => $r->status,
                'requester_name' => $r->requester?->name,
                'job_title'      => $r->projectJob?->title,
            ])
            ->toArray();
    }

    private function getMonthEvents(): array
    {
        $proofRequests = ProofRequest::with(['proofreader', 'projectJob'])
            ->whereNotNull('deadline')
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'type'        => 'proof_request',
                'title'       => $r->title,
                'start'       => Carbon::parse($r->getRawOriginal('deadline'), 'UTC')
                                    ->setTimezone('Asia/Tokyo')->toDateString(),
                'end'         => null,
                'status'      => $r->status,
                'proofreader' => $r->proofreader?->name,
                'job_title'   => $r->projectJob?->title,
            ])
            ->toArray();

        $reservations = ProofReservation::with('projectJob')
            ->whereNotNull('calendar_registered_at')
            ->where('status', '!=', 'deleted')
            ->where('requested_at_mode', 'datetime')
            ->where('deadline_mode', 'datetime')
            ->whereNotNull('requested_at')
            ->whereNotNull('deadline_at')
            ->get()
            ->map(function ($reservation) {
                $start = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $reservation->getRawOriginal('requested_at'),
                    'UTC',
                )->setTimezone('Asia/Tokyo');
                $deadline = Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $reservation->getRawOriginal('deadline_at'),
                    'UTC',
                )->setTimezone('Asia/Tokyo');

                return [
                    'id' => $reservation->id,
                    'type' => 'proof_reservation',
                    'title' => $reservation->title,
                    'start' => $start->toDateString(),
                    // FullCalendar の all-day end は排他的。締切日を含めるため翌日を渡す。
                    'end' => $deadline->copy()->addDay()->toDateString(),
                    'status' => match ($reservation->status) {
                        'in_progress' => 'in_progress',
                        'completed' => 'completed',
                        default => 'reservation',
                    },
                    'proofreader' => null,
                    'job_title' => $reservation->projectJob?->title,
                ];
            })
            ->toArray();

        return array_merge($proofRequests, $reservations);
    }

    /** DB に UTC で保存された datetime を UTC ISO 文字列として返す */
    private function toUtcIso(?string $raw): ?string
    {
        if (! $raw) return null;
        return Carbon::createFromFormat('Y-m-d H:i:s', $raw, 'UTC')->toIso8601String();
    }

    private function formatSchedule(ProofSchedule $s): array
    {
        return [
            'id'               => $s->id,
            'proof_request_id' => $s->proof_request_id,
            'user_id'          => $s->user_id,
            'starts_at'        => $this->toUtcIso($s->getRawOriginal('starts_at')),
            'ends_at'          => $this->toUtcIso($s->getRawOriginal('ends_at')),
            'title'            => $s->proofRequest?->title ?? '—',
            'job_title'        => $s->proofRequest?->projectJob?->title ?? null,
            'status'           => $s->proofRequest?->status ?? null,
            'deadline'         => $this->toUtcIso($s->proofRequest?->getRawOriginal('deadline')),
            'user_name'        => $s->user?->name ?? '—',
            'event_id'         => $s->event_id,
        ];
    }

    /**
     * ProofSchedule からユーザーの Event を作成 or 返す
     * pja100 → pja101（なければ作成）→ Event::create
     */
    private function syncEventFromSchedule(ProofSchedule $schedule): ?Event
    {
        $proofRequest = ProofRequest::find($schedule->proof_request_id);
        if (! $proofRequest || ! $proofRequest->proofreader_id || ! $proofRequest->proof_coordinator_id) {
            return null;
        }

        $pja100 = ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
            ->where('user_id', $proofRequest->proofreader_id)
            ->where('sender_id', $proofRequest->proof_coordinator_id)
            ->latest()->first();

        if (! $pja100) return null;

        // pja101 を取得または作成
        $pja101 = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
            ->where(function ($q) use ($pja100) {
                $q->where('coordinator_assignment_id', $pja100->id)
                  ->orWhere('supersedes_assignment_id', $pja100->id);
            })->latest()->first();

        if (! $pja101) {
            $pja101 = ProjectJobAssignment::create([
                'project_job_id'            => $proofRequest->project_job_id,
                'user_id'                   => $proofRequest->proofreader_id,
                'sender_id'                 => $proofRequest->proofreader_id,
                'coordinator_assignment_id' => $pja100->id,
                'job_type'                  => 'proof',
                'title'                => $proofRequest->title,
                'scheduled'            => true,
                'scheduled_at'         => now(),
            ]);
        }

        // ProofSchedule.starts_at は DATETIME カラムで UTC を格納している。
        // $schedule->starts_at の datetime キャストは PHP タイムゾーン (Asia/Tokyo) で解釈するため
        // UTC 格納値を JST と誤認識する。getRawOriginal() で正しい UTC 文字列を取得し JST に変換する。
        $rawStart = $schedule->getRawOriginal('starts_at');
        $rawEnd   = $schedule->getRawOriginal('ends_at');
        $jstStart = Carbon::createFromFormat('Y-m-d H:i:s', $rawStart, 'UTC')->setTimezone('Asia/Tokyo');
        $jstEnd   = Carbon::createFromFormat('Y-m-d H:i:s', $rawEnd,   'UTC')->setTimezone('Asia/Tokyo');

        return Event::create([
            'user_id'                   => $proofRequest->proofreader_id,
            'project_job_assignment_id' => $pja101->id,
            'date'                      => $jstStart->toDateString(),
            'start'                     => $jstStart->format('Y-m-d H:i:s'),
            'end'                       => $jstEnd->format('Y-m-d H:i:s'),
            'starts_at'                 => $rawStart,
            'ends_at'                   => $rawEnd,
            'title'                     => $proofRequest->title,
        ]);
    }
}
