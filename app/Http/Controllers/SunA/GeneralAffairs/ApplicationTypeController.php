<?php

namespace App\Http\Controllers\SunA\GeneralAffairs;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\SunA\GeneralAffairs\ApplicationTypeRequest;
use App\Models\SunA\GeneralAffairs\ApplicationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * サンエー印刷 総務: 申請の種類の管理（CRUD）
 */
class ApplicationTypeController extends Controller
{
    use ResolvesContextCompany;

    public function index()
    {
        return Inertia::render('SunA/GeneralAffairs/ApplicationTypes/Index', [
            'types' => ApplicationType::forCompany($this->companyId())->ordered()->get(),
            'features' => ApplicationType::FEATURES,
        ]);
    }

    public function create()
    {
        return Inertia::render('SunA/GeneralAffairs/ApplicationTypes/Form', [
            'type' => null,
            'features' => ApplicationType::FEATURES,
            'nextSortOrder' => (int) ApplicationType::forCompany($this->companyId())->max('sort_order') + 10,
        ]);
    }

    public function store(ApplicationTypeRequest $request)
    {
        ApplicationType::create($request->validated() + [
            'company_id' => $this->companyId(),
            'created_by' => Auth::id(),
        ]);

        return to_route('clerk.general_affairs.application_types.index')->with('success', '申請の種類を登録しました。');
    }

    public function edit(ApplicationType $applicationType)
    {
        $this->checkCompany($applicationType);

        return Inertia::render('SunA/GeneralAffairs/ApplicationTypes/Form', [
            'type' => $applicationType,
            'features' => ApplicationType::FEATURES,
            'nextSortOrder' => $applicationType->sort_order,
        ]);
    }

    public function update(ApplicationTypeRequest $request, ApplicationType $applicationType)
    {
        $this->checkCompany($applicationType);
        $applicationType->update($request->validated());

        return to_route('clerk.general_affairs.application_types.index')->with('success', '申請の種類を更新しました。');
    }

    public function toggle(Request $request, ApplicationType $applicationType)
    {
        $this->checkCompany($applicationType);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $applicationType->update($data);

        return to_route('clerk.general_affairs.application_types.index')
            ->with('success', $data['is_active'] ? '申請の種類を再開しました。' : '申請の種類を停止しました。');
    }

    public function destroy(ApplicationType $applicationType)
    {
        $this->checkCompany($applicationType);
        $applicationType->delete();

        return to_route('clerk.general_affairs.application_types.index')->with('success', '申請の種類を削除しました。');
    }

    private function checkCompany(ApplicationType $type): void
    {
        abort_unless((int) $type->company_id === $this->companyId(), 404);
    }

    private function companyId(): int
    {
        $id = $this->contextCompanyId() ?? Auth::user()->company_id;
        abort_unless($id, 403, '会社を選択してください。');

        return (int) $id;
    }
}
