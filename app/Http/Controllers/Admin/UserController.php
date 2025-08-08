<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Team;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index()
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        return Inertia::render('Admin/Users/Create');
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|string|max:255',
            'user_role' => 'required|in:admin,leader,user',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'user_role' => $request->user_role,
            'email_verified_at' => now(), // Admin作成のユーザーは即座に認証済み
        ]);

        // Jetstreamのチーム作成
        $team = Team::create([
            'user_id' => $user->id,
            'name' => $user->name . "'s Team",
            'personal_team' => true,
        ]);

        $user->current_team_id = $team->id;
        $user->save();

        return redirect()->route('admin.users.index')
            ->with('success', 'ユーザーが正常に作成されました。');
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
            'role' => 'required|string|max:255',
            'user_role' => 'required|in:admin,leader,user',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
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

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'ユーザーが削除されました。');
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

        $file = $request->file('csv_file');

        // デバッグ: アップロードされたファイルの情報を確認
        Log::info('Uploaded file info:', [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'temp_path' => $file->path(),
            'is_valid' => $file->isValid()
        ]);

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
            $line = 0;

            // 選択された部署情報を取得
            $department = Department::findOrFail($request->department_id);

            if (($handle = fopen(Storage::path($path), 'r')) !== FALSE) {
                // ヘッダー行をスキップ
                $header = fgetcsv($handle, 1000, ',');

                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $line++;

                    // CSV形式: name,email,password,role,user_role
                    if (count($data) < 5) {
                        $errors[] = "行 {$line}: データが不足しています（5列必要です）";
                        continue;
                    }

                    $userData = [
                        'line' => $line,
                        'name' => trim($data[0]),
                        'email' => trim($data[1]),
                        'password' => trim($data[2]),
                        'role' => trim($data[3]),
                        'user_role' => trim($data[4]),
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
                    $roleResult = $this->validateAndFixRole($userData['role'], $department->name);
                    if (isset($roleResult['error'])) {
                        $errors[] = "行 {$line}: {$roleResult['error']}";
                    } elseif (isset($roleResult['fixed'])) {
                        $userData['role'] = $roleResult['fixed'];
                        $warnings[] = "行 {$line}: 役職「{$roleResult['original']}」を「{$roleResult['fixed']}」に自動修正しました";
                    }

                    // ユーザー権限のバリデーションと自動修正
                    $userRoleResult = $this->validateAndFixUserRole($userData['user_role']);
                    if (isset($userRoleResult['error'])) {
                        $errors[] = "行 {$line}: {$userRoleResult['error']}";
                    } elseif (isset($userRoleResult['fixed'])) {
                        $userData['user_role'] = $userRoleResult['fixed'];
                        $warnings[] = "行 {$line}: ユーザー権限「{$userRoleResult['original']}」を「{$userRoleResult['fixed']}」に自動修正しました";
                    }

                    // 重複チェック
                    if (User::where('email', $userData['email'])->exists()) {
                        $errors[] = "行 {$line}: メールアドレス '{$userData['email']}' は既に使用されています";
                    }

                    $csvData[] = $userData;
                }
                fclose($handle);
            }

            // 一時ファイルを削除
            Storage::disk('local')->delete($path);

            // 選択された会社・部署情報を取得
            $company = Company::findOrFail($request->company_id);

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

            return Inertia::render('Admin/Users/CsvPreview', [
                'csvData' => $csvData,
                'errors' => $errors,
                'hasErrors' => !empty($errors),
                'company' => $company,
                'department' => $department,
                'company_id' => $request->company_id,
                'department_id' => $request->department_id,
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
                'users.*.role' => 'required|string|max:255',
                'users.*.user_role' => 'required|in:admin,leader,user',
                'company_id' => 'required|exists:companies,id',
                'department_id' => 'required|exists:departments,id',
            ]);
            Log::info('Validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed: ', $e->errors());
            throw $e;
        }

        $successCount = 0;
        $errors = [];

        // 部署のチームを取得
        $department = Department::findOrFail($request->department_id);
        $departmentTeam = Team::where('department_id', $department->id)
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
                $user = User::create([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                    'role' => $userData['role'],
                    'user_role' => $userData['user_role'],
                ]);
                Log::info('User created with ID: ' . $user->id);

                // 部署チームにユーザーを追加
                Log::info('Adding user to department team: ' . $departmentTeam->id);
                $user->teams()->attach($departmentTeam->id, ['role' => 'editor']);

                // 個人チームも作成
                $personalTeam = Team::create([
                    'name' => $user->name . '\'s Team',
                    'user_id' => $user->id,
                    'personal_team' => true,
                    'company_id' => $request->company_id,
                    'department_id' => $request->department_id,
                    'team_type' => 'personal',
                ]);

                // 個人チームにも追加
                $user->teams()->attach($personalTeam->id, ['role' => 'admin']);

                // 現在のチームを部署チームに設定
                $user->current_team_id = $departmentTeam->id;
                $user->save();

                Log::info('User processing completed');
                $successCount++;
            }

            DB::commit();
            Log::info('Transaction committed successfully');

            return redirect()->route('admin.users.index')
                ->with('success', "{$successCount}件のユーザーが一括登録されました。");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('csvStore failed: ' . $e->getMessage());
            Log::error('Exception trace: ' . $e->getTraceAsString());
            return redirect()->route('admin.users.csv.upload')->withErrors(['batch' => 'ユーザー登録中にエラーが発生しました: ' . $e->getMessage()]);
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
    private function validateAndFixRole($role, $departmentName)
    {
        // 部署別許可役職
        $validRoles = [
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

        $trimmedRole = trim($role);

        // 自動修正を試行
        if (isset($typoFixes[$trimmedRole])) {
            $fixedRole = $typoFixes[$trimmedRole];

            // 修正後の役職が部署で許可されているかチェック
            if (isset($validRoles[$departmentName]) && in_array($fixedRole, $validRoles[$departmentName])) {
                return ['fixed' => $fixedRole, 'original' => $role];
            } else {
                return ['error' => "部署「{$departmentName}」では役職「{$fixedRole}」は使用できません"];
            }
        }

        // 完全一致チェック
        if (isset($validRoles[$departmentName]) && in_array($trimmedRole, $validRoles[$departmentName])) {
            return ['valid' => $trimmedRole];
        }

        // 無効な役職
        return ['error' => "部署「{$departmentName}」では無効な役職: {$role}"];
    }

    /**
     * ユーザー権限の自動修正とバリデーション
     */
    private function validateAndFixUserRole($userRole)
    {
        $validUserRoles = ['admin', 'leader', 'user'];

        // タイポ修正マップ
        $typoFixes = [
            'administrator' => 'admin',
            'アドミン' => 'admin',
            '管理者' => 'admin',
            'リーダー' => 'leader',
            'manager' => 'leader',
            'ユーザー' => 'user',
            'member' => 'user',
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
