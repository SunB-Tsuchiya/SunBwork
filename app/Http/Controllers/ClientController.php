<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksAdminPermission;
use App\Http\Controllers\Concerns\ChecksLeaderPermission;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class ClientController extends Controller
{
    use ChecksAdminPermission, ChecksLeaderPermission;

    public function index()
    {
        $this->requireAdminPermission('client_management');
        $this->requireLeaderPermission('client_management');
        $user = Auth::user();
        if ($user && $user->user_role === 'superadmin') {
            $clients = Client::all();
        } else {
            $companyId = $user->company_id ?? null;
            $clients = Client::forCompany($companyId)->get();
        }

        return Inertia::render('Clients/Index', ['clients' => $clients]);
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
        $client->delete();
        return redirect()->route("{$this->routePrefix()}.clients.index");
    }

    /** ルートプレフィックスをロールから解決 */
    private function routePrefix(): string
    {
        $role = Auth::user()->user_role ?? 'leader';
        return match ($role) {
            'admin', 'superadmin' => 'admin',
            default => 'leader',
        };
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
