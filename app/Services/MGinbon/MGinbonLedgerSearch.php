<?php

namespace App\Services\MGinbon;

use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class MGinbonLedgerSearch
{
    private const METADATA_FIELDS = [
        'mikuni_code' => 'units.mikuni_code',
        'n_code' => 'units.n_code',
        'category' => 'units.n_category',
        'school_name' => 'units.display_name',
        'media' => 'media.name',
        'publication_status' => 'items.publication_status',
        'note' => 'items.note',
    ];

    private const SUBJECTS = ['japanese', 'math', 'social', 'science'];

    private const CONDITION_FIELDS = ['mikuni_code', 'n_code', 'category', 'school_name', 'media', 'publication_status', 'note', 'subject', 'milestone', 'actor', 'anomaly'];

    /** @return array<int, array{omit: bool, conditions: array<int, array<string, string>>}> */
    public function decode(?string $json): array
    {
        if ($json === null || trim($json) === '') return [];

        $decoded = json_decode($json, true);
        $requests = is_array($decoded) && ($decoded['version'] ?? null) === 2 ? ($decoded['requests'] ?? null) : $decoded;
        if (! is_array($requests) || count($requests) > 20) return [];

        return collect($requests)->map(function ($request) {
            $conditions = is_array($request['conditions'] ?? null) ? $request['conditions'] : [];

            return [
                'omit' => (bool) ($request['omit'] ?? false),
                'conditions' => collect($conditions)
                ->filter(fn ($condition) => is_array($condition)
                    && in_array((string) ($condition['field'] ?? ''), self::CONDITION_FIELDS, true)
                    && trim((string) ($condition['value'] ?? '')) !== ''
                    && in_array((string) ($condition['subject'] ?? ''), ['', ...self::SUBJECTS], true))
                ->take(100)
                ->map(fn ($condition) => [
                    'field' => mb_substr((string) ($condition['field'] ?? ''), 0, 40),
                    'code' => mb_substr((string) ($condition['code'] ?? ''), 0, 80),
                    'subject' => mb_substr((string) ($condition['subject'] ?? ''), 0, 20),
                    'value' => mb_substr(trim((string) ($condition['value'] ?? '')), 0, 100),
                ])->values()->all(),
            ];
        })->filter(fn ($request) => count($request['conditions']) > 0)->values()->all();
    }

    /**
     * 現在ページの各レコードについて、実際に一致した条件だけを返す。
     *
     * @param iterable<int> $itemIds
     * @param array<int, array{omit: bool, conditions: array<int, array<string, string>>}> $requests
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function matchDescriptors(Builder $baseQuery, iterable $itemIds, array $requests): array
    {
        $ids = collect($itemIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty() || $requests === []) return [];

        $descriptors = [];
        $seen = [];
        foreach ($requests as $requestIndex => $request) {
            foreach ($request['conditions'] as $condition) {
                $key = json_encode([$request['omit'], $condition], JSON_UNESCAPED_UNICODE);
                if (isset($seen[$key]) || count($seen) >= 50) continue;
                $seen[$key] = true;
                $query = clone $baseQuery;
                $query->whereIn('items.id', $ids);
                $this->applyCondition($query, $condition);
                foreach ($query->reorder()->distinct()->pluck('items.id') as $itemId) {
                    $descriptors[(int) $itemId][] = [
                        'request_index' => $requestIndex,
                        'omit' => (bool) $request['omit'],
                        ...$condition,
                    ];
                }
            }
        }

        return $descriptors;
    }

    /** @param array<int, array{omit: bool, conditions: array<int, array<string, string>>}> $requests */
    public function apply(Builder $query, array $requests): Builder
    {
        if ($requests === []) return $query;

        $included = array_values(array_filter($requests, fn ($request) => ! $request['omit']));
        $omitted = array_values(array_filter($requests, fn ($request) => $request['omit']));

        if ($included !== []) {
            $query->where(function (Builder $outer) use ($included) {
                foreach ($included as $request) {
                    $outer->orWhere(function (Builder $requestQuery) use ($request) {
                        foreach ($request['conditions'] as $condition) $this->applyCondition($requestQuery, $condition);
                    });
                }
            });
        }

        if ($omitted !== []) {
            $query->whereNot(function (Builder $outer) use ($omitted) {
                foreach ($omitted as $request) {
                    $outer->orWhere(function (Builder $requestQuery) use ($request) {
                        foreach ($request['conditions'] as $condition) $this->applyCondition($requestQuery, $condition);
                    });
                }
            });
        }

        return $query;
    }

    /** @param array<string, string> $condition */
    private function applyCondition(Builder $query, array $condition): void
    {
        $field = $condition['field'];
        $rawValue = $condition['value'];

        if (isset(self::METADATA_FIELDS[$field])) {
            $this->applySearchValue($query, self::METADATA_FIELDS[$field], $rawValue);
            return;
        }

        if ($field === 'anomaly' && $condition['code'] !== '') {
            $this->applyAnomaly($query, $condition['code']);
            return;
        }

        if ($field === 'subject' && in_array($condition['subject'], self::SUBJECTS, true)) {
            $query->whereExists(function (Builder $inner) use ($condition) {
                $inner->selectRaw('1')
                    ->from('mginbon_item_subjects as find_item_subjects')
                    ->join('mginbon_subjects as find_subjects', 'find_subjects.id', '=', 'find_item_subjects.mginbon_subject_id')
                    ->whereColumn('find_item_subjects.mginbon_item_id', 'items.id')
                    ->where('find_subjects.code', $condition['subject']);
            });
            return;
        }

        if ($field === 'milestone' && $condition['code'] !== '') {
            if ($rawValue === '=') {
                $query->whereNotExists(function (Builder $inner) use ($condition) {
                    $inner->selectRaw('1')->from('mginbon_milestones as find_milestones')
                        ->whereColumn('find_milestones.mginbon_item_id', 'items.id')
                        ->where('find_milestones.code', $condition['code'])
                        ->whereNotNull('find_milestones.occurred_on');
                    $this->applySubjectScope($inner, $condition['subject'], 'find_milestones.mginbon_item_subject_id');
                });
                return;
            }
            $query->whereExists(function (Builder $inner) use ($condition, $rawValue) {
                $inner->selectRaw('1')->from('mginbon_milestones as find_milestones')
                    ->whereColumn('find_milestones.mginbon_item_id', 'items.id')
                    ->where('find_milestones.code', $condition['code']);
                $this->applySearchValue($inner, 'find_milestones.occurred_on', $rawValue, true);
                $this->applySubjectScope($inner, $condition['subject'], 'find_milestones.mginbon_item_subject_id');
            });
            return;
        }

        if ($field === 'actor' && $condition['code'] !== '') {
            $actorTerm = $this->plainSearchTerm($rawValue);
            $userIds = User::query()->where('name', 'like', "%{$actorTerm}%")->pluck('id');
            $subcontractorIds = Subcontractor::query()->where('name', 'like', "%{$actorTerm}%")->pluck('id');
            $query->whereExists(function (Builder $inner) use ($condition, $rawValue, $userIds, $subcontractorIds) {
                $inner->selectRaw('1')->from('mginbon_stage_tasks as find_tasks')
                    ->join('mginbon_stage_definitions as find_stages', 'find_stages.id', '=', 'find_tasks.mginbon_stage_definition_id')
                    ->leftJoin('mginbon_stage_task_participants as find_participants', 'find_participants.mginbon_stage_task_id', '=', 'find_tasks.id')
                    ->leftJoin('mginbon_work_package_tasks as find_package_tasks', 'find_package_tasks.mginbon_stage_task_id', '=', 'find_tasks.id')
                    ->leftJoin('mginbon_work_packages as find_packages', 'find_packages.id', '=', 'find_package_tasks.mginbon_work_package_id')
                    ->whereColumn('find_tasks.mginbon_item_id', 'items.id')
                    ->where('find_stages.code', $condition['code'])
                    ->where(function (Builder $actorQuery) use ($rawValue, $userIds, $subcontractorIds) {
                        $this->applySearchValue($actorQuery, 'find_participants.legacy_value', $rawValue);
                        if ($userIds->isNotEmpty()) $actorQuery->orWhereIn('find_packages.user_id', $userIds);
                        if ($subcontractorIds->isNotEmpty()) $actorQuery->orWhereIn('find_packages.subcontractor_id', $subcontractorIds);
                    });
                $this->applySubjectScope($inner, $condition['subject'], 'find_tasks.mginbon_item_subject_id');
            });
        }
    }

    private function applyAnomaly(Builder $query, string $code): void
    {
        $datePairs = [
            'initial_return_before_shared' => ['initial_returned_on', 'initial_shared_on'],
            'reproof_return_before_shared' => ['reproof_returned_on', 'reproof_shared_on'],
        ];
        if (isset($datePairs[$code])) {
            [$returnCode, $sharedCode] = $datePairs[$code];
            $query->whereExists(function (Builder $inner) use ($returnCode, $sharedCode) {
                $inner->selectRaw('1')->from('mginbon_milestones as anomaly_return')
                    ->join('mginbon_milestones as anomaly_shared', function ($join) use ($sharedCode) {
                        $join->on('anomaly_shared.mginbon_item_id', '=', 'anomaly_return.mginbon_item_id')
                            ->where('anomaly_shared.code', $sharedCode);
                    })
                    ->whereColumn('anomaly_return.mginbon_item_id', 'items.id')
                    ->where('anomaly_return.code', $returnCode)
                    ->whereColumn('anomaly_return.occurred_on', '<', 'anomaly_shared.occurred_on');
            });
            return;
        }
        if ($code === 'stale_30_days') {
            $query->where('items.updated_at', '<', now()->subDays(30));
            return;
        }
        if ($code === 'missing_actor') {
            $query->whereExists(function (Builder $inner) {
                $inner->selectRaw('1')->from('mginbon_stage_tasks as anomaly_tasks')
                    ->whereColumn('anomaly_tasks.mginbon_item_id', 'items.id')
                    ->whereNotExists(function (Builder $assigned) {
                        $assigned->selectRaw('1')->from('mginbon_work_package_tasks as anomaly_links')
                            ->whereColumn('anomaly_links.mginbon_stage_task_id', 'anomaly_tasks.id');
                    });
            });
            return;
        }
        if ($code === 'completed_with_missing_steps') {
            $query->whereExists(function (Builder $done) {
                $done->selectRaw('1')->from('mginbon_milestones as anomaly_done')
                    ->whereColumn('anomaly_done.mginbon_item_id', 'items.id')->where('anomaly_done.code', 'completed_on');
            })->whereNotExists(function (Builder $required) {
                $required->selectRaw('1')->from('mginbon_milestones as anomaly_required')
                    ->whereColumn('anomaly_required.mginbon_item_id', 'items.id')
                    ->whereIn('anomaly_required.code', ['manuscript_received_on', 'initial_shared_on', 'reproof_shared_on'])
                    ->groupBy('anomaly_required.mginbon_item_id')->havingRaw('COUNT(DISTINCT anomaly_required.code) = 3');
            });
            return;
        }
        if ($code === 'proof_completed_before_started') {
            $pairs = [
                ['initial_text_proof_completed_on', 'initial_text_proof_started_on'],
                ['reproof_scan_check_completed_on', 'reproof_scan_check_started_on'],
                ['reproof_text_proof_completed_on', 'reproof_text_proof_started_on'],
            ];
            $query->where(function (Builder $outer) use ($pairs) {
                foreach ($pairs as [$completed, $started]) {
                    $outer->orWhereExists(function (Builder $inner) use ($completed, $started) {
                        $inner->selectRaw('1')->from('mginbon_milestones as anomaly_completed')
                            ->join('mginbon_milestones as anomaly_started', function ($join) use ($started) {
                                $join->on('anomaly_started.mginbon_item_id', '=', 'anomaly_completed.mginbon_item_id')
                                    ->whereColumn('anomaly_started.mginbon_item_subject_id', 'anomaly_completed.mginbon_item_subject_id')
                                    ->where('anomaly_started.code', $started);
                            })->whereColumn('anomaly_completed.mginbon_item_id', 'items.id')
                            ->where('anomaly_completed.code', $completed)
                            ->whereColumn('anomaly_completed.occurred_on', '<', 'anomaly_started.occurred_on');
                    });
                }
            });
            return;
        }
        if ($code === 'multiple_active_actor') {
            $query->whereExists(function (Builder $inner) {
                $inner->selectRaw('1')->from('mginbon_stage_tasks as anomaly_tasks')
                    ->join('mginbon_work_package_tasks as anomaly_links', 'anomaly_links.mginbon_stage_task_id', '=', 'anomaly_tasks.id')
                    ->join('mginbon_work_packages as anomaly_packages', 'anomaly_packages.id', '=', 'anomaly_links.mginbon_work_package_id')
                    ->whereColumn('anomaly_tasks.mginbon_item_id', 'items.id')
                    ->whereNotIn('anomaly_packages.status', ['completed', 'legacy_completed', 'cancelled'])
                    ->groupBy('anomaly_tasks.id')->havingRaw('COUNT(DISTINCT anomaly_packages.id) > 1');
            });
            return;
        }
        if ($code === 'duplicate_item_subject') {
            $query->whereExists(function (Builder $inner) {
                $inner->selectRaw('1')->from('mginbon_items as anomaly_duplicate_items')
                    ->join('mginbon_item_subjects as anomaly_current_subjects', 'anomaly_current_subjects.mginbon_item_id', '=', 'items.id')
                    ->join('mginbon_item_subjects as anomaly_duplicate_subjects', function ($join) {
                        $join->on('anomaly_duplicate_subjects.mginbon_item_id', '=', 'anomaly_duplicate_items.id')
                            ->on('anomaly_duplicate_subjects.mginbon_subject_id', '=', 'anomaly_current_subjects.mginbon_subject_id');
                    })->whereColumn('anomaly_duplicate_items.mginbon_production_unit_id', 'items.mginbon_production_unit_id')
                    ->whereColumn('anomaly_duplicate_items.mginbon_media_type_id', 'items.mginbon_media_type_id')
                    ->whereColumn('anomaly_duplicate_items.id', '<>', 'items.id');
            });
            return;
        }
        if ($code === 'inactive_actor_reference') {
            $userIds = DB::connection('mginbon')->table('mginbon_work_packages')->whereNotNull('user_id')->distinct()->pluck('user_id');
            $subcontractorIds = DB::connection('mginbon')->table('mginbon_work_packages')->whereNotNull('subcontractor_id')->distinct()->pluck('subcontractor_id');
            $invalidUsers = $userIds->diff(User::withGhosts()->whereIn('id', $userIds)->where('is_ghost', false)->pluck('id'));
            $invalidSubcontractors = $subcontractorIds->diff(Subcontractor::query()->whereIn('id', $subcontractorIds)->pluck('id'));
            $query->whereExists(function (Builder $inner) use ($invalidUsers, $invalidSubcontractors) {
                $inner->selectRaw('1')->from('mginbon_work_packages as anomaly_packages')
                    ->whereColumn('anomaly_packages.mginbon_item_id', 'items.id')
                    ->where(function (Builder $actor) use ($invalidUsers, $invalidSubcontractors) {
                        if ($invalidUsers->isNotEmpty()) $actor->orWhereIn('anomaly_packages.user_id', $invalidUsers);
                        if ($invalidSubcontractors->isNotEmpty()) $actor->orWhereIn('anomaly_packages.subcontractor_id', $invalidSubcontractors);
                        if ($invalidUsers->isEmpty() && $invalidSubcontractors->isEmpty()) $actor->whereRaw('1 = 0');
                    });
            });
            return;
        }
        if ($code === 'previous_done_next_missing') {
            $pairs = [['manuscript_received_on', 'initial_shared_on'], ['initial_shared_on', 'initial_returned_on'], ['initial_returned_on', 'reproof_shared_on'], ['reproof_shared_on', 'reproof_returned_on']];
            $query->where(function (Builder $outer) use ($pairs) {
                foreach ($pairs as [$previous, $next]) {
                    $outer->orWhere(function (Builder $pair) use ($previous, $next) {
                        $pair->whereExists(fn (Builder $exists) => $exists->selectRaw('1')->from('mginbon_milestones as anomaly_previous')->whereColumn('anomaly_previous.mginbon_item_id', 'items.id')->where('anomaly_previous.code', $previous))
                            ->whereNotExists(fn (Builder $missing) => $missing->selectRaw('1')->from('mginbon_milestones as anomaly_next')->whereColumn('anomaly_next.mginbon_item_id', 'items.id')->where('anomaly_next.code', $next));
                    });
                }
            });
            return;
        }
        if ($code === 'overdue_pending') {
            $query->whereExists(fn (Builder $due) => $due->selectRaw('1')->from('mginbon_milestones as anomaly_due')->whereColumn('anomaly_due.mginbon_item_id', 'items.id')->where('anomaly_due.code', 'manuscript_due_on')->whereDate('anomaly_due.occurred_on', '<', now()->toDateString()))
                ->whereNotExists(fn (Builder $done) => $done->selectRaw('1')->from('mginbon_milestones as anomaly_received')->whereColumn('anomaly_received.mginbon_item_id', 'items.id')->where('anomaly_received.code', 'manuscript_received_on'));
            return;
        }
        if ($code === 'missing_stage_task') {
            $query->whereExists(function (Builder $subject) {
                $subject->selectRaw('1')->from('mginbon_item_subjects as anomaly_subjects')
                    ->whereColumn('anomaly_subjects.mginbon_item_id', 'items.id')
                    ->whereExists(function (Builder $stage) {
                        $stage->selectRaw('1')->from('mginbon_stage_definitions as anomaly_stages')
                            ->join('mginbon_production_units as anomaly_units', 'anomaly_units.mginbon_project_id', '=', 'anomaly_stages.mginbon_project_id')
                            ->whereColumn('anomaly_units.id', 'items.mginbon_production_unit_id')->where('anomaly_stages.is_active', true)
                            ->whereNotExists(fn (Builder $task) => $task->selectRaw('1')->from('mginbon_stage_tasks as anomaly_existing_tasks')->whereColumn('anomaly_existing_tasks.mginbon_item_id', 'items.id')->whereColumn('anomaly_existing_tasks.mginbon_item_subject_id', 'anomaly_subjects.id')->whereColumn('anomaly_existing_tasks.mginbon_stage_definition_id', 'anomaly_stages.id'));
                    });
            });
            return;
        }
        $query->whereRaw('1 = 0');
    }

    private function applySearchValue(Builder $query, string $column, string $rawValue, bool $date = false): void
    {
        $rawValue = trim($rawValue);
        if ($date && str_starts_with($rawValue, 'relative:')) {
            $rawValue = $this->resolveRelativeDate($rawValue);
        }
        if ($date && $rawValue === '//') {
            $query->whereDate($column, now()->toDateString());
            return;
        }
        if ($date && $rawValue === '?') {
            $query->whereNotNull($column)->whereRaw("{$column} NOT REGEXP ?", ['^[0-9]{4}-[0-9]{2}-[0-9]{2}$']);
            return;
        }
        if ($rawValue === '!') {
            $this->applyDuplicateValue($query, $column);
            return;
        }

        if (str_contains($rawValue, '...')) {
            [$from, $to] = array_pad(explode('...', $rawValue, 2), 2, '');
            if ($from !== '') $query->where($column, '>=', $this->normalizeComparable($from, $date));
            if ($to !== '') $query->where($column, '<=', $this->normalizeComparable($to, $date));
            return;
        }

        foreach ([['≤', '<='], ['>=', '>='], ['≥', '>='], ['<=', '<='], ['<', '<'], ['>', '>']] as [$prefix, $operator]) {
            if (str_starts_with($rawValue, $prefix)) {
                $query->where($column, $operator, $this->normalizeComparable(substr($rawValue, strlen($prefix)), $date));
                return;
            }
        }
        if (str_starts_with($rawValue, '==')) {
            $query->where($column, '=', $this->normalizeComparable(substr($rawValue, 2), $date));
            return;
        }
        if (str_starts_with($rawValue, '=')) {
            $word = $this->normalizeComparable(substr($rawValue, 1), $date);
            if ($word === '') {
                $query->where(function (Builder $empty) use ($column) {
                    $empty->whereNull($column)->orWhere($column, '');
                });
                return;
            }
            $term = preg_quote($word, '/');
            $query->whereRaw("{$column} REGEXP ?", ["(^|[^[:alnum:]]){$term}([^[:alnum:]]|$)"]);
            return;
        }

        if ($date) $rawValue = $this->normalizeComparable($rawValue, true);
        $term = $this->plainSearchTerm($rawValue);
        if (strpbrk($rawValue, '*@#') !== false) {
            $regex = '';
            $escaped = false;
            foreach (mb_str_split($term) as $character) {
                if ($escaped) { $regex .= preg_quote($character, '/'); $escaped = false; continue; }
                if ($character === '\\' || $character === '¥') { $escaped = true; continue; }
                $regex .= match ($character) { '*' => '.*', '@' => '.', '#' => '[0-9]', default => preg_quote($character, '/') };
            }
            $query->whereRaw("{$column} REGEXP ?", [$regex]);
            return;
        }

        $value = addcslashes($term, '\\%_');
        $query->where($column, 'like', "%{$value}%");
    }

    private function resolveRelativeDate(string $token): string
    {
        $today = now()->startOfDay();
        return match ($token) {
            'relative:today' => $today->toDateString(),
            'relative:yesterday' => $today->copy()->subDay()->toDateString(),
            'relative:tomorrow' => $today->copy()->addDay()->toDateString(),
            'relative:this_week' => $today->copy()->startOfWeek()->toDateString().'...'.$today->copy()->endOfWeek()->toDateString(),
            'relative:next_week' => $today->copy()->addWeek()->startOfWeek()->toDateString().'...'.$today->copy()->addWeek()->endOfWeek()->toDateString(),
            'relative:this_month' => $today->copy()->startOfMonth()->toDateString().'...'.$today->copy()->endOfMonth()->toDateString(),
            'relative:before_today' => '<='.$today->toDateString(),
            'relative:after_today' => '>='.$today->toDateString(),
            default => $this->resolveRelativeDayCount($token, $today),
        };
    }

    private function resolveRelativeDayCount(string $token, $today): string
    {
        if (preg_match('/^relative:past_days:(\d{1,3})$/', $token, $matches)) {
            $days = min(365, max(1, (int) $matches[1]));
            return $today->copy()->subDays($days)->toDateString().'...'.$today->toDateString();
        }
        if (preg_match('/^relative:next_days:(\d{1,3})$/', $token, $matches)) {
            $days = min(365, max(1, (int) $matches[1]));
            return $today->toDateString().'...'.$today->copy()->addDays($days)->toDateString();
        }
        return $token;
    }

    private function applyDuplicateValue(Builder $query, string $column): void
    {
        $metadataColumns = [
            'units.mikuni_code' => 'duplicate_units.mikuni_code',
            'units.n_code' => 'duplicate_units.n_code',
            'units.n_category' => 'duplicate_units.n_category',
            'units.display_name' => 'duplicate_units.display_name',
            'media.name' => 'duplicate_media.name',
            'items.publication_status' => 'duplicate_items.publication_status',
            'items.note' => 'duplicate_items.note',
        ];

        if (isset($metadataColumns[$column])) {
            $duplicateColumn = $metadataColumns[$column];
            $query->whereIn($column, function (Builder $duplicates) use ($duplicateColumn) {
                $duplicates->select($duplicateColumn)
                    ->from('mginbon_items as duplicate_items')
                    ->join('mginbon_production_units as duplicate_units', 'duplicate_units.id', '=', 'duplicate_items.mginbon_production_unit_id')
                    ->join('mginbon_media_types as duplicate_media', 'duplicate_media.id', '=', 'duplicate_items.mginbon_media_type_id')
                    ->whereNotNull($duplicateColumn)
                    ->where($duplicateColumn, '<>', '')
                    ->groupBy($duplicateColumn)
                    ->havingRaw('COUNT(*) > 1');
            });
            return;
        }

        if ($column === 'find_milestones.occurred_on') {
            $query->whereIn($column, function (Builder $duplicates) {
                $duplicates->select('duplicate_milestones.occurred_on')
                    ->from('mginbon_milestones as duplicate_milestones')
                    ->whereNotNull('duplicate_milestones.occurred_on')
                    ->groupBy('duplicate_milestones.occurred_on')
                    ->havingRaw('COUNT(*) > 1');
            });
            return;
        }

        $query->whereNotNull($column)->where($column, '<>', '');
    }

    private function normalizeComparable(string $value, bool $date): string
    {
        $value = trim($value);
        if ($date && preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/', $value, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }
        if ($date && preg_match('/^(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
            return sprintf('%02d-%02d', (int) $matches[1], (int) $matches[2]);
        }
        return $value;
    }

    private function plainSearchTerm(string $value): string
    {
        $value = ltrim($value, '~');
        if (str_starts_with($value, '*"')) $value = substr($value, 2);
        elseif (str_starts_with($value, '"')) $value = substr($value, 1);
        return rtrim($value, '"');
    }

    private function applySubjectScope(Builder $query, string $subjectCode, string $subjectIdColumn): void
    {
        if (! in_array($subjectCode, self::SUBJECTS, true)) return;

        $query->whereExists(function (Builder $inner) use ($subjectCode, $subjectIdColumn) {
            $inner->selectRaw('1')->from('mginbon_item_subjects as find_scope_subjects')
                ->join('mginbon_subjects as find_scope_subject_codes', 'find_scope_subject_codes.id', '=', 'find_scope_subjects.mginbon_subject_id')
                ->whereColumn('find_scope_subjects.id', $subjectIdColumn)
                ->where('find_scope_subject_codes.code', $subjectCode);
        });
    }
}
