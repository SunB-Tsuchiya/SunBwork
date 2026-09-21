<?php

namespace App\Services\MGinbon;

use App\Models\MGinbon\MGinbonImportBatch;
use App\Models\MGinbon\MGinbonImportRow;
use App\Models\MGinbon\MGinbonItem;
use App\Models\MGinbon\MGinbonItemSubject;
use App\Models\MGinbon\MGinbonMediaType;
use App\Models\MGinbon\MGinbonProductionUnit;
use App\Models\MGinbon\MGinbonStageDefinition;
use App\Models\MGinbon\MGinbonSubject;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MGinbonNormalizedDataPromotionService
{
    private const SUBJECTS = [
        'japanese' => ['name' => '国語', 'sort_order' => 10],
        'math' => ['name' => '算数', 'sort_order' => 20],
        'social' => ['name' => '社会', 'sort_order' => 30],
        'science' => ['name' => '理科', 'sort_order' => 40],
    ];

    private const STAGES = [
        'text_input' => ['文字入力', 'operation', 10],
        'drawing' => ['作図・スキャン', 'operation', 20],
        'initial_operation' => ['初校組版', 'operation', 30],
        'initial_check' => ['初校出稿前チェック', 'proof', 40],
        'initial_text_proof' => ['初校文字校正', 'proof', 50],
        'reproof_operation' => ['再校修正', 'operation', 60],
        'reproof_scan_check' => ['再校スキャン図校正', 'proof', 70],
        'reproof_text_proof' => ['再校文字校正', 'proof', 80],
        'third_operation' => ['三校修正', 'operation', 90],
        'third_proof' => ['三校赤字照合', 'proof', 100],
        'client_return_operation' => ['みくに戻り対応', 'operation', 110],
        'fourth_operation' => ['四校修正', 'operation', 120],
        'fourth_proof' => ['四校赤字照合', 'proof', 130],
        'fifth_operation' => ['五校修正', 'operation', 140],
    ];

    /** @return array<string, int> */
    public function promote(?int $batchId = null): array
    {
        $batch = $batchId
            ? MGinbonImportBatch::find($batchId)
            : MGinbonImportBatch::query()->latest('id')->first();

        if (! $batch || ! $batch->mginbon_project_id) {
            throw new RuntimeException('年度プロジェクトに接続された取込バッチがありません。');
        }

        $rows = MGinbonImportRow::query()
            ->where('mginbon_import_batch_id', $batch->id)
            ->whereNotNull('normalized_json')
            ->orderBy('source_row_number')
            ->get();

        $summary = [
            'rows' => $rows->count(), 'promoted' => 0, 'skipped' => 0,
            'units' => 0, 'items' => 0, 'subjects' => 0, 'tasks' => 0,
            'participants' => 0, 'milestones' => 0, 'measurements' => 0,
        ];

        DB::connection('mginbon')->transaction(function () use ($batch, $rows, &$summary) {
            $subjects = $this->seedSubjects();
            $mediaTypes = $this->seedMediaTypes($rows);
            $stages = $this->seedStages($batch->mginbon_project_id);

            foreach ($rows as $row) {
                if (MGinbonItem::query()->where('mginbon_import_row_id', $row->id)->exists()) {
                    $summary['skipped']++;
                    continue;
                }

                $data = $row->normalized_json;
                $unit = $this->resolveUnit($batch->mginbon_project_id, $row, $data);
                if ($unit->wasRecentlyCreated) {
                    $summary['units']++;
                }

                $mediaName = trim((string) ($data['media_type'] ?? '')) ?: '未分類';
                $item = MGinbonItem::create([
                    'mginbon_production_unit_id' => $unit->id,
                    'mginbon_media_type_id' => $mediaTypes[$mediaName],
                    'mginbon_import_row_id' => $row->id,
                    'publication_status' => $data['publication_status'] ?? null,
                    'note' => $data['note'] ?? null,
                    'review_status' => $row->resolution_status === 'candidate' ? 'draft' : 'review_required',
                ]);
                $summary['items']++;

                foreach (self::SUBJECTS as $subjectCode => $subjectDefinition) {
                    $subjectData = $data['subjects'][$subjectCode] ?? null;
                    if (! ($subjectData['active'] ?? false)) {
                        continue;
                    }

                    $itemSubject = MGinbonItemSubject::create([
                        'mginbon_item_id' => $item->id,
                        'mginbon_subject_id' => $subjects[$subjectCode],
                    ]);
                    $summary['subjects']++;
                    $this->createSubjectDetails($item, $itemSubject, $subjectData, $stages, $summary);
                }

                foreach (($data['shared_dates'] ?? []) as $code => $date) {
                    if ($date) {
                        DB::connection('mginbon')->table('mginbon_milestones')->insert([
                            'mginbon_item_id' => $item->id,
                            'mginbon_item_subject_id' => null,
                            'code' => $code,
                            'occurred_on' => $date,
                            'source' => 'legacy_import',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $summary['milestones']++;
                    }
                }

                $summary['promoted']++;
            }

            $batch->update([
                'status' => 'promoted_draft',
                'imported_at' => now(),
                'summary_json' => [...($batch->summary_json ?? []), 'promotion' => $summary],
            ]);
        });

        return $summary;
    }

    /** @return array<string, int> */
    private function seedSubjects(): array
    {
        $ids = [];
        foreach (self::SUBJECTS as $code => $definition) {
            $ids[$code] = MGinbonSubject::updateOrCreate(
                ['code' => $code],
                ['name' => $definition['name'], 'sort_order' => $definition['sort_order'], 'is_active' => true]
            )->id;
        }

        return $ids;
    }

    /** @param iterable<MGinbonImportRow> $rows @return array<string, int> */
    private function seedMediaTypes(iterable $rows): array
    {
        $names = collect($rows)->map(fn ($row) => trim((string) ($row->normalized_json['media_type'] ?? '')) ?: '未分類')
            ->unique()->values();
        $ids = [];
        foreach ($names as $index => $name) {
            $code = 'legacy_'.substr(hash('sha256', $name), 0, 16);
            $ids[$name] = MGinbonMediaType::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort_order' => ($index + 1) * 10, 'is_active' => true]
            )->id;
        }

        return $ids;
    }

    /** @return array<string, int> */
    private function seedStages(int $projectId): array
    {
        $ids = [];
        foreach (self::STAGES as $code => [$name, $activityType, $sortOrder]) {
            $ids[$code] = MGinbonStageDefinition::updateOrCreate(
                ['mginbon_project_id' => $projectId, 'code' => $code],
                ['name' => $name, 'activity_type' => $activityType, 'sort_order' => $sortOrder, 'is_active' => true]
            )->id;
        }

        return $ids;
    }

    /** @param array<string, mixed> $data */
    private function resolveUnit(int $projectId, MGinbonImportRow $row, array $data): MGinbonProductionUnit
    {
        $attributes = [
            'mginbon_project_id' => $projectId,
            'unit_type' => $data['unit_type'] ?? 'exam',
            'mikuni_code' => $data['mikuni_code'] ?: null,
            'n_code' => $data['n_code'] ?: null,
        ];

        // コードのない前書き・目次・奥付等は、同名でも別冊子の可能性があるため自動統合しない。
        if (! $attributes['mikuni_code'] && ! $attributes['n_code']) {
            return MGinbonProductionUnit::create([
                ...$attributes,
                'display_name' => $data['display_name'] ?: '名称未設定（取込行'.($row->source_row_number - 1).'）',
                'school_category' => $data['school_category'] ?? null,
                'n_category' => $data['n_category'] ?? null,
                'review_status' => 'review_required',
            ]);
        }

        return MGinbonProductionUnit::firstOrCreate($attributes, [
            'display_name' => $data['display_name'] ?: '名称未設定',
            'school_category' => $data['school_category'] ?? null,
            'n_category' => $data['n_category'] ?? null,
            'review_status' => $row->resolution_status === 'candidate' ? 'draft' : 'review_required',
        ]);
    }

    /** @param array<string, mixed> $subjectData @param array<string, int> $stages @param array<string, int> $summary */
    private function createSubjectDetails(
        MGinbonItem $item,
        MGinbonItemSubject $itemSubject,
        array $subjectData,
        array $stages,
        array &$summary
    ): void {
        foreach (($subjectData['measurements'] ?? []) as $measurement) {
            DB::connection('mginbon')->table('mginbon_work_measurements')->insert([
                'mginbon_item_subject_id' => $itemSubject->id,
                'work_type' => $measurement['work_type'],
                'execution_type' => $measurement['execution_type'],
                'quantity' => $measurement['quantity'] ?? 0,
                'unit' => 'point',
                'legacy_value' => $measurement['raw_value'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $summary['measurements']++;
        }

        foreach (($subjectData['dates'] ?? []) as $code => $date) {
            DB::connection('mginbon')->table('mginbon_milestones')->insert([
                'mginbon_item_id' => $item->id,
                'mginbon_item_subject_id' => $itemSubject->id,
                'code' => $code,
                'occurred_on' => $date,
                'source' => 'legacy_import',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $summary['milestones']++;
        }

        foreach ($stages as $stageCode => $stageId) {
            $actor = $subjectData['actors'][$stageCode] ?? null;
            $taskId = DB::connection('mginbon')->table('mginbon_stage_tasks')->insertGetId([
                'mginbon_item_id' => $item->id,
                'mginbon_item_subject_id' => $itemSubject->id,
                'mginbon_stage_definition_id' => $stageId,
                'status' => $actor ? 'legacy_completed' : 'not_started',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $summary['tasks']++;

            if ($actor && trim((string) ($actor['raw_value'] ?? '')) !== '') {
                DB::connection('mginbon')->table('mginbon_stage_task_participants')->insert([
                    'mginbon_stage_task_id' => $taskId,
                    'role_type' => self::STAGES[$stageCode][1],
                    'legacy_value' => $actor['raw_value'],
                    'resolution_status' => 'unresolved',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $summary['participants']++;
            }
        }
    }
}
