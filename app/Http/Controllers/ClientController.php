<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksAdminPermission;
use App\Http\Controllers\Concerns\ChecksLeaderPermission;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    use ChecksAdminPermission, ChecksLeaderPermission;

    public function index(Request $request)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $showDormant = $request->boolean('dormant', false);
        $user = Auth::user();
        if ($user && $user->user_role === 'superadmin') {
            $query = Client::query();
        } else {
            $companyId = $user->company_id ?? null;
            $query = Client::forCompany($companyId);
        }

        if ($showDormant) {
            $clients = $query->dormant()->get();
        } else {
            $clients = $query->active()->get();
        }

        return Inertia::render('Clients/Index', [
            'clients'     => $clients,
            'showDormant' => $showDormant,
        ]);
    }

    public function create()
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('create', Client::class);
        return Inertia::render('Clients/Create');
    }

    public function store(Request $request)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $user = Auth::user();
        $this->authorize('create', Client::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        // Non-superadmin users may only create clients for their own company
        if (!($user && $user->user_role === 'superadmin')) {
            $data['company_id'] = $user->company_id ?? null;
        }

        // DB column is `notes`, form sends `detail`
        $data['notes'] = $data['detail'] ?? null;
        unset($data['detail']);

        Client::create($data);
        return redirect()->route("{$this->routePrefix()}.clients.index");
    }

    public function edit(Client $client)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('view', $client);
        return Inertia::render('Clients/Edit', ['client' => $client]);
    }

    public function update(Request $request, Client $client)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('update', $client);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        // Non-superadmin users should not be able to change company_id
        $user = Auth::user();
        if (!($user && $user->user_role === 'superadmin')) {
            unset($data['company_id']);
        }

        // DB column is `notes`, form sends `detail`
        $data['notes'] = $data['detail'] ?? null;
        unset($data['detail']);

        $client->update($data);
        return redirect()->route("{$this->routePrefix()}.clients.index");
    }

    /** 休眠状態の切り替え */
    public function dormant(Request $request, Client $client)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('update', $client);

        $request->validate(['dormant' => 'required|boolean']);
        $isDormant = (bool) $request->dormant;
        $client->update(['is_dormant' => $isDormant]);

        $msg = $isDormant
            ? "「{$client->name}」を休眠状態にしました。"
            : "「{$client->name}」の休眠を解除しました。";

        return redirect()->route("{$this->routePrefix()}.clients.index")
            ->with('success', $msg);
    }

    public function show(Client $client)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('view', $client);
        return Inertia::render('Clients/Show', ['client' => $client]);
    }

    public function destroy(Client $client)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('delete', $client);

        $projectJobCount = $client->projectJobs()->count();

        if ($projectJobCount > 0) {
            $projectJobTitles = $client->projectJobs()
                ->orderBy('id')
                ->limit(5)
                ->pluck('title')
                ->toArray();

            return back()->with('clientDeleteError', [
                'clientName'       => $client->name,
                'projectJobCount'  => $projectJobCount,
                'projectJobTitles' => $projectJobTitles,
            ]);
        }

        $client->delete();
        return redirect()->route("{$this->routePrefix()}.clients.index")
            ->with('success', "「{$client->name}」を削除しました。");
    }

    /** クライアント検索JSON（json エンドポイント：全ロール共用） */
    public function clientsJson(Request $request)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');

        $user  = Auth::user();
        $query = Client::select('id', 'name', 'is_dormant');

        if (!($user && $user->user_role === 'superadmin')) {
            $query->forCompany($user->company_id ?? null);
        }

        // 案件作成等の通常用途は休眠クライアントを除外。
        // include_dormant=1 を渡すと統合先選択など管理画面で休眠も含めて返す。
        if (!$request->boolean('include_dormant', false)) {
            $query->active();
        }

        if ($request->filled('id')) {
            $client = (clone $query)->find((int) $request->id);
            return $client ? response()->json($client) : response()->json(null, 404);
        }

        if ($request->filled('name')) {
            $searchName = $request->name;
            $query->where('name', 'like', '%' . $searchName . '%');
        }

        // 結果数制限（オートコンプリート用）
        if ($request->filled('limit')) {
            $query->limit((int) $request->limit);
        }

        return response()->json($query->orderBy('name')->get());
    }

    /** クライアント統合（merge_into_id に全案件を移して自身を削除） */
    public function merge(Request $request, Client $client)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('delete', $client);

        $request->validate([
            'merge_into_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id'),
                Rule::notIn([$client->id]),
            ],
        ]);

        $mergeIntoId = (int) $request->merge_into_id;
        $mergeInto   = Client::findOrFail($mergeIntoId);

        // superadmin 以外は同一会社のクライアント間でのみ統合可
        $user = Auth::user();
        if ($user->user_role !== 'superadmin') {
            if ((int) ($mergeInto->company_id ?? 0) !== (int) ($client->company_id ?? 0)) {
                return back()->with('error', '異なる会社のクライアントには統合できません。');
            }
        }

        $clientName   = $client->name;
        $mergeIntoName = $mergeInto->name;

        DB::transaction(function () use ($client, $mergeInto) {
            // project_jobs の client_id を移行
            $client->projectJobs()->update(['client_id' => $mergeInto->id]);

            // job_requests テーブルが存在すれば移行
            if (Schema::hasTable('job_requests')) {
                DB::table('job_requests')
                    ->where('client_id', $client->id)
                    ->update(['client_id' => $mergeInto->id]);
            }

            $client->delete();
        });

        return redirect()->route("{$this->routePrefix()}.clients.index")
            ->with('success', "「{$clientName}」の案件をすべて「{$mergeIntoName}」に移し、統合しました。");
    }

    /** 重複チェック（登録・編集前のフロント呼び出し用 JSON エンドポイント） */
    public function checkDuplicate(Request $request)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');

        $request->validate([
            'name'       => 'required|string|max:255',
            'exclude_id' => 'nullable|integer',
        ]);

        $inputNormalized = $this->normalizeClientName($request->name);

        $user  = Auth::user();
        $query = Client::select('id', 'name');

        if (!($user && $user->user_role === 'superadmin')) {
            $query->forCompany($user->company_id ?? null);
        }
        if ($request->filled('exclude_id')) {
            $query->where('id', '!=', (int) $request->exclude_id);
        }

        $duplicates = $query->get()
            ->filter(fn($c) => $this->normalizeClientName($c->name) === $inputNormalized)
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
            ->values();

        return response()->json(['duplicates' => $duplicates]);
    }

    /**
     * クライアント名を正規化して重複比較用文字列を返す。
     *
     * 変換内容:
     *  1. 全角英数字・スペースを半角に変換
     *  2. 法人格（株式会社・有限会社など、前後どちらでも）を除去
     *  3. スペース・中黒を除去
     *  4. 小文字化
     */
    private function normalizeClientName(string $name): string
    {
        // 全角英数字・スペース → 半角
        $name = mb_convert_kana($name, 'as', 'UTF-8');

        // 除去する法人格リスト（長い順に並べて部分一致を防ぐ）
        $suffixes = [
            '特定非営利活動法人',
            '公益社団法人',
            '公益財団法人',
            '一般社団法人',
            '一般財団法人',
            '医療法人社団',
            '医療法人財団',
            '株式会社',
            '有限会社',
            '合同会社',
            '合資会社',
            '合名会社',
            '医療法人',
            '学校法人',
            '宗教法人',
            '（株）',
            '(株)',
            '（有）',
            '(有)',
            '㈱',
            '㈲',
        ];

        foreach ($suffixes as $suffix) {
            $quoted = preg_quote($suffix, '/');
            $name   = preg_replace('/^' . $quoted . '/u', '', $name);
            $name   = preg_replace('/' . $quoted . '$/u', '', $name);
        }

        // スペース・全角スペース・中黒を除去
        $name = preg_replace('/[\s　・]+/u', '', $name);

        // 小文字化（英字対応）
        $name = mb_strtolower($name, 'UTF-8');

        return $name;
    }

    /** ルートプレフィックスをロールから解決 */
    private function routePrefix(): string
    {
        $role = Auth::user()->user_role ?? 'leader';
        return match ($role) {
            'admin', 'superadmin' => 'admin',
            'coordinator'         => 'coordinator',
            default               => 'leader',
        };
    }

    /**
     * クライアントの直近案件設定を返す（案件作成フォームのプリセット用）
     * GET coordinator/clients/{client}/last-job-config
     */
    public function lastJobConfig(Client $client)
    {
        $lastJob = \App\Models\ProjectJob::where('client_id', $client->id)
            ->with(['user', 'teamMembers.user', 'coordinators', 'size'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastJob) {
            return response()->json(null);
        }

        return response()->json([
            'job_title'             => $lastJob->title,
            'job_created_at'        => $lastJob->created_at?->format('Y年n月'),
            'user_id'               => $lastJob->user_id,
            'user_name'             => $lastJob->user?->name,
            'sub_coordinator_ids'   => $lastJob->coordinators->pluck('id')->values(),
            'sub_coordinator_names' => $lastJob->coordinators->pluck('name')->values(),
            'size_id'               => $lastJob->size_id,
            'size_name'             => $lastJob->size?->name,
            'page_count'            => $lastJob->page_count,
            'detail'                => $lastJob->detail,
            'team_members'          => $lastJob->teamMembers->map(fn($m) => [
                'user_id'   => $m->user_id,
                'user_name' => $m->user?->name,
            ])->values(),
        ]);
    }

    /** サンプルCSVダウンロード */
    public function csvSampleDownload()
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $rows = [
            ['name', 'detail'],
            ['株式会社サンプル', '詳細テキスト（省略可）'],
            ['テスト商事', ''],
        ];
        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
        }
        return response("\xEF\xBB\xBF" . $csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="clients_sample.csv"');
    }

    /** CSVアップロード画面 */
    public function csvUpload()
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('create', Client::class);
        $user = Auth::user();
        $companies = ($user && $user->user_role === 'superadmin')
            ? Company::orderBy('name')->get(['id', 'name'])
            : [];
        return Inertia::render('Clients/CsvUpload', ['companies' => $companies]);
    }

    /** CSVプレビュー */
    public function csvPreview(Request $request)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('create', Client::class);
        $request->validate([
            'csv_file'   => 'required|file|mimes:csv,txt|max:2048',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $file = $request->file('csv_file');
        $tempDir = storage_path('app/private/temp_csv');
        if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
        $path = $file->store('temp_csv', 'local');

        try {
            $csvData = [];
            $errors = [];
            $line = 0;

            if (($handle = fopen(Storage::path($path), 'r')) !== false) {
                fgetcsv($handle, 1000, ','); // ヘッダースキップ
                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    $line++;
                    if (count($data) < 1) {
                        $errors[] = "行 {$line}: データが不足しています";
                        continue;
                    }
                    $name = trim($data[0]);
                    $detail = isset($data[1]) ? trim($data[1]) : '';
                    if (empty($name)) {
                        $errors[] = "行 {$line}: 名前が空です";
                    }
                    $csvData[] = ['line' => $line, 'name' => $name, 'detail' => $detail];
                }
                fclose($handle);
            }

            $user = Auth::user();
            $companyId = ($user && $user->user_role === 'superadmin')
                ? $request->company_id
                : ($user->company_id ?? null);
            $company = $companyId ? Company::find($companyId) : null;

            return Inertia::render('Clients/CsvPreview', [
                'csvData'    => $csvData,
                'errors'     => $errors,
                'hasErrors'  => count($errors) > 0,
                'prefix'     => $this->routePrefix(),
                'company_id' => $companyId,
                'company'    => $company ? $company->only('id', 'name') : null,
            ]);
        } catch (\Exception $e) {
            Log::error('clientCsvPreview error: ' . $e->getMessage());
            return redirect()->back()->withErrors(['csv_file' => 'CSVの処理中にエラーが発生しました: ' . $e->getMessage()]);
        }
    }

    /** CSV一括登録 */
    public function csvStore(Request $request)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('create', Client::class);
        $request->validate([
            'clients'    => 'required|array',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $user = Auth::user();
        $companyId = ($user && $user->user_role === 'superadmin')
            ? $request->company_id
            : ($user->company_id ?? null);

        foreach ($request->clients as $row) {
            Client::create([
                'name'       => $row['name'],
                'notes'      => $row['detail'] ?? null,
                'company_id' => $companyId,
            ]);
        }

        $prefix = $this->routePrefix();
        return redirect()->route("{$prefix}.clients.index")->with('success', count($request->clients) . '件のクライアントを登録しました。');
    }
}
