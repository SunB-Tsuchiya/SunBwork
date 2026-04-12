<?php

namespace App\Http\Controllers\ProofCoordinator;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ProjectJobAssignment;
use App\Models\ProofRequest;
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
    // ──────────────────────────────────────────────────────
    //  ページ表示
    // ──────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $date = $request->query('date', now()->setTimezone('Asia/Tokyo')->toDateString());

        return Inertia::render('ProofCoordinator/Calendar', [
            'members'    => $this->getMembers(),
            'schedules'  => $this->getSchedulesForDate($date),
            'unassigned' => $this->getUnassigned(),
            'date'       => $date,
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

        return response()->json($this->formatSchedule($proofSchedule));
    }

    // ──────────────────────────────────────────────────────
    //  スケジュール削除
    // ──────────────────────────────────────────────────────

    public function destroy(ProofSchedule $proofSchedule): JsonResponse
    {
        $proofSchedule->delete();
        return response()->json(['ok' => true]);
    }

    // ──────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────

    private function getMembers(): array
    {
        $teamUserIds = ProofTeamMember::pluck('user_id');
        return User::whereIn('id', $teamUserIds)
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->toArray();
    }

    private function getSchedulesForDate(string $date): array
    {
        // JSTの日付の範囲をUTCに変換
        $dayStart = Carbon::parse($date . ' 00:00:00', 'Asia/Tokyo')->utc();
        $dayEnd   = Carbon::parse($date . ' 23:59:59', 'Asia/Tokyo')->utc();

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
                    $q->where('source_assignment_id', $coordAssignment->id)
                      ->orWhere('supersedes_assignment_id', $coordAssignment->id);
                })
                ->latest()
                ->first();
            if (! $selfJob) continue;

            // 該当日のイベントを取得
            $events = Event::where('project_job_assignment_id', $selfJob->id)
                ->where(function ($q) use ($dayStart, $dayEnd) {
                    $q->whereBetween('starts_at', [$dayStart, $dayEnd])
                      ->orWhereBetween('ends_at', [$dayStart, $dayEnd]);
                })
                ->orderBy('starts_at')
                ->get();

            foreach ($events as $ev) {
                $fromEvents[] = [
                    'id'               => 'ev_' . $ev->id,
                    'proof_request_id' => $pr->id,
                    'user_id'          => $selfJob->user_id,
                    'starts_at'        => $this->toUtcIso($ev->getRawOriginal('starts_at')),
                    'ends_at'          => $this->toUtcIso($ev->getRawOriginal('ends_at')),
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
}
