<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Event;
use App\Models\PersonalWorkEntry;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

/** Local-only, identifiable trial records for the department work analysis. */
class PersonalWorkDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || DB::connection()->getDatabaseName() !== 'sunbwork') {
            throw new LogicException('PersonalWorkDemoSeeder is restricted to the local sunbwork database.');
        }

        $department = DB::table('departments')->where('code', 'INFO')->where('company_id', 2)->first();
        if (! $department) throw new LogicException('Local INFO department was not found.');

        $profiles = [
            ['dtp_a', '試用・組版A', 'operator', 'dtp', 'new_creation', 95, 75, 'ページ'],
            ['dtp_b', '試用・制作B', 'seisaku', 'dtp', 'new_creation', 65, 38, 'ページ'],
            ['dtp_c', '試用・組版C', 'operator', 'dtp', 'new_creation', 78, 51, 'ページ'],
            ['proof_a', '試用・校正A', 'kousei', 'kousei', 'proof_first', 90, 66, 'ページ'],
            ['proof_b', '試用・校正B', 'kousei', 'kousei', 'proof_revision_heavy', 70, 30, 'ページ'],
            ['proof_c', '試用・校正C', 'kousei', 'kousei', 'proof_first', 82, 42, 'ページ'],
            ['progress', '試用・進行管理', 'shinko', 'shinko', 'manuscript_check', 85, 6, '件'],
            ['progress_b', '試用・進行管理B', 'shinko', 'shinko', 'manuscript_check', 68, 4, '件'],
            ['office', '試用・事務制作', 'other', 'jimu', 'document_processing', 55, 12, '件'],
            ['office_b', '試用・事務B', 'other', 'jimu', 'document_processing', 73, 9, '件'],
            ['sales', '試用・営業', 'eigyo', 'eigyo', 'sales_proposal', 75, 3, '件'],
            ['plan_only', '試用・予定のみ', 'operator', 'dtp', 'revision', 0, 0, 'ページ'],
        ];

        $created = ['users' => 0, 'projects' => 0, 'plans' => 0, 'my_jobs' => 0];
        DB::transaction(function () use ($department, $profiles, &$created): void {
            Assignment::firstOrCreate(
                ['department_id' => $department->id, 'code' => 'eigyo'],
                ['name' => '営業', 'active' => true, 'sort_order' => 50]
            );
            foreach ($profiles as [$key, $name, $role, $category, $activity, $completion, $quantity, $unit]) {
                $email = "demo-work-{$key}@example.invalid";
                $assignmentId = DB::table('assignments')->where('department_id', $department->id)
                    ->where('code', $role)->value('id');
                if (! $assignmentId) throw new LogicException("Trial assignment {$role} was not found.");
                $permissionRole = str_starts_with($key, 'office') ? 'clerk' : 'user';
                $user = User::firstOrCreate(['email' => $email], [
                    'name' => $name, 'password' => Hash::make(Str::random(40)), 'user_role' => $permissionRole,
                    'company_id' => $department->company_id, 'department_id' => $department->id,
                    'assignment_id' => $assignmentId, 'is_ghost' => false,
                ]);
                if ($user->wasRecentlyCreated) $created['users']++;
                if ((int) $user->company_id !== (int) $department->company_id || (int) $user->department_id !== (int) $department->id) {
                    throw new LogicException("Trial user {$email} has an unexpected department.");
                }
                if ($user->user_role !== $permissionRole || (int) $user->assignment_id !== (int) $assignmentId) {
                    $user->update(['user_role' => $permissionRole, 'assignment_id' => $assignmentId]);
                }

                $project = ProjectJob::firstOrCreate(
                    ['title' => "【試用作業】{$name}・教材制作", 'company_id' => $department->company_id],
                    ['user_id' => $user->id, 'show_on_department_schedule' => true]
                );
                if ($project->wasRecentlyCreated) $created['projects']++;

                $index = 0;
                for ($date = Carbon::parse('2026-09-21'); $date->lte(Carbon::parse('2026-10-09')); $date->addDay()) {
                    if ($date->isWeekend()) continue;
                    $day = $date->toDateString();
                    $hasResult = $completion > 0 && (($index * 37 + strlen($key) * 13) % 100) < $completion;
                    $entry = PersonalWorkEntry::firstOrCreate(
                        ['user_id' => $user->id, 'work_date' => $day, 'title' => "{$name}の作業", 'planned_start_time' => '09:00'],
                        [
                            'company_id' => $department->company_id, 'department_id' => $department->id,
                            'project_job_id' => $project->id, 'work_category' => $category,
                            'activity_type' => $hasResult ? $activity : null, 'plan_slot' => 'hour',
                            'planned_end_time' => '10:30', 'entry_visibility' => 'department',
                            'result_note' => $hasResult ? "{$name}の担当分を確認し、次工程へ引き渡した" : null,
                            'actual_minutes' => $hasResult ? 90 : null,
                            'actual_quantity' => $hasResult ? $quantity + ($index % 4) * 3 : null,
                            'actual_unit' => $hasResult ? $unit : null,
                        ]
                    );
                    if ($entry->wasRecentlyCreated) $created['plans']++;

                    if (in_array($key, ['dtp_a', 'dtp_c', 'proof_a', 'proof_c', 'progress', 'progress_b'], true) && $index % 2 === 0) {
                        $secondActivity = str_starts_with($key, 'dtp') ? 'revision'
                            : (str_starts_with($key, 'proof') ? 'proof_revision_light' : 'progress_coordination');
                        $second = PersonalWorkEntry::firstOrCreate(
                            ['user_id' => $user->id, 'work_date' => $day, 'title' => "{$name}の追加作業", 'planned_start_time' => '13:00'],
                            [
                                'company_id' => $department->company_id, 'department_id' => $department->id,
                                'project_job_id' => $project->id, 'work_category' => $category,
                                'activity_type' => $secondActivity, 'plan_slot' => 'hour',
                                'planned_end_time' => '14:00', 'entry_visibility' => 'department',
                                'result_note' => "追加分の修正・確認を完了し、担当者に共有した",
                                'actual_minutes' => 60, 'actual_quantity' => $key === 'progress' ? 3 : 18 + $index,
                                'actual_unit' => $unit,
                            ]
                        );
                        if ($second->wasRecentlyCreated) $created['plans']++;
                    }

                    if (in_array($key, ['dtp_a', 'dtp_b', 'dtp_c'], true) && $index % 4 === 0) {
                        $jobTitle = "【試用作業】{$day} マイジョブ";
                        $assignment = ProjectJobAssignment::firstOrCreate(
                            ['project_job_id' => $project->id, 'user_id' => $user->id, 'title' => $jobTitle],
                            ['sender_id' => $user->id, 'company_id' => $department->company_id,
                                'department_id' => $department->id, 'job_type' => 'operator',
                                'desired_end_date' => $day, 'assigned' => true]
                        );
                        $event = Event::firstOrCreate(
                            ['user_id' => $user->id, 'project_job_assignment_id' => $assignment->id],
                            ['title' => $jobTitle, 'starts_at' => "{$day} 15:00:00", 'ends_at' => "{$day} 16:00:00"]
                        );
                        $result = PersonalWorkEntry::firstOrCreate(['source_event_id' => $event->id], [
                            'user_id' => $user->id, 'company_id' => $department->company_id,
                            'department_id' => $department->id, 'work_date' => $day,
                            'project_job_id' => $project->id, 'project_job_assignment_id' => $assignment->id,
                            'work_category' => 'dtp', 'activity_type' => 'revision', 'title' => $jobTitle,
                            'entry_visibility' => 'department', 'result_note' => '修正を終えて校正へ提出した',
                            'actual_minutes' => 60, 'actual_quantity' => 24 + $index,
                            'actual_unit' => 'ページ',
                        ]);
                        if ($result->wasRecentlyCreated) $created['my_jobs']++;
                    }
                    $index++;
                }
            }
        });

        $this->command?->info('Local trial data: '.json_encode($created, JSON_UNESCAPED_UNICODE));
    }
}
