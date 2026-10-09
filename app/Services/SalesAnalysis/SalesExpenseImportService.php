<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesAuditLog;
use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use App\Services\SalesAnalysis\Exceptions\SalesImportConfirmException;
use App\Services\SalesAnalysis\Exceptions\SalesWorkbookException;
use Illuminate\Support\Facades\DB;

/**
 * 部署別経費表（Phase 21）の確定保存を行う。1ファイル＝1会計年度＝全社分のため、
 * 売上Excelのような月単位のactive pointer切替は無く、会計年度単位でまるごと新しい版に差し替える
 * （旧版の行は履歴として残る。現在有効な版は(company_id, fiscal_year)内の最大versionで解決する）。
 */
class SalesExpenseImportService
{
    public function __construct(private SalesExpenseWorkbookReader $reader)
    {
    }

    /**
     * @throws SalesWorkbookException|SalesImportConfirmException
     */
    public function confirm(string $filePath, int $fiscalYear, string $originalFilename, int $userId, int $companyId, bool $force = false): SalesExpenseImport
    {
        $fileHash = hash_file('sha256', $filePath);

        // 取込処理のロジック更新後に同じファイルを読み直したい等の正当な理由があるため、
        // $forceで明示的に指示された場合は同一内容チェックをスキップする（誤操作防止の既定動作は維持）
        if (! $force && SalesExpenseImport::where('company_id', $companyId)->where('file_sha256', $fileHash)->exists()) {
            throw new SalesImportConfirmException('同一内容のファイルが既に取り込まれています。内容を確認のうえ、再取込みが必要な場合は「警告があっても取り込む」を選択してください。');
        }

        $result = $this->reader->read($filePath, $fiscalYear, $force);

        return DB::connection('sales')->transaction(function () use ($result, $fiscalYear, $originalFilename, $fileHash, $userId, $companyId) {
            $version = $this->nextVersion($companyId, $fiscalYear);
            $totalAmount = array_sum(array_column($result['line_items'], 'amount'));
            $categoryCount = collect($result['line_items'])->pluck('category')->unique()->count();

            $import = SalesExpenseImport::create([
                'company_id' => $companyId,
                'fiscal_year' => $fiscalYear,
                'version' => $version,
                'original_filename' => $originalFilename,
                'file_sha256' => $fileHash,
                'status' => 'completed',
                'imported_by' => $userId,
                'imported_at' => now(),
                'category_count' => $categoryCount,
                'line_item_count' => count($result['line_items']),
                'total_amount' => $totalAmount,
                'warnings' => empty($result['warnings']) ? null : $result['warnings'],
            ]);

            $now = now();
            $rows = array_map(fn ($item) => [
                'sales_expense_import_id' => $import->id,
                'department_key' => $item['department_key'],
                'category' => $item['category'],
                'category_order' => $item['category_order'],
                'vendor_name' => $item['vendor_name'],
                'calendar_year' => $item['calendar_year'],
                'calendar_month' => $item['calendar_month'],
                'amount' => $item['amount'],
                'is_shared_split' => $item['is_shared_split'],
                'subtotal_group' => $item['subtotal_group'],
                'subtotal_label' => $item['subtotal_label'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $result['line_items']);

            // 件数が多くなり得るため（科目×会社名×月で数百行規模）チャンク挿入する
            foreach (array_chunk($rows, 500) as $chunk) {
                SalesExpenseLineItem::insert($chunk);
            }

            SalesAuditLog::create([
                'user_id' => $userId,
                'action' => 'expense_import',
                'target_type' => 'sales_expense_import',
                'target_id' => $import->id,
                'context' => [
                    'fiscal_year' => $fiscalYear,
                    'version' => $version,
                    'line_item_count' => count($result['line_items']),
                ],
            ]);

            return $import;
        });
    }

    private function nextVersion(int $companyId, int $fiscalYear): int
    {
        $max = SalesExpenseImport::where('company_id', $companyId)
            ->where('fiscal_year', $fiscalYear)
            ->max('version');

        return ((int) $max) + 1;
    }

    /** 指定会社・会計年度で現在有効な版のID（無ければnull） */
    public function activeImportId(int $companyId, int $fiscalYear): ?int
    {
        return SalesExpenseImport::where('company_id', $companyId)
            ->where('fiscal_year', $fiscalYear)
            ->orderByDesc('version')
            ->value('id');
    }
}
