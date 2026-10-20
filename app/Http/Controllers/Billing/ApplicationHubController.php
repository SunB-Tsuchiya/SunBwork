<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\SunA\GeneralAffairs\ApplicationType;
use App\Models\TransportDeadlineExtension;
use App\Models\TransportExpense;
use App\Models\TransportFare;
use App\Services\Billing\Transport\TransportCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 総務・経理（Clerk）の「申請」ハブ。両社共通。
 *  - 交通費: 全 Clerk に表示（申請済みの件数をバッジで知らせる）
 *  - 年末調整など「申請の種類」: サンエー印刷（general）の総務だけ。サン・ブレーンには出さない
 * レイアウトはサンエー印刷の「申請一覧」（SunA/GeneralAffairs/Applications）を参考にしている。
 */
class ApplicationHubController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(TransportCompany::isManager($user) || $user->isLeader(), 403);

        $companyId = TransportCompany::manageScopeId($user);
        $company = $companyId ? Company::find($companyId) : null;

        // 交通費: 申請済み（要対応）の件数（この会社の分。SuperAdmin の全社表示なら全社）
        $pending = TransportExpense::where('status', TransportExpense::STATUS_SUBMITTED)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->count();

        // 期限後の申請の依頼（依頼中）の件数
        $deadlinePending = TransportDeadlineExtension::where('status', TransportDeadlineExtension::STATUS_REQUESTED)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))->count();

        // 運賃マスタ: 未検査の件数（確認待ち）。台帳の会社は、SuperAdmin なら切り替え中の会社（未選択なら自分の所属）
        $fareCompanyId = $companyId ?? TransportCompany::idFor($user);
        $fareUnverified = $fareCompanyId
            ? TransportFare::where('company_id', $fareCompanyId)->where('status', TransportFare::STATUS_UNVERIFIED)->count() : 0;

        // サンエー印刷（general）の総務だけ、既存の「申請の種類」（年末調整など）を並べる
        $showTypes = $company?->company_type === 'general' && !$this->isKeiri($user);
        $types = $showTypes
            ? ApplicationType::forCompany((int) $company->id)->where('is_active', true)->ordered()
                ->get(['id', 'name', 'description', 'feature_key'])
            : [];

        return Inertia::render('Billing/Applications/Index', [
            'transportPending' => $pending,
            'fareUnverified'   => $fareUnverified,
            'deadlinePending'  => $deadlinePending,
            'showTypes'        => $showTypes,
            'types'            => $types,
        ]);
    }

    /** 経理か（general 会社の Clerk は所属部署名に「経理」を含む。Admin 以上は選択中の種別） */
    private function isKeiri($user): bool
    {
        if ($user->isClerk()) {
            return str_contains((string) Department::find($user->department_id)?->name, '経理');
        }
        return session('clerk_kind', 'soumu') === 'keiri';
    }
}
