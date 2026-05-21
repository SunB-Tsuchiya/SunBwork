<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksAdminPermission;
use App\Http\Controllers\Concerns\ChecksLeaderPermission;
use App\Http\Controllers\Concerns\NormalizesCsvEncoding;
use App\Models\Client;
use App\Models\Company;
use App\Models\Department;
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
    use ChecksAdminPermission, ChecksLeaderPermission, NormalizesCsvEncoding;

    public function index(Request $request)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $showDormant = $request->boolean('dormant', false);
        $user = Auth::user();
        $isSuperOrAdmin = in_array($user->user_role, ['superadmin', 'admin']);
        $isLeader       = $user->user_role === 'leader';

        if ($isSuperOrAdmin) {
            $query   = Client::with('departments:id,name');
            $clients = $showDormant ? $query->dormant()->get() : $query->active()->get();

            return Inertia::render('Clients/Index', [
                'clients'     => $clients,
                'showDormant' => $showDormant,
            ]);
        }

        if ($isLeader) {
            $companyId = $user->company_id ?? null;
            $all = Client::with('departments:id,name')
                ->forCompany($companyId);
            $all = $showDormant ? $all->dormant()->get() : $all->active()->get();

            $registered   = $all->filter(fn($c) => $c->departments->contains('id', $user->department_id))->values();
            $unregistered = $all->filter(fn($c) => !$c->departments->contains('id', $user->department_id))->values();

            return Inertia::render('Clients/Index', [
                'clients'             => $registered,
                'unregisteredClients' => $unregistered,
                'showDormant'         => $showDormant,
            ]);
        }

        // Coordinator / Clerk など: 自部署のみ
        $companyId = $user->company_id ?? null;
        $query     = Client::with('departments:id,name')
            ->forCompany($companyId)
            ->whereHas('departments', fn($q) => $q->where('departments.id', $user->department_id));
        $clients   = $showDormant ? $query->dormant()->get() : $query->active()->get();

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
        $departments = Department::orderBy('id')->get(['id', 'name']);
        return Inertia::render('Clients/Create', ['departments' => $departments]);
    }

    public function store(Request $request)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $user = Auth::user();
        $this->authorize('create', Client::class);
        $isSuperOrAdmin = $user->isSuperAdmin() || $user->isAdmin();
        $isLeader       = $user->isLeader();
        $isCoordinator  = $user->isCoordinator() || $user->isClerk();

        $rules = [
            'name'        => 'required|string|max:255',
            'client_code' => 'nullable|string|max:64|unique:clients,client_code',
            'detail'      => 'nullable|string',
            'company_id'  => 'nullable|exists:companies,id',
        ];
        if ($isSuperOrAdmin) {
            $rules['department_ids']   = 'nullable|array';
            $rules['department_ids.*'] = 'exists:departments,id';
        } elseif ($isLeader || $isCoordinator) {
            $rules['department_ids']   = 'nullable|array';
            $rules['department_ids.*'] = Rule::in([$user->department_id]);
        }

        $data = $request->validate($rules);
        $departmentIds = $data['department_ids'] ?? null;
        unset($data['department_ids']);

        if (!$isSuperOrAdmin) {
            $data['company_id'] = $user->company_id ?? null;
        }

        $data['client_code'] = $data['client_code'] ? trim($data['client_code']) : null;
        $data['notes'] = $data['detail'] ?? null;
        unset($data['detail']);

        $client = Client::create($data);

        if (!empty($departmentIds)) {
            $client->departments()->attach($departmentIds);
        } elseif (!$isSuperOrAdmin && !$isLeader && $user->department_id) {
            $client->departments()->attach($user->department_id);
        }

        return redirect()->route("{$this->routePrefix()}.clients.index");
    }

    public function edit(Client $client)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('view', $client);
        $client->load('departments:id,name');
        $departments = Department::orderBy('id')->get(['id', 'name']);
        return Inertia::render('Clients/Edit', [
            'client'      => $client,
            'departments' => $departments,
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('update', $client);

        $user = Auth::user();
        $isSuperOrAdmin = in_array($user->user_role, ['superadmin', 'admin']);
        $isLeader       = $user->user_role === 'leader';

        $rules = [
            'name'        => 'required|string|max:255',
            'client_code' => ['nullable', 'string', 'max:64', Rule::unique('clients', 'client_code')->ignore($client->id)],
            'detail'      => 'nullable|string',
            'company_id'  => 'nullable|exists:companies,id',
        ];
        if ($isSuperOrAdmin) {
            $rules['department_ids']   = 'nullable|array';
            $rules['department_ids.*'] = 'exists:departments,id';
        } elseif ($isLeader) {
            $rules['department_ids']   = 'nullable|array';
            $rules['department_ids.*'] = Rule::in([$user->department_id]);
        }

        $data = $request->validate($rules);
        $departmentIds = $data['department_ids'] ?? null;
        unset($data['department_ids']);

        if (!$isSuperOrAdmin) {
            unset($data['company_id']);
        }

        $data['client_code'] = isset($data['client_code']) && $data['client_code'] !== null ? trim($data['client_code']) : null;
        $data['notes'] = $data['detail'] ?? null;
        unset($data['detail']);

        $client->update($data);

        if ($isSuperOrAdmin && $request->has('department_ids')) {
            $client->departments()->sync($departmentIds ?? []);
        } elseif ($isLeader && $request->has('department_ids')) {
            // 自部署のみオン/オフ（他部署の紐付けは変更しない）
            $ownDeptId = $user->department_id;
            if (!empty($departmentIds) && in_array($ownDeptId, $departmentIds)) {
                $client->departments()->syncWithoutDetaching([$ownDeptId]);
            } else {
                $client->departments()->detach($ownDeptId);
            }
        }

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

    /** Leader が自部署とのクライアント紐付けをトグルする */
    public function toggleDepartment(Client $client)
    {
        $this->requireLeaderPermission('client_management');
        $user = Auth::user();
        if ($user->user_role !== 'leader' || !$user->department_id) {
            abort(403);
        }

        $deptId = $user->department_id;
        if ($client->departments()->where('department_id', $deptId)->exists()) {
            $client->departments()->detach($deptId);
            $msg = "「{$client->name}」を自部署から外しました。";
        } else {
            $client->departments()->attach($deptId);
            $msg = "「{$client->name}」を自部署に登録しました。";
        }

        return redirect()->route('leader.clients.index')->with('success', $msg);
    }

    public function show(Client $client)
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $this->authorize('view', $client);
        $client->load('departments:id,name');
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
        $isSuperOrAdmin = in_array($user->user_role, ['superadmin', 'admin']);
        $query = Client::select('id', 'name', 'client_code', 'is_dormant');

        if (!$isSuperOrAdmin) {
            $query->forCompany($user->company_id ?? null)
                  ->whereHas('departments', fn($q) => $q->where('departments.id', $user->department_id));
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

        // client_code での部分一致（インライン入力オートコンプリート・モーダル検索用）
        if ($request->filled('code')) {
            $query->where('client_code', 'like', '%' . $request->code . '%');
        } elseif ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
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
            'name'        => 'required|string|max:255',
            'client_code' => 'nullable|string|max:64',
            'exclude_id'  => 'nullable|integer',
        ]);

        $inputNormalized = $this->normalizeClientName($request->name);
        $inputCode = ($request->filled('client_code')) ? trim($request->client_code) : null;

        $user  = Auth::user();
        $query = Client::select('id', 'name', 'client_code');

        if (!($user && $user->user_role === 'superadmin')) {
            $query->forCompany($user->company_id ?? null);
        }
        if ($request->filled('exclude_id')) {
            $query->where('id', '!=', (int) $request->exclude_id);
        }

        $allClients = $query->get();

        // 同名クライアントを client_code の状況で分類
        $noCodeSameName   = [];
        $diffCodeSameName = [];

        foreach ($allClients as $c) {
            if ($this->normalizeClientName($c->name) !== $inputNormalized) {
                continue;
            }
            $existingCode = $c->client_code ? trim($c->client_code) : null;

            if (empty($inputCode) || empty($existingCode)) {
                // どちらかのコードが未設定 → 統合候補として警告（ブロック）
                $noCodeSameName[] = ['id' => $c->id, 'name' => $c->name, 'client_code' => $c->client_code];
            } elseif ($inputCode !== $existingCode) {
                // 両方コードがあって異なる → 確認（通過可能）
                $diffCodeSameName[] = ['id' => $c->id, 'name' => $c->name, 'client_code' => $c->client_code];
            }
            // コードが同じかつ名前も同じ → unique 制約でサーバー側が弾く
        }

        // 同 client_code で名前が異なるクライアント → アラート（ブロック）
        $sameCodeDiffName = [];
        if (!empty($inputCode)) {
            foreach ($allClients as $c) {
                $existingCode = $c->client_code ? trim($c->client_code) : null;
                if (!empty($existingCode) && $existingCode === $inputCode && $this->normalizeClientName($c->name) !== $inputNormalized) {
                    $sameCodeDiffName[] = ['id' => $c->id, 'name' => $c->name, 'client_code' => $c->client_code];
                }
            }
        }

        return response()->json([
            'no_code_same_name'   => $noCodeSameName,
            'diff_code_same_name' => $diffCodeSameName,
            'same_code_diff_name' => $sameCodeDiffName,
        ]);
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
            'admin', 'superadmin'  => 'admin',
            'coordinator', 'clerk' => 'coordinator',
            default                => 'leader',
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
        $this->normalizeCsvStoredFile($path);

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
        $isSuperOrAdmin = in_array($user->user_role, ['superadmin', 'admin']);
        $companyId = $isSuperOrAdmin
            ? $request->company_id
            : ($user->company_id ?? null);
        $deptId = $isSuperOrAdmin ? 1 : ($user->department_id ?? 1);

        foreach ($request->clients as $row) {
            $client = Client::create([
                'name'       => $row['name'],
                'notes'      => $row['detail'] ?? null,
                'company_id' => $companyId,
            ]);
            $client->departments()->attach($deptId);
        }

        $prefix = $this->routePrefix();
        return redirect()->route("{$prefix}.clients.index")->with('success', count($request->clients) . '件のクライアントを登録しました。');
    }
}
