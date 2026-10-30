<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;

/** Read-only management scope. This never grants access to trial input APIs. */
class WorkloadAnalysisAccess
{
    public function authorize(User $user, string $prefix): void
    {
        $roles = match ($prefix) {
            'superadmin' => ['superadmin'],
            'admin' => ['superadmin', 'admin'],
            'leader' => ['superadmin', 'admin', 'leader'],
            default => [],
        };
        abort_unless(in_array($user->user_role, $roles, true), 403);
        if ($user->isAdmin()) {
            abort_if($user->adminPermission !== null && ! $user->adminPermission->workload_analysis, 403);
        }
        if ($user->isLeader()) {
            abort_if($user->leaderPermission !== null && ! $user->leaderPermission->workload_analysis, 403);
        }
    }

    /** @return array{0: User, 1: array<int>, 2: array} */
    public function resolve(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $prefix = explode('.', (string) $request->route()?->getName())[0];
        $this->authorize($user, $prefix);
        $contextCompany = $user->isSuperAdmin() && $request->hasSession()
            ? $request->session()->get('superadmin_context.company_id') : null;
        $allowedMembers = null;
        if ($user->isLeader()) {
            // Match the legacy unit-team membership scope, not the whole department.
            $teamIds = $user->teams()->pluck('teams.id');
            $allowedMembers = User::whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teamIds))
                ->get(['users.id', 'users.company_id', 'users.department_id']);
        }
        $companyQuery = Company::query();
        if ($user->isAdmin()) {
            $companyQuery->whereKey($user->company_id);
        } elseif ($user->isLeader()) {
            $companyQuery->whereIn('id', $allowedMembers->pluck('company_id')->filter()->unique());
        } elseif ($contextCompany) {
            $companyQuery->whereKey($contextCompany);
        }
        $companies = $companyQuery->orderBy('sort_order')->orderBy('id')->get(['id', 'name']);
        $departments = Department::whereIn('company_id', $companies->pluck('id'))
            ->when($allowedMembers !== null, fn ($q) => $q->whereIn('id', $allowedMembers->pluck('department_id')->filter()->unique()))
            ->orderBy('sort_order')->orderBy('id')->get(['id', 'company_id', 'name']);
        $companyId = $this->requestedId($request, 'company_id');
        if ($companyId !== null) {
            abort_unless($companies->contains('id', $companyId), 403);
        }
        $companyId ??= $companies->contains('id', (int) ($contextCompany ?: $user->company_id))
            ? (int) ($contextCompany ?: $user->company_id) : $companies->first()?->id;
        $companyDepartments = $departments->where('company_id', $companyId);
        $departmentId = $this->requestedId($request, 'department_id');
        if ($departmentId !== null) {
            abort_unless($companyDepartments->contains('id', $departmentId), 403);
        }
        $departmentId ??= $companyDepartments->contains('id', (int) $user->department_id)
            ? (int) $user->department_id : $companyDepartments->first()?->id;
        $ids = $companyId && $departmentId
            ? User::where('company_id', $companyId)->where('department_id', $departmentId)
                ->when($allowedMembers !== null, fn ($q) => $q->whereIn('id', $allowedMembers->pluck('id')))
                ->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
        // Keep the actual identity/permissions; change only the calculation's data context.
        $viewer = clone $user;
        $viewer->company_id = $companyId;
        $viewer->department_id = $departmentId;

        return [$viewer, $ids, ['analysisContext' => [
            'routePrefix' => $prefix.'.workload_analyzer',
            'dashboardRoute' => $prefix.'.dashboard',
            'role' => $prefix,
            'companyId' => $companyId,
            'departmentId' => $departmentId,
            'companies' => $companies->values()->toArray(),
            'departments' => $departments->values()->toArray(),
            'scopeLabel' => $user->isLeader() ? '所属ユニットの対象者' : '選択部署の対象者',
        ]]];
    }

    /** Secure direct links to the retained legacy person archive as well. */
    public function assertUserVisible(Request $request, int $userId): void
    {
        $prefix = explode('.', (string) $request->route()?->getName())[0];
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $this->authorize($actor, $prefix);
        $target = User::findOrFail($userId, ['id', 'company_id', 'department_id']);
        $scoped = clone $request;
        $scoped->query = clone $request->query;
        $scoped->query->set('company_id', $target->company_id);
        $scoped->query->set('department_id', $target->department_id);
        [, $ids] = $this->resolve($scoped);
        abort_unless(in_array($userId, $ids, true), 403);
    }

    private function requestedId(Request $request, string $key): ?int
    {
        $value = $request->query($key);
        if ($value === null || $value === '') {
            return null;
        }
        abort_unless(is_scalar($value) && ctype_digit((string) $value) && (int) $value > 0, 422);

        return (int) $value;
    }
}
