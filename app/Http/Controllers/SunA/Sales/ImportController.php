<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Services\SunA\Sales\ImportHistoryService;
use App\Services\SunA\Sales\SunaSalesImportException;
use App\Services\SunA\Sales\SunaSalesImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * 売上レポート（サンエー印刷）の取込と取込履歴。
 * 取込（画面・アップロード・確定）は suna_sales_report:import（取込を許可された人）、履歴は :view で保護する。
 * 流れ: アップロード → 解析・検証 → 差分プレビュー → 確定（SunaSalesImportService）。取込ファイルは保存しない。
 */
class ImportController extends Controller
{
    private const MAX_UPLOAD_KB = 20480;

    public function __construct(
        private SunaSalesImportService $importService,
        private ImportHistoryService $historyService,
    ) {
    }

    public function create()
    {
        return Inertia::render('SunA/Sales/Import', ['maxUploadMb' => self::MAX_UPLOAD_KB / 1024]);
    }

    /** アップロード → 解析 → 差分プレビュー（まだ何も保存しない。確定するまで、データは変わらない） */
    public function preview(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . self::MAX_UPLOAD_KB],
            'replace_years' => ['nullable', 'array'],
            'replace_years.*' => ['integer'],
        ]);

        $file = $data['file'];
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            return response()->json(['message' => 'xlsxファイルを選択してください。'], 422);
        }

        try {
            return response()->json($this->importService->preview(
                $file->getRealPath(),
                basename($file->getClientOriginalName()),
                (int) Auth::id(),
                $data['replace_years'] ?? [],
            ));
        } catch (SunaSalesImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** 「この年は入れ替える」の指定を変えて、プレビューし直す（ファイルは再アップロード不要） */
    public function repreview(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:80'],
            'replace_years' => ['nullable', 'array'],
            'replace_years.*' => ['integer'],
        ]);

        try {
            return response()->json($this->importService->previewToken($data['token'], (int) Auth::id(), $data['replace_years'] ?? []));
        } catch (SunaSalesImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function confirm(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:80'],
            'replace_years' => ['nullable', 'array'],
            'replace_years.*' => ['integer'],
        ]);

        try {
            $import = $this->importService->confirm($data['token'], (int) Auth::id(), $data['replace_years'] ?? []);
        } catch (SunaSalesImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'import_id' => $import->id,
            'original_filename' => $import->original_filename,
            'row_count' => $import->row_count,
            'added_count' => $import->added_count,
            'changed_count' => $import->changed_count,
            'removed_count' => $import->removed_count,
            'unchanged_count' => $import->unchanged_count,
            'year_min' => $import->year_min,
            'year_max' => $import->year_max,
        ]);
    }

    public function discard(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:80']]);
        $this->importService->discard($data['token'], (int) Auth::id());

        return response()->json(['discarded' => true]);
    }

    public function history()
    {
        return Inertia::render('SunA/Sales/ImportHistory', ['imports' => $this->historyService->history()]);
    }

    public function changes(Request $request, int $import)
    {
        $result = $this->historyService->changes($import, (int) $request->query('page', 1));
        abort_if($result === null, 404);

        return response()->json($result);
    }
}
