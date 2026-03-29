<?php

namespace App\Http\Controllers\Leader;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksAdminPermission;
use App\Http\Controllers\Concerns\ChecksLeaderPermission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Company;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\ProjectJobAssignment;
use App\Models\ProjectJobAssignmentByMyself;
use App\Models\Stage;
use App\Models\Size;
use App\Models\WorkItemType;
use App\Models\Difficulty;
use App\Models\EventItemType;
use App\Models\WorktimeItemType;
use App\Models\WorkRecord;
use Illuminate\Support\Facades\DB;

class WorkloadAnalyzerController extends Controller
{
    use ChecksAdminPermission, ChecksLeaderPermission;

    public function index(Request $request)
    {
        $this->requireAdminPermission('workload_analysis');
        $this->requireLeaderPermission('workload_analysis');
        // month selection: expect query param 'ym' as YYYY-MM or use current month
        $ym = $request->query('ym');
        if (!$ym) {
            $ym = now()->format('Y-m');
        }
        [$year, $month] = explode('-', $ym);
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth();
        // If this is current month, limit to today
        if ($start->format('Y-m') === now()->format('Y-m')) {
            $end = now()->endOfDay();
        }

        // load worktime coefficients (通常残業 / 超過残業)
        $worktimeCoefficients = WorktimeItemType::all()->keyBy('id');
        $normalOvertimeCoeff = 1.0;
        $excessOvertimeCoeff = 1.0;
        foreach ($worktimeCoefficients as $wt) {
            if ($wt->type === 'over') {
                if ($wt->name === '超過残業') {
                    $excessOvertimeCoeff = (float) $wt->coefficient;
                } elseif ($wt->name === '残業') {
                    $normalOvertimeCoeff = (float) $wt->coefficient;
                }
            }
        }

        // helper to calculate aggregates for a given user id and model class
        // Extended to compute per-category points (stage/size/type/difficulty) and overall points
        $calcAggregates = function ($userId) use ($start, $end, $normalOvertimeCoeff, $excessOvertimeCoeff) {
            $result = [
                'assigned' => [
                    'pages' => 0,
                    'work_hours' => 0.0,
                    'desired_hours' => 0.0,
                    'total_items' => 0,
                ],
                'self' => [
                    'pages' => 0,
                    'work_hours' => 0.0,
                    'desired_hours' => 0.0,
                    'total_items' => 0,
                ],
            ];

            // Assigned tasks (project_job_assignments)
            $assignedQ = ProjectJobAssignment::where(function ($q) use ($userId) {
                // always include user_id; only add assigned_to condition if column exists
                $q->where('user_id', $userId);
                if (Schema::hasColumn('project_job_assignments', 'assigned_to')) {
                    $q->orWhere('assigned_to', $userId);
                }
            })->where(function ($q) use ($start, $end) {
                // prefer desired_start_date filter if column exists
                if (Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                    $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                } else {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            });

            $assigned = $assignedQ->get();
            foreach ($assigned as $a) {
                $result['assigned']['total_items'] += 1;
                // amounts/pages
                if (isset($a->amounts) && $a->amounts) {
                    if (isset($a->amounts_unit) && $a->amounts_unit === 'page') {
                        $result['assigned']['pages'] += (int) $a->amounts;
                    }
                } elseif (isset($a->pages) && $a->pages) {
                    $result['assigned']['pages'] += (int) $a->pages;
                }
                // estimated_hours as proxy for work hours
                if (isset($a->estimated_hours) && $a->estimated_hours) {
                    $result['assigned']['work_hours'] += (float) $a->estimated_hours;
                    $result['assigned']['desired_hours'] += (float) $a->estimated_hours;
                }
            }

            // Self tasks (project_job_assignment_by_myself)
            $selfQ = ProjectJobAssignmentByMyself::where('user_id', $userId)->where(function ($q) use ($start, $end) {
                if (Schema::hasColumn('project_job_assignment_by_myself', 'desired_start_date')) {
                    $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                } else {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            });
            $self = $selfQ->get();
            foreach ($self as $s) {
                $result['self']['total_items'] += 1;
                if (isset($s->amounts) && $s->amounts) {
                    if (isset($s->amounts_unit) && $s->amounts_unit === 'page') {
                        $result['self']['pages'] += (int) $s->amounts;
                    }
                } elseif (isset($s->pages) && $s->pages) {
                    $result['self']['pages'] += (int) $s->pages;
                }
                if (isset($s->estimated_hours) && $s->estimated_hours) {
                    $result['self']['work_hours'] += (float) $s->estimated_hours;
                    $result['self']['desired_hours'] += (float) $s->estimated_hours;
                }
            }

            // --- compute per-category points for this user ---
            // We'll iterate the same assigned and self items and apply coefficients
            $totalPointsRaw = 0.0; // stage-based raw sum (stage * diff)
            $totalAmount = 0;

            $typePointsMap = [];
            $sizePointsMap = [];
            $difficultyPointsMap = [];

            $resolveDifficultyCoeff = function ($assignment) {
                try {
                    $diff = $assignment->difficulty ?? null;
                    if (!$diff && isset($assignment->projectJob) && isset($assignment->projectJob->difficulty)) {
                        $diff = $assignment->projectJob->difficulty;
                    }
                    if (!$diff) return 1.0;
                    if (is_numeric($diff)) {
                        $d = \App\Models\Difficulty::find((int)$diff);
                        if ($d) return (float)$d->coefficient;
                    }
                    $d = \App\Models\Difficulty::where('name', $diff)->first();
                    if ($d) return (float)$d->coefficient;
                } catch (\Throwable $e) {
                }
                return 1.0;
            };

            $processAssignment = function ($assignment) use (&$totalPointsRaw, &$totalAmount, &$typePointsMap, &$sizePointsMap, &$difficultyPointsMap, $resolveDifficultyCoeff) {
                $pages = 0;
                if (isset($assignment->amounts) && $assignment->amounts && isset($assignment->amounts_unit) && $assignment->amounts_unit === 'page') {
                    $pages = (int) $assignment->amounts;
                } elseif (isset($assignment->pages) && $assignment->pages) {
                    $pages = (int) $assignment->pages;
                }
                if ($pages <= 0) return;

                $sid = $assignment->stage_id ?? null;
                $stageCoeff = 1.0;
                try {
                    if ($sid) {
                        $st = \App\Models\Stage::find($sid);
                        if ($st && isset($st->coefficient)) $stageCoeff = (float)$st->coefficient;
                    }
                } catch (\Throwable $e) {
                }

                $tid = $assignment->work_item_type_id ?? null;
                $typeCoeff = 1.0;
                try {
                    if ($tid) {
                        $tt = \App\Models\WorkItemType::find($tid);
                        if ($tt && isset($tt->coefficient)) $typeCoeff = (float)$tt->coefficient;
                    }
                } catch (\Throwable $e) {
                }

                $z = $assignment->size_id ?? null;
                $sizeCoeff = 1.0;
                try {
                    if ($z) {
                        $sz = \App\Models\Size::find($z);
                        if ($sz && isset($sz->coefficient)) $sizeCoeff = (float)$sz->coefficient;
                    }
                } catch (\Throwable $e) {
                }

                $diffCoeff = $resolveDifficultyCoeff($assignment);

                // accumulate
                $rawStagePoints = $pages * $stageCoeff * $diffCoeff;
                $totalPointsRaw += $rawStagePoints;

                if (!isset($typePointsMap[$tid])) $typePointsMap[$tid] = 0.0;
                $typePointsMap[$tid] += $pages * $typeCoeff * $diffCoeff;

                if (!isset($sizePointsMap[$z])) $sizePointsMap[$z] = 0.0;
                $sizePointsMap[$z] += $pages * $sizeCoeff * $diffCoeff;

                // difficulty points as pages * diffCoeff (summed)
                $did = null;
                try {
                    if (isset($assignment->difficulty_id) && $assignment->difficulty_id) {
                        $did = (int)$assignment->difficulty_id;
                    } elseif (isset($assignment->difficultyModel) && isset($assignment->difficultyModel->id)) {
                        $did = (int)$assignment->difficultyModel->id;
                    } elseif (isset($assignment->difficulty) && $assignment->difficulty) {
                        $did = $assignment->difficulty;
                    }
                } catch (\Throwable $e) {
                }
                if (!isset($difficultyPointsMap[$did])) $difficultyPointsMap[$did] = 0.0;
                $difficultyPointsMap[$did] += $pages * $diffCoeff;

                $totalAmount += $pages;
            };

            foreach ($assigned as $a) {
                $processAssignment($a);
            }
            foreach ($self as $s2) {
                $processAssignment($s2);
            }

            $stagePointsTotal = round($totalPointsRaw, 1);

            $typeTotal = 0.0;
            foreach ($typePointsMap as $v) $typeTotal += $v;
            $typeTotal = round($typeTotal, 1);

            $sizeTotal = 0.0;
            foreach ($sizePointsMap as $v) $sizeTotal += $v;
            $sizeTotal = round($sizeTotal, 1);

            $difficultyTotal = 0.0;
            foreach ($difficultyPointsMap as $did => $v) {
                // try to apply difficulty coefficient if available (avoid double-counting: v already includes diffCoeff)
                $difficultyTotal += $v;
            }
            $difficultyTotal = round($difficultyTotal, 1);

            // --- compute event points for this user ---
            $eventTotalPoints = 0.0;
            try {
                $evItems = \App\Models\Event::where('user_id', $userId)
                    ->whereBetween('starts_at', [$start, $end])
                    ->get();
                $evCoeffMap = EventItemType::pluck('coefficient', 'id')->toArray();
                foreach ($evItems as $ev) {
                    $hours = 0.0;
                    try {
                        if ($ev->starts_at && $ev->ends_at) {
                            $s = \Carbon\Carbon::parse($ev->starts_at);
                            $e2 = \Carbon\Carbon::parse($ev->ends_at);
                            $hours = max(0.0, $e2->diffInMinutes($s) / 60.0);
                        }
                    } catch (\Throwable $evE) {}
                    if ($hours <= 0) continue;
                    $eid = $ev->event_item_type_id ?? null;
                    $coeff = ($eid && isset($evCoeffMap[$eid])) ? (float)$evCoeffMap[$eid] : 1.0;
                    $eventTotalPoints += $hours * $coeff;
                }
            } catch (\Throwable $evErr) {}
            $eventTotalPoints = round($eventTotalPoints, 1);

            // compute overtime stats and points for this user in the period
            $overtimeNormalMinutes = 0;
            $overtimeExcessMinutes = 0;
            try {
                $wrRecords = WorkRecord::where('user_id', $userId)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->get(['overtime_minutes']);
                $result['overtime_minutes']    = (int) $wrRecords->sum('overtime_minutes');
                // 通常残業: 0 < overtime_minutes <= 180 (≤3時間)
                $normalRecords = $wrRecords->filter(fn($r) => ($r->overtime_minutes ?? 0) > 0 && ($r->overtime_minutes ?? 0) <= 180);
                // 超過残業: overtime_minutes > 180 (>3時間)
                $excessRecords = $wrRecords->filter(fn($r) => ($r->overtime_minutes ?? 0) > 180);
                $result['overtime_days_normal'] = $normalRecords->count();
                $result['overtime_days_excess'] = $excessRecords->count();
                $overtimeNormalMinutes = (int) $normalRecords->sum('overtime_minutes');
                $overtimeExcessMinutes = (int) $excessRecords->sum('overtime_minutes');
            } catch (\Throwable $e) {
                $result['overtime_minutes']     = 0;
                $result['overtime_days_normal'] = 0;
                $result['overtime_days_excess'] = 0;
            }

            $overtimeNormalPoints = round($overtimeNormalMinutes * $normalOvertimeCoeff, 1);
            $overtimeExcessPoints = round($overtimeExcessMinutes * $excessOvertimeCoeff, 1);
            $overtimeTotalPoints  = round($overtimeNormalPoints + $overtimeExcessPoints, 1);

            // attach points summary into aggregates
            $result['points'] = [
                'stage' => $stagePointsTotal,
                'size' => $sizeTotal,
                'type' => $typeTotal,
                'difficulty' => $difficultyTotal,
                'event' => $eventTotalPoints,
                'overtime' => $overtimeTotalPoints,
                'overtime_normal' => $overtimeNormalPoints,
                'overtime_excess' => $overtimeExcessPoints,
                'overall' => $stagePointsTotal + $eventTotalPoints + $overtimeTotalPoints,
                'total_amount' => $totalAmount,
            ];

            return $result;
        };

        $user = $request->user();

        // SuperAdmin: 全会社のデータ
        if (method_exists($user, 'isSuperAdmin') ? $user->isSuperAdmin() : (($user->user_role ?? '') === 'superadmin')) {
            $companies = Company::with(['departments.teams.members.assignment'])->get();
        }
        // Admin: 自社の全メンバー
        elseif (method_exists($user, 'isAdmin') ? $user->isAdmin() : (($user->user_role ?? '') === 'admin')) {
            $companies = Company::with(['departments.teams.members.assignment'])
                ->where('id', $user->company_id)
                ->get();
        }
        // Leader: ユニットチームに登録されたメンバーのみ表示
        else {
            // Leader: find teams where leader is a member (unit teams)
            // This is a conservative implementation: find teams where user is a member and load related members
            $teams = $user->teams()->with(['members.assignment', 'department', 'company'])->get();

            // Build companies structure from teams
            $companies = [];
            foreach ($teams as $team) {
                $company = $team->company ?? null;
                $department = $team->department ?? null;

                if (!$company || !$department) {
                    continue;
                }

                $companyIndex = null;
                foreach ($companies as $i => $c) {
                    if ($c['id'] === $company->id) {
                        $companyIndex = $i;
                        break;
                    }
                }

                if (is_null($companyIndex)) {
                    $companies[] = [
                        'id' => $company->id,
                        'name' => $company->name,
                        'departments' => [],
                    ];
                    $companyIndex = count($companies) - 1;
                }

                // find or create department
                $deptIndex = null;
                foreach ($companies[$companyIndex]['departments'] as $j => $d) {
                    if ($d['id'] === $department->id) {
                        $deptIndex = $j;
                        break;
                    }
                }
                if (is_null($deptIndex)) {
                    $companies[$companyIndex]['departments'][] = [
                        'id' => $department->id,
                        'name' => $department->name,
                        'teams' => [],
                        'members' => [],
                    ];
                    $deptIndex = count($companies[$companyIndex]['departments']) - 1;
                }

                // add team
                $companies[$companyIndex]['departments'][$deptIndex]['teams'][] = [
                    'id' => $team->id,
                    'name' => $team->name,
                    'members' => $team->members->map(function ($m) {
                        return [
                            'id'                    => $m->id,
                            'name'                  => $m->name,
                            'assignment_name'       => $m->assignment->name ?? '',
                            'employment_type'       => $m->employment_type ?? 'regular',
                            'employment_type_label' => $m->employmentTypeLabel(),
                        ];
                    })->toArray(),
                ];

                // merge members into department members list (unique by id)
                foreach ($team->members as $m) {
                    $exists = false;
                    foreach ($companies[$companyIndex]['departments'][$deptIndex]['members'] as $mm) {
                        if ($mm['id'] === $m->id) {
                            $exists = true;
                            break;
                        }
                    }
                    if (!$exists) {
                        $companies[$companyIndex]['departments'][$deptIndex]['members'][] = [
                            'id'                    => $m->id,
                            'name'                  => $m->name,
                            'assignment_name'       => $m->assignment->name ?? '',
                            'employment_type'       => $m->employment_type ?? 'regular',
                            'employment_type_label' => $m->employmentTypeLabel(),
                        ];
                    }
                }
            }
        }

        // If companies is an Eloquent collection, we need to map members and attach aggregates
        $companiesArray = [];
        if (isset($companies) && $companies instanceof \Illuminate\Database\Eloquent\Collection) {
            foreach ($companies as $company) {
                $companyArr = ['id' => $company->id, 'name' => $company->name, 'departments' => []];
                foreach ($company->departments as $dept) {
                    $deptArr = ['id' => $dept->id, 'name' => $dept->name, 'teams' => [], 'members' => []];
                    // Gather department-level members (could be users relation)
                    $members = $dept->members ?? collect();
                    foreach ($members as $m) {
                        $agg = $calcAggregates($m->id);
                        $deptArr['members'][] = [
                            'id'                    => $m->id,
                            'name'                  => $m->name,
                            'assignment_name'       => $m->assignment->name ?? '',
                            'employment_type'       => $m->employment_type ?? 'regular',
                            'employment_type_label' => $m->employmentTypeLabel(),
                            'aggregates'            => $agg,
                        ];
                    }
                    // teams
                    foreach ($dept->teams ?? collect() as $team) {
                        $teamArr = ['id' => $team->id, 'name' => $team->name, 'members' => []];
                        foreach ($team->members ?? collect() as $tm) {
                            $agg = $calcAggregates($tm->id);
                            $teamArr['members'][] = [
                                'id'                    => $tm->id,
                                'name'                  => $tm->name,
                                'assignment_name'       => $tm->assignment->name ?? '',
                                'employment_type'       => $tm->employment_type ?? 'regular',
                                'employment_type_label' => $tm->employmentTypeLabel(),
                                'aggregates'            => $agg,
                            ];
                        }
                        $deptArr['teams'][] = $teamArr;
                    }
                    $companyArr['departments'][] = $deptArr;
                }
                $companiesArray[] = $companyArr;
            }
        } else {
            // If companies already built as arrays (Leader path), attach aggregates for each member in structure
            foreach ($companies as $ci => $c) {
                foreach ($companies[$ci]['departments'] as $di => $d) {
                    // department members
                    foreach ($companies[$ci]['departments'][$di]['members'] as $mi => $m) {
                        $companies[$ci]['departments'][$di]['members'][$mi]['aggregates'] = $calcAggregates($m['id']);
                    }
                    // teams
                    foreach ($companies[$ci]['departments'][$di]['teams'] as $ti => $t) {
                        foreach ($companies[$ci]['departments'][$di]['teams'][$ti]['members'] as $mi => $m) {
                            $companies[$ci]['departments'][$di]['teams'][$ti]['members'][$mi]['aggregates'] = $calcAggregates($m['id']);
                        }
                    }
                }
            }
            $companiesArray = $companies;
        }

        // Compute per-department percentile scores (0–100 per category, 0–600 overall)
        // 【職種グループ別パーセンタイル】
        // 同じ担当（assignment_name）を持つメンバー同士で比較する。
        // グループ人数が 3 人未満の場合は部署全体を比較対象にフォールバックする。
        // これにより、校正者のページ数と組版者のページ数を直接比較せず、
        // 「同職種内での相対的な貢献度」を公平に評価できる。
        $pCats = ['stage', 'size', 'type', 'difficulty', 'event', 'overtime'];

        // Reusable closure: compute percentile scores for a given set of UIDs within a score map
        $computePct = function (array $uidList, array $allScores, array $cats): array {
            $groupN = count($uidList);
            $result = [];
            foreach ($cats as $cat) {
                foreach ($uidList as $uid) {
                    $my = $allScores[$uid][$cat] ?? 0.0;
                    $above = 0; $tied = 0;
                    foreach ($uidList as $other) {
                        $ov = $allScores[$other][$cat] ?? 0.0;
                        if ($ov > $my) $above++;
                        elseif (abs($ov - $my) < 0.001) $tied++;
                    }
                    $avgRank = $above + ($tied + 1) / 2.0;
                    $result[$uid][$cat] = $groupN === 1
                        ? 100.0
                        : max(0.0, round((($groupN - $avgRank) / ($groupN - 1)) * 100.0, 1));
                }
            }
            return $result;
        };

        try {
            foreach ($companiesArray as $ci => $c) {
                foreach ($c['departments'] as $di => $d) {
                    // Collect unique members: scores + assignment_name
                    $mScores = [];      // [uid => [cat => rawScore]]
                    $mAssignment = [];  // [uid => assignment_name]

                    foreach ($d['members'] ?? [] as $m) {
                        $uid = $m['id'];
                        if (!isset($mScores[$uid])) {
                            foreach ($pCats as $cat) {
                                $mScores[$uid][$cat] = (float)($m['aggregates']['points'][$cat] ?? 0);
                            }
                            $mAssignment[$uid] = $m['assignment_name'] ?? '';
                        }
                    }
                    foreach ($d['teams'] ?? [] as $t) {
                        foreach ($t['members'] ?? [] as $tm) {
                            $uid = $tm['id'];
                            if (!isset($mScores[$uid])) {
                                foreach ($pCats as $cat) {
                                    $mScores[$uid][$cat] = (float)($tm['aggregates']['points'][$cat] ?? 0);
                                }
                                $mAssignment[$uid] = $tm['assignment_name'] ?? '';
                            }
                        }
                    }

                    $n = count($mScores);
                    if ($n === 0) continue;

                    // Department-wide percentile (fallback for small role groups)
                    $allUids  = array_keys($mScores);
                    $deptPct  = $computePct($allUids, $mScores, $pCats);

                    // Group by assignment_name
                    $roleGroups = []; // [assignment_name => [uid, ...]]
                    foreach ($allUids as $uid) {
                        $role = $mAssignment[$uid] ?: '未設定';
                        $roleGroups[$role][] = $uid;
                    }

                    // Choose role-group percentile (N≥3) or dept fallback (N<3)
                    $mPct       = []; // [uid => [cat => percentile]]
                    $mCompLevel = []; // [uid => 'role'|'department']
                    foreach ($roleGroups as $role => $uids) {
                        if (count($uids) >= 3) {
                            $rolePct = $computePct($uids, $mScores, $pCats);
                            foreach ($uids as $uid) {
                                $mPct[$uid]       = $rolePct[$uid];
                                $mCompLevel[$uid] = 'role';
                            }
                        } else {
                            foreach ($uids as $uid) {
                                $mPct[$uid]       = $deptPct[$uid];
                                $mCompLevel[$uid] = 'department';
                            }
                        }
                    }

                    // overall = sum of all 6 category percentiles (max 600)
                    foreach ($allUids as $uid) {
                        $tot = 0.0;
                        foreach ($pCats as $cat) $tot += $mPct[$uid][$cat] ?? 0;
                        $mPct[$uid]['overall'] = round($tot, 1);
                    }

                    // Write back
                    foreach ($d['members'] as $mi => $m) {
                        $uid = $m['id'];
                        if (isset($mPct[$uid])) {
                            $companiesArray[$ci]['departments'][$di]['members'][$mi]['aggregates']['percentile_scores'] = $mPct[$uid];
                            $companiesArray[$ci]['departments'][$di]['members'][$mi]['aggregates']['points']['overall']  = $mPct[$uid]['overall'];
                            $companiesArray[$ci]['departments'][$di]['members'][$mi]['aggregates']['comparison_level']  = $mCompLevel[$uid] ?? 'department';
                        }
                    }
                    foreach ($d['teams'] as $ti => $t) {
                        foreach ($t['members'] as $tmi => $tm) {
                            $uid = $tm['id'];
                            if (isset($mPct[$uid])) {
                                $companiesArray[$ci]['departments'][$di]['teams'][$ti]['members'][$tmi]['aggregates']['percentile_scores'] = $mPct[$uid];
                                $companiesArray[$ci]['departments'][$di]['teams'][$ti]['members'][$tmi]['aggregates']['points']['overall']  = $mPct[$uid]['overall'];
                                $companiesArray[$ci]['departments'][$di]['teams'][$ti]['members'][$tmi]['aggregates']['comparison_level']  = $mCompLevel[$uid] ?? 'department';
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore percentile errors — fall back to raw overall
        }

        // Compute company-level ranks and deviation (偏差値) per company
        try {
            foreach ($companiesArray as $ci => $c) {
                $userPoints = [];
                // collect member overall points
                foreach (($c['departments'] ?? []) as $di => $d) {
                    foreach (($d['members'] ?? []) as $mi => $m) {
                        $pts = $m['aggregates']['points']['overall'] ?? 0;
                        $userPoints[$m['id']] = $pts;
                    }
                    foreach (($d['teams'] ?? []) as $ti => $t) {
                        foreach (($t['members'] ?? []) as $tm) {
                            $pts = $tm['aggregates']['points']['overall'] ?? 0;
                            $userPoints[$tm['id']] = $pts;
                        }
                    }
                }
                $vals = array_values($userPoints);
                $count = count($vals);
                $mean = $count ? array_sum($vals) / $count : 0.0;
                $var = 0.0;
                if ($count) {
                    $sumSq = 0.0;
                    foreach ($vals as $v) {
                        $d0 = $v - $mean;
                        $sumSq += $d0 * $d0;
                    }
                    $var = $sumSq / $count;
                }
                $std = sqrt($var);

                // assign deviation score to members (mean=50 sd=10)
                foreach (($c['departments'] ?? []) as $di => $d) {
                    foreach (($d['members'] ?? []) as $mi => $m) {
                        $pts = $m['aggregates']['points']['overall'] ?? 0;
                        if ($std > 0.000001) {
                            $z = ($pts - $mean) / $std;
                            $dev = round(50 + $z * 10, 1);
                        } else {
                            $dev = null;
                        }
                        $companiesArray[$ci]['departments'][$di]['members'][$mi]['aggregates']['deviation_score'] = $dev;
                    }
                    foreach (($d['teams'] ?? []) as $ti => $t) {
                        foreach (($t['members'] ?? $t['members']) as $mi => $tm) {
                            $pts = $tm['aggregates']['points']['overall'] ?? 0;
                            if ($std > 0.000001) {
                                $z = ($pts - $mean) / $std;
                                $dev = round(50 + $z * 10, 1);
                            } else {
                                $dev = null;
                            }
                            $companiesArray[$ci]['departments'][$di]['teams'][$ti]['members'][$mi]['aggregates']['deviation_score'] = $dev;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore any errors computing deviation
        }

        $routeName = $request->route()?->getName() ?? '';
        $component = str_ends_with($routeName, 'category_rank')
            ? 'WorkloadAnalyzer/CategoryRank'
            : 'WorkloadAnalyzer/Index';

        return Inertia::render($component, [
            'companies' => $companiesArray ?? [],
            'selected_ym' => $ym,
        ]);
    }

    public function show(Request $request, $userId)
    {
        $this->requireAdminPermission('workload_analysis');
        $this->requireLeaderPermission('workload_analysis');
        $ym = $request->query('ym') ?: now()->format('Y-m');
        [$year, $month] = explode('-', $ym);
        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth();
        if ($start->format('Y-m') === now()->format('Y-m')) {
            $end = now()->endOfDay();
        }

        // totals like in index
        $calc = function ($userId) use ($start, $end) {
            $tot = [
                'assigned' => ['pages' => 0, 'work_hours' => 0.0, 'desired_hours' => 0.0, 'total_items' => 0],
                'self' => ['pages' => 0, 'work_hours' => 0.0, 'desired_hours' => 0.0, 'total_items' => 0]
            ];

            $assigned = ProjectJobAssignment::where(function ($q) use ($userId) {
                $q->where('user_id', $userId);
                if (Schema::hasColumn('project_job_assignments', 'assigned_to')) {
                    $q->orWhere('assigned_to', $userId);
                }
            })->where(function ($q) use ($start, $end) {
                if (Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                    $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                } else {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            })->get();
            foreach ($assigned as $a) {
                $tot['assigned']['total_items'] += 1;
                if (isset($a->amounts) && $a->amounts) {
                    if (isset($a->amounts_unit) && $a->amounts_unit === 'page') {
                        $tot['assigned']['pages'] += (int) $a->amounts;
                    }
                } elseif (isset($a->pages) && $a->pages) {
                    $tot['assigned']['pages'] += (int) $a->pages;
                }
                if (isset($a->estimated_hours) && $a->estimated_hours) {
                    $tot['assigned']['work_hours'] += (float) $a->estimated_hours;
                    $tot['assigned']['desired_hours'] += (float) $a->estimated_hours;
                }
            }

            $self = ProjectJobAssignmentByMyself::where('user_id', $userId)->where(function ($q) use ($start, $end) {
                if (Schema::hasColumn('project_job_assignment_by_myself', 'desired_start_date')) {
                    $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                } else {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            })->get();
            foreach ($self as $s) {
                $tot['self']['total_items'] += 1;
                if (isset($s->amounts) && $s->amounts) {
                    if (isset($s->amounts_unit) && $s->amounts_unit === 'page') {
                        $tot['self']['pages'] += (int) $s->amounts;
                    }
                } elseif (isset($s->pages) && $s->pages) {
                    $tot['self']['pages'] += (int) $s->pages;
                }
                if (isset($s->estimated_hours) && $s->estimated_hours) {
                    $tot['self']['work_hours'] += (float) $s->estimated_hours;
                    $tot['self']['desired_hours'] += (float) $s->estimated_hours;
                }
            }

            return $tot;
        };

        $totals = $calc($userId);

        // stage breakdown: group by stage_id and sum pages (from both assigned and self)
        $stageSums = [];
        $assignedItems = ProjectJobAssignment::where(function ($q) use ($userId) {
            $q->where('user_id', $userId);
            if (Schema::hasColumn('project_job_assignments', 'assigned_to')) {
                $q->orWhere('assigned_to', $userId);
            }
        })->where(function ($q) use ($start, $end) {
            if (Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
            } else {
                $q->whereBetween('created_at', [$start, $end]);
            }
        })->get();
        foreach ($assignedItems as $a) {
            $stageId = $a->stage_id ?? null;
            $pages = 0;
            if (isset($a->amounts) && $a->amounts && isset($a->amounts_unit) && $a->amounts_unit === 'page') {
                $pages = (int) $a->amounts;
            } elseif (isset($a->pages) && $a->pages) {
                $pages = (int) $a->pages;
            }
            if (!isset($stageSums[$stageId])) {
                $stageSums[$stageId] = 0;
            }
            $stageSums[$stageId] += $pages;
        }

        $selfItems = ProjectJobAssignmentByMyself::where('user_id', $userId)->where(function ($q) use ($start, $end) {
            if (Schema::hasColumn('project_job_assignment_by_myself', 'desired_start_date')) {
                $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
            } else {
                $q->whereBetween('created_at', [$start, $end]);
            }
        })->get();
        foreach ($selfItems as $s) {
            $stageId = $s->stage_id ?? null;
            $pages = 0;
            if (isset($s->amounts) && $s->amounts && isset($s->amounts_unit) && $s->amounts_unit === 'page') {
                $pages = (int) $s->amounts;
            } elseif (isset($s->pages) && $s->pages) {
                $pages = (int) $s->pages;
            }
            if (!isset($stageSums[$stageId])) {
                $stageSums[$stageId] = 0;
            }
            $stageSums[$stageId] += $pages;
        }

        // Prepare chart data: get stage names
        $stageLabels = [];
        $stageData = [];
        $stageCoefficients = [];
        $stageIds = [];
        if (count($stageSums)) {
            $stages = \App\Models\Stage::whereIn('id', array_filter(array_keys($stageSums)))->get()->keyBy('id');
            foreach ($stageSums as $sid => $val) {
                $label = $sid && isset($stages[$sid]) ? $stages[$sid]->name : '未設定';
                $coeff = $sid && isset($stages[$sid]) && isset($stages[$sid]->coefficient) ? (float)$stages[$sid]->coefficient : 1.0;
                $stageLabels[] = $label;
                $stageIds[] = $sid;
                $stageData[] = $val;
                $stageCoefficients[] = $coeff;
            }
        }

        // --- work_item_types aggregation ---
        $typeSums = [];
        foreach ($assignedItems as $a) {
            $tid = $a->work_item_type_id ?? null;
            $pages = 0;
            if (isset($a->amounts) && $a->amounts && isset($a->amounts_unit) && $a->amounts_unit === 'page') {
                $pages = (int) $a->amounts;
            } elseif (isset($a->pages) && $a->pages) {
                $pages = (int) $a->pages;
            }
            if (!isset($typeSums[$tid])) $typeSums[$tid] = 0;
            $typeSums[$tid] += $pages;
        }
        foreach ($selfItems as $s) {
            $tid = $s->work_item_type_id ?? null;
            $pages = 0;
            if (isset($s->amounts) && $s->amounts && isset($s->amounts_unit) && $s->amounts_unit === 'page') {
                $pages = (int) $s->amounts;
            } elseif (isset($s->pages) && $s->pages) {
                $pages = (int) $s->pages;
            }
            if (!isset($typeSums[$tid])) $typeSums[$tid] = 0;
            $typeSums[$tid] += $pages;
        }

        $typeLabels = [];
        $typeData = [];
        $typeCoefficients = [];
        if (count($typeSums)) {
            $types = \App\Models\WorkItemType::whereIn('id', array_filter(array_keys($typeSums)))->get()->keyBy('id');
            foreach ($typeSums as $tid => $val) {
                $label = $tid && isset($types[$tid]) ? $types[$tid]->name : '未設定';
                $coeff = $tid && isset($types[$tid]) && isset($types[$tid]->coefficient) ? (float)$types[$tid]->coefficient : 1.0;
                $typeLabels[] = $label;
                $typeData[] = $val;
                $typeCoefficients[] = $coeff;
            }
        }

        // --- sizes aggregation ---
        $sizeSums = [];
        foreach ($assignedItems as $a) {
            $z = $a->size_id ?? null;
            $pages = 0;
            if (isset($a->amounts) && $a->amounts && isset($a->amounts_unit) && $a->amounts_unit === 'page') {
                $pages = (int) $a->amounts;
            } elseif (isset($a->pages) && $a->pages) {
                $pages = (int) $a->pages;
            }
            if (!isset($sizeSums[$z])) $sizeSums[$z] = 0;
            $sizeSums[$z] += $pages;
        }
        foreach ($selfItems as $s) {
            $z = $s->size_id ?? null;
            $pages = 0;
            if (isset($s->amounts) && $s->amounts && isset($s->amounts_unit) && $s->amounts_unit === 'page') {
                $pages = (int) $s->amounts;
            } elseif (isset($s->pages) && $s->pages) {
                $pages = (int) $s->pages;
            }
            if (!isset($sizeSums[$z])) $sizeSums[$z] = 0;
            $sizeSums[$z] += $pages;
        }

        $sizeLabels = [];
        $sizeData = [];
        $sizeCoefficients = [];
        if (count($sizeSums)) {
            $sizes = \App\Models\Size::whereIn('id', array_filter(array_keys($sizeSums)))->get()->keyBy('id');
            foreach ($sizeSums as $zid => $val) {
                $label = $zid && isset($sizes[$zid]) ? $sizes[$zid]->name : '未設定';
                $coeff = $zid && isset($sizes[$zid]) && isset($sizes[$zid]->coefficient) ? (float)$sizes[$zid]->coefficient : 1.0;
                $sizeLabels[] = $label;
                $sizeData[] = $val;
                $sizeCoefficients[] = $coeff;
            }
        }

        // --- difficulties aggregation: get ordered difficulties from table and sum pages per difficulty ---
        $difficultyLabels = [];
        $difficultyData = [];
        $difficultyMap = [];
        $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get();
        $difficultyIds = [];
        $difficultyNameToId = [];
        foreach ($difficulties as $d) {
            $difficultyLabels[] = $d->name;
            $difficultyIds[] = $d->id;
            $difficultyNameToId[$d->name] = $d->id;
            // initialize map
            $difficultyMap[$d->id] = 0;
        }

        // sum pages per difficulty from assigned and self
        foreach ($assignedItems as $a) {
            // prefer difficulty_id column or relation
            $did = $a->difficulty_id ?? null;
            if (!$did && isset($a->difficultyModel) && isset($a->difficultyModel->id)) {
                $did = $a->difficultyModel->id;
            }
            $pages = 0;
            if (isset($a->amounts) && $a->amounts && isset($a->amounts_unit) && $a->amounts_unit === 'page') {
                $pages = (int) $a->amounts;
            } elseif (isset($a->pages) && $a->pages) {
                $pages = (int) $a->pages;
            }
            if ($pages <= 0) continue;
            if ($did && isset($difficultyMap[$did])) {
                $difficultyMap[$did] += $pages;
            }
        }
        foreach ($selfItems as $s) {
            $did = $s->difficulty_id ?? null;
            if (!$did && isset($s->difficultyModel) && isset($s->difficultyModel->id)) {
                $did = $s->difficultyModel->id;
            }
            $pages = 0;
            if (isset($s->amounts) && $s->amounts && isset($s->amounts_unit) && $s->amounts_unit === 'page') {
                $pages = (int) $s->amounts;
            } elseif (isset($s->pages) && $s->pages) {
                $pages = (int) $s->pages;
            }
            if ($pages <= 0) continue;
            if ($did && isset($difficultyMap[$did])) {
                $difficultyMap[$did] += $pages;
            }
        }

        // build difficultyData aligned with difficultyLabels
        foreach ($difficulties as $d) {
            $difficultyData[] = $difficultyMap[$d->id] ?? 0;
        }

        // --- build stage x difficulty matrix (rows: difficulties, cols: stages) ---
        $stageDifficultyMatrix = [];
        // initialize rows
        foreach ($difficultyIds as $did) {
            $row = [];
            foreach ($stageIds as $sid) {
                $row[] = 0;
            }
            $stageDifficultyMatrix[$did] = $row;
        }

        $accumulateToMatrix = function ($assignment) use (&$stageDifficultyMatrix, $stageIds, $difficultyIds, $difficultyNameToId) {
            $pages = 0;
            if (isset($assignment->amounts) && $assignment->amounts && isset($assignment->amounts_unit) && $assignment->amounts_unit === 'page') {
                $pages = (int) $assignment->amounts;
            } elseif (isset($assignment->pages) && $assignment->pages) {
                $pages = (int) $assignment->pages;
            }
            if ($pages <= 0) return;

            $sid = $assignment->stage_id ?? null;

            // Prefer canonical difficulty_id column when present, then relation, then legacy difficulty value
            $did = null;
            if (isset($assignment->difficulty_id) && $assignment->difficulty_id) {
                $did = (int)$assignment->difficulty_id;
            } elseif (isset($assignment->difficultyModel) && isset($assignment->difficultyModel->id)) {
                $did = (int)$assignment->difficultyModel->id;
            } elseif (isset($assignment->difficulty) && $assignment->difficulty) {
                $did = $assignment->difficulty;
            }

            // normalize difficulty value (id or name)
            if (is_object($did) && isset($did->id)) {
                $did = (int)$did->id;
            } elseif (is_numeric($did)) {
                $did = (int)$did;
            } elseif (is_string($did) && isset($difficultyNameToId[$did])) {
                $did = $difficultyNameToId[$did];
            } else {
                $did = null;
            }

            // find indices
            $sIndex = array_search($sid, $stageIds, true);
            $dIndex = array_search($did, $difficultyIds, true);
            if ($sIndex === false || $dIndex === false) return;

            // $stageDifficultyMatrix keyed by difficulty id; update correct column
            $stageDifficultyMatrix[$did][$sIndex] += $pages;
        };

        // accumulate from assigned and self items
        foreach ($assignedItems as $a) {
            $accumulateToMatrix($a);
        }
        foreach ($selfItems as $s) {
            $accumulateToMatrix($s);
        }

        // convert matrix to ordered rows aligned with difficultyLabels and stageLabels
        $stageDifficultyRows = [];
        foreach ($difficultyIds as $did) {
            $stageDifficultyRows[] = array_values($stageDifficultyMatrix[$did]);
        }

        // --- Include unclassified assignments (difficulty not set) as a virtual "未設定" difficulty row ---
        // Sum pages per stage for assignments where difficulty could not be resolved
        $unclassifiedPerStage = [];
        $colsCount = count($stageIds);
        if ($colsCount > 0) {
            $unclassifiedPerStage = array_fill(0, $colsCount, 0);

            $collectUnclassified = function ($assignment) use (&$unclassifiedPerStage, $stageIds) {
                $pages = 0;
                if (isset($assignment->amounts) && $assignment->amounts && isset($assignment->amounts_unit) && $assignment->amounts_unit === 'page') {
                    $pages = (int) $assignment->amounts;
                } elseif (isset($assignment->pages) && $assignment->pages) {
                    $pages = (int) $assignment->pages;
                }
                if ($pages <= 0) return;

                // determine difficulty id similarly to accumulateToMatrix
                $did = null;
                if (isset($assignment->difficulty_id) && $assignment->difficulty_id) {
                    $did = (int)$assignment->difficulty_id;
                } elseif (isset($assignment->difficultyModel) && isset($assignment->difficultyModel->id)) {
                    $did = (int)$assignment->difficultyModel->id;
                } elseif (isset($assignment->difficulty) && $assignment->difficulty) {
                    $did = $assignment->difficulty;
                }

                if (is_object($did) && isset($did->id)) {
                    $did = (int)$did->id;
                } elseif (is_numeric($did)) {
                    $did = (int)$did;
                } elseif (is_string($did) && isset($difficultyNameToId[$did])) {
                    $did = $difficultyNameToId[$did];
                } else {
                    $did = null;
                }

                if ($did !== null) return; // only care about unclassified here

                $sid = $assignment->stage_id ?? null;
                $sIndex = array_search($sid, $stageIds, true);
                if ($sIndex !== false) {
                    $unclassifiedPerStage[$sIndex] += $pages;
                }
            };

            foreach ($assignedItems as $a) {
                $collectUnclassified($a);
            }
            foreach ($selfItems as $s) {
                $collectUnclassified($s);
            }

            $unclassifiedTotal = array_sum($unclassifiedPerStage);
            if ($unclassifiedTotal > 0) {
                // append to difficulty data arrays so ordering remains consistent
                $difficultyData[] = $unclassifiedTotal;
                // append virtual difficulty id (0) to ids and rows
                $difficultyIds[] = 0;
                $stageDifficultyRows[] = array_values($unclassifiedPerStage);

                // append a virtual difficulty object to $difficulties so client ordering/labels include it
                try {
                    $virtual = new \stdClass();
                    $virtual->id = 0;
                    $virtual->name = '未設定';
                    $virtual->sort_order = 9999;
                    $virtual->description = null;
                    $virtual->created_at = null;
                    $virtual->updated_at = null;
                    $virtual->company_id = null;
                    $virtual->department_id = null;
                    $virtual->coefficient = '1.000';
                    if (is_object($difficulties)) {
                        // Eloquent Collection or array-like
                        if (method_exists($difficulties, 'push')) {
                            $difficulties->push($virtual);
                        } else {
                            $difficulties[] = $virtual;
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore any error creating virtual difficulty
                }
            }
        }

        // --- event_item_types aggregation: sum hours per event_item_type_id ---
        $eventTypeSums = []; // event_item_type_id => hours
        try {
            $evItems = \App\Models\Event::where('user_id', $userId)
                ->whereBetween('starts_at', [$start, $end])
                ->get();
            foreach ($evItems as $ev) {
                $hours = 0.0;
                try {
                    if ($ev->starts_at && $ev->ends_at) {
                        $s = \Carbon\Carbon::parse($ev->starts_at);
                        $e2 = \Carbon\Carbon::parse($ev->ends_at);
                        $hours = max(0.0, $e2->diffInMinutes($s) / 60.0);
                    }
                } catch (\Throwable $evE) {}
                if ($hours <= 0) continue;
                $eid = $ev->event_item_type_id ?? null;
                if (!isset($eventTypeSums[$eid])) $eventTypeSums[$eid] = 0.0;
                $eventTypeSums[$eid] += $hours;
            }
        } catch (\Throwable $evErr) {}

        $eventTypeLabels = [];
        $eventTypeData   = [];
        $eventTypeCoefficients = [];
        $eventTypePoints = [];
        $eventTotalPoints = 0.0;
        if (count($eventTypeSums)) {
            $evTypes = EventItemType::orderBy('sort_order')->get()->keyBy('id');
            // include "未設定" for null key
            foreach ($eventTypeSums as $eid => $hours) {
                $hours = round($hours, 2);
                $label = ($eid && isset($evTypes[$eid])) ? $evTypes[$eid]->name : '未設定';
                $coeff = ($eid && isset($evTypes[$eid]) && isset($evTypes[$eid]->coefficient))
                    ? (float)$evTypes[$eid]->coefficient : 1.0;
                $pts   = round($hours * $coeff, 1);
                $eventTypeLabels[]      = $label;
                $eventTypeData[]        = $hours;
                $eventTypeCoefficients[] = $coeff;
                $eventTypePoints[]      = $pts;
                $eventTotalPoints       += $pts;
            }
        }
        $eventTotalPoints = round($eventTotalPoints, 1);

        // --- overtime distribution: 5固定バケット + 通常/超過日数 + ポイント ---
        $totalOvertimeMinutes  = 0;
        $overtimeDaysNormal    = 0; // ≤180min
        $overtimeDaysExcess    = 0; // >180min
        $overtimeNormalMinutes = 0;
        $overtimeExcessMinutes = 0;
        $overtimeLabels = ['〜1時間', '〜2時間', '〜3時間', '〜4時間', '4時間〜'];
        $overtimeCounts = [0, 0, 0, 0, 0];
        // load worktime coefficients
        $showWorktimeCoeffs  = WorktimeItemType::all();
        $showNormalCoeff     = 1.0;
        $showExcessCoeff     = 1.0;
        foreach ($showWorktimeCoeffs as $wt) {
            if ($wt->type === 'over') {
                if ($wt->name === '超過残業') $showExcessCoeff = (float) $wt->coefficient;
                elseif ($wt->name === '残業') $showNormalCoeff = (float) $wt->coefficient;
            }
        }
        try {
            $workRecords = WorkRecord::where('user_id', $userId)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get(['overtime_minutes']);
            $totalOvertimeMinutes = (int) $workRecords->sum('overtime_minutes');

            foreach ($workRecords as $wr) {
                $min = (int) ($wr->overtime_minutes ?? 0);
                if ($min <= 0) continue;
                if ($min <= 60)       { $overtimeCounts[0]++; $overtimeDaysNormal++; $overtimeNormalMinutes += $min; }
                elseif ($min <= 120)  { $overtimeCounts[1]++; $overtimeDaysNormal++; $overtimeNormalMinutes += $min; }
                elseif ($min <= 180)  { $overtimeCounts[2]++; $overtimeDaysNormal++; $overtimeNormalMinutes += $min; }
                elseif ($min <= 240)  { $overtimeCounts[3]++; $overtimeDaysExcess++; $overtimeExcessMinutes += $min; }
                else                  { $overtimeCounts[4]++; $overtimeDaysExcess++; $overtimeExcessMinutes += $min; }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $overtimeNormalPoints = round($overtimeNormalMinutes * $showNormalCoeff, 1);
        $overtimeExcessPoints = round($overtimeExcessMinutes * $showExcessCoeff, 1);
        $overtimeTotalPoints  = round($overtimeNormalPoints + $overtimeExcessPoints, 1);

        // --- Defensive normalization: ensure shapes are consistent ---
        // Guarantee stage labels and data are arrays
        $stage_labels_safe = is_array($stageLabels) ? $stageLabels : [];
        $stage_data_safe = is_array($stageData) ? $stageData : [];

        // Ensure stage_difficulty_rows is an array of arrays and each row has a value for every stage (pad with 0)
        $cols = count($stage_labels_safe);
        $stage_difficulty_rows_safe = [];
        foreach ($stageDifficultyRows as $row) {
            if (!is_array($row)) $row = (array)$row;
            // pad to expected column count
            for ($ci = 0; $ci < $cols; $ci++) {
                if (!array_key_exists($ci, $row) || $row[$ci] === null) {
                    $row[$ci] = 0;
                }
            }
            // reindex and cast to int
            $stage_difficulty_rows_safe[] = array_map(function ($v) {
                return is_numeric($v) ? (int)$v : 0;
            }, array_values($row));
        }

        // If there are no difficulty rows (e.g., no difficulties configured), create empty rows array
        if (empty($stage_difficulty_rows_safe)) {
            $stage_difficulty_rows_safe = [];
        }

        // Build an associative mapping keyed by difficulty id so the client can reliably
        // look up per-difficulty rows by id. This avoids any ordering mismatch between
        // difficulty objects and numeric-indexed rows after serialization.
        $stage_difficulty_rows_assoc = [];
        foreach ($difficultyIds as $idx => $did) {
            // ensure we have a row (pad with zeros if missing)
            $stage_difficulty_rows_assoc[$did] = $stage_difficulty_rows_safe[$idx] ?? array_fill(0, $cols, 0);
        }

        // Ensure stage_data_safe has a numeric value for each stage
        for ($si = 0; $si < $cols; $si++) {
            if (!array_key_exists($si, $stage_data_safe) || $stage_data_safe[$si] === null) {
                $stage_data_safe[$si] = 0;
            }
        }
        $stage_data_safe = array_map(function ($v) {
            return is_numeric($v) ? (int)$v : 0;
        }, array_values($stage_data_safe));

        // (debug logging consolidated later after calculations)

        // Calculate total points including difficulty coefficients (Rule B: sum raw then round final)
        $totalPointsRaw = 0.0;
        $totalAmount = 0;

        // maps for per-type and per-size raw point sums
        $typePointsMap = [];
        $sizePointsMap = [];

        // helper to resolve difficulty coefficient from an assignment (fallback to 1.0)
        $getDifficultyCoeff = function ($assignment) {
            try {
                $diff = $assignment->difficulty ?? null;
                // if not set on assignment, try linked project job
                if (!$diff && isset($assignment->projectJob) && isset($assignment->projectJob->difficulty)) {
                    $diff = $assignment->projectJob->difficulty;
                }

                if (!$diff) return 1.0;

                if (is_numeric($diff)) {
                    $d = \App\Models\Difficulty::find((int)$diff);
                    if ($d) return (float)$d->coefficient;
                }

                // try find by name
                $d = \App\Models\Difficulty::where('name', $diff)->first();
                if ($d) return (float)$d->coefficient;
            } catch (\Throwable $e) {
                // ignore and fallback
            }
            return 1.0;
        };

        // iterate assigned items
        foreach ($assignedItems as $a) {
            $pages = 0;
            if (isset($a->amounts) && $a->amounts && isset($a->amounts_unit) && $a->amounts_unit === 'page') {
                $pages = (int) $a->amounts;
            } elseif (isset($a->pages) && $a->pages) {
                $pages = (int) $a->pages;
            }
            if ($pages <= 0) continue;
            $sid = $a->stage_id ?? null;
            $stageCoeff = 1.0;
            if ($sid && isset($stages[$sid]) && isset($stages[$sid]->coefficient)) {
                $stageCoeff = (float)$stages[$sid]->coefficient;
            }
            $tid = $a->work_item_type_id ?? null;
            $typeCoeff = 1.0;
            if ($tid && isset($types[$tid]) && isset($types[$tid]->coefficient)) {
                $typeCoeff = (float)$types[$tid]->coefficient;
            }
            $z = $a->size_id ?? null;
            $sizeCoeff = 1.0;
            if ($z && isset($sizes[$z]) && isset($sizes[$z]->coefficient)) {
                $sizeCoeff = (float)$sizes[$z]->coefficient;
            }

            $diffCoeff = $getDifficultyCoeff($a);

            // accumulate raw (no per-item rounding)
            $rawStagePoints = $pages * $stageCoeff * $diffCoeff;
            $totalPointsRaw += $rawStagePoints;

            // per-type and per-size raw accumulation
            if (!isset($typePointsMap[$tid])) $typePointsMap[$tid] = 0.0;
            $typePointsMap[$tid] += $pages * $typeCoeff * $diffCoeff;

            if (!isset($sizePointsMap[$z])) $sizePointsMap[$z] = 0.0;
            $sizePointsMap[$z] += $pages * $sizeCoeff * $diffCoeff;

            $totalAmount += $pages;
        }

        // iterate self items
        foreach ($selfItems as $s) {
            $pages = 0;
            if (isset($s->amounts) && $s->amounts && isset($s->amounts_unit) && $s->amounts_unit === 'page') {
                $pages = (int) $s->amounts;
            } elseif (isset($s->pages) && $s->pages) {
                $pages = (int) $s->pages;
            }
            if ($pages <= 0) continue;
            $sid = $s->stage_id ?? null;
            $stageCoeff = 1.0;
            if ($sid && isset($stages[$sid]) && isset($stages[$sid]->coefficient)) {
                $stageCoeff = (float)$stages[$sid]->coefficient;
            }
            $tid = $s->work_item_type_id ?? null;
            $typeCoeff = 1.0;
            if ($tid && isset($types[$tid]) && isset($types[$tid]->coefficient)) {
                $typeCoeff = (float)$types[$tid]->coefficient;
            }
            $z = $s->size_id ?? null;
            $sizeCoeff = 1.0;
            if ($z && isset($sizes[$z]) && isset($sizes[$z]->coefficient)) {
                $sizeCoeff = (float)$sizes[$z]->coefficient;
            }

            $diffCoeff = $getDifficultyCoeff($s);

            // accumulate raw
            $rawStagePoints = $pages * $stageCoeff * $diffCoeff;
            $totalPointsRaw += $rawStagePoints;

            if (!isset($typePointsMap[$tid])) $typePointsMap[$tid] = 0.0;
            $typePointsMap[$tid] += $pages * $typeCoeff * $diffCoeff;

            if (!isset($sizePointsMap[$z])) $sizePointsMap[$z] = 0.0;
            $sizePointsMap[$z] += $pages * $sizeCoeff * $diffCoeff;

            $totalAmount += $pages;
        }

        // final rounding for overall
        $totalPoints = round($totalPointsRaw, 1);
        $score = $totalAmount ? round(($totalPoints / $totalAmount) * 100, 1) : 0;

        // compute type/size total points (rounded final)
        $typeTotalPoints = 0.0;
        foreach ($typePointsMap as $k => $v) {
            $typeTotalPoints += $v;
        }
        $typeTotalPoints = round($typeTotalPoints, 1);

        $sizeTotalPoints = 0.0;
        foreach ($sizePointsMap as $k => $v) {
            $sizeTotalPoints += $v;
        }
        $sizeTotalPoints = round($sizeTotalPoints, 1);

        // Build per-label point arrays for types and sizes (aligned with labels arrays)
        $typePointsArray = [];
        foreach ($typeLabels as $i => $lbl) {
            $tid = array_keys($typeSums)[$i] ?? null;
            $typePointsArray[] = isset($typePointsMap[$tid]) ? round($typePointsMap[$tid], 1) : 0;
        }

        $sizePointsArray = [];
        foreach ($sizeLabels as $i => $lbl) {
            $zid = array_keys($sizeSums)[$i] ?? null;
            $sizePointsArray[] = isset($sizePointsMap[$zid]) ? round($sizePointsMap[$zid], 1) : 0;
        }

        // -----------------------------------------------------------------
        // Team statistics: compute per-user total_points for comparison group
        // -----------------------------------------------------------------
        // Helper to compute total points for an arbitrary user (reuse local coeff maps)
        $computeUserPoints = function ($uid) use ($start, $end) {
            $pts = 0.0;
            $amt = 0;

            $aItems = ProjectJobAssignment::where(function ($q) use ($uid) {
                $q->where('user_id', $uid);
                if (Schema::hasColumn('project_job_assignments', 'assigned_to')) {
                    $q->orWhere('assigned_to', $uid);
                }
            })->where(function ($q) use ($start, $end) {
                if (Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                    $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                } else {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            })->get();

            $sItems = ProjectJobAssignmentByMyself::where('user_id', $uid)->where(function ($q) use ($start, $end) {
                if (Schema::hasColumn('project_job_assignment_by_myself', 'desired_start_date')) {
                    $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                } else {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            })->get();

            // local helper to resolve difficulty coefficient per-assignment
            $getDiff = function ($assignment) {
                try {
                    $diff = $assignment->difficulty ?? null;
                    if (!$diff && isset($assignment->projectJob) && isset($assignment->projectJob->difficulty)) {
                        $diff = $assignment->projectJob->difficulty;
                    }
                    if (!$diff) return 1.0;
                    if (is_numeric($diff)) {
                        $d = \App\Models\Difficulty::find((int)$diff);
                        if ($d) return (float)$d->coefficient;
                    }
                    $d = \App\Models\Difficulty::where('name', $diff)->first();
                    if ($d) return (float)$d->coefficient;
                } catch (\Throwable $e) {
                }
                return 1.0;
            };

            foreach ($aItems as $aI) {
                $pages = 0;
                if (isset($aI->amounts) && $aI->amounts && isset($aI->amounts_unit) && $aI->amounts_unit === 'page') {
                    $pages = (int)$aI->amounts;
                } elseif (isset($aI->pages) && $aI->pages) {
                    $pages = (int)$aI->pages;
                }
                if ($pages <= 0) continue;

                $sid = $aI->stage_id ?? null;
                $stageCoeff = 1.0;
                if ($sid && isset($stages[$sid]) && isset($stages[$sid]->coefficient)) {
                    $stageCoeff = (float)$stages[$sid]->coefficient;
                }

                $diffCoeff = $getDiff($aI);
                $pts += ($pages * $stageCoeff * $diffCoeff);
                $amt += $pages;
            }

            foreach ($sItems as $sI) {
                $pages = 0;
                if (isset($sI->amounts) && $sI->amounts && isset($sI->amounts_unit) && $sI->amounts_unit === 'page') {
                    $pages = (int)$sI->amounts;
                } elseif (isset($sI->pages) && $sI->pages) {
                    $pages = (int)$sI->pages;
                }
                if ($pages <= 0) continue;
                $sid = $sI->stage_id ?? null;
                $stageCoeff = 1.0;
                if ($sid && isset($stages[$sid]) && isset($stages[$sid]->coefficient)) {
                    $stageCoeff = (float)$stages[$sid]->coefficient;
                }
                $diffCoeff = $getDiff($sI);
                $pts += ($pages * $stageCoeff * $diffCoeff);
                $amt += $pages;
            }

            return ['total_points' => round($pts, 1), 'total_amount' => $amt];
        };

        // Helper: compute all 6 raw category scores for any user (used for group-wide percentile)
        $computeUserCategoryScores = function ($uid) use ($start, $end, $showNormalCoeff, $showExcessCoeff) {
            $s = ['stage' => 0.0, 'size' => 0.0, 'type' => 0.0, 'difficulty' => 0.0, 'event' => 0.0, 'overtime' => 0.0];
            try {
                $aItems = ProjectJobAssignment::where(function ($q) use ($uid) {
                    $q->where('user_id', $uid);
                    if (Schema::hasColumn('project_job_assignments', 'assigned_to')) {
                        $q->orWhere('assigned_to', $uid);
                    }
                })->where(function ($q) use ($start, $end) {
                    if (Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                        $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                    } else {
                        $q->whereBetween('created_at', [$start, $end]);
                    }
                })->get();
                $sItems = ProjectJobAssignmentByMyself::where('user_id', $uid)->where(function ($q) use ($start, $end) {
                    if (Schema::hasColumn('project_job_assignment_by_myself', 'desired_start_date')) {
                        $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                    } else {
                        $q->whereBetween('created_at', [$start, $end]);
                    }
                })->get();
                foreach ($aItems->concat($sItems) as $item) {
                    $pages = 0;
                    if (isset($item->amounts) && $item->amounts && isset($item->amounts_unit) && $item->amounts_unit === 'page') {
                        $pages = (int)$item->amounts;
                    } elseif (isset($item->pages) && $item->pages) {
                        $pages = (int)$item->pages;
                    }
                    if ($pages <= 0) continue;
                    $stageC = 1.0; $typeC = 1.0; $sizeC = 1.0; $diffC = 1.0;
                    try { if ($item->stage_id) { $st = \App\Models\Stage::find($item->stage_id); if ($st) $stageC = (float)$st->coefficient; } } catch (\Throwable $e) {}
                    try { if ($item->work_item_type_id) { $tp = \App\Models\WorkItemType::find($item->work_item_type_id); if ($tp) $typeC = (float)$tp->coefficient; } } catch (\Throwable $e) {}
                    try { if ($item->size_id) { $sz = \App\Models\Size::find($item->size_id); if ($sz) $sizeC = (float)$sz->coefficient; } } catch (\Throwable $e) {}
                    try {
                        $diff = $item->difficulty ?? null;
                        if ($diff) {
                            $dObj = is_numeric($diff) ? \App\Models\Difficulty::find((int)$diff) : \App\Models\Difficulty::where('name', $diff)->first();
                            if ($dObj) $diffC = (float)$dObj->coefficient;
                        }
                    } catch (\Throwable $e) {}
                    $s['stage']      += $pages * $stageC * $diffC;
                    $s['type']       += $pages * $typeC  * $diffC;
                    $s['size']       += $pages * $sizeC  * $diffC;
                    $s['difficulty'] += $pages * $diffC;
                }
            } catch (\Throwable $e) {}
            try {
                $evCoeffMap = EventItemType::pluck('coefficient', 'id')->toArray();
                $evItems = \App\Models\Event::where('user_id', $uid)->whereBetween('starts_at', [$start, $end])->get();
                foreach ($evItems as $ev) {
                    if (!$ev->starts_at || !$ev->ends_at) continue;
                    $hours = max(0.0, \Carbon\Carbon::parse($ev->ends_at)->diffInMinutes(\Carbon\Carbon::parse($ev->starts_at)) / 60.0);
                    if ($hours <= 0) continue;
                    $coeff = ($ev->event_item_type_id && isset($evCoeffMap[$ev->event_item_type_id])) ? (float)$evCoeffMap[$ev->event_item_type_id] : 1.0;
                    $s['event'] += $hours * $coeff;
                }
            } catch (\Throwable $e) {}
            try {
                $wrRecs = WorkRecord::where('user_id', $uid)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->get(['overtime_minutes']);
                $nm = 0; $xm = 0;
                foreach ($wrRecs as $wr) {
                    $min = (int)($wr->overtime_minutes ?? 0);
                    if ($min <= 0) continue;
                    if ($min <= 180) $nm += $min; else $xm += $min;
                }
                $s['overtime'] = $nm * $showNormalCoeff + $xm * $showExcessCoeff;
            } catch (\Throwable $e) {}
            foreach ($s as $k => $v) $s[$k] = round($v, 1);
            return $s;
        };

        // determine comparison group: prefer users in the same company as the viewed user
        $groupUserIds = [];
        try {
            $viewedUser = \App\Models\User::find($userId);
            if ($viewedUser && isset($viewedUser->company_id) && $viewedUser->company_id) {
                $groupUserIds = \App\Models\User::where('company_id', $viewedUser->company_id)->pluck('id')->toArray();
            }
        } catch (\Throwable $e) {
            $groupUserIds = [];
        }
        // fallback: collect users who have assignments in the period
        if (empty($groupUserIds)) {
            $p1 = ProjectJobAssignment::where(function ($q) use ($start, $end) {
                if (Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                    $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                } else {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            })->pluck('user_id')->toArray();
            // assigned_to column may not exist on older schemas — guard the pluck to avoid SQL errors
            if (Schema::hasColumn('project_job_assignments', 'assigned_to')) {
                $p2 = ProjectJobAssignment::where(function ($q) use ($start, $end) {
                    if (Schema::hasColumn('project_job_assignments', 'desired_start_date')) {
                        $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                    } else {
                        $q->whereBetween('created_at', [$start, $end]);
                    }
                })->pluck('assigned_to')->toArray();
            } else {
                $p2 = [];
            }
            $p3 = ProjectJobAssignmentByMyself::where(function ($q) use ($start, $end) {
                if (Schema::hasColumn('project_job_assignment_by_myself', 'desired_start_date')) {
                    $q->whereBetween('desired_start_date', [$start->toDateString(), $end->toDateString()]);
                } else {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            })->pluck('user_id')->toArray();
            $groupUserIds = array_filter(array_unique(array_merge($p1, $p2, $p3)));
            // ensure at least viewed user
            if (!in_array($userId, $groupUserIds)) $groupUserIds[] = $userId;
        }

        // compute per-user total_points for the group (raw, used for legacy team stats)
        $teamPoints = [];
        foreach ($groupUserIds as $gid) {
            $res = $computeUserPoints($gid);
            $teamPoints[$gid] = $res['total_points'];
        }

        // compute per-category scores for each group member and percentile for the viewed user
        // 【職種グループ別パーセンタイル】show ページでも同じ担当同士で比較する
        $showPCats = ['stage', 'size', 'type', 'difficulty', 'event', 'overtime'];
        $groupCatScores = [];
        foreach ($groupUserIds as $gid) {
            $groupCatScores[$gid] = $computeUserCategoryScores($gid);
        }
        if (!isset($groupCatScores[$userId])) {
            $groupCatScores[$userId] = $computeUserCategoryScores($userId);
        }

        // Collect assignment_name per user in comparison group
        $showAssignmentMap = [];
        try {
            $groupUsers = \App\Models\User::with('assignment')->whereIn('id', array_keys($groupCatScores))->get();
            foreach ($groupUsers as $gu) {
                $showAssignmentMap[$gu->id] = $gu->assignment->name ?? '';
            }
        } catch (\Throwable $e) {}

        // Determine peer UIDs for the viewed user: same assignment_name, N≥3 → role group; else company-wide
        $viewerAssignment = $showAssignmentMap[$userId] ?? '';
        $sameRoleUids = array_filter(array_keys($groupCatScores), fn($uid) => ($showAssignmentMap[$uid] ?? '') === $viewerAssignment);
        $sameRoleUids = array_values($sameRoleUids);
        $showComparisonLevel = count($sameRoleUids) >= 3 ? 'role' : 'department';
        $showPeerUids = $showComparisonLevel === 'role' ? $sameRoleUids : array_keys($groupCatScores);

        // Percentile for the viewed user within the chosen peer group
        $peerN = count($showPeerUids);
        $viewerPercentile = [];
        foreach ($showPCats as $cat) {
            $my = $groupCatScores[$userId][$cat] ?? 0;
            $above = 0; $tied = 0;
            foreach ($showPeerUids as $peerUid) {
                $ov = $groupCatScores[$peerUid][$cat] ?? 0;
                if ($ov > $my) $above++;
                elseif (abs($ov - $my) < 0.001) $tied++;
            }
            $avgRank = $above + ($tied + 1) / 2.0;
            $viewerPercentile[$cat] = $peerN === 1 ? 100.0 : max(0.0, round((($peerN - $avgRank) / ($peerN - 1)) * 100.0, 1));
        }
        $viewerPercentile['overall'] = round(array_sum(array_map(fn($cat) => $viewerPercentile[$cat], $showPCats)), 1);

        // per-category rank within the chosen peer group (1 = highest score)
        $categoryRanks = [];
        foreach ($showPCats as $cat) {
            $myScore = $groupCatScores[$userId][$cat] ?? 0;
            $rank = 1;
            foreach ($showPeerUids as $peerUid) {
                if (($groupCatScores[$peerUid][$cat] ?? 0) > $myScore) $rank++;
            }
            $categoryRanks[$cat] = $rank;
        }
        $teamValues = array_values($teamPoints);
        $teamCount = count($teamValues);
        $teamMean = $teamCount ? array_sum($teamValues) / $teamCount : 0.0;
        // population std dev
        $teamVar = 0.0;
        if ($teamCount) {
            $sumSq = 0.0;
            foreach ($teamValues as $v) {
                $d = $v - $teamMean;
                $sumSq += $d * $d;
            }
            $teamVar = $sumSq / $teamCount;
        }
        $teamStd = sqrt($teamVar);

        // compute viewed user's deviation score (偏差値 mean=50 sd=10)
        if ($teamStd > 0.000001) {
            $z = ($totalPoints - $teamMean) / $teamStd;
            $deviationScore = round(50 + $z * 10, 1);
        } else {
            $deviationScore = null; // not enough variance
        }

        // percentile: proportion of group with points <= viewed user's points
        $lte = 0;
        foreach ($teamValues as $v) {
            if ($v <= $totalPoints) $lte++;
        }
        $teamPercentile = $teamCount ? round(($lte / $teamCount) * 100, 1) : null;

        // Build ranking: sort users by points desc and assign dense ranks
        $ranking = [];
        // prepare array of [user_id, points]
        $pairs = [];
        foreach ($teamPoints as $uid => $pts) {
            $pairs[] = ['user_id' => $uid, 'points' => $pts];
        }
        usort($pairs, function ($a, $b) {
            if ($a['points'] == $b['points']) return 0;
            return ($a['points'] > $b['points']) ? -1 : 1;
        });

        $rank = 0;
        $prevPts = null;
        foreach ($pairs as $i => $p) {
            if ($prevPts === null || $p['points'] !== $prevPts) {
                $rank = $rank + 1;
                $prevPts = $p['points'];
            }
            $uid = $p['user_id'];
            // get user name if possible
            $u = null;
            try {
                $u = \App\Models\User::find($uid);
            } catch (\Throwable $e) {
                $u = null;
            }
            $userName = $u ? ($u->name ?? ('user-' . $uid)) : ('user-' . $uid);
            // compute deviation score per user (if teamStd available)
            if ($teamStd > 0.000001) {
                $zui = ($p['points'] - $teamMean) / $teamStd;
                $dev = round(50 + $zui * 10, 1);
            } else {
                $dev = null;
            }
            // percentile per user
            $lteCount = 0;
            foreach ($teamValues as $tv) {
                if ($tv <= $p['points']) $lteCount++;
            }
            $perc = $teamCount ? round(($lteCount / $teamCount) * 100, 1) : null;

            $ranking[] = [
                'user_id' => $uid,
                'name' => $userName,
                'total_points' => $p['points'],
                'deviation_score' => $dev,
                'percentile' => $perc,
                'rank' => $rank,
            ];
        }


        // --- DEBUG: log arrays so we can inspect what's actually being computed for the page ---
        try {
            \Illuminate\Support\Facades\Log::debug('WorkloadAnalyzer::show debug', [
                'user_id' => $userId,
                'stage_labels' => $stageLabels,
                'stage_data' => $stageData,
                'stage_coefficients' => $stageCoefficients,
                'type_labels' => $typeLabels,
                'type_data' => $typeData,
                'type_coefficients' => $typeCoefficients,
                'type_points' => $typePointsArray ?? [],
                'size_labels' => $sizeLabels,
                'size_data' => $sizeData,
                'size_coefficients' => $sizeCoefficients,
                'size_points' => $sizePointsArray ?? [],
                'difficulty_labels' => $difficultyLabels,
                'difficulty_data' => $difficultyData,
                'total_points' => $totalPoints,
                'total_amount' => $totalAmount,
            ]);
        } catch (\Throwable $e) {
            // ignore logging errors
        }

        $userName = null;
        try {
            if (isset($viewedUser) && $viewedUser) {
                $userName = $viewedUser->name ?? null;
            }
            if (!$userName) {
                $u = \App\Models\User::find($userId);
                if ($u) $userName = $u->name ?? null;
            }
        } catch (\Throwable $e) {}

        return Inertia::render('WorkloadAnalyzer/Show', [
            'user_id' => $userId,
            'user_name' => $userName,
            'selected_ym' => $ym,
            'totals' => $totals,
            // Provide normalized arrays to avoid client-side undefined indexing
            'stage_labels' => $stage_labels_safe,
            'stage_data' => $stage_data_safe,
            'stage_coefficients' => $stageCoefficients,
            'stage_ids' => $stageIds ?? [],
            'stage_difficulty_rows' => $stage_difficulty_rows_assoc ?? [],
            'total_points' => $totalPoints,
            'total_amount' => $totalAmount,
            'score' => $score,
            'type_labels' => $typeLabels ?? [],
            'type_data' => $typeData ?? [],
            'type_coefficients' => $typeCoefficients ?? [],
            'type_points' => $typePointsArray ?? [],
            'size_labels' => $sizeLabels ?? [],
            'size_data' => $sizeData ?? [],
            'size_coefficients' => $sizeCoefficients ?? [],
            'size_points' => $sizePointsArray ?? [],
            'type_points_map' => $typePointsMap ?? [],
            'size_points_map' => $sizePointsMap ?? [],
            'difficulty_labels' => $difficultyLabels ?? [],
            'difficulty_data' => $difficultyData ?? [],
            'difficulties' => $difficulties ?? [],
            'event_type_labels' => $eventTypeLabels ?? [],
            'event_type_data' => $eventTypeData ?? [],
            'event_type_coefficients' => $eventTypeCoefficients ?? [],
            'event_type_points' => $eventTypePoints ?? [],
            'event_total_points' => $eventTotalPoints ?? 0.0,
            // Team-level statistics for comparison (mean/std/devscore/percentile)
            'team_mean_points' => round($teamMean, 2),
            'team_std_points' => round($teamStd, 2),
            'team_count' => $teamCount,
            'deviation_score' => $deviationScore,
            'team_percentile' => $teamPercentile,
            'team_ranking' => $ranking,
            // overtime
            'total_overtime_minutes'       => $totalOvertimeMinutes ?? 0,
            'overtime_days_normal'         => $overtimeDaysNormal ?? 0,
            'overtime_days_excess'         => $overtimeDaysExcess ?? 0,
            'overtime_distribution_labels' => $overtimeLabels,
            'overtime_distribution_data'   => $overtimeCounts,
            'overtime_normal_points'       => $overtimeNormalPoints ?? 0.0,
            'overtime_excess_points'       => $overtimeExcessPoints ?? 0.0,
            'total_overtime_points'        => $overtimeTotalPoints ?? 0.0,
            'overtime_normal_coeff'        => $showNormalCoeff ?? 1.0,
            'overtime_excess_coeff'        => $showExcessCoeff ?? 1.0,
            // per-category percentile scores (0–100 each, 0–600 overall)
            'percentile_scores'            => $viewerPercentile ?? [],
            'category_ranks'               => $categoryRanks ?? [],
            'group_count'                  => $peerN ?? count($groupCatScores),
            // 比較グループ情報
            'comparison_level'             => $showComparisonLevel ?? 'department',
            'peer_group_size'              => $peerN ?? count($groupCatScores),
            'peer_assignment'              => $viewerAssignment ?? '',
        ]);
    }

    /**
     * Show analysis guide / explanation page
     */
    public function guide(Request $request)
    {
        $this->requireAdminPermission('workload_analysis');
        $this->requireLeaderPermission('workload_analysis');

        return Inertia::render('WorkloadAnalyzer/AnalysisGuide');
    }

    /**
     * Show settings page for coefficients
     */
    public function settings(Request $request)
    {
        $this->requireAdminPermission('workload_analysis');
        $this->requireLeaderPermission('workload_analysis');
        // log entry for diagnostics: help determine whether this handler is invoked
        try {
            \Illuminate\Support\Facades\Log::debug('WorkloadAnalyzerController::settings invoked', ['path' => $request->path(), 'method' => $request->method()]);
        } catch (\Throwable $e) {
            // ignore logging failures
        }

        // Load full lists of rows for each table so the UI can edit per-row coefficients
        $stages = Stage::orderBy('sort_order')->get(['id', 'name', 'coefficient'])->toArray();
        $sizes = Size::orderBy('id')->get(['id', 'name', 'coefficient'])->toArray();
        $types = WorkItemType::orderBy('id')->get(['id', 'name', 'coefficient'])->toArray();
        $difficulties = Difficulty::orderBy('sort_order')->get(['id', 'name', 'coefficient'])->toArray();
        $eventItemTypes = EventItemType::orderBy('sort_order')->get(['id', 'name', 'coefficient'])->toArray();
        $worktimeItemTypes = WorktimeItemType::orderBy('sort_order')->get(['id', 'name', 'coefficient', 'type'])->toArray();

        return Inertia::render('WorkloadAnalyzer/Settings', [
            'stages' => $stages,
            'sizes' => $sizes,
            'types' => $types,
            'difficulties' => $difficulties,
            'eventItemTypes' => $eventItemTypes,
            'worktimeItemTypes' => $worktimeItemTypes,
        ]);
    }

    /**
     * Persist coefficient settings to corresponding tables
     */
    public function saveSettings(Request $request)
    {
        $this->requireAdminPermission('workload_analysis');
        $this->requireLeaderPermission('workload_analysis');
        // Accept per-table payloads. Payload should be an object like { table: 'stages', rows: [{id:1, coefficient:1.25}, ...] }
        $payload = $request->all();

        // Validate basic shape
        if (!isset($payload['table']) || !isset($payload['rows']) || !is_array($payload['rows'])) {
            return redirect()->back()->with('error', '不正なリクエストです。');
        }

        $table = $payload['table'];
        $rows = $payload['rows'];

        // Allow only known tables
        $allowed = ['stages', 'sizes', 'types', 'difficulties', 'event_item_types', 'worktime_item_types'];
        if (!in_array($table, $allowed, true)) {
            return redirect()->back()->with('error', '不正なテーブル名です。');
        }

        // Validate each row: id present and coefficient numeric 0..3
        foreach ($rows as $r) {
            if (!isset($r['id']) || !isset($r['coefficient'])) {
                return redirect()->back()->with('error', '行データが不正です。');
            }
            if (!is_numeric($r['coefficient']) || $r['coefficient'] < 0 || $r['coefficient'] > 3) {
                return redirect()->back()->with('error', '係数は0〜3の範囲で指定してください。');
            }
        }

        DB::transaction(function () use ($table, $rows) {
            // Map table to model
            switch ($table) {
                case 'stages':
                    $model = Stage::class;
                    break;
                case 'sizes':
                    $model = Size::class;
                    break;
                case 'types':
                    $model = WorkItemType::class;
                    break;
                case 'difficulties':
                    $model = Difficulty::class;
                    break;
                case 'event_item_types':
                    $model = EventItemType::class;
                    break;
                case 'worktime_item_types':
                    $model = WorktimeItemType::class;
                    break;
                default:
                    $model = null;
            }

            if ($model) {
                foreach ($rows as $r) {
                    // use update where id matches
                    $id = (int)$r['id'];
                    $coeff = $r['coefficient'];
                    try {
                        $m = $model::find($id);
                        if ($m) {
                            $m->coefficient = $coeff;
                            $m->save();
                        }
                    } catch (\Throwable $e) {
                        // ignore per-row failures but continue
                    }
                }
            }
        });

        // If this was an XHR/Inertia request, return JSON to avoid a full redirect/navigation
        if ($request->wantsJson() || $request->header('X-Inertia')) {
            return response()->json(['success' => true, 'message' => '係数を保存しました。']);
        }

        return redirect()->back()->with('success', '係数を保存しました。');
    }
}
