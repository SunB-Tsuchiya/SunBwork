<?php

namespace App\Services\SunA\Sales;

use App\Models\SunA\Sales\Import;
use App\Models\SunA\Sales\ImportChange;
use App\Models\User;

/**
 * 売上レポートの取込履歴。取込ごとの件数・ファイル名・取込者・日時と、変更ログ（何が変わったか）を返す。
 * 会社のコンテキスト（SunaCompanyContext）が必要。
 */
class ImportHistoryService
{
    private const CHANGES_PER_PAGE = 200;

    /** @return array<int, array<string, mixed>> 新しい順 */
    public function history(): array
    {
        $imports = Import::query()->orderByDesc('imported_at')->orderByDesc('id')->limit(200)->get();
        $names = User::whereIn('id', $imports->pluck('imported_by')->unique()->all())->pluck('name', 'id');

        return $imports->map(fn (Import $i) => [
            'id' => $i->id,
            'original_filename' => $i->original_filename,
            'imported_by_name' => $names[$i->imported_by] ?? '（不明）',
            'imported_at' => $i->imported_at?->toIso8601String(),
            'year_min' => $i->year_min,
            'year_max' => $i->year_max,
            'row_count' => $i->row_count,
            'added_count' => $i->added_count,
            'changed_count' => $i->changed_count,
            'removed_count' => $i->removed_count,
            'unchanged_count' => $i->unchanged_count,
            'replace_years' => $i->replace_years ?? [],
            'warning_count' => count($i->warnings ?? []),
        ])->values()->all();
    }

    /**
     * 1回の取込の変更ログ（金額の変化が大きい順ではなく、ファイルの順）。ページ分割して返す。
     *
     * @return array{import: array<string, mixed>, changes: array<int, array<string, mixed>>, total: int, page: int, per_page: int}|null
     */
    public function changes(int $importId, int $page = 1): ?array
    {
        $import = Import::query()->find($importId);
        if ($import === null) {
            return null; // 他社の取込・存在しない取込は見つからない扱い（会社の絞り込みはモデルが行う）
        }

        $query = ImportChange::query()->where('import_id', $importId)->orderBy('id');
        $total = (clone $query)->count();
        $page = max(1, $page);

        return [
            'import' => [
                'id' => $import->id,
                'original_filename' => $import->original_filename,
                'imported_at' => $import->imported_at?->toIso8601String(),
                'warnings' => $import->warnings ?? [],
            ],
            'changes' => $query->forPage($page, self::CHANGES_PER_PAGE)->get()->map(fn (ImportChange $c) => [
                'client_name' => $c->client_name,
                'year' => $c->sales_year,
                'month' => $c->sales_month,
                'kind' => $c->kind,
                'field' => $c->field,
                'old' => $c->old_value,
                'new' => $c->new_value,
            ])->values()->all(),
            'total' => $total,
            'page' => $page,
            'per_page' => self::CHANGES_PER_PAGE,
        ];
    }
}
