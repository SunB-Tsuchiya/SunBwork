<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisRoutePrefix;
use App\Http\Requests\SalesAnalysis\UploadSalesWorkbookRequest;
use App\Models\Sales\SalesImport;
use App\Services\SalesAnalysis\Exceptions\SalesImportConfirmException;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesImportService;
use App\Services\SalesAnalysis\SalesImportValidator;
use App\Services\SalesAnalysis\SalesOrderChannels;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ImportController extends Controller
{
    use ResolvesSalesAnalysisRoutePrefix, ResolvesSalesAnalysisCompany;

    private const PREVIEW_TTL_MINUTES = 30;

    public function __construct(
        private SalesImportValidator $validator,
        private SalesImportService $importService,
    ) {
    }

    public function create()
    {
        $companyId = $this->salesAnalysisCompanyId();

        return Inertia::render('SalesAnalysis/Import', [
            'routePrefix' => $this->salesAnalysisRoutePrefix(),
            'hasCompanySelected' => $companyId !== null,
            'departmentLabels' => $companyId !== null ? SalesDepartments::labelsFor($companyId) : [],
            'enabledDepartmentKeys' => $companyId !== null ? SalesDepartments::enabledKeysFor($companyId) : [],
            'supportsOrderChannels' => $companyId !== null && SalesOrderChannels::supportsChannelsFor($companyId),
        ]);
    }

    public function preview(UploadSalesWorkbookRequest $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();

        $data = $request->validated();
        $file = $request->file('file');
        $originalFilename = basename($file->getClientOriginalName());

        // サン・ブレーンでは受注経路（サンエー印刷経由/独自受注）をファイル名だけを正本として
        // サーバー側で厳格再判定する。フロント側の自動入力（表示補助）は信用しない
        // （PLAN Phase20 20.3）。他社は経路の概念自体を持たないためstandard固定のまま。
        if (SalesOrderChannels::supportsChannelsFor($companyId)) {
            $parsed = SalesOrderChannels::parseFilename($originalFilename, array_values(SalesDepartments::labelsFor($companyId)));

            if ($parsed === null) {
                return response()->json([
                    'valid' => false,
                    'errors' => [
                        'ファイル名が命名規則と一致しません（例: 企画_2026年08月.xlsx / 企画_2026年08月_独自.xlsx）。'
                        . 'ファイル名を規則どおりに修正してから再度アップロードしてください。',
                    ],
                    'invalid_orders' => [],
                    'excluded_orders' => [],
                    'warnings' => [],
                    'summary' => null,
                    'diff' => [],
                    'preview_token' => null,
                ], 422);
            }

            $departmentKey = SalesDepartments::keyFromLabel($companyId, $parsed['department_label']);

            if ($departmentKey === null) {
                return response()->json([
                    'valid' => false,
                    'errors' => ["ファイル名から読み取った部署「{$parsed['department_label']}」が登録された部署と一致しません。"],
                    'invalid_orders' => [],
                    'excluded_orders' => [],
                    'warnings' => [],
                    'summary' => null,
                    'diff' => [],
                    'preview_token' => null,
                ], 422);
            }

            $sourceType = $parsed['source_type'];
            $sourceYear = $parsed['source_year'];
            $sourceMonth = $parsed['source_month'];
            $sourceMonthEnd = $parsed['source_month_end'];
            $orderChannel = $parsed['order_channel'];
        } else {
            $departmentKey = $data['department_key'];
            $sourceType = $data['source_type'];
            $sourceYear = (int) $data['source_year'];
            $sourceMonth = isset($data['source_month']) ? (int) $data['source_month'] : null;
            $sourceMonthEnd = isset($data['source_month_end']) ? (int) $data['source_month_end'] : null;
            $orderChannel = SalesOrderChannels::STANDARD;
        }

        // 非公開領域（storage/app/private）へ一時保存。元ファイル名はパスとして使わない
        $storedPath = $file->store('sales_imports', 'local');
        $absolutePath = Storage::disk('local')->path($storedPath);

        try {
            $fileHash = hash_file('sha256', $absolutePath);

            $result = $this->validator->validate(
                $absolutePath,
                $departmentKey,
                $sourceType,
                $sourceYear,
                $sourceMonth,
                $sourceMonthEnd,
                $companyId,
                $data['excluded_order_numbers'] ?? [],
                $orderChannel
            );

            if (SalesImport::where('company_id', $companyId)->where('file_sha256', $fileHash)->exists()) {
                $result['warnings'][] = '同一内容のファイルが既に取り込まれています（二重取込の可能性があります）。';
            }

            $result['file_sha256'] = $fileHash;
            $result['original_filename'] = $originalFilename;
            // 確定時に検証者と確定者が一致することを照合するため保持する
            // （Codexレビュー2回目 High-2対応: 他ユーザーのプレビュートークンを確定できてしまう問題）
            $result['previewed_by'] = Auth::id();
            // 確定時にどの会社として保存するかを固定する（会社別データ分離、2026-09-05）
            $result['company_id'] = $companyId;

            $diff = $result['valid']
                ? $this->importService->calculateDiff($result['orders'], $departmentKey, $companyId, $orderChannel)
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
                'department_key' => $departmentKey,
                'source_type' => $sourceType,
                'source_year' => $sourceYear,
                'source_month' => $sourceMonth,
                'source_month_end' => $sourceMonthEnd,
                'order_channel' => $orderChannel,
                'order_channel_label' => SalesOrderChannels::label($orderChannel),
                'supports_order_channels' => SalesOrderChannels::supportsChannelsFor($companyId),
                'preview_token' => $token,
            ]);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }

    public function store(Request $request)
    {
        $companyId = $this->requireSalesAnalysisCompanyId();

        $data = $request->validate([
            'preview_token' => 'required|string',
        ]);

        try {
            $import = $this->importService->confirm($data['preview_token'], Auth::id(), $companyId);
        } catch (SalesImportConfirmException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'import_id' => $import->id,
            'version' => $import->version,
            'department_key' => $import->department_key,
            'order_channel' => $import->order_channel,
            'order_channel_label' => SalesOrderChannels::label($import->order_channel),
            'source_year' => $import->source_year,
            'source_month' => $import->source_month,
        ]);
    }
}
