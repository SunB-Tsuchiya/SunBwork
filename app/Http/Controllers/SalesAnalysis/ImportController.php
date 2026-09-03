<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Http\Requests\SalesAnalysis\UploadSalesWorkbookRequest;
use App\Models\Sales\SalesImport;
use App\Services\SalesAnalysis\Exceptions\SalesImportConfirmException;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesImportService;
use App\Services\SalesAnalysis\SalesImportValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ImportController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix;

    private const PREVIEW_TTL_MINUTES = 30;

    public function __construct(
        private SalesImportValidator $validator,
        private SalesImportService $importService,
    ) {
    }

    public function create()
    {
        return Inertia::render('SalesAnalysis/Import', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'departmentLabels' => SalesDepartments::LABELS,
            'enabledDepartmentKeys' => SalesDepartments::ENABLED_KEYS,
        ]);
    }

    public function preview(UploadSalesWorkbookRequest $request)
    {
        $data = $request->validated();
        $file = $request->file('file');

        // 非公開領域（storage/app/private）へ一時保存。元ファイル名はパスとして使わない
        $storedPath = $file->store('sales_imports', 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        try {
            $fileHash = hash_file('sha256', $absolutePath);

            $result = $this->validator->validate(
                $absolutePath,
                $data['department_key'],
                $data['source_type'],
                (int) $data['source_year'],
                isset($data['source_month']) ? (int) $data['source_month'] : null,
                isset($data['source_month_end']) ? (int) $data['source_month_end'] : null,
                $data['excluded_order_numbers'] ?? []
            );

            if (SalesImport::where('file_sha256', $fileHash)->exists()) {
                $result['warnings'][] = '同一内容のファイルが既に取り込まれています（二重取込の可能性があります）。';
            }

            $result['file_sha256'] = $fileHash;
            $result['original_filename'] = basename($file->getClientOriginalName());
            // 確定時に検証者と確定者が一致することを照合するため保持する
            // （Codexレビュー2回目 High-2対応: 他ユーザーのプレビュートークンを確定できてしまう問題）
            $result['previewed_by'] = Auth::id();

            $diff = $result['valid']
                ? $this->importService->calculateDiff($result['orders'], $data['department_key'])
                : [];

            $token = null;
            if ($result['valid']) {
                $token = (string) Str::uuid();
                $this->importService->previewCacheStore()->put(
                    $this->importService->previewCacheKey($token),
                    Crypt::encrypt($result),
                    now()->addMinutes(self::PREVIEW_TTL_MINUTES)
                );
            }

            return response()->json([
                'valid' => $result['valid'],
                'errors' => $result['errors'],
                'invalid_orders' => $result['invalid_orders'],
                'excluded_orders' => $result['excluded_orders'],
                'warnings' => $result['warnings'],
                'summary' => $result['summary'],
                'diff' => $diff,
                'department_key' => $data['department_key'],
                'source_type' => $data['source_type'],
                'source_year' => (int) $data['source_year'],
                'source_month' => isset($data['source_month']) ? (int) $data['source_month'] : null,
                'source_month_end' => isset($data['source_month_end']) ? (int) $data['source_month_end'] : null,
                'preview_token' => $token,
            ]);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'preview_token' => 'required|string',
        ]);

        try {
            $import = $this->importService->confirm($data['preview_token'], Auth::id());
        } catch (SalesImportConfirmException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'import_id' => $import->id,
            'version' => $import->version,
            'department_key' => $import->department_key,
            'source_year' => $import->source_year,
            'source_month' => $import->source_month,
        ]);
    }
}
