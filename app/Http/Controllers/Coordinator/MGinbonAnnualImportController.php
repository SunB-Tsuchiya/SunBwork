<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Services\MGinbon\MGinbonAnnualSchoolListReader;
use App\Services\MGinbon\MGinbonValueListDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class MGinbonAnnualImportController extends Controller
{
    private const STAGES = [
        ['text_input', '文字入力', 'operation', 10], ['drawing', '作図・スキャン', 'operation', 20],
        ['initial_operation', '初校組版', 'operation', 30], ['initial_check', '初校出稿前チェック', 'proof', 40],
        ['initial_text_proof', '初校文字校正', 'proof', 50], ['reproof_scan_check', '再校スキャン図校正', 'proof', 60],
        ['reproof_text_proof', '再校文字校正', 'proof', 70], ['reproof_operation', '再校修正', 'operation', 80],
        ['client_return_operation', 'みくに戻り対応', 'operation', 90], ['third_proof', '三校赤字照合', 'proof', 100],
        ['third_operation', '三校修正', 'operation', 110], ['fourth_operation', '四校修正', 'operation', 120],
        ['fourth_proof', '四校赤字照合', 'proof', 130], ['fifth_operation', '五校修正', 'operation', 140],
    ];

    public function create(): Response
    {
        return Inertia::render('Coordinator/MGinbon/AnnualImport', ['preview' => null]);
    }

    public function preview(Request $request, MGinbonAnnualSchoolListReader $reader): Response
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);
        try {
            $result = $reader->read($request->file('file')->getRealPath());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => 'Excelファイルを解析できませんでした。ファイル形式と固定5列を確認してください。']);
        }
        $token = (string) Str::uuid();
        try {
            Cache::store('mginbon_preview')->put($this->cacheKey($request, $token), [
                'year' => (int) $data['year'], 'filename' => $request->file('file')->getClientOriginalName(),
                'rows' => $result['rows'],
            ], now()->addMinutes(30));
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['file' => 'プレビューを一時保存できませんでした。管理者へ連絡してください。']);
        }

        return Inertia::render('Coordinator/MGinbon/AnnualImport', [
            'preview' => [...$result, 'year' => (int) $data['year'], 'filename' => $request->file('file')->getClientOriginalName(), 'token' => $token],
        ]);
    }

    public function store(Request $request, ?MGinbonValueListDefaults $valueListDefaults = null)
    {
        $valueListDefaults ??= app(MGinbonValueListDefaults::class);
        $data = $request->validate([
            'token' => ['required', 'uuid'], 'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.source_row_number' => ['required', 'integer'],
            'rows.*.mikuni_code' => ['required', 'string', 'max:50'],
            'rows.*.n_code' => ['required', 'string', 'max:100'],
            'rows.*.alpha_group' => ['nullable', 'string', 'max:20'],
            'rows.*.school_name' => ['required', 'string', 'max:300'],
            'rows.*.exam_session' => ['nullable', 'string', 'max:255'],
            'rows.*.confirmed' => ['required', 'boolean'],
        ]);
        try {
            $cached = Cache::store('mginbon_preview')->get($this->cacheKey($request, $data['token']));
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['token' => 'プレビューを読み込めませんでした。Excelを再度アップロードしてください。']);
        }
        if (! $cached || $cached['year'] !== (int) $data['year']) {
            throw ValidationException::withMessages(['token' => 'プレビューの有効期限が切れました。Excelを再度アップロードしてください。']);
        }
        if (MGinbonProject::query()->where('year', $data['year'])->exists()) {
            return back()->withErrors(['year' => '同年度は既に登録されています。']);
        }
        foreach (['mikuni_code' => 'Mコード', 'n_code' => 'Nコード'] as $key => $label) {
            $duplicates = collect($data['rows'])->pluck($key)->filter()->duplicates();
            if ($duplicates->isNotEmpty()) {
                return back()->withErrors(['rows' => $label.'が重複しています: '.$duplicates->unique()->implode(', ')]);
            }
        }
        $rawByRow = collect($cached['rows'])->keyBy('source_row_number');
        foreach ($data['rows'] as $row) {
            $source = $rawByRow->get($row['source_row_number']);
            if (! $source) {
                return back()->withErrors(['rows' => $row['source_row_number'].'行目は元ファイルに存在しません。']);
            }
            if (! empty($source['warnings']) && ! $row['confirmed']) {
                return back()->withErrors(['rows' => $row['source_row_number'].'行目の要確認項目を確認してください。']);
            }
        }
        try {
            DB::connection('mginbon')->transaction(function () use ($data, $rawByRow, $request, $valueListDefaults) {
                $project = MGinbonProject::create([
                    'year' => $data['year'], 'name' => $data['year'].'年 中学入試問題集',
                    'status' => 'draft', 'created_by' => $request->user()?->id,
                ]);
                $now = now();
                foreach ($data['rows'] as $row) {
                    $source = $rawByRow->get($row['source_row_number']);
                    DB::connection('mginbon')->table('mginbon_production_units')->insert([
                        'mginbon_project_id' => $project->id, 'unit_type' => 'exam',
                        'mikuni_code' => trim($row['mikuni_code']), 'n_code' => trim($row['n_code']),
                        'display_name' => trim($row['school_name']), 'alpha_group' => trim((string) ($row['alpha_group'] ?? '')) ?: null,
                        'exam_session' => trim((string) ($row['exam_session'] ?? '')) ?: null,
                        'source_row_number' => $row['source_row_number'], 'source_data' => json_encode($source['raw'] ?? [], JSON_UNESCAPED_UNICODE),
                        'import_warnings' => json_encode($source['warnings'] ?? [], JSON_UNESCAPED_UNICODE),
                        'review_status' => 'draft',
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                foreach (self::STAGES as [$code, $name, $type, $order]) {
                    DB::connection('mginbon')->table('mginbon_stage_definitions')->insert([
                        'mginbon_project_id' => $project->id, 'code' => $code, 'name' => $name,
                        'activity_type' => $type, 'sort_order' => $order, 'is_active' => true,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                $valueListDefaults->ensureForProject($project, $request->user()?->id);
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['rows' => '年度データを登録できませんでした。入力内容を確認し、解決しない場合は管理者へ連絡してください。']);
        }
        try {
            Cache::store('mginbon_preview')->forget($this->cacheKey($request, $data['token']));
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->route('coordinator.mginbon.index', ['year' => $data['year']])->with('success', '年度対象校リストを登録しました。');
    }

    private function cacheKey(Request $request, string $token): string
    {
        return 'mginbon:annual-import:'.($request->user()?->id ?? 0).':'.$token;
    }
}
