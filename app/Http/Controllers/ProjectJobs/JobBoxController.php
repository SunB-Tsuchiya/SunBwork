<?php

namespace App\Http\Controllers\ProjectJobs;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\JobAssignmentMessage;
use App\Models\ProjectJobAssignment;
use Illuminate\Http\Request;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class JobBoxController extends Controller
{
    public function index(ProjectJob $projectJob, Request $request)
    {
        // Allow coordinators/leaders/admins/superadmins as before; for normal users,
        // ensure the user is assigned to this project_job before showing jobbox.
        $user = $request->user();
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin() || $user->isClerk()));

        // routeContext: user ルート経由ならば 'user'、coordinator ルート経由ならば 'coordinator'
        $routeName = $request->route()?->getName();
        $routeContext = str_starts_with((string) $routeName, 'user.') ? 'user' : 'coordinator';

        // Additionally allow the project owner or any user who has sent a job-assignment
        // message for this project to view the project jobbox even if they are not
        // explicitly assigned. This lets senders (coordinators or project owners)
        // view the jobbox they created messages in.
        $isOwner = $user && $projectJob->user_id && $user->id === $projectJob->user_id;
        $isSender = false;
        if ($user) {
            $isSender = \App\Models\JobAssignmentMessage::join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
                ->where('project_job_assignments.project_job_id', $projectJob->id)
                ->where('job_assignment_messages.sender_id', $user->id)
                ->exists();
        }

        if (! $isPrivileged && ! $isOwner && ! $isSender) {
            // check if the current user has any assignment for this project job
            $hasAssignment = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->where('user_id', $user ? $user->id : 0)
                ->exists();
            if (! $hasAssignment) {
                abort(403, 'Access denied.');
            }
        }

        $q = $request->input('q');
        $periodParam = $request->query('period');
        $usePeriodFilter = true;
        $periodModel = $periodParam;
        // period 未指定 または 'all' の場合は全期間表示（global() と同じ動作に統一）
        if ($periodParam === null || $periodParam === '' || $periodParam === 'all') {
            $usePeriodFilter = false;
            $periodModel = 'all';
        }

        $periodStart = null;
        $periodEnd = null;
        if ($usePeriodFilter) {
            try {
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            } catch (\Throwable $__e) {
                $periodModel = now()->format('Y-m');
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            }
        }
        $sort = $request->input('sort');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Build a query with joins so we can sort by related columns (assignment desired_start_date, sender name)
        $base = JobAssignmentMessage::select('job_assignment_messages.*')
            ->join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->leftJoin('users as senders', 'job_assignment_messages.sender_id', '=', 'senders.id')
            ->where('project_job_assignments.project_job_id', $projectJob->id);

        // If the current user is not privileged, restrict to assignments belonging to them
        if (! $isPrivileged && $user) {
            $base->where('project_job_assignments.user_id', $user->id);
        }

        // Apply search
        if ($q) {
            $base->where(function ($sub) use ($q) {
                $sub->where('job_assignment_messages.subject', 'like', "%{$q}%")->orWhere('job_assignment_messages.body', 'like', "%{$q}%");
            });
        }

        if ($usePeriodFilter && $periodStart && $periodEnd) {
            $base->whereBetween(
                DB::raw('COALESCE(project_job_assignments.desired_end_date, job_assignment_messages.created_at)'),
                [$periodStart, $periodEnd]
            );
        }

        // Sorting whitelist
        switch ($sort) {
            case 'desired_start_date':
                $base->orderBy('project_job_assignments.desired_start_date', $dir);
                break;
            case 'sender':
                $base->orderBy('senders.name', $dir);
                break;
            case 'subject':
                $base->orderBy('job_assignment_messages.subject', $dir);
                break;
            default:
                $base->orderBy('job_assignment_messages.created_at', 'desc');
        }

        // Ensure statusModel is loaded so we can attach a canonical `status` object
        $messages = $base->with([
            'sender',
            'projectJobAssignment.projectJob.client',
            'projectJobAssignment',
            'projectJobAssignment.statusModel',
            'message.recipients.user',
            'message.fromUser',
            'projectJobAssignment.user',
        ])
            ->paginate($usePeriodFilter ? 500 : 50)
            ->appends(array_filter(['q' => $q, 'period' => $periodModel, 'sort' => $sort, 'dir' => $dir]));
        // Attach canonical `status` object to each loaded assignment for frontend convenience
        try {
            $messages->getCollection()->transform(function ($msg) {
                try {
                    if (isset($msg->projectJobAssignment) && $msg->projectJobAssignment && isset($msg->projectJobAssignment->statusModel) && $msg->projectJobAssignment->statusModel) {
                        $sm = $msg->projectJobAssignment->statusModel;
                        $msg->projectJobAssignment->status = [
                            'id' => $sm->id,
                            'key' => $sm->key ?? $sm->slug ?? null,
                            'name' => $sm->name,
                        ];
                    }
                } catch (\Throwable $__e) {
                    // non-fatal
                }
                return $msg;
            });
        } catch (\Throwable $__e) {
            // non-fatal
        }

        // カレンダーイベントIDをメッセージに付加（行クリックで event 詳細へ遷移するため）
        try {
            $aidList = $messages->getCollection()
                ->map(fn ($m) => $m->project_job_assignment_id)
                ->filter()->unique()->values()->toArray();
            if (!empty($aidList)) {
                // 直接リンク
                $directEventMap = DB::table('events')
                    ->whereIn('project_job_assignment_id', $aidList)
                    ->orderBy('starts_at')
                    ->get(['id', 'project_job_assignment_id'])
                    ->keyBy('project_job_assignment_id');
                // supersede チェーン経由（ユーザーがマイジョブとして登録してカレンダー登録した場合）
                $supersedeEventMap = DB::table('events')
                    ->join('project_job_assignments as pja_self', 'events.project_job_assignment_id', '=', 'pja_self.id')
                    ->whereIn('pja_self.supersedes_assignment_id', $aidList)
                    ->orderBy('events.starts_at')
                    ->get(['events.id as eid', 'pja_self.supersedes_assignment_id as original_aid'])
                    ->keyBy('original_aid');
                $messages->getCollection()->transform(function ($msg) use ($directEventMap, $supersedeEventMap) {
                    $aid = $msg->project_job_assignment_id;
                    if ($aid) {
                        if (isset($directEventMap[$aid])) {
                            $msg->event_id = $directEventMap[$aid]->id;
                        } elseif (isset($supersedeEventMap[$aid])) {
                            $msg->event_id = $supersedeEventMap[$aid]->eid;
                        }
                    }
                    return $msg;
                });
            }
        } catch (\Throwable $__e) {
            // non-fatal
        }

        $monthBase = JobAssignmentMessage::join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->where('project_job_assignments.project_job_id', $projectJob->id);

        if (! $isPrivileged && $user) {
            $monthBase->where('project_job_assignments.user_id', $user->id);
        }

        $monthValues = $monthBase
            ->selectRaw("DATE_FORMAT(COALESCE(project_job_assignments.desired_end_date, job_assignment_messages.created_at), '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderBy('ym', 'desc')
            ->pluck('ym');

        $monthOptions = $monthValues
            ->filter()
            ->map(function ($ym) {
                try {
                    $label = Carbon::createFromFormat('Y-m', $ym)->format('Y年n月');
                } catch (\Throwable $__e) {
                    $label = $ym;
                }
                return ['value' => $ym, 'label' => $label];
            })
            ->values();

        return inertia('JobBox/Index', [
            'projectJob' => $projectJob,
            'messages' => $messages,
            'q' => $q,
            'period' => $periodModel,
            'monthOptions' => $monthOptions,
            'sort' => $sort,
            'dir' => $dir,
            'routeContext' => $routeContext,
        ]);
    }

    /**
     * Global jobbox for authenticated user: shows job messages across all assignments
     * Useful for the top-level /jobbox fallback route when no project context is provided.
     */
    public function global(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $q = $request->input('q');
        $periodParam = $request->query('period');
        $usePeriodFilter = true;
        $periodModel = $periodParam;
        if ($periodParam === null || $periodParam === '' || $periodParam === 'all') {
            // デフォルト: 全期間表示
            $usePeriodFilter = false;
            $periodModel = 'all';
        }

        $periodStart = null;
        $periodEnd = null;
        if ($usePeriodFilter) {
            try {
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            } catch (\Throwable $__e) {
                $periodModel = now()->format('Y-m');
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            }
        }
        $sort = $request->input('sort');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $base = JobAssignmentMessage::select('job_assignment_messages.*')
            ->join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->join('project_jobs', 'project_job_assignments.project_job_id', '=', 'project_jobs.id')
            ->leftJoin('users as senders', 'job_assignment_messages.sender_id', '=', 'senders.id')
            ->where(function ($qry) use ($user) {
                $qry->where('project_job_assignments.user_id', $user->id)
                    ->orWhere('job_assignment_messages.sender_id', $user->id)
                    ->orWhere('project_jobs.user_id', $user->id)
                    // 副リーダー（サブCo）: project_job_coordinators に登録された案件の全ジョブ履歴を表示
                    ->orWhereExists(function ($sub) use ($user) {
                        $sub->select(DB::raw(1))
                            ->from('project_job_coordinators')
                            ->whereColumn('project_job_coordinators.project_job_id', 'project_jobs.id')
                            ->where('project_job_coordinators.user_id', $user->id);
                    });
            })
            // マイジョブで supersedes 済みの依頼ジョブは非表示
            // ① supersedes_assignment_id で直接指定（新規フロー）
            // ② フォールバック: 同ユーザー・同案件・同タイトルの自己割当が別レコードとして存在する（既存データ対応）
            ->whereNotExists(function ($sub) {
                $sub->from('project_job_assignments as pja_self')
                    ->where(function ($q) {
                        $q->whereColumn('pja_self.supersedes_assignment_id', 'project_job_assignments.id')
                          ->orWhere(function ($q2) {
                              // 自己一致（pja_self が同一レコード）を防ぐため id が異なる条件を付ける
                              $q2->whereColumn('pja_self.title', 'project_job_assignments.title')
                                 ->whereColumn('pja_self.project_job_id', 'project_job_assignments.project_job_id')
                                 ->whereColumn('pja_self.id', '<>', 'project_job_assignments.id');
                          });
                    })
                    ->whereColumn('pja_self.user_id', 'project_job_assignments.user_id')
                    ->whereColumn('pja_self.sender_id', 'pja_self.user_id');
            });

        if ($q) {
            $base->where(function ($sub) use ($q) {
                $sub->where('job_assignment_messages.subject', 'like', "%{$q}%")->orWhere('job_assignment_messages.body', 'like', "%{$q}%");
            });
        }

        if ($usePeriodFilter && $periodStart && $periodEnd) {
            // 締め切り日（desired_end_date）または依頼日（created_at）が選択月に含まれる場合に表示
            $base->where(function ($sub) use ($periodStart, $periodEnd) {
                $sub->whereBetween('project_job_assignments.desired_end_date', [$periodStart, $periodEnd])
                    ->orWhereBetween('job_assignment_messages.created_at', [$periodStart, $periodEnd]);
            });
        }

        switch ($sort) {
            case 'desired_start_date':
                $base->orderBy('project_job_assignments.desired_start_date', $dir);
                break;
            case 'sender':
                $base->orderBy('senders.name', $dir);
                break;
            case 'subject':
                $base->orderBy('job_assignment_messages.subject', $dir);
                break;
            default:
                $base->orderBy('job_assignment_messages.created_at', 'desc');
        }

        // Ensure statusModel is loaded for each linked assignment
        $jamMessages = $base->with(['sender', 'message.recipients.user', 'message.fromUser', 'projectJobAssignment.projectJob.client', 'projectJobAssignment.statusModel', 'projectJobAssignment.user'])
            ->limit(500)
            ->get();

        // カレンダーイベントIDをメッセージに付加（行クリックで event 詳細へ遷移するため）
        try {
            $aidList = $jamMessages
                ->map(fn ($m) => $m->project_job_assignment_id)
                ->filter()->unique()->values()->toArray();

            \Illuminate\Support\Facades\Log::info('[JobBox.global] aidList', ['aids' => $aidList]);

            if (!empty($aidList)) {
                // ① 直接リンク: 依頼ジョブのアサインID → event
                $directEventMap = DB::table('events')
                    ->whereIn('project_job_assignment_id', $aidList)
                    ->orderBy('starts_at')
                    ->get(['id', 'project_job_assignment_id'])
                    ->keyBy('project_job_assignment_id');

                \Illuminate\Support\Facades\Log::info('[JobBox.global] directEventMap', $directEventMap->map(fn($e) => $e->id)->toArray());

                // ② supersedes_assignment_id 経由
                $supersedeEventMap = DB::table('events')
                    ->join('project_job_assignments as pja_self', 'events.project_job_assignment_id', '=', 'pja_self.id')
                    ->whereIn('pja_self.supersedes_assignment_id', $aidList)
                    ->orderBy('events.starts_at')
                    ->get(['events.id as eid', 'pja_self.supersedes_assignment_id as original_aid'])
                    ->keyBy('original_aid');

                \Illuminate\Support\Facades\Log::info('[JobBox.global] supersedeEventMap', $supersedeEventMap->map(fn($e) => $e->eid)->toArray());

                // ③ 同ユーザー・同案件・同タイトルの自己割当（マイジョブ）が持つイベント
                // supersedes_assignment_id が未設定でも同ユーザー・同案件・同タイトルなら紐付ける
                $sameUserProjectEventMap = DB::table('events')
                    ->join('project_job_assignments as pja_self', 'events.project_job_assignment_id', '=', 'pja_self.id')
                    ->whereColumn('pja_self.sender_id', 'pja_self.user_id') // 自己割当
                    ->join('project_job_assignments as pja_req', function ($join) use ($aidList) {
                        $join->on('pja_req.user_id', '=', 'pja_self.user_id')
                             ->on('pja_req.project_job_id', '=', 'pja_self.project_job_id')
                             ->on('pja_req.title', '=', 'pja_self.title') // タイトル一致
                             ->whereIn('pja_req.id', $aidList)
                             ->whereColumn('pja_req.sender_id', '<>', 'pja_req.user_id'); // 依頼ジョブ
                    })
                    ->orderBy('events.starts_at')
                    ->get(['events.id as eid', 'pja_req.id as original_aid'])
                    ->keyBy('original_aid');

                \Illuminate\Support\Facades\Log::info('[JobBox.global] sameUserProjectEventMap', $sameUserProjectEventMap->map(fn($e) => $e->eid)->toArray());

                $jamMessages->transform(function ($msg) use ($directEventMap, $supersedeEventMap, $sameUserProjectEventMap) {
                    $aid = $msg->project_job_assignment_id;
                    if ($aid) {
                        if (isset($directEventMap[$aid])) {
                            $msg->event_id = $directEventMap[$aid]->id;
                        } elseif (isset($supersedeEventMap[$aid])) {
                            $msg->event_id = $supersedeEventMap[$aid]->eid;
                        } elseif (isset($sameUserProjectEventMap[$aid])) {
                            $msg->event_id = $sameUserProjectEventMap[$aid]->eid;
                        }
                    }
                    return $msg;
                });
            }
        } catch (\Throwable $__e) {
            // non-fatal
        }

        // ── JAMなしジョブを追加 ──
        // Coordinator が管理する案件（オーナー or サブCo）の
        // job_assignment_messages に紐付かない ProjectJobAssignment を取得する
        $extraItems = [];
        try {
            $ownQ = \App\Models\ProjectJobAssignment::with([
                'projectJob.client',
                'user',
                'sender',
                'statusModel',
            ])
                ->where(function ($qry) use ($user) {
                    // 自分自身のジョブ
                    $qry->where('user_id', $user->id)
                        // 自分が送信者（Coordinator が依頼したがJAMなし）
                        ->orWhere('sender_id', $user->id)
                        // 自分がオーナーの案件の全ジョブ
                        ->orWhereExists(function ($sub) use ($user) {
                            $sub->from('project_jobs')
                                ->whereColumn('project_jobs.id', 'project_job_assignments.project_job_id')
                                ->where('project_jobs.user_id', $user->id);
                        })
                        // 自分がサブCoの案件の全ジョブ
                        ->orWhereExists(function ($sub) use ($user) {
                            $sub->from('project_job_coordinators')
                                ->whereColumn('project_job_coordinators.project_job_id', 'project_job_assignments.project_job_id')
                                ->where('project_job_coordinators.user_id', $user->id);
                        });
                })
                ->whereNotExists(function ($sub) {
                    $sub->from('job_assignment_messages')
                        ->whereColumn('job_assignment_messages.project_job_assignment_id', 'project_job_assignments.id');
                });

            if ($q) {
                $ownQ->where(function ($sub) use ($q) {
                    $sub->where('title', 'like', "%{$q}%")
                        ->orWhere('detail', 'like', "%{$q}%");
                });
            }
            if ($usePeriodFilter && $periodStart && $periodEnd) {
                $ownQ->where(function ($sub) use ($periodStart, $periodEnd) {
                    $sub->whereBetween('desired_end_date', [$periodStart, $periodEnd])
                        ->orWhereBetween('created_at', [$periodStart, $periodEnd]);
                });
            }

            $ownList = $ownQ->orderBy('created_at', 'desc')->limit(300)->get();

            \Illuminate\Support\Facades\Log::info('[JobBox.global] extraItems count', ['count' => $ownList->count(), 'ids' => $ownList->pluck('id')->toArray()]);

            // イベントIDを取得
            $ownAids = $ownList->pluck('id')->filter()->toArray();
            $ownEventMap = collect();
            if (!empty($ownAids)) {
                $ownEventMap = DB::table('events')
                    ->whereIn('project_job_assignment_id', $ownAids)
                    ->whereNotNull('starts_at')
                    ->orderBy('starts_at')
                    ->get(['id', 'project_job_assignment_id'])
                    ->keyBy('project_job_assignment_id');
            }

            // JAM 形式に変換してマージ用配列を作成
            $extraItems = $ownList->map(function ($a) use ($ownEventMap, $user) {
                return [
                    'id'                        => 'a_' . $a->id,
                    '__type'                    => 'assignment',
                    'project_job_assignment_id' => $a->id,
                    'subject'                   => $a->title,
                    'body'                      => $a->detail,
                    'created_at'                => $a->created_at ? $a->created_at->toISOString() : null,
                    'sender_id'                 => $a->sender_id,
                    'sender'                    => $a->sender
                        ? ['id' => $a->sender->id, 'name' => $a->sender->name]
                        : ($a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : ['id' => $user->id, 'name' => $user->name]),
                    'read_at'                   => $a->read_at ? $a->read_at->toISOString() : null,
                    'completed'                 => (bool) $a->completed,
                    'accepted'                  => (bool) $a->accepted,
                    'scheduled'                 => (bool) $a->scheduled,
                    'scheduled_at'              => $a->scheduled_at ? $a->scheduled_at->toISOString() : null,
                    'message_id'                => null,
                    'message'                   => null,
                    'project_job_assignment'    => $a->toArray(),
                    'event_id'                  => isset($ownEventMap[$a->id]) ? $ownEventMap[$a->id]->id : null,
                ];
            })->toArray();
        } catch (\Throwable $__e) {
            $extraItems = [];
        }

        // JAM records をプレーン配列に変換してマージ・ソート
        $jamArray = $jamMessages->map(fn ($m) => $m->toArray())->toArray();
        $merged = collect(array_merge($jamArray, $extraItems))
            ->sortByDesc(fn ($m) => $m['created_at'] ?? '')
            ->values()
            ->toArray();

        // all_events / total_minutes / source_assignment_id をエントリに付加
        try {
            $allAids = collect($merged)
                ->map(fn ($m) => (int) ($m['project_job_assignment_id'] ?? $m['project_job_assignment']['id'] ?? 0))
                ->filter()->unique()->values()->toArray();

            if (!empty($allAids)) {
                // source_assignment_id を一括取得
                $sourceMap = \App\Models\ProjectJobAssignment::whereIn('id', $allAids)
                    ->pluck('source_assignment_id', 'id');

                // 全イベントを groupBy で取得
                $allEventsGrouped = DB::table('events')
                    ->whereIn('project_job_assignment_id', $allAids)
                    ->whereNotNull('starts_at')
                    ->orderBy('starts_at')
                    ->get(['id', 'project_job_assignment_id', 'starts_at', 'ends_at', 'interruption_minutes'])
                    ->groupBy('project_job_assignment_id');

                $merged = array_map(function ($m) use ($sourceMap, $allEventsGrouped) {
                    $aid = (int) ($m['project_job_assignment_id'] ?? $m['project_job_assignment']['id'] ?? 0);
                    // source_assignment_id を project_job_assignment に埋め込む
                    if ($aid && isset($sourceMap[$aid])) {
                        if (isset($m['project_job_assignment']) && is_array($m['project_job_assignment'])) {
                            $m['project_job_assignment']['source_assignment_id'] = $sourceMap[$aid];
                        }
                    }
                    // all_events / total_minutes
                    if ($aid && $allEventsGrouped->has($aid)) {
                        $evs = $allEventsGrouped->get($aid);
                        $m['all_events'] = $evs->map(function ($ev) {
                            $s = $ev->starts_at ? \Carbon\Carbon::parse($ev->starts_at)->setTimezone('Asia/Tokyo') : null;
                            $e = $ev->ends_at   ? \Carbon\Carbon::parse($ev->ends_at)->setTimezone('Asia/Tokyo')   : null;
                            $totalMins = ($s && $e) ? max(0, (int) $s->diffInMinutes($e, false)) : 0;
                            $interrupt = (int) ($ev->interruption_minutes ?? 0);
                            return [
                                'id'      => $ev->id,
                                'date'    => $s ? $s->toDateString() : null,
                                'start'   => $s ? $s->format('H:i') : null,
                                'end'     => $e ? $e->format('H:i') : null,
                                'minutes' => max(0, $totalMins - $interrupt),
                            ];
                        })->values()->toArray();
                        $m['total_minutes'] = array_sum(array_column($m['all_events'], 'minutes'));
                    } else {
                        $m['all_events']    = [];
                        $m['total_minutes'] = 0;
                    }
                    return $m;
                }, $merged);
            }
        } catch (\Throwable $__e) {
            // non-fatal
        }

        $messages = ['data' => array_values($merged)];

        // 締め切り日からの月リスト
        $monthsFromEndDate = JobAssignmentMessage::join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->join('project_jobs', 'project_job_assignments.project_job_id', '=', 'project_jobs.id')
            ->where(function ($qry) use ($user) {
                $qry->where('project_job_assignments.user_id', $user->id)
                    ->orWhere('job_assignment_messages.sender_id', $user->id)
                    ->orWhere('project_jobs.user_id', $user->id)
                    ->orWhereExists(function ($sub) use ($user) {
                        $sub->select(DB::raw(1))
                            ->from('project_job_coordinators')
                            ->whereColumn('project_job_coordinators.project_job_id', 'project_jobs.id')
                            ->where('project_job_coordinators.user_id', $user->id);
                    });
            })
            ->whereNotNull('project_job_assignments.desired_end_date')
            ->selectRaw("DATE_FORMAT(project_job_assignments.desired_end_date, '%Y-%m') as ym")
            ->groupBy('ym')
            ->pluck('ym');

        // 依頼日（メッセージ作成日）からの月リスト
        $monthsFromCreated = JobAssignmentMessage::join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->join('project_jobs', 'project_job_assignments.project_job_id', '=', 'project_jobs.id')
            ->where(function ($qry) use ($user) {
                $qry->where('project_job_assignments.user_id', $user->id)
                    ->orWhere('job_assignment_messages.sender_id', $user->id)
                    ->orWhere('project_jobs.user_id', $user->id)
                    ->orWhereExists(function ($sub) use ($user) {
                        $sub->select(DB::raw(1))
                            ->from('project_job_coordinators')
                            ->whereColumn('project_job_coordinators.project_job_id', 'project_jobs.id')
                            ->where('project_job_coordinators.user_id', $user->id);
                    });
            })
            ->selectRaw("DATE_FORMAT(job_assignment_messages.created_at, '%Y-%m') as ym")
            ->groupBy('ym')
            ->pluck('ym');

        $monthValues = $monthsFromEndDate->merge($monthsFromCreated)->filter()->unique()->sort()->reverse()->values();

        // JAMなしジョブ（管理案件全体）の月もドロップダウンに加える
        try {
            $ownNoJamBase = \App\Models\ProjectJobAssignment::where(function ($qry) use ($user) {
                    $qry->where('user_id', $user->id)
                        ->orWhere('sender_id', $user->id)
                        ->orWhereExists(function ($sub) use ($user) {
                            $sub->from('project_jobs')
                                ->whereColumn('project_jobs.id', 'project_job_assignments.project_job_id')
                                ->where('project_jobs.user_id', $user->id);
                        })
                        ->orWhereExists(function ($sub) use ($user) {
                            $sub->from('project_job_coordinators')
                                ->whereColumn('project_job_coordinators.project_job_id', 'project_job_assignments.project_job_id')
                                ->where('project_job_coordinators.user_id', $user->id);
                        });
                })
                ->whereNotExists(function ($sub) {
                    $sub->from('job_assignment_messages')
                        ->whereColumn('job_assignment_messages.project_job_assignment_id', 'project_job_assignments.id');
                });
            $monthsFromOwnEnd = (clone $ownNoJamBase)
                ->whereNotNull('desired_end_date')
                ->selectRaw("DATE_FORMAT(desired_end_date, '%Y-%m') as ym")
                ->groupBy('ym')
                ->pluck('ym');
            $monthsFromOwnCreated = (clone $ownNoJamBase)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym")
                ->groupBy('ym')
                ->pluck('ym');
            $monthValues = $monthValues->merge($monthsFromOwnEnd)->merge($monthsFromOwnCreated)
                ->filter()->unique()->sort()->reverse()->values();
        } catch (\Throwable $__e) {
            // non-fatal
        }

        $monthOptions = $monthValues
            ->filter()
            ->map(function ($ym) {
                try {
                    $label = Carbon::createFromFormat('Y-m', $ym)->format('Y年n月');
                } catch (\Throwable $__e) {
                    $label = $ym;
                }
                return ['value' => $ym, 'label' => $label];
            })
            ->values();

        return inertia('Coordinator/JobBox/Index', [
            'projectJob' => null,
            'messages' => $messages,
            'q' => $q,
            'period' => $periodModel,
            'monthOptions' => $monthOptions,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    /**
     * User-scoped jobbox: show job messages where the assignment's user_id is the
     * authenticated user. This returns only messages for which the current user
     * is the assignee (project_job_assignments.user_id == auth id).
     */
    public function user(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $q = $request->input('q');
        $periodParam = $request->query('period');
        $usePeriodFilter = true;
        $periodModel = $periodParam;
        if ($periodParam === null || $periodParam === '' || $periodParam === 'all') {
            // デフォルト: 全期間表示
            $usePeriodFilter = false;
            $periodModel = 'all';
        }

        $periodStart = null;
        $periodEnd = null;
        if ($usePeriodFilter) {
            try {
                $periodStart = Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth();
                $periodEnd = Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth();
            } catch (\Throwable $__e) {
                $usePeriodFilter = false;
                $periodModel = 'all';
            }
        }
        $sort = $request->input('sort');
        $dir = strtolower($request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $base = JobAssignmentMessage::select('job_assignment_messages.*')
            ->join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->leftJoin('users as senders', 'job_assignment_messages.sender_id', '=', 'senders.id')
            ->where('project_job_assignments.user_id', $user->id)
            // 自己割当・自分の返信を除外: メッセージ送信者が自分自身のものは表示しない
            // (受信箱として機能させる：他者から送られたメッセージのみ表示)
            ->where('job_assignment_messages.sender_id', '!=', $user->id)
            // マイジョブで supersedes 済みの依頼ジョブは非表示
            ->whereNotExists(function ($sub) {
                $sub->from('project_job_assignments as pja_self')
                    ->whereColumn('pja_self.supersedes_assignment_id', 'project_job_assignments.id')
                    ->whereColumn('pja_self.user_id', 'project_job_assignments.user_id')
                    ->whereColumn('pja_self.sender_id', 'pja_self.user_id');
            });

        if ($q) {
            $base->where(function ($sub) use ($q) {
                $sub->where('job_assignment_messages.subject', 'like', "%{$q}%")->orWhere('job_assignment_messages.body', 'like', "%{$q}%");
            });
        }

        if ($usePeriodFilter && $periodStart && $periodEnd) {
            $base->whereBetween(
                DB::raw('COALESCE(project_job_assignments.desired_end_date, job_assignment_messages.created_at)'),
                [$periodStart, $periodEnd]
            );
        }

        switch ($sort) {
            case 'desired_start_date':
                $base->orderBy('project_job_assignments.desired_start_date', $dir);
                break;
            case 'sender':
                $base->orderBy('senders.name', $dir);
                break;
            case 'subject':
                $base->orderBy('job_assignment_messages.subject', $dir);
                break;
            default:
                $base->orderBy('job_assignment_messages.created_at', 'desc');
        }

        $messages = $base->with(['sender', 'message.recipients.user', 'message.fromUser', 'projectJobAssignment.projectJob.client', 'projectJobAssignment.statusModel', 'projectJobAssignment.user'])
            ->paginate($usePeriodFilter ? 500 : 50)
            ->appends(array_filter(['q' => $q, 'period' => $periodModel, 'sort' => $sort, 'dir' => $dir]));

        // Attach canonical `status` object to each loaded assignment so frontend getAssignmentStatus() works correctly
        try {
            $messages->getCollection()->transform(function ($msg) {
                try {
                    if (isset($msg->projectJobAssignment) && $msg->projectJobAssignment && isset($msg->projectJobAssignment->statusModel) && $msg->projectJobAssignment->statusModel) {
                        $sm = $msg->projectJobAssignment->statusModel;
                        $msg->projectJobAssignment->status = [
                            'id' => $sm->id,
                            'key' => $sm->key ?? $sm->slug ?? null,
                            'name' => $sm->name,
                        ];
                    }
                } catch (\Throwable $__e) {
                    // non-fatal
                }
                return $msg;
            });
        } catch (\Throwable $__e) {
            // non-fatal
        }

        $monthValues = JobAssignmentMessage::join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->where('project_job_assignments.user_id', $user->id)
            ->where('job_assignment_messages.sender_id', '!=', $user->id)
            ->selectRaw("DATE_FORMAT(COALESCE(project_job_assignments.desired_end_date, job_assignment_messages.created_at), '%Y-%m') as ym")
            ->groupBy('ym')
            ->orderBy('ym', 'desc')
            ->pluck('ym');

        $monthOptions = $monthValues
            ->filter()
            ->map(function ($ym) {
                try {
                    $label = Carbon::createFromFormat('Y-m', $ym)->format('Y年n月');
                } catch (\Throwable $__e) {
                    $label = $ym;
                }
                return ['value' => $ym, 'label' => $label];
            })
            ->values();

        return inertia('JobBox/Index', [
            'projectJob' => null,
            'messages' => $messages,
            'q' => $q,
            'period' => $periodModel,
            'monthOptions' => $monthOptions,
            'sort' => $sort,
            'dir' => $dir,
            'routeContext' => 'user',
        ]);
    }

    public function show(ProjectJob $projectJob, JobAssignmentMessage $message, Request $request)
    {
        // Authorization: allow privileged roles or assigned users only
        $user = $request->user();
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin() || $user->isClerk()));

        // routeContext: user ルート経由ならば 'user'、coordinator ルート経由ならば 'coordinator'
        $showRouteName = $request->route()?->getName();
        $showRouteContext = str_starts_with((string) $showRouteName, 'user.') ? 'user' : 'coordinator';

        if (! $isPrivileged) {
            $hasAssignment = \App\Models\ProjectJobAssignment::where('project_job_id', $projectJob->id)
                ->where('user_id', $user ? $user->id : 0)
                ->exists();
            if (! $hasAssignment) {
                abort(403, 'Access denied.');
            }
        }

        // mark read: only when the recipient (assigned user) views the message
        // Coordinator/Leader viewing should NOT set read_at (that would show 確認済み prematurely)
        $viewerIsRecipient = false;
        try {
            if ($user && $message->project_job_assignment_id) {
                $recipientAssignment = ProjectJobAssignment::find($message->project_job_assignment_id);
                $viewerIsRecipient = $recipientAssignment && (int)$recipientAssignment->user_id === (int)$user->id;
            }
        } catch (\Throwable $__e) {}

        if (! $message->read_at && $viewerIsRecipient) {
            $message->read_at = now();
            // reflect read_at on the related assignment as well
            try {
                if ($message->project_job_assignment_id) {
                    $assignment = ProjectJobAssignment::find($message->project_job_assignment_id);
                    if ($assignment) {
                        $assignment->read_at = $message->read_at;
                        $assignment->save();
                    }
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }
            $message->save();

            // If this job message is linked to a Message, mark MessageRecipient rows as read
            if ($message->message_id) {
                try {
                    $updated = \App\Models\MessageRecipient::where('message_id', $message->message_id)
                        ->where('user_id', \Illuminate\Support\Facades\Auth::id())
                        ->whereNull('read_at')
                        ->update(['read_at' => now()]);
                } catch (\Throwable $__e) {
                    // non-fatal
                }
            }

            // Broadcast job-specific read event so frontend can decrement job unread counter.
            // We broadcast the JobAssignmentMessage id (not Message id) so listeners can
            // treat this as a jobbox read. Payload uses key `message_id` for backward
            // compatibility with existing frontend listeners.
            try {
                // also notify the original sender so they receive the read update in real-time
                $senderId = $message->sender_id ?? null;
                $readAt = $message->read_at ? $message->read_at->toDateTimeString() : now()->toDateTimeString();
                $ev = new \App\Events\JobMessageRead($message->id, \Illuminate\Support\Facades\Auth::id(), $senderId ? [$senderId] : null);
                $ev->read_at = $readAt;
                event($ev);
            } catch (\Throwable $__e) {
                // non-fatal
            }
        }

        // load sender and the related assignment with its user and lookup relations
        // so the frontend Show view can access `message.project_job_assignment` and display
        // assignment details such as sizes, stages and work item types.
        $message->load([
            'sender',
            'projectJobAssignment.user',
            'projectJobAssignment.projectJob.client',
            'projectJobAssignment.size',
            'projectJobAssignment.stage',
            'projectJobAssignment.workItemType',
            'projectJobAssignment.statusModel',
        ]);

        // Add human-friendly label attributes on the loaded relation so the Inertia
        // page can read `assignment.size_label`, `assignment.stage_label`, etc.
        try {
            if ($message->projectJobAssignment) {
                $a = $message->projectJobAssignment;
                $a->type_label = isset($a->workItemType) && $a->workItemType ? ($a->workItemType->name ?? $a->workItemType->label ?? null) : null;
                $a->size_label = isset($a->size) && $a->size ? ($a->size->name ?? $a->size->label ?? null) : null;
                $a->stage_label = isset($a->stage) && $a->stage ? ($a->stage->name ?? $a->stage->label ?? null) : null;
                $a->status_label = isset($a->statusModel) && $a->statusModel ? ($a->statusModel->name ?? $a->statusModel->label ?? null) : null;
                // resolve difficulty_label for frontend display (prefer difficulty_id, fallback to legacy difficulty)
                try {
                    $a->difficulty_label = null;
                    if (isset($a->difficulty_id) && $a->difficulty_id) {
                        $d = \App\Models\Difficulty::find($a->difficulty_id);
                        if ($d) $a->difficulty_label = $d->name;
                    }
                    if (empty($a->difficulty_label) && !empty($a->difficulty)) {
                        $q = \App\Models\Difficulty::query();
                        if (Schema::hasColumn('difficulties', 'slug')) {
                            $q->where('slug', $a->difficulty)->orWhere('name', $a->difficulty);
                        } else {
                            $q->where('name', $a->difficulty);
                        }
                        $d2 = $q->first();
                        if ($d2) $a->difficulty_label = $d2->name;
                    }
                } catch (\Throwable $__e) {
                    // non-fatal
                }

                // 進行表セルと紐付いているか判定（progress_cells.assignment_id で逆引き）
                try {
                    $progressCell = \App\Models\ProgressCell::where('assignment_id', $a->id)->first();
                    $a->progress_cell_id = $progressCell ? $progressCell->id : null;
                    $a->progress_sheet_id = $progressCell ? $progressCell->progress_sheet_id : null;
                    $a->progress_row_id = $progressCell ? $progressCell->row_id : null;
                    $a->progress_col_key = $progressCell ? $progressCell->col_key : null;
                } catch (\Throwable $__e) {
                    $a->progress_cell_id = null;
                    $a->progress_sheet_id = null;
                    $a->progress_row_id = null;
                    $a->progress_col_key = null;
                }

                // ユーザーがすでにマイジョブとして登録済みかチェック（supersedes_assignment_id で紐づく自己割当の存在確認）
                try {
                    $a->is_registered = \App\Models\ProjectJobAssignment::where('supersedes_assignment_id', $a->id)
                        ->whereColumn('sender_id', 'user_id')
                        ->exists();
                } catch (\Throwable $__e) {
                    $a->is_registered = false;
                }
            }
        } catch (\Throwable $__e) {
            // non-fatal: if lookup relations or attributes are missing, continue without labels
        }

        // build difficulties list with either (id,name,slug) when slug exists, or (id,name) otherwise
        $difficultySelect = ['id', 'name'];
        try {
            if (Schema::hasColumn('difficulties', 'slug')) $difficultySelect[] = 'slug';
        } catch (\Throwable $__e) {
            // if schema introspection fails for any reason, default to id,name
        }
        try {
            $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get($difficultySelect);
        } catch (\Throwable $__e) {
            $difficulties = collect();
        }

        // 削除権限の判定
        $canDelete = false;
        if ($user) {
            $isSender        = $message->sender_id === $user->id;
            $isAdminOrAbove  = $user->isSuperAdmin() || $user->isAdmin();
            $isLeader        = $user->isLeader();
            $isProjectOwner  = $projectJob->user_id === $user->id;
            $isSubCo         = $projectJob->coordinators()->where('users.id', $user->id)->exists();
            $canDelete = $isSender || $isAdminOrAbove || $isLeader || $isProjectOwner || $isSubCo;
        }

        return inertia('JobBox/Show', [
            'projectJob' => $projectJob,
            'message' => $message,
            'difficulties' => $difficulties,
            'canDelete' => $canDelete,
            'routeContext' => $showRouteContext,
        ]);
    }

    /**
     * Lightweight JSON endpoint for fetching a single JobAssignmentMessage by id.
     * Used by the frontend when an event contains only the jam id.
     */
    public function apiShow(Request $request, $id)
    {
        $user = $request->user();
        $jam = JobAssignmentMessage::with([
            'sender',
            'projectJobAssignment.user',
            'projectJobAssignment.size',
            'projectJobAssignment.stage',
            'projectJobAssignment.workItemType',
            'projectJobAssignment.statusModel',
            'message.fromUser',
            'message.recipients.user',
        ])->findOrFail($id);

        // Authorization: allow if user is privileged, owner of the project, sender, or assigned
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin() || $user->isClerk()));
        $isOwner = false;
        try {
            $pj = $jam->projectJobAssignment ? $jam->projectJobAssignment->projectJob : null;
            if ($pj && $pj->user_id && $user && $user->id === $pj->user_id) $isOwner = true;
        } catch (\Throwable $__e) {
            // ignore
        }

        $isSender = $user && $jam->sender_id && $user->id === $jam->sender_id;
        $isAssignee = $user && $jam->project_job_assignment && $jam->project_job_assignment->user_id && $user->id === $jam->project_job_assignment->user_id;

        if (! $isPrivileged && ! $isOwner && ! $isSender && ! $isAssignee) {
            abort(403, 'Access denied.');
        }

        // map to a JSON-friendly shape
        $mapped = [
            'id' => $jam->id,
            'subject' => $jam->subject,
            'body' => $jam->body,
            'sender' => $jam->sender ? ['id' => $jam->sender->id, 'name' => $jam->sender->name] : null,
            'project_job_assignment' => $jam->project_job_assignment ? [
                'project_job' => $jam->project_job_assignment->projectJob ? [
                    'id' => $jam->project_job_assignment->projectJob->id,
                    'title' => $jam->project_job_assignment->projectJob->title ?? null,
                    'client' => $jam->project_job_assignment->projectJob->client ? ['id' => $jam->project_job_assignment->projectJob->client->id, 'name' => $jam->project_job_assignment->projectJob->client->name] : null,
                ] : null,
                'id' => $jam->project_job_assignment->id,
                'user' => $jam->project_job_assignment->user ? ['id' => $jam->project_job_assignment->user->id, 'name' => $jam->project_job_assignment->user->name] : null,
                'title' => $jam->project_job_assignment->title ?? null,
                'detail' => $jam->project_job_assignment->detail ?? null,
                'difficulty' => $jam->project_job_assignment->difficulty ?? null,
                'difficulty_id' => $jam->project_job_assignment->difficulty_id ?? null,
                'difficulty_label' => null,
                'desired_start_date' => $jam->project_job_assignment->desired_start_date ?? null,
                'desired_end_date' => $jam->project_job_assignment->desired_end_date ?? null,
                'desired_time' => $jam->project_job_assignment->desired_time ?? null,
                'estimated_hours' => $jam->project_job_assignment->estimated_hours ?? null,
                'assigned' => (bool) ($jam->project_job_assignment->assigned ?? false),
                'scheduled' => (bool) ($jam->project_job_assignment->scheduled ?? false),
                'scheduled_at' => $jam->project_job_assignment->scheduled_at ?? null,
                'completed' => (bool) ($jam->project_job_assignment->completed ?? false),
                'accepted' => (bool) ($jam->project_job_assignment->accepted ?? false),
                'read_at' => $jam->project_job_assignment->read_at ? $jam->project_job_assignment->read_at->toDateTimeString() : null,
                'size_label' => $jam->project_job_assignment->size ? ($jam->project_job_assignment->size->name ?? $jam->project_job_assignment->size->label ?? null) : null,
                'stage_label' => $jam->project_job_assignment->stage ? ($jam->project_job_assignment->stage->name ?? $jam->project_job_assignment->stage->label ?? null) : null,
                'type_label' => $jam->project_job_assignment->workItemType ? ($jam->project_job_assignment->workItemType->name ?? $jam->project_job_assignment->workItemType->label ?? null) : null,
                'status_label' => $jam->project_job_assignment->statusModel ? ($jam->project_job_assignment->statusModel->name ?? $jam->project_job_assignment->statusModel->label ?? null) : null,
                'difficulty_label' => (function () use ($jam) {
                    try {
                        $a = $jam->project_job_assignment;
                        if (!$a) return null;
                        if (isset($a->difficulty_id) && $a->difficulty_id) {
                            $d = \App\Models\Difficulty::find($a->difficulty_id);
                            if ($d) return $d->name;
                        }
                        if (!empty($a->difficulty)) {
                            $q = \App\Models\Difficulty::query();
                            if (Schema::hasColumn('difficulties', 'slug')) {
                                $q->where('slug', $a->difficulty)->orWhere('name', $a->difficulty);
                            } else {
                                $q->where('name', $a->difficulty);
                            }
                            $d2 = $q->first();
                            if ($d2) return $d2->name;
                            return $a->difficulty;
                        }
                    } catch (\Throwable $__e) {
                        return null;
                    }
                    return null;
                })(),
            ] : null,
            'message' => $jam->message ? [
                'id' => $jam->message->id,
                'subject' => $jam->message->subject,
                'fromUser' => $jam->message->fromUser ? ['id' => $jam->message->fromUser->id, 'name' => $jam->message->fromUser->name] : null,
            ] : null,
            'read_at' => $jam->read_at ? $jam->read_at->toDateTimeString() : null,
        ];

        return response()->json(['data' => $mapped]);
    }

    /**
     * SPA-friendly endpoint to mark a JobAssignmentMessage as read.
     * This is used by the frontend Show view when opened via Inertia or when
     * the broadcast supplied only an id and the frontend fetched the jam.
     */
    public function apiMarkRead(Request $request, $id)
    {
        $user = $request->user();
        $jam = JobAssignmentMessage::findOrFail($id);

        // 既読マークは認証済みユーザーであれば許可（表示権限は呼び出し元ページで確認済み）
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // 確認済み（read_at）は受信者（担当ユーザー）が開いた時のみセット
        // コーディネーター等が開いても read_at は変えない
        $isRecipient = false;
        try {
            if ($jam->project_job_assignment_id) {
                $checkAssignment = ProjectJobAssignment::find($jam->project_job_assignment_id);
                $isRecipient = $checkAssignment && (int)$checkAssignment->user_id === (int)$user->id;
            }
        } catch (\Throwable $__e) {}

        if (! $jam->read_at && $isRecipient) {
            $jam->read_at = now();
            // reflect read on related assignment as well
            try {
                if ($jam->project_job_assignment_id) {
                    $assignment = ProjectJobAssignment::find($jam->project_job_assignment_id);
                    if ($assignment) {
                        $assignment->read_at = $jam->read_at;
                        $assignment->save();
                    }
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }
            $jam->save();

            // If linked to a Message, update corresponding recipient
            if ($jam->message_id) {
                try {
                    \App\Models\MessageRecipient::where('message_id', $jam->message_id)
                        ->where('user_id', $user ? $user->id : 0)
                        ->whereNull('read_at')
                        ->update(['read_at' => now()]);
                } catch (\Throwable $__e) {
                    // non-fatal
                }
            }

            try {
                $senderId = $jam->sender_id ?? null;
                $readAt = $jam->read_at ? $jam->read_at->toDateTimeString() : now()->toDateTimeString();
                $ev = new \App\Events\JobMessageRead($jam->id, $user ? $user->id : null, $senderId ? [$senderId] : null);
                $ev->read_at = $readAt;
                event($ev);
            } catch (\Throwable $__e) {
                // non-fatal
            }
        }

        return response()->json(['data' => ['id' => $jam->id, 'read_at' => $jam->read_at ? $jam->read_at->toDateTimeString() : null]]);
    }

    /**
     * Delete a JobAssignmentMessage
     */
    public function edit(ProjectJob $projectJob, JobAssignmentMessage $message)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin() || $user->isClerk()));
        if (! $isPrivileged) {
            abort(403, 'Access denied.');
        }
        $isSenderOrLeader = ($message->sender_id && $user->id === $message->sender_id)
            || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin();
        if (! $isSenderOrLeader) {
            abort(403, '作成者またはリーダー以外は編集できません。');
        }

        $message->load([
            'projectJobAssignment.user',
            'projectJobAssignment.projectJob.client',
        ]);

        return inertia('JobBox/Edit', [
            'projectJob' => $projectJob,
            'message'    => $message,
        ]);
    }

    public function update(ProjectJob $projectJob, JobAssignmentMessage $message, \Illuminate\Http\Request $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin() || $user->isClerk()));
        if (! $isPrivileged) {
            abort(403, 'Access denied.');
        }
        $isSenderOrLeader = ($message->sender_id && $user->id === $message->sender_id)
            || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin();
        if (! $isSenderOrLeader) {
            abort(403, '作成者またはリーダー以外は編集できません。');
        }

        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'body'    => 'nullable|string',
        ]);

        $message->update($data);

        return redirect()->route('coordinator.project_jobs.jobbox.show', [
            'projectJob' => $projectJob->id,
            'message'    => $message->id,
        ])->with('success', 'メッセージを更新しました。');
    }

    public function destroy(Request $request, ProjectJob $projectJob, JobAssignmentMessage $message)
    {
        $user = $request->user();

        $isSender        = $message->sender_id === $user->id;
        $isAdminOrAbove  = $user->isSuperAdmin() || $user->isAdmin();
        $isLeader        = $user->isLeader();
        $isProjectOwner  = $projectJob->user_id === $user->id;
        $isSubCo         = $projectJob->coordinators()->where('users.id', $user->id)->exists();

        if (! ($isSender || $isAdminOrAbove || $isLeader || $isProjectOwner || $isSubCo)) {
            abort(403, '削除する権限がありません。');
        }

        // If this job message has an associated Message record, delete it and recipients
        if ($message->message_id) {
            $msg = \App\Models\Message::find($message->message_id);
            if ($msg) {
                // delete recipients first
                \App\Models\MessageRecipient::where('message_id', $msg->id)->delete();
                $msg->delete();
            }
        }

        $message->delete();

        return redirect()->route('coordinator.project_jobs.jobbox.index', [
            'projectJob' => $projectJob->id,
        ])->with('success', 'メッセージを削除しました。');
    }

    public function store(ProjectJob $projectJob, Request $request)
    {
        $data = $request->validate([
            'project_job_assignment_id' => 'required|integer|exists:project_job_assignments,id',
            'to' => 'required|array',
            'to.*' => 'integer|exists:users,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        // Wrap creation in transaction: create JobAssignmentMessage, Message, recipients
        DB::beginTransaction();
        try {
            // Create JobAssignmentMessage record first
            $jam = JobAssignmentMessage::create([
                'project_job_assignment_id' => $data['project_job_assignment_id'],
                'sender_id' => $request->user()->id,
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'] ?? null,
            ]);

            // For job assignment requests, do NOT create a regular Message that lands in users' inboxes.
            // Job assignment notifications live in JobBox only. We still broadcast a job-specific event
            // so frontends subscribed to jobmessages.* receive the notification.
            $sanitizer = app(\App\Services\HtmlSanitizer::class);
            $body = $sanitizer->purify($data['body'] ?? null);

            // Do not create \App\Models\Message here. Keep job_assignment_messages self-contained.
            // Persist attachments in the job_assignment_messages.attachments JSON if provided
            if (!empty($data['attachments'])) {
                $jam->attachments = $data['attachments'];
                $jam->save();
            }

            // mark the related assignment as assigned so UI/status reflects sent state
            try {
                $assignment = ProjectJobAssignment::find($jam->project_job_assignment_id);
                if ($assignment && (int)$assignment->project_job_id === (int)$projectJob->id) {
                    $assignment->assigned = true;
                    $assignment->save();
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }

            // Broadcast event for job-specific real-time notifications. We construct a lightweight
            // pseudo-message object so the event payload contains subject/from_user_name while
            // avoiding creating a Message row that would appear in users' message inboxes.
            try {
                // Reload the created JAM with useful relations so the event can include a full jam payload
                try {
                    $jamLoaded = JobAssignmentMessage::with(['sender', 'projectJobAssignment.user', 'projectJobAssignment.projectJob.client', 'message.fromUser', 'message.recipients.user'])->find($jam->id);
                    $recipientIds = is_array($data['to']) ? array_values(array_unique($data['to'])) : [];
                    // pass the loaded jam model to the event so broadcastWith() can serialize relations
                    event(new \App\Events\JobMessageCreated($jamLoaded, $recipientIds, $jam->id));
                } catch (\Throwable $__e) {
                    // fallback: attempt a simpler reload of the JAM with client relation before sending a minimal payload
                    $recipientIds = is_array($data['to']) ? array_values(array_unique($data['to'])) : [];
                    try {
                        $jamLoadedFallback = JobAssignmentMessage::with(['sender', 'projectJobAssignment.user', 'projectJobAssignment.projectJob.client', 'message.fromUser', 'message.recipients.user'])->find($jam->id);
                        if ($jamLoadedFallback) {
                            event(new \App\Events\JobMessageCreated($jamLoadedFallback, $recipientIds, $jam->id));
                        } else {
                            event(new \App\Events\JobMessageCreated((object) ['id' => $jam->id, 'subject' => $data['subject'] ?? null, 'body' => $body, 'fromUser' => (object) ['name' => $request->user()->name ?? null]], $recipientIds, $jam->id));
                        }
                    } catch (\Throwable $__inner2) {
                        event(new \App\Events\JobMessageCreated((object) ['id' => $jam->id, 'subject' => $data['subject'] ?? null, 'body' => $body, 'fromUser' => (object) ['name' => $request->user()->name ?? null]], $recipientIds, $jam->id));
                    }
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }

            DB::commit();

            // route 'project_jobs.jobbox.index' is not defined in this app; redirect to assignments index
            return redirect()->route('coordinator.project_jobs.assignments.index', ['projectJob' => $projectJob->id])->with('success', 'JobBox: メッセージを送信しました');
        } catch (\Throwable $e) {
            DB::rollBack();
            // log and surface a friendly error
            report($e);
            return redirect()->back()->with('error', 'メッセージ送信でエラーが発生しました。詳細はログを確認してください。');
        }
    }

    /**
     * Reply / completion report from assigned user back to coordinator(s)
     * This endpoint is available to authenticated users who have an assignment
     * for the given project job. It will create a Message and associate it
     * with a JobAssignmentMessage for traceability, then broadcast a job
     * specific event so coordinators receive the notification.
     */
    /**
     * Mark a ProjectJobAssignment as completed (status = completed, completed = true).
     * Accessible by the assignee or privileged roles.
     */
    public function completeAssignment(Request $request, ProjectJobAssignment $assignment)
    {
        $user = $request->user();
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isLeader() || $user->isAdmin() || $user->isSuperAdmin() || $user->isClerk()));

        if (!$isPrivileged && (!$user || $assignment->user_id !== $user->id)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            $assignment->completed = true;
        } catch (\Throwable $__e) {}

        // Set status to 'completed'
        try {
            if (Schema::hasTable('statuses') && Schema::hasColumn('project_job_assignments', 'status_id')) {
                $status = DB::table('statuses')->where('key', 'completed')->orWhere('slug', 'completed')->first();
                if (!$status) {
                    $statusId = DB::table('statuses')->insertGetId(['key' => 'completed', 'slug' => 'completed', 'name' => '完了', 'created_at' => now(), 'updated_at' => now()]);
                } else {
                    $statusId = $status->id;
                }
                $assignment->status_id = $statusId;
            }
        } catch (\Throwable $__e) {}

        $assignment->save();

        // workerセルの completed_at を記録
        try {
            \App\Models\ProgressCell::where('assignment_id', $assignment->id)
                ->whereNull('completed_at')
                ->update(['completed_at' => now()]);
        } catch (\Throwable $__e) {
            // non-fatal
        }

        // ジョブ通知
        try {
            $pj = $assignment->projectJob
                ?? \App\Models\ProjectJob::find($assignment->project_job_id);
            if ($pj) {
                $hasProgressLink = \App\Models\ProgressCell::where('assignment_id', $assignment->id)->exists();
                if (!$hasProgressLink && !empty($assignment->source_assignment_id)) {
                    $cur = $assignment;
                    for ($__i = 0; $__i < 20 && !$hasProgressLink; $__i++) {
                        if (empty($cur->source_assignment_id)) break;
                        $parent = \App\Models\ProjectJobAssignment::find($cur->source_assignment_id);
                        if (!$parent) break;
                        $hasProgressLink = \App\Models\ProgressCell::where('assignment_id', $parent->id)->exists();
                        $cur = $parent;
                    }
                }
                if ($hasProgressLink) {
                    \App\Services\JobNotificationService::notifyProgressCompleted($user, $pj, $assignment);
                } else {
                    \App\Services\JobNotificationService::notifyCompleted($user, $assignment, $pj);
                }
            }
        } catch (\Throwable $__eNotify) {
            \Illuminate\Support\Facades\Log::warning('JobNotification dispatch error in JobBoxController::completeAssignment', ['error' => $__eNotify->getMessage()]);
        }

        return response()->json(['success' => true, 'assignment_id' => $assignment->id]);
    }

    public function reply(ProjectJob $projectJob, Request $request)
    {
        $data = $request->validate([
            'project_job_assignment_id' => 'required|integer|exists:project_job_assignments,id',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
        ]);

        $user = $request->user();

        // Ensure the user is assigned to this project job
        $assignment = ProjectJobAssignment::where('id', $data['project_job_assignment_id'])
            ->where('project_job_id', $projectJob->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $assignment) {
            abort(403, 'このジョブに対する割り当てがありません。');
        }

        DB::beginTransaction();
        try {
            // Create a JobAssignmentMessage to represent this reply (sender is the assigned user)
            $jam = JobAssignmentMessage::create([
                'project_job_assignment_id' => $assignment->id,
                'sender_id' => $user->id,
                'subject' => $data['subject'] ?? null,
                'body' => $data['body'] ?? null,
            ]);

            // Sanitize body
            $sanitizer = app(\App\Services\HtmlSanitizer::class);
            $body = $sanitizer->purify($data['body'] ?? null);

            // Create a Message addressed to coordinator(s). Determine recipients: project owner and/or assignment creator
            // Simple strategy: include the project job owner (projectJob.user_id) and any users who have 'coordinator' role within team
            $message = \App\Models\Message::create([
                'from_user_id' => $user->id,
                'subject' => $data['subject'] ?? null,
                'body' => $body,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            $recipientIds = [];
            if ($projectJob->user_id) $recipientIds[] = $projectJob->user_id;

            // Also include assignment creator if different
            // (If the assignment was created by a coordinator and stored somewhere; fallback to project owner)
            $creatorId = $assignment->created_by ?? null;
            if ($creatorId && ! in_array($creatorId, $recipientIds)) {
                $recipientIds[] = $creatorId;
            }

            // Deduplicate and create recipients
            $recipientIds = array_values(array_unique(array_filter($recipientIds)));
            foreach ($recipientIds as $uid) {
                \App\Models\MessageRecipient::create([
                    'message_id' => $message->id,
                    'user_id' => $uid,
                    'type' => 'to',
                ]);
            }

            // link jam -> message
            $jam->message_id = $message->id;
            $jam->save();

            // (accepted flag is set only when user calendars/sets the job, not on reply)

            // Broadcast job-specific event to coordinators
            try {
                // When replying we have both a Message and a linked JAM; load the JAM with relations
                // so the event payload contains consistent jam/project_job_assignment/recipients info.
                try {
                    $jamLoaded = JobAssignmentMessage::with(['sender', 'projectJobAssignment.user', 'projectJobAssignment.projectJob.client', 'message.fromUser', 'message.recipients.user'])->find($jam->id);
                    event(new \App\Events\JobMessageCreated($jamLoaded, $recipientIds, $jam->id));
                } catch (\Throwable $__inner) {
                    // fallback: try to load the JAM with client relation; if it exists prefer it, otherwise send the Message object
                    try {
                        $jamLoadedFallback = JobAssignmentMessage::with(['sender', 'projectJobAssignment.user', 'projectJobAssignment.projectJob.client', 'message.fromUser', 'message.recipients.user'])->find($jam->id);
                        if ($jamLoadedFallback) {
                            event(new \App\Events\JobMessageCreated($jamLoadedFallback, $recipientIds, $jam->id));
                        } else {
                            event(new \App\Events\JobMessageCreated($message, $recipientIds, $jam->id));
                        }
                    } catch (\Throwable $__inner2) {
                        event(new \App\Events\JobMessageCreated($message, $recipientIds, $jam->id));
                    }
                }
            } catch (\Throwable $__e) {
                // non-fatal
            }

            DB::commit();

            return redirect()->route('user.project_jobs.jobbox.show', ['projectJob' => $projectJob->id, 'message' => $jam->id])->with('success', '完了報告を送信しました。');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('error', '完了報告の送信中にエラーが発生しました。');
        }
    }

    /**
     * Show a schedule-only form for a coordinator-assigned job.
     * Job content is read-only; only date/time can be set.
     */
    public function schedule(Request $request, ProjectJobAssignment $assignment)
    {
        $user = $request->user();

        // Authorize: only the assignee or privileged users may access
        $isAssignee = $user && $assignment->user_id === $user->id;
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isAdmin() || $user->isSuperAdmin()));
        if (! $isAssignee && ! $isPrivileged) {
            abort(403);
        }

        $assignment->load(['projectJob.client', 'user', 'size', 'stage', 'workItemType', 'statusModel', 'difficultyModel']);

        // Find existing event linked to this assignment for the current user
        $existingEvent = null;
        if (Schema::hasColumn('events', 'project_job_assignment_id')) {
            $existingEvent = Event::where('project_job_assignment_id', $assignment->id)
                ->where('user_id', $user->id)
                ->latest()
                ->first();
        }

        return Inertia::render('JobBox/Schedule', [
            'assignment' => $assignment,
            'projectJob' => $assignment->projectJob,
            'existingEvent' => $existingEvent,
        ]);
    }

    /**
     * Store or update the schedule (event) for a coordinator-assigned job.
     */
    public function storeSchedule(Request $request, ProjectJobAssignment $assignment)
    {
        $user = $request->user();

        $isAssignee = $user && $assignment->user_id === $user->id;
        $isPrivileged = $user && (method_exists($user, 'isCoordinator') && ($user->isCoordinator() || $user->isAdmin() || $user->isSuperAdmin()));
        if (! $isAssignee && ! $isPrivileged) {
            abort(403);
        }

        $data = $request->validate([
            'date'        => 'required|date',
            'startHour'   => 'required|integer|min:0|max:23',
            'startMinute' => 'required|integer|min:0|max:59',
            'endHour'     => 'required|integer|min:0|max:23',
            'endMinute'   => 'required|integer|min:0|max:59',
            'event_id'    => 'nullable|integer|exists:events,id',
        ]);

        $start = $data['date'] . ' ' . str_pad($data['startHour'], 2, '0', STR_PAD_LEFT) . ':' . str_pad($data['startMinute'], 2, '0', STR_PAD_LEFT) . ':00';
        $end   = $data['date'] . ' ' . str_pad($data['endHour'], 2, '0', STR_PAD_LEFT) . ':' . str_pad($data['endMinute'], 2, '0', STR_PAD_LEFT) . ':00';

        $title = $assignment->title ?: ($assignment->projectJob->title ?? 'ジョブ作業');
        $description = $assignment->detail ?: $title;

        DB::beginTransaction();
        try {
            if ($data['event_id']) {
                // Update existing event
                $event = Event::find($data['event_id']);
                if ($event) {
                    $event->start = $start;
                    $event->end   = $end;
                    if (Schema::hasColumn('events', 'date')) {
                        $event->date = $data['date'];
                    }
                    $event->save();
                }
            } else {
                // Create new event
                $event = new Event();
                $event->user_id     = $user->id;
                $event->title       = $title;
                $event->description = $description;
                $event->start       = $start;
                $event->end         = $end;
                if (Schema::hasColumn('events', 'date')) {
                    $event->date = $data['date'];
                }
                if (Schema::hasColumn('events', 'project_job_assignment_id')) {
                    $event->project_job_assignment_id = $assignment->id;
                }
                $event->save();

                // ── 重複イベントの interruption_minutes 処理 ──────────────────
                try {
                    $evNewStart = \Carbon\Carbon::parse($start);
                    $evNewEnd   = \Carbon\Carbon::parse($end);
                    $newDurationMins = abs((int)$evNewEnd->diffInMinutes($evNewStart));

                    $overlappingEvents = Event::where('user_id', $user->id)
                        ->where('id', '!=', $event->id)
                        ->where('starts_at', '<', $evNewEnd->toDateTimeString())
                        ->where('ends_at', '>', $evNewStart->toDateTimeString())
                        ->get();

                    foreach ($overlappingEvents as $existingEv) {
                        $evStart = \Carbon\Carbon::parse($existingEv->starts_at);
                        $evEnd   = \Carbon\Carbon::parse($existingEv->ends_at);
                        $existingDurationMins = abs((int)$evEnd->diffInMinutes($evStart));

                        $overlapStart = $evNewStart->gt($evStart) ? $evNewStart : $evStart;
                        $overlapEnd   = $evNewEnd->lt($evEnd)    ? $evNewEnd   : $evEnd;
                        $overlapMins  = max(0, (int)$overlapStart->diffInMinutes($overlapEnd, false));

                        if ($overlapMins <= 0) continue;

                        if ($newDurationMins >= $existingDurationMins) {
                            $event->increment('interruption_minutes', $overlapMins);
                        } else {
                            $existingEv->increment('interruption_minutes', $overlapMins);
                        }
                    }
                } catch (\Throwable $__overlapE) {
                    \Illuminate\Support\Facades\Log::warning('JobBoxController: failed to process event overlap', ['error' => $__overlapE->getMessage()]);
                }
                // ──────────────────────────────────────────────────────────────

                // Mark assignment as scheduled (セット: accepted=true, scheduled=true)
                if (Schema::hasColumn('project_job_assignments', 'accepted')) {
                    $assignment->accepted = true;
                }
                if (Schema::hasColumn('project_job_assignments', 'scheduled')) {
                    $assignment->scheduled = true;
                }
                if (Schema::hasColumn('project_job_assignments', 'scheduled_at')) {
                    $assignment->scheduled_at = $event->start;
                }
                // Update status to scheduled if statuses table has a matching record
                try {
                    $status = DB::table('statuses')->where('key', 'scheduled')->first();
                    if ($status && Schema::hasColumn('project_job_assignments', 'status_id')) {
                        $assignment->status_id = $status->id;
                    }
                } catch (\Throwable $__e) {}
                $assignment->save();

                // Update JobAssignmentMessage scheduled flags
                try {
                    JobAssignmentMessage::where('project_job_assignment_id', $assignment->id)
                        ->where(function ($q) {
                            $q->whereNull('scheduled')->orWhere('scheduled', false);
                        })
                        ->update(['scheduled' => true, 'scheduled_at' => $event->start]);
                } catch (\Throwable $__e) {}
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return redirect()->back()->with('error', 'スケジュールの保存中にエラーが発生しました。');
        }

        // Redirect back to jobbox show
        $message = JobAssignmentMessage::where('project_job_assignment_id', $assignment->id)
            ->latest()->first();
        if ($message && $assignment->projectJob) {
            return redirect()
                ->route('user.project_jobs.jobbox.show', ['projectJob' => $assignment->project_job_id, 'message' => $message->id])
                ->with('success', 'スケジュールを保存しました。');
        }
        return redirect()->route('user.jobbox.index')->with('success', 'スケジュールを保存しました。');
    }
}
