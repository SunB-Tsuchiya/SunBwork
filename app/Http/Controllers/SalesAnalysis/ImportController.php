<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesAnalysis\UploadSalesWorkbookRequest;
use App\Models\Sales\SalesImport;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesImportValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ImportController extends Controller
{
    private const PREVIEW_TTL_MINUTES = 30;

    public function __construct(private SalesImportValidator $validator)
    {
    }

    public function create()
    {
        return Inertia::render('SalesAnalysis/Import', [
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
                isset($data['source_month']) ? (int) $data['source_month'] : null
            );

            if (SalesImport::where('file_sha256', $fileHash)->exists()) {
                $result['warnings'][] = '同一内容のファイルが既に取り込まれています（二重取込の可能性があります）。';
            }

            $result['file_sha256'] = $fileHash;
            $result['original_filename'] = basename($file->getClientOriginalName());

            $token = null;
            if ($result['valid']) {
                $token = (string) Str::uuid();
                Cache::put(
                    $this->previewCacheKey($token),
                    Crypt::encrypt($result),
                    now()->addMinutes(self::PREVIEW_TTL_MINUTES)
                );
            }

            return response()->json([
                'valid' => $result['valid'],
                'errors' => $result['errors'],
                'warnings' => $result['warnings'],
                'summary' => $result['summary'],
                'department_key' => $data['department_key'],
                'source_type' => $data['source_type'],
                'source_year' => (int) $data['source_year'],
                'source_month' => isset($data['source_month']) ? (int) $data['source_month'] : null,
                'preview_token' => $token,
            ]);
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }

    private function previewCacheKey(string $token): string
    {
        return "sales_import_preview:{$token}";
    }
}
