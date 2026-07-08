<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectJobMemberScheduleController extends Controller
{
    // ──────────────────────────────────────────────────────
    //  ページ表示
    // ──────────────────────────────────────────────────────

    public function index(Request $request, ProjectJob $projectJob): Response
    {
        $projectJob->load(['teamMembers.user', 'user', 'coordinators']);

        $date    = $request->query('date', now()->setTimezone('Asia/Tokyo')->toDateString());
        $members = $this->getMembers($projectJob);
        $events  = $this->getEventsForDate($projectJob, $members, $date);

        return Inertia::render('Coordinator/ProjectJobs/MemberSchedule', [
            'job'     => [
                'id'      => $projectJob->id,
                'title'   => $projectJob->title ?? $projectJob->name ?? '（案件名なし）',
                'user_id' => $projectJob->user_id,
            ],
            'subCoordinatorIds' => $projectJob->coordinators->pluck('id')->all(),
            'members' => $members,
            'events'  => $events,
            'date'    => $date,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  JSON データ取得（日付切り替え時）
    // ──────────────────────────────────────────────────────

    public function data(Request $request, ProjectJob $projectJob): JsonResponse
    {
        $projectJob->load(['teamMembers.user']);
        $date    = $request->query('date', now()->setTimezone('Asia/Tokyo')->toDateString());
        $members = $this->getMembers($projectJob);
        $events  = $this->getEventsForDate($projectJob, $members, $date);

        return response()->json([
            'events' => $events,
            'date'   => $date,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────

    private function getMembers(ProjectJob $projectJob): array
    {
        $leaderId  = $projectJob->user_id;
        $subCoIds  = $projectJob->coordinators->pluck('id')->flip();

        return $projectJob->teamMembers
            ->filter(fn ($m) => $m->user !== null)
            ->map(function ($m) use ($leaderId, $subCoIds) {
                $uid      = $m->user->id;
                $userRole = $m->user->user_role ?? 'user';
                if ($uid === $leaderId) {
                    $role = 'leader';
                } elseif ($subCoIds->has($uid)) {
                    $role = 'sub_leader';
                } elseif (in_array($userRole, ['coordinator', 'clerk'], true)) {
                    $role = 'coordinator';
                } else {
                    $role = 'user';
                }
                return [
                    'id'   => $uid,
                    'name' => $m->user->name,
                    'role' => $role,
                ];
            })
            ->sortBy(fn ($m) => match ($m['role']) {
                'leader'      => 0,
                'sub_leader'  => 1,
                'coordinator' => 2,
                default       => 3,
            })
            ->values()
            ->toArray();
    }

    /**
     * 指定日のEventを全メンバー対象で取得し、
     * この案件のAssignmentに紐づくものは詳細付き、それ以外は色のみで返す。
     */
    private function getEventsForDate(ProjectJob $projectJob, array $members, string $date): array
    {
        if (empty($members)) {
            return [];
        }

        $memberIds = array_column($members, 'id');
        $dayStart  = $date . ' 00:00:00';
        $dayEnd    = $date . ' 23:59:59';

        // この案件に属する Assignment IDを一括取得
        $jobAssignmentIds = ProjectJobAssignment::where('project_job_id', $projectJob->id)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        // 該当日のEventsを取得（event_item_type が付いた「会議/外出等の絶対に作業できない予定」のみ。
        // マイジョブ等の project_job_assignment 由来イベントは event_item_type_id を持たないため除外される）
        $eventModels = Event::whereIn('user_id', $memberIds)
            ->whereNotNull('event_item_type_id')
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('starts_at', [$dayStart, $dayEnd])
                  ->orWhereBetween('ends_at', [$dayStart, $dayEnd])
                  ->orWhere(fn ($q2) => $q2->where('starts_at', '<=', $dayStart)->where('ends_at', '>=', $dayEnd));
            })
            ->get();

        // 全pja IDを一括取得（N+1回避）
        $allPjaIds = $eventModels->pluck('project_job_assignment_id')->filter()->unique()->values()->all();

        $senderMap = $allPjaIds
            ? ProjectJobAssignment::whereIn('id', $allPjaIds)->pluck('sender_id', 'id')->toArray()
            : [];

        $progressLinkedIds = $allPjaIds
            ? \App\Models\ProgressCell::whereIn('assignment_id', $allPjaIds)
                ->pluck('assignment_id')
                ->map(fn ($v) => (int) $v)
                ->all()
            : [];

        // この案件のAssignmentのtitle/detailをまとめて取得
        $relatedAssignments = $jobAssignmentIds
            ? ProjectJobAssignment::whereIn('id', $jobAssignmentIds)
                ->get(['id', 'title', 'detail'])
                ->keyBy('id')
            : collect();

        return $eventModels->map(function ($e) use (
            $senderMap,
            $progressLinkedIds,
            $jobAssignmentIds,
            $relatedAssignments
        ) {
            $pjaId    = $e->project_job_assignment_id ? (int) $e->project_job_assignment_id : null;
            $isRelated = $pjaId && in_array($pjaId, $jobAssignmentIds, true);

            // 色判定（校正カレンダーと同規則）
            if (! $pjaId) {
                $color = '#1fb6b3'; // 予定（ティール）
            } elseif (in_array($pjaId, $progressLinkedIds, true)) {
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

            $item = [
                'id'        => $e->id,
                'user_id'   => $e->user_id,
                'starts_at' => $this->toUtcIso($e->getRawOriginal('starts_at')),
                'ends_at'   => $this->toUtcIso($e->getRawOriginal('ends_at')),
                'color'     => $color,
                'related'   => $isRelated,
            ];

            // この案件のAssignmentに紐づく場合のみ詳細を付与
            if ($isRelated) {
                $assignment  = $relatedAssignments->get($pjaId);
                $item['title']  = $e->title ?? $assignment?->title ?? '（タイトル未設定）';
                $item['detail'] = $assignment?->detail ?? null;
            }

            return $item;
        })->values()->all();
    }

    private function toUtcIso(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        try {
            return Carbon::parse($raw, 'Asia/Tokyo')->toIso8601String();
        } catch (\Throwable) {
            return $raw;
        }
    }
}
