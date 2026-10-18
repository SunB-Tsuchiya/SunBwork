<?php

namespace App\Http\Controllers\SunA\GeneralAffairs\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\SunA\GeneralAffairs\Nencho\EmployeeProfileRequest;
use App\Models\SunA\GeneralAffairs\Nencho\EmployeeProfile;
use App\Models\User;
use App\Services\SunA\GeneralAffairs\Nencho\MyNumberAuditService;
use App\Support\SunA\GeneralAffairs\MarkdownGuide;
use App\Support\SunA\GeneralAffairs\CompanyTabs;
use App\Support\SunA\GeneralAffairs\Nencho\NenchoRules;
use App\Support\SunA\GeneralAffairs\StaffAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * サンエー印刷 総務: 社員情報（両社の社員を扱う。会社では絞り込まず、権限（StaffAccess）で制御し、画面で会社ごとに切り分ける）
 * ユーザー情報（users）とは別。マイナンバー・住所・最寄り駅などは権限のある人だけが見られる。
 */
class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $companies = CompanyTabs::list();
        $selected = $companies->firstWhere('id', (int) $request->query('company')) ?? $companies->firstWhere('id', Auth::user()->company_id) ?? $companies->first();
        abort_unless($selected, 404);

        $profiles = EmployeeProfile::where('company_id', $selected->id)->get()->keyBy('user_id');
        $users = User::where('company_id', $selected->id)
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'employment_type', 'department_id'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'department' => $u->department?->name,
                'employment_type' => $u->employment_type,
                'registered' => $profiles->has($u->id),
                'name_kana' => $profiles->get($u->id)?->name_kana,
                'employee_number' => $profiles->get($u->id)?->employee_number,
                'has_my_number' => (bool) $profiles->get($u->id)?->my_number,
            ]);

        return Inertia::render('SunA/GeneralAffairs/Employees/Index', [
            'companies' => $companies->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'selectedCompanyId' => $selected->id,
            'users' => $users,
            'employmentLabels' => NenchoRules::EMPLOYMENT_LABELS,
            'abilities' => StaffAccess::for(Auth::user())->toArray(),
        ]);
    }

    public function edit(User $user)
    {
        $this->checkEmployee($user);
        $profile = EmployeeProfile::where('user_id', $user->id)->first();

        return Inertia::render('SunA/GeneralAffairs/Employees/Form', [
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'department' => $user->department?->name,
                'employment_type' => $user->employment_type,
                'company_id' => $user->company_id,
                'company_name' => $user->company?->name,
            ],
            'profile' => $profile,                                   // my_number は $hidden で含まれない
            'maskedMyNumber' => $profile?->maskedMyNumber(),
            'prefectures' => NenchoRules::PREFECTURES,
            'relations' => NenchoRules::HOUSEHOLD_RELATIONS,
            'employmentLabels' => NenchoRules::EMPLOYMENT_LABELS,
            'abilities' => StaffAccess::for(Auth::user())->toArray(),
        ]);
    }

    public function update(EmployeeProfileRequest $request, User $user, MyNumberAuditService $audit)
    {
        $this->checkEmployee($user);
        $access = StaffAccess::for(Auth::user());
        $data = $request->validated();
        $myNumber = $data['my_number'] ?? null;
        $clear = (bool) ($data['clear_my_number'] ?? false);
        unset($data['my_number'], $data['clear_my_number']);

        // マイナンバーの登録・変更・削除には「マイナンバーを見る」権限が必要
        abort_if(($myNumber || $clear) && ! $access->viewMyNumber, 403, 'マイナンバーを変更する権限がありません。');

        $profile = EmployeeProfile::firstOrNew(['user_id' => $user->id]);
        $profile->fill($data + ['company_id' => $user->company_id]);
        $action = null;
        if ($clear) {
            $profile->my_number = null;
            $action = 'clear';
        } elseif ($myNumber) {
            $profile->my_number = $myNumber;
            $action = 'update';
        }
        $profile->save();
        if ($action) {
            $audit->record(Auth::user(), $profile, $action, $request);
        }

        return to_route('clerk.general_affairs.employees.index', ['company' => $user->company_id])
            ->with('success', "{$user->name}さんの社員情報を保存しました。");
    }

    /** マイナンバーの全文表示（表示のたびに記録する） */
    public function revealMyNumber(Request $request, User $user, MyNumberAuditService $audit)
    {
        $this->checkEmployee($user);
        $profile = EmployeeProfile::where('user_id', $user->id)->first();
        abort_unless($profile && $profile->my_number, 404, 'マイナンバーが登録されていません。');

        $audit->record(Auth::user(), $profile, 'view', $request);

        return response()->json(['my_number' => $profile->my_number])->header('Cache-Control', 'no-store');
    }

    public function guide()
    {
        // 元データ: resources/docs/suna/general_affairs/employees_overview.md（z_instructions/EMPLOYEES_OVERVIEW_FOR_GA.md 参照）
        return Inertia::render('SunA/GeneralAffairs/Employees/Guide', MarkdownGuide::render('employees_overview.md'));
    }


    private function checkEmployee(User $user): void
    {
        // SuperAdmin も所属会社（company_id）があれば社員情報の対象。所属会社のないユーザーは対象外
        abort_unless($user->company_id, 404);
    }
}
