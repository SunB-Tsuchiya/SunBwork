<?php

namespace App\Http\Controllers\Coordinator;

use App\Models\MGinbon\MGinbonProject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MGinbonCsvExportController extends MGinbonLedgerController
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'search' => ['nullable', 'string', 'max:100'],
            'media' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', Rule::in(['', 'japanese', 'math', 'social', 'science'])],
            'status' => ['nullable', Rule::in(['all', 'draft', 'review_required'])],
        ]);
        $project = isset($data['year'])
            ? MGinbonProject::query()->where('year', (int) $data['year'])->first()
            : MGinbonProject::query()->orderByDesc('year')->first();
        abort_unless($project, 404, 'MGinbon年度プロジェクトがありません。');

        $filters = [
            'search' => trim((string) ($data['search'] ?? '')),
            'media' => (string) ($data['media'] ?? ''),
            'subject' => (string) ($data['subject'] ?? ''),
            'status' => (string) ($data['status'] ?? 'all'),
        ];
        $items = $this->applyItemFilters($this->itemsQuery($project->id), $filters)
            ->orderByRaw('CASE WHEN units.mikuni_code REGEXP "^[0-9]+$" THEN CAST(units.mikuni_code AS UNSIGNED) ELSE 999999 END')
            ->orderBy('units.mikuni_code')->orderBy('media.sort_order')->orderBy('items.id')->get();
        $items = $this->hydrateItems($items);

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [
            '年度', 'Mコード', 'Nコード', '分類', '学校名', '媒体', '教科', '掲載状態', '確認状態',
            '社外scan点数', '社内scan点数', '社外作図点数', '社内作図点数',
            '原本入稿日', 'scan UP日', '文字発注日', '文字納品日',
            '作図発注日', '作図納品日', '原本scan発注日', '原本scan納品日',
        ], ',', '"', '\\');

        foreach ($items as $item) {
            foreach ($item->subjects as $subject) {
                if ($filters['subject'] !== '' && $subject->code !== $filters['subject']) continue;
                $quantity = static function ($measurements, string $workType, string $executionType): int {
                    return (int) ($measurements->first(
                        fn ($row) => $row['work_type'] === $workType && $row['execution_type'] === $executionType
                    )['quantity'] ?? 0);
                };
                fputcsv($handle, [
                    $project->year, $item->mikuni_code, $item->n_code,
                    $item->n_category ?: $item->school_category, $item->display_name, $item->media_name,
                    $subject->name, $item->publication_status, $item->review_status,
                    $quantity($subject->measurements, 'scan', 'subcontracted'),
                    $quantity($subject->measurements, 'scan', 'internal'),
                    $quantity($subject->measurements, 'drawing', 'subcontracted'),
                    $quantity($subject->measurements, 'drawing', 'internal'),
                    $item->shared_dates->get('original_received_on'),
                    $item->shared_dates->get('original_scan_completed_on'),
                    $subject->dates->get('text_input_ordered_on'),
                    $subject->dates->get('text_input_completed_on'),
                    $subject->dates->get('drawing_ordered_on'),
                    $subject->dates->get('drawing_completed_on'),
                    $subject->dates->get('original_scan_ordered_on'),
                    $subject->dates->get('original_scan_delivered_on'),
                ], ',', '"', '\\');
            }
        }

        rewind($handle);
        $csv = str_replace("\n", "\r\n", stream_get_contents($handle));
        fclose($handle);
        $encoded = mb_convert_encoding($csv, 'SJIS-win', 'UTF-8');
        $filename = 'mginbon_'.$project->year.'_'.now()->format('Ymd_His').'.csv';

        return response($encoded, 200, [
            'Content-Type' => 'text/csv; charset=Shift_JIS',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
