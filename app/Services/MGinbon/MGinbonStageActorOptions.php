<?php

namespace App\Services\MGinbon;

use App\Models\MGinbon\MGinbonProject;
use App\Models\ProjectJob;
use App\Models\User;
use App\Services\ProjectJobAssigneeOptions;
use Illuminate\Support\Facades\DB;

class MGinbonStageActorOptions
{
    private const LIST_BY_STAGE = [
        'text_input' => 'drawing_text_operator',
        'drawing' => 'drawing_text_operator',
        'initial_operation' => 'composition_operator',
        'initial_check' => 'checker',
        'initial_text_proof' => 'proofreader',
        'reproof_operation' => 'composition_operator',
        'reproof_scan_check' => 'proofreader',
        'reproof_text_proof' => 'proofreader',
        'third_operation' => 'composition_operator',
        'third_proof' => 'proofreader',
        'client_return_operation' => 'composition_operator',
        'fourth_operation' => 'composition_operator',
        'fourth_proof' => 'proofreader',
        'fifth_operation' => 'composition_operator',
    ];

    public function __construct(private readonly ProjectJobAssigneeOptions $projectOptions) {}

    /** @return array{users: mixed, subcontractors: mixed, restricted: bool, value_list_code: ?string} */
    public function forStage(MGinbonProject $project, ProjectJob $projectJob, User $actor, string $stageCode): array
    {
        $base = $this->projectOptions->for($projectJob, $actor);
        $listCode = self::LIST_BY_STAGE[$stageCode] ?? null;
        if (! $listCode) return [...$base, 'restricted' => false, 'value_list_code' => null];

        $links = DB::connection('mginbon')->table('mginbon_value_list_items as items')
            ->join('mginbon_value_lists as lists', 'lists.id', '=', 'items.mginbon_value_list_id')
            ->where('lists.mginbon_project_id', $project->id)
            ->where('lists.code', $listCode)
            ->where('lists.is_active', true)->where('items.is_active', true)
            ->where(fn ($query) => $query->whereNotNull('items.linked_user_id')
                ->orWhereNotNull('items.linked_subcontractor_id'))
            ->orderBy('items.sort_order')->orderBy('items.id')
            ->get(['items.value', 'items.linked_user_id', 'items.linked_subcontractor_id']);

        if ($links->isEmpty()) return [...$base, 'restricted' => false, 'value_list_code' => $listCode];

        $userLabels = $links->whereNotNull('linked_user_id')->unique('linked_user_id')->mapWithKeys(fn ($row) => [(int) $row->linked_user_id => $row->value]);
        $subcontractorLabels = $links->whereNotNull('linked_subcontractor_id')->unique('linked_subcontractor_id')->mapWithKeys(fn ($row) => [(int) $row->linked_subcontractor_id => $row->value]);

        return [
            'users' => $base['users']->whereIn('id', $userLabels->keys())->map(fn ($row) => [...$row, 'actual_name' => $row['name'], 'name' => $userLabels[(int) $row['id']]])->values(),
            'subcontractors' => $base['subcontractors']->whereIn('id', $subcontractorLabels->keys())->map(fn ($row) => [...$row, 'actual_name' => $row['name'], 'name' => $subcontractorLabels[(int) $row['id']]])->values(),
            'restricted' => true,
            'value_list_code' => $listCode,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function forProject(MGinbonProject $project, ProjectJob $projectJob, User $actor): array
    {
        return collect(array_keys(self::LIST_BY_STAGE))->mapWithKeys(
            fn (string $stageCode) => [$stageCode => $this->forStage($project, $projectJob, $actor, $stageCode)]
        )->all();
    }

    public function allows(MGinbonProject $project, ProjectJob $projectJob, User $actor, string $stageCode, string $type, int $id): bool
    {
        $options = $this->forStage($project, $projectJob, $actor, $stageCode);
        $rows = $type === 'user' ? $options['users'] : $options['subcontractors'];

        return $rows->pluck('id')->contains($id);
    }
}
