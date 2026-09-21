<?php

namespace App\Services\MGinbon;

use App\Models\MGinbon\MGinbonImportBatch;
use App\Models\MGinbon\MGinbonImportRow;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MGinbonNormalizationPreviewService
{
    public function __construct(private readonly MGinbonLegacyRowNormalizer $normalizer) {}

    /** @return array<string, int> */
    public function generate(?int $batchId = null): array
    {
        $batch = $batchId
            ? MGinbonImportBatch::find($batchId)
            : MGinbonImportBatch::query()->latest('id')->first();

        if (! $batch) {
            throw new RuntimeException('対象の取込バッチがありません。');
        }

        $rows = MGinbonImportRow::query()
            ->where('mginbon_import_batch_id', $batch->id)
            ->orderBy('source_row_number')
            ->get();

        $identityCounts = $rows->countBy(fn (MGinbonImportRow $row) => implode('|', [
            $row->raw_mikuni_code ?? '', $row->raw_n_code ?? '',
            $row->raw_school_name ?? '', $row->raw_media_type ?? '',
        ]));

        $summary = ['rows' => 0, 'candidate' => 0, 'review_required' => 0, 'warning_count' => 0];

        DB::connection('mginbon')->transaction(function () use ($rows, $identityCounts, &$summary) {
            foreach ($rows as $row) {
                $normalized = $this->normalizer->normalize($row->raw_json);
                $warnings = array_values(array_unique([
                    ...array_filter(
                        $row->warning_codes ?? [],
                        fn (string $warning) => ! $this->isGeneratedWarning($warning)
                    ),
                    ...$normalized['warnings'],
                ]));
                $identity = implode('|', [
                    $row->raw_mikuni_code ?? '', $row->raw_n_code ?? '',
                    $row->raw_school_name ?? '', $row->raw_media_type ?? '',
                ]);
                if (($identityCounts[$identity] ?? 0) > 1) {
                    $warnings[] = 'duplicate_identity_candidate';
                    $warnings = array_values(array_unique($warnings));
                }

                $status = $warnings === [] ? 'candidate' : 'review_required';
                $row->update([
                    'normalized_json' => $normalized['data'],
                    'warning_codes' => $warnings === [] ? null : $warnings,
                    'resolution_status' => $status,
                ]);

                $summary['rows']++;
                $summary[$status]++;
                $summary['warning_count'] += count($warnings);
            }
        });

        $batch->update([
            'status' => 'normalized_preview',
            'summary_json' => [...($batch->summary_json ?? []), 'normalization' => $summary],
        ]);

        return $summary;
    }

    private function isGeneratedWarning(string $warning): bool
    {
        return $warning === 'duplicate_identity_candidate'
            || str_starts_with($warning, 'invalid_quantity:')
            || str_starts_with($warning, 'invalid_date:')
            || str_starts_with($warning, 'non_padded_date:');
    }
}
