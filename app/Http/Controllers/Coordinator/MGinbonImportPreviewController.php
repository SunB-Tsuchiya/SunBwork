<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonImportBatch;
use App\Models\MGinbon\MGinbonImportRow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MGinbonImportPreviewController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'batch' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'media' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'candidate', 'review_required'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $batches = MGinbonImportBatch::query()
            ->with('project:id,year,name')
            ->latest('id')
            ->get();
        $batch = isset($validated['batch'])
            ? $batches->firstWhere('id', (int) $validated['batch'])
            : $batches->first();

        abort_unless($batch, 404, 'MGinbon取込バッチがありません。');

        $search = trim((string) ($validated['search'] ?? ''));
        $escapedSearch = addcslashes($search, '\\%_');
        $media = (string) ($validated['media'] ?? '');
        $status = (string) ($validated['status'] ?? 'all');
        $perPage = (int) ($validated['per_page'] ?? 25);

        $baseQuery = MGinbonImportRow::query()
            ->where('mginbon_import_batch_id', $batch->id);

        $mediaOptions = (clone $baseQuery)
            ->whereNotNull('raw_media_type')
            ->distinct()
            ->orderBy('raw_media_type')
            ->pluck('raw_media_type')
            ->values();

        $rows = $baseQuery
            ->when($search !== '', function ($query) use ($escapedSearch) {
                $query->where(function ($inner) use ($escapedSearch) {
                    $inner->where('raw_school_name', 'like', "%{$escapedSearch}%")
                        ->orWhere('raw_mikuni_code', 'like', "%{$escapedSearch}%")
                        ->orWhere('raw_n_code', 'like', "%{$escapedSearch}%");
                });
            })
            ->when($media !== '', fn ($query) => $query->where('raw_media_type', $media))
            ->when($status !== 'all', fn ($query) => $query->where('resolution_status', $status))
            ->orderBy('source_row_number')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (MGinbonImportRow $row) => $this->formatRow($row));

        $summary = MGinbonImportRow::query()
            ->where('mginbon_import_batch_id', $batch->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(resolution_status = 'candidate') as candidates")
            ->selectRaw("SUM(resolution_status = 'review_required') as review_required")
            ->first();

        return Inertia::render('Coordinator/MGinbon/ImportPreview', [
            'batch' => [
                'id' => $batch->id,
                'year' => $batch->project?->year ?? $batch->source_year,
                'name' => $batch->project?->name,
                'source_filename' => $batch->source_filename,
                'status' => $batch->status,
                'record_count' => $batch->record_count,
                'column_count' => $batch->column_count,
            ],
            'batches' => $batches->map(fn (MGinbonImportBatch $item) => [
                'id' => $item->id,
                'year' => $item->project?->year ?? $item->source_year,
                'source_filename' => $item->source_filename,
                'status' => $item->status,
            ])->values(),
            'rows' => $rows,
            'summary' => [
                'total' => (int) ($summary?->total ?? 0),
                'candidates' => (int) ($summary?->candidates ?? 0),
                'review_required' => (int) ($summary?->review_required ?? 0),
            ],
            'mediaOptions' => $mediaOptions,
            'filters' => [
                'batch' => $batch->id,
                'search' => $search,
                'media' => $media,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function formatRow(MGinbonImportRow $row): array
    {
        $normalized = $row->normalized_json ?? [];
        $normalizedSubjects = $normalized['subjects'] ?? [];
        $subjectOrder = ['japanese', 'math', 'social', 'science'];
        $subjects = collect($subjectOrder)->map(function (string $subjectCode) use ($normalizedSubjects) {
            $subject = $normalizedSubjects[$subjectCode] ?? [];
            $measurements = collect($subject['measurements'] ?? []);

            return [
                'code' => $subjectCode,
                'label' => $subject['label'] ?? '',
                'active' => (bool) ($subject['active'] ?? false),
                'actor_count' => count($subject['actors'] ?? []),
                'date_count' => count($subject['dates'] ?? []),
                'scan_internal' => (int) ($measurements->firstWhere('execution_type', 'internal')['quantity'] ?? 0),
                'scan_subcontracted' => (int) ($measurements->first(fn ($item) => ($item['work_type'] ?? null) === 'scan'
                    && ($item['execution_type'] ?? null) === 'subcontracted')['quantity'] ?? 0),
                'drawing_internal' => (int) ($measurements->first(fn ($item) => ($item['work_type'] ?? null) === 'drawing'
                    && ($item['execution_type'] ?? null) === 'internal')['quantity'] ?? 0),
                'drawing_subcontracted' => (int) ($measurements->first(fn ($item) => ($item['work_type'] ?? null) === 'drawing'
                    && ($item['execution_type'] ?? null) === 'subcontracted')['quantity'] ?? 0),
            ];
        })->values();

        return [
            'id' => $row->id,
            'source_row_number' => $row->source_row_number,
            'record_number' => max(1, $row->source_row_number - 1),
            'status' => $row->resolution_status,
            'warnings' => $row->warning_codes ?? [],
            'raw' => [
                'mikuni_code' => $row->raw_mikuni_code,
                'n_code' => $row->raw_n_code,
                'school_name' => $row->raw_school_name,
                'media_type' => $row->raw_media_type,
                'school_category' => $row->raw_json['学校分類'] ?? null,
                'n_category' => $row->raw_json['日能研分類'] ?? null,
                'subjects' => $row->raw_json['科目'] ?? null,
                'publication_status' => $row->raw_json['銀本掲載'] ?? null,
            ],
            'normalized' => [
                'unit_type' => $normalized['unit_type'] ?? null,
                'media_type' => $normalized['media_type'] ?? null,
                'subjects' => $subjects,
                'original_received_on' => $normalized['shared_dates']['original_received_on'] ?? null,
                'original_scan_completed_on' => $normalized['shared_dates']['original_scan_completed_on'] ?? null,
            ],
        ];
    }
}
