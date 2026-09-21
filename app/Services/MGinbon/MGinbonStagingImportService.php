<?php

namespace App\Services\MGinbon;

use App\Models\MGinbon\MGinbonImportBatch;
use App\Models\MGinbon\MGinbonImportRow;
use App\Models\MGinbon\MGinbonProject;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MGinbonStagingImportService
{
    public function __construct(
        private readonly FileMakerMergeReader $reader,
        private readonly MGinbonImportPreviewService $previewService,
    ) {}

    /** @return array<string, int|string> */
    public function import(string $path, int $year, ?int $userId = null): array
    {
        $preview = $this->previewService->preview($path);
        $existing = MGinbonImportBatch::query()
            ->where('source_year', $year)
            ->where('source_sha256', $preview['sha256'])
            ->first();

        if ($existing) {
            throw new RuntimeException("同じファイルは取込済みです（batch: {$existing->id}）。");
        }

        return DB::connection('mginbon')->transaction(function () use ($path, $year, $userId, $preview) {
            $project = MGinbonProject::firstOrCreate(
                ['year' => $year],
                [
                    'name' => "{$year}年 中学入試問題集",
                    'starts_on' => "{$year}-01-01",
                    'ends_on' => "{$year}-06-30",
                    'status' => 'draft',
                    'created_by' => $userId,
                ]
            );

            $batch = MGinbonImportBatch::create([
                'mginbon_project_id' => $project->id,
                'source_filename' => basename($path),
                'source_sha256' => $preview['sha256'],
                'source_encoding' => 'CP932',
                'source_year' => $year,
                'status' => 'staging',
                'record_count' => $preview['records'],
                'column_count' => $preview['columns'],
                'summary_json' => $preview,
                'previewed_at' => now(),
                'created_by' => $userId,
            ]);

            $parsed = $this->reader->read($path);
            $inserted = 0;
            foreach ($parsed['rows'] as $rowNumber => $row) {
                $mikuniCode = $row['みくにコード'] ?? null;
                $nCode = $row['日能研コード'] ?? null;
                $warnings = [];
                if ($mikuniCode === null && $nCode === null) {
                    $warnings[] = 'missing_both_codes';
                }

                MGinbonImportRow::create([
                    'mginbon_import_batch_id' => $batch->id,
                    'source_row_number' => $rowNumber,
                    'raw_mikuni_code' => $mikuniCode,
                    'raw_n_code' => $nCode,
                    'raw_school_name' => $row['学校名'] ?? null,
                    'raw_media_type' => $row['媒体分類'] ?? null,
                    'raw_json' => $row,
                    'normalized_json' => null,
                    'resolution_status' => $warnings === [] ? 'pending' : 'review_required',
                    'warning_codes' => $warnings === [] ? null : $warnings,
                ]);
                $inserted++;
            }

            $batch->update([
                'status' => 'staged',
                'summary_json' => [...$preview, 'staged_rows' => $inserted],
            ]);

            return [
                'project_id' => $project->id,
                'batch_id' => $batch->id,
                'staged_rows' => $inserted,
                'review_required' => MGinbonImportRow::query()
                    ->where('mginbon_import_batch_id', $batch->id)
                    ->where('resolution_status', 'review_required')
                    ->count(),
            ];
        });
    }
}
