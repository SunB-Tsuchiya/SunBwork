<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Team;
use App\Models\Company;
use App\Models\Department;
use App\Models\Assignment;
use App\Models\PositionTitle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Expr\Assign;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();
        $assignments = \App\Models\Assignment::all();
        $departments = Department::all();
        $user = Auth::user();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'assignments' => $assignments,
            'departments' => $departments,
            'user' => $user,
        ]);
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        // 会社ごとに部署・役職をネストして取得
        $companies = Company::with(['departments.assignments' => function($q){
            $q->where('active', true);
        }])->where('active', true)->get();

        $positionTitles = PositionTitle::orderBy('sort_order')->get()->groupBy('applicable_role');

        return Inertia::render('Admin/Users/Create', [
            'companies'    => $companies,
            'adminTitles'  => $positionTitles->get('admin',  collect())->values(),
            'leaderTitles' => $positionTitles->get('leader', collect())->values(),
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        // 管理者作成は superadmin のみ許可 (server-side guard)
    $current = Auth::user();
    if ($request->input('user_role') === 'admin' && (! $current || $current->user_role !== 'superadmin')) {
            return redirect()->route('admin.users.index')
                ->with('error', '管理者の作成は許可されていません。');
        }

        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|lowercase|email|max:255|unique:users|email',
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'assignment_id' => 'required|exists:assignments,id',
                'user_role' => [
                    'required',
                    function($attribute, $value, $fail) {
                        $allowed = ['admin', 'leader', 'coordinator', 'user'];
                        if (!in_array($value, $allowed)) {
                            $fail("{$attribute} の値 '{$value}' は許可されていません（許可値: " . implode(',', $allowed) . ")");
                        }
                    }
                ],
            ]);


            // 会社チーム（department_id=null, team_type=company）
            $companyTeam = Team::where('company_id', $request->company_id)
                ->where('team_type', 'company')
                ->first();
            // 部署チーム（company_id, department_id一致, team_type=department）
            $departmentTeam = Team::where('department_id', $request->department_id)
                ->first();

            $positionTitleId = $request->input('position_title_id') ?: null;

            $user = User::create([
                'name'              => $request->name,
                'email'             => $request->email,
                'password'          => Hash::make($request->password),
                'company_id'        => $request->company_id,
                'department_id'     => $request->department_id,
                'assignment_id'     => $request->assignment_id,
                'position_title_id' => $positionTitleId,
                'current_team_id'   => $request->company_id,
                'user_role'         => $request->user_role,
                'email_verified_at' => now(),
            ]);

            $role = ($request->user_role === 'admin') ? 'admin' : 'viewer';

            // 会社チームに登録
            // team_userはピボットテーブルだから、attachメソッドで登録
            if ($companyTeam) {
                $user->teams()->attach($companyTeam->id, ['role' => $role]);
            }
            // 部署チームに登録
            // team_userはピボットテーブルだから、attachメソッドで登録
            if ($departmentTeam) {
                $user->teams()->attach($departmentTeam->id, ['role' => $role]);
            }
            // Jetstreamの個人チームも必要なら作成
            // try {
            //     $personalTeam = Team::create([
            //         'user_id' => $user->id,
            //         'name' => $user->name . "'s Team",
            //         'personal_team' => false,
            //     ]);
            //     $user->teams()->attach($personalTeam->id, ['assignment' => 'admin', 'role' => $role]);
            // } catch (\Exception $e) {
            //     Log::error('Team作成エラー: ' . $e->getMessage());
            //     Log::error('Exception trace: ' . $e->getTraceAsString());
            // }

            return redirect()->route('admin.users.index')
                ->with('success', 'ユーザーが正常に作成されました。');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // バリデーションエラー時は登録処理を行わず、ログ出力
            Log::error('登録バリデーションエラー:', $e->errors());
            throw $e;
        }
    }

    /**
     * Display the specified user
     */
    public function show(User $user)
    {
        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified user
     */
    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email,' . $user->id,
            'assignment' => 'required|string|max:255',
            'user_role' => 'required|string',
        ]);

        // 非 superadmin が user_role を 'admin' に変更できないようサーバー側でガード
        $current = Auth::user();
        if ($request->input('user_role') === 'admin' && (! $current || $current->user_role !== 'superadmin')) {
            return redirect()->route('admin.users.index')
                ->with('error', '管理者への昇格は許可されていません。');
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'assignment' => $request->assignment,
            'user_role' => $request->user_role,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'ユーザー情報が更新されました。');
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        // 現在ログイン中のAdminユーザーは削除できない
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users.index')
                ->with('error', '自分自身のアカウントは削除できません。');
        }
        // admin ユーザーの削除は superadmin のみ許可
        $current = Auth::user();
        if ($user->user_role === 'admin' && (! $current || $current->user_role !== 'superadmin')) {
            return redirect()->route('admin.users.index')
                ->with('error', '管理者ユーザーの削除は許可されていません。');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'ユーザーが削除されました。');
    }

    /**
     * Download sample CSV file for bulk user import
     */
    public function csvSampleDownload(Request $request)
    {
        // 選択された部署の担当名を取得してサンプルに使用
        $departmentId = $request->query('department_id');
        $assignments  = [];
        if ($departmentId) {
            $assignments = \App\Models\Assignment::where('department_id', $departmentId)
                ->where('active', true)
                ->orderBy('id')
                ->pluck('name')
                ->toArray();
        }
        // 担当が取れなければ汎用例を使用
        if (empty($assignments)) {
            $assignments = ['進行管理', 'オペレーター', 'そのほか'];
        }

        // leader 用役職称号のサンプル（DBから取得）
        $leaderTitle = \App\Models\PositionTitle::where('applicable_role', 'leader')
            ->orderBy('sort_order')->value('name') ?? '部長';

        $rows = [
            ['name', 'email', 'password', 'assignment', 'user_role', 'position_title'],
            ['山田太郎', 'yamada@example.com',  'Password123!', $assignments[0],                            'user',        ''],
            ['鈴木花子', 'suzuki@example.com',  'Password123!', $assignments[1] ?? $assignments[0],         'coordinator', ''],
            ['田中一郎', 'tanaka@example.com',  'Password123!', $assignments[array_key_last($assignments)], 'leader',      $leaderTitle],
        ];

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\n";
        }

        // BOM付きUTF-8でExcelでも文字化けしない
        return response("\xEF\xBB\xBF" . $csv)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="users_sample.csv"');
    }

    /**
     * Show CSV upload form
     */
    public function csvUpload()
    {
        $companies = Company::with('departments')->where('active', 1)->get();

        return Inertia::render('Admin/Users/CsvUpload', [
            'companies' => $companies
        ]);
    }

    /**
     * Preview CSV data
     */
    public function csvPreview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            'company_id' => 'required|exists:companies,id',
            'department_id' => 'required|exists:departments,id',
        ]);
        Log::info('[csvPreview] company_id: ' . $request->company_id);
        Log::info('[csvPreview] department_id: ' . $request->department_id);

        $file = $request->file('csv_file');

        // ディレクトリが存在することを確認
        $tempDir = storage_path('app/private/temp_csv');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
            Log::info('Created temp_csv directory');
        }

        try {
            $path = $file->store('temp_csv', 'local');
            Log::info('File stored at: ' . $path);

            $fullPath = Storage::path($path);
            Log::info('Full path: ' . $fullPath);
            Log::info('File exists: ' . (file_exists($fullPath) ? 'yes' : 'no'));
        } catch (\Exception $e) {
            Log::error('Store failed: ' . $e->getMessage());
            return back()->withErrors(['csv_file' => 'ファイルの保存に失敗しました: ' . $e->getMessage()]);
        }

        try {
            $csvData = [];
            $errors = [];
            $warnings = []; // 自動修正の警告
            $seenEmails = [];
            $line = 0;

            // 選択された部署情報を取得
            $department = Department::findOrFail($request->department_id);

            if (($handle = fopen(Storage::path($path), 'r')) !== FALSE) {
                // ヘッダー行をスキップ
                $header = fgetcsv($handle, 1000, ',');

                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $line++;

                    // CSV形式: name,email,password,assignment,user_role[,position_title]
                    if (count($data) < 5) {
                        $errors[] = "行 {$line}: データが不足しています（5列以上必要です）";
                        continue;
                    }

                    $assignmentName    = trim($data[3]);
                    $positionTitleName = trim($data[5] ?? '');
                    $assignment_id     = \App\Models\Assignment::where('name', $assignmentName)
                        ->where('department_id', $department->id)
                        ->value('id');
                    $position_title_id = $positionTitleName
                        ? \App\Models\PositionTitle::where('name', $positionTitleName)->value('id')
                        : null;

                    $userData = [
                        'line'              => $line,
                        'name'              => trim($data[0]),
                        'email'             => trim($data[1]),
                        'password'          => trim($data[2]),
                        'assignment'        => $assignmentName,
                        'assignment_id'     => $assignment_id,
                        'user_role'         => trim($data[4]),
                        'position_title'    => $positionTitleName,
                        'position_title_id' => $position_title_id,
                        'company_id'        => $request->company_id,
                        'department_id'     => $request->department_id,
                    ];

                    // 基本的なバリデーション
                    if (empty($userData['name'])) {
                        $errors[] = "行 {$line}: 名前が空です";
                    }
                    if (empty($userData['email']) || !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "行 {$line}: 有効なメールアドレスではありません";
                    }
                    if (empty($userData['password'])) {
                        $errors[] = "行 {$line}: パスワードが空です";
                    }

                    // 役職のバリデーションと自動修正
                    $assignmentResult = $this->validateAndFixAssignment($userData['assignment'], $department->name);
                    if (isset($assignmentResult['error'])) {
                        $errors[] = "行 {$line}: {$assignmentResult['error']}";
                    } elseif (isset($assignmentResult['fixed'])) {
                        $userData['assignment'] = $assignmentResult['fixed'];
                        $warnings[] = "行 {$line}: 役職「{$assignmentResult['original']}」を「{$assignmentResult['fixed']}」に自動修正しました";
                    }

                    // ユーザー権限のバリデーションと自動修正
                    $userRoleResult = $this->validateAndFixUserRole($userData['user_role']);
                    if (isset($userRoleResult['error'])) {
                        $errors[] = "行 {$line}: {$userRoleResult['error']}";
                    } elseif (isset($userRoleResult['fixed'])) {
                        $userData['user_role'] = $userRoleResult['fixed'];
                        $warnings[] = "行 {$line}: ユーザー権限「{$userRoleResult['original']}」を「{$userRoleResult['fixed']}」に自動修正しました";
                    }

                    // superadmin以外のadmin登録を禁止
                    if (strtolower(trim($userData['user_role'])) === 'admin') {
                        $errors[] = "行 {$line}: CSVからの管理者(admin)登録は許可されていません。";
                    }

                    // position_title は leader のみ設定可能
                    if (!empty($userData['position_title']) && $userData['user_role'] !== 'leader') {
                        $warnings[] = "行 {$line}: 役職称号はリーダーのみ設定できます（{$userData['user_role']} には無効）。役職称号を空にしました。";
                        $userData['position_title']    = '';
                        $userData['position_title_id'] = null;
                    }

                    // CSV内重複チェック
                    if (in_array(strtolower($userData['email']), $seenEmails ?? [])) {
                        $errors[] = "行 {$line}: メールアドレス '{$userData['email']}' はこのCSV内で重複しています";
                    } else {
                        $seenEmails[] = strtolower($userData['email']);
                        // DBとの重複チェック
                        if (User::where('email', $userData['email'])->exists()) {
                            $errors[] = "行 {$line}: メールアドレス '{$userData['email']}' は既に使用されています";
                        }
                    }

                    $csvData[] = $userData;
                }
                fclose($handle);
            }

            // 一時ファイルを削除
            Storage::disk('local')->delete($path);

            // 選択された会社・部署情報を取得
            $company = Company::findOrFail($request->company_id);

            Log::info('[csvPreview] company: ' . ($company ? $company->name : 'null'));
            Log::info('[csvPreview] department: ' . ($department ? $department->name : 'null'));
            return Inertia::render('Admin/Users/CsvPreview', [
                'csvData' => $csvData,
                'errors' => $errors,
                'warnings' => $warnings,
                'hasErrors' => !empty($errors),
                'hasWarnings' => !empty($warnings),
                'company' => $company,
                'department' => $department,
                'company_id' => $request->company_id,
                'department_id' => $request->department_id,
            ]);

            // 選択された会社・部署情報を取得
            $company = Company::findOrFail($request->company_id);
            $department = Department::findOrFail($request->department_id);
            $assignment_id = Assignment::where('name', $request->input('assignment'))
                ->where('department_id', $department->id)
                ->value('id');

            return Inertia::render('Admin/Users/CsvPreview', [
                'csvData' => $csvData,
                'errors' => $errors,
                'hasErrors' => !empty($errors),
                'company' => $company,
                'department' => $department,
                'company_id' => $request->company_id,
                'department_id' => $request->department_id,
                'assignment_id' => $assignment_id,
            ]);
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return redirect()->back()->withErrors(['csv_file' => 'CSVファイルの処理中にエラーが発生しました: ' . $e->getMessage()]);
        }
    }

    /**
     * Store users from CSV data
     */
    public function csvStore(Request $request)
    {
        Log::info('csvStore method called');
        Log::info('Request method: ' . $request->method());
        Log::info('Request data: ', $request->all());

        try {
            $request->validate([
                'users' => 'required|array',
                'users.*.name' => 'required|string|max:255',
                'users.*.email' => 'required|string|lowercase|email|max:255|unique:users',
                'users.*.password' => 'required|string',
                'users.*.assignment_id' => 'required|exists:assignments,id',
                // CSV経由での 'admin' 登録は不可
                'users.*.user_role'         => 'required|in:leader,coordinator,user',
                'users.*.position_title_id' => 'nullable|exists:position_titles,id',
                'company_id' => 'required|exists:companies,id',
                'department_id' => 'required|exists:departments,id',
            ]);
            Log::info('Validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed: ', $e->errors());
            return redirect()->route('admin.users.csv.upload')
                ->with('error', 'バリデーションエラーが発生しました。CSVファイルを確認してください。')
                ->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('csvStore unexpected error: ' . $e->getMessage());
            return redirect()->route('admin.users.csv.upload')
                ->with('error', '予期せぬエラーが発生しました: ' . $e->getMessage());
        }

        $successCount = 0;
        $errors = [];

        $department = Department::findOrFail($request->department_id);
        // 会社チーム（department_id=null, team_type=company）
        $companyTeam = Team::where('company_id', $request->company_id)
            ->whereNull('department_id')
            ->where('team_type', 'company')
            ->first();
        // 部署チーム（company_id, department_id一致, team_type=department）
        $departmentTeam = Team::where('company_id', $request->company_id)
            ->where('department_id', $request->department_id)
            ->where('team_type', 'department')
            ->first();

        if (!$departmentTeam) {
            return redirect()->route('admin.users.csv.upload')
                ->withErrors(['department' => '選択された部署のチームが見つかりません。']);
        }

        DB::beginTransaction();
        try {
            foreach ($request->users as $userData) {
                Log::info('Creating user: ' . $userData['email']);
                // assignment名からassignment_idを取得
                $assignment_id = isset($userData['assignment_id']) ? $userData['assignment_id'] : null;
                // 念のためassignment_idがなければnameから再取得
                if (!$assignment_id && isset($userData['assignment'])) {
                    $assignment_id = \App\Models\Assignment::where('name', $userData['assignment'])
                        ->where('department_id', $department->id)
                        ->value('id');
                }
                // position_title_id の解決（leader のみ有効）
                $position_title_id = null;
                if (($userData['user_role'] ?? '') === 'leader') {
                    $position_title_id = $userData['position_title_id'] ?? null;
                    if (!$position_title_id && !empty($userData['position_title'])) {
                        $position_title_id = \App\Models\PositionTitle::where('name', $userData['position_title'])->value('id');
                    }
                }
                $user = User::create([
                    'name'              => $userData['name'],
                    'email'             => $userData['email'],
                    'password'          => Hash::make($userData['password']),
                    'company_id'        => $userData['company_id'],
                    'department_id'     => $userData['department_id'],
                    'assignment_id'     => $assignment_id,
                    'position_title_id' => $position_title_id,
                    'user_role'         => $userData['user_role'],
                    'current_team_id'   => $departmentTeam ? $departmentTeam->id : null,
                ]);
                $role = ($userData['user_role'] === 'admin') ? 'admin' : 'viewer';
                // 会社チームに登録
                if ($companyTeam) {
                    $user->teams()->attach($companyTeam->id, ['role' => $role]);
                }
                // 部署チームに登録
                if ($departmentTeam) {
                    $user->teams()->attach($departmentTeam->id, ['role' => $role]);
                }
                Log::info('User processing completed');
                $successCount++;
            }
            DB::commit();
            Log::info('Transaction committed successfully');
            return redirect()->route('admin.users.index')
                ->with('success', "{$successCount}件のユーザーが一括登録されました。");
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('csvStore validation error: ', $e->errors());
            return redirect()->route('admin.users.csv.upload')
                ->with('error', 'バリデーションエラーが発生しました。CSVファイルを確認してください。')
                ->withErrors($e->errors());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('csvStore failed: ' . $e->getMessage());
            Log::error('Exception trace: ' . $e->getTraceAsString());
            return redirect()->route('admin.users.csv.upload')
                ->with('error', 'ユーザー登録中にエラーが発生しました: ' . $e->getMessage());
        }
    }

    /**
     * 会社名の自動修正とバリデーション
     */
    private function validateAndFixCompanyName($name)
    {
        // 許可された会社名
        $validCompanies = [
            'サン・ブレーン',
            'サン・ブレーン株式会社',
        ];

        // タイポ修正マップ
        $typoFixes = [
            'サンブレン' => 'サン・ブレーン',
            'サンブレーン' => 'サン・ブレーン',
            'サン-ブレーン' => 'サン・ブレーン',
            'サン_ブレーン' => 'サン・ブレーン',
            'ｓａｎ・ブレーン' => 'サン・ブレーン',
            'サン・ブレン' => 'サン・ブレーン',
        ];

        $trimmedName = trim($name);

        // 自動修正を試行
        if (isset($typoFixes[$trimmedName])) {
            return ['fixed' => $typoFixes[$trimmedName], 'original' => $name];
        }

        // 完全一致チェック
        if (in_array($trimmedName, $validCompanies)) {
            return ['valid' => $trimmedName];
        }

        // 無効な会社名
        return ['error' => "無効な会社名: {$name}"];
    }

    /**
     * 部署名の自動修正とバリデーション
     */
    private function validateAndFixDepartmentName($name)
    {
        // 許可された部署名
        $validDepartments = [
            '情報出版',
            '出力',
            'オンデマンド',
        ];

        // タイポ修正マップ
        $typoFixes = [
            '情報出版部' => '情報出版',
            '情報出版課' => '情報出版',
            'ジョウホウシュッパン' => '情報出版',
            '出力部' => '出力',
            '出力課' => '出力',
            'シュツリョク' => '出力',
            'オンデマンド部' => 'オンデマンド',
            'オンデマンド課' => 'オンデマンド',
            'on-demand' => 'オンデマンド',
            'OnDemand' => 'オンデマンド',
        ];

        $trimmedName = trim($name);

        // 自動修正を試行
        if (isset($typoFixes[$trimmedName])) {
            return ['fixed' => $typoFixes[$trimmedName], 'original' => $name];
        }

        // 完全一致チェック
        if (in_array($trimmedName, $validDepartments)) {
            return ['valid' => $trimmedName];
        }

        // 無効な部署名
        return ['error' => "無効な部署名: {$name}"];
    }

    /**
     * 役職名の自動修正とバリデーション
     */
    private function validateAndFixAssignment($assignment, $departmentName)
    {
        // 部署別許可役職
        $validAssignments = [
            '情報出版' => ['管理者', '進行管理', 'オペレーター', '校正', '営業', 'そのほか'],
            '出力' => ['管理者', '進行管理', 'オペレーター', 'そのほか'],
            'オンデマンド' => ['管理者', '進行管理', 'オペレーター', 'そのほか'],
        ];

        // 共通タイポ修正マップ
        $typoFixes = [
            'かんりしゃ' => '管理者',
            'カンリシャ' => '管理者',
            'admin' => '管理者',
            'しんこうかんり' => '進行管理',
            'シンコウカンリ' => '進行管理',
            'progress' => '進行管理',
            'オペレータ' => 'オペレーター',
            'operator' => 'オペレーター',
            'OP' => 'オペレーター',
            'こうせい' => '校正',
            'コウセイ' => '校正',
            'proofreading' => '校正',
            'えいぎょう' => '営業',
            'エイギョウ' => '営業',
            'sales' => '営業',
            'その他' => 'そのほか',
            'other' => 'そのほか',
            'others' => 'そのほか',
        ];

        $trimmedAssignment = trim($assignment);

        // 自動修正を試行
        if (isset($typoFixes[$trimmedAssignment])) {
            $fixedAssignment = $typoFixes[$trimmedAssignment];

            // 修正後の役職が部署で許可されているかチェック
            if (isset($validAssignments[$departmentName]) && in_array($fixedAssignment, $validAssignments[$departmentName])) {
                return ['fixed' => $fixedAssignment, 'original' => $assignment];
            } else {
                return ['error' => "部署「{$departmentName}」では役職「{$fixedAssignment}」は使用できません"];
            }
        }

        // 完全一致チェック
        if (isset($validAssignments[$departmentName]) && in_array($trimmedAssignment, $validAssignments[$departmentName])) {
            return ['valid' => $trimmedAssignment];
        }

        // 無効な役職
        return ['error' => "部署「{$departmentName}」では無効な役職: {$assignment}"];
    }

    /**
     * ユーザー権限の自動修正とバリデーション
     */
    private function validateAndFixUserRole($userRole)
    {
        $validUserRoles = ['admin', 'leader', 'coordinator', 'user'];

        // タイポ修正マップ
        $typoFixes = [
            'administrator' => 'admin',
            'アドミン' => 'admin',
            '管理者' => 'admin',
            'リーダー' => 'leader',
            'manager' => 'leader',
            'ユーザー' => 'user',
            'member' => 'user',
            'cordinator' => 'coordinator',
            'coodinator' => 'coordinator',
            'coordnator' => 'coordinator',
        ];

        $trimmedRole = trim(strtolower($userRole));

        // 自動修正を試行
        if (isset($typoFixes[$trimmedRole])) {
            return ['fixed' => $typoFixes[$trimmedRole], 'original' => $userRole];
        }

        // 完全一致チェック
        if (in_array($trimmedRole, $validUserRoles)) {
            return ['valid' => $trimmedRole];
        }

        // 無効なユーザー権限
        return ['error' => "無効なユーザー権限: {$userRole}"];
    }
}
