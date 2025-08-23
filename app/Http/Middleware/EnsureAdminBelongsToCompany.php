<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\Company;
use App\Models\Department;
use App\Models\Team;

class EnsureAdminBelongsToCompany
{
    /**
     * Handle an incoming request.
     * If the authenticated user is not superadmin, ensure route-bound models belong to user's company.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (! $user) {
            return abort(403);
        }

        // superadmin bypass: support property, method, or role-check
        $isSuperAdmin = false;
        if (method_exists($user, 'isSuperAdmin')) {
            try {
                $isSuperAdmin = (bool) $user->isSuperAdmin();
            } catch (\Throwable $e) {
                $isSuperAdmin = false;
            }
        }

        if (! $isSuperAdmin && ($user->is_superadmin ?? false)) {
            $isSuperAdmin = true;
        }

        if (! $isSuperAdmin && method_exists($user, 'hasRole')) {
            try {
                $isSuperAdmin = (bool) $user->hasRole('superadmin');
            } catch (\Throwable $e) {
                $isSuperAdmin = false;
            }
        }

        if ($isSuperAdmin) {
            return $next($request);
        }

        $params = $request->route() ? $request->route()->parameters() : [];

        foreach ($params as $key => $param) {
            // If parameter is an Eloquent model instance, check its company_id if present
            if (is_object($param)) {
                if (isset($param->company_id)) {
                    if ($param->company_id != $user->company_id) {
                        return $this->forbidden($request);
                    }
                    continue;
                }

                if (method_exists($param, 'department')) {
                    try {
                        $department = $param->department;
                        if ($department && isset($department->company_id) && $department->company_id != $user->company_id) {
                            return $this->forbidden($request);
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }

                continue;
            }

            // If parameter looks like a numeric company/department/team id, validate by querying DB
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, ['company', 'company_id'])) {
                $companyId = intval($param);
                if ($companyId && $companyId !== intval($user->company_id)) {
                    return $this->forbidden($request);
                }
            }

            if (in_array($lowerKey, ['department', 'department_id'])) {
                $departmentId = intval($param);
                if ($departmentId) {
                    $department = Department::find($departmentId);
                    if ($department && $department->company_id != $user->company_id) {
                        return $this->forbidden($request);
                    }
                }
            }

            if (in_array($lowerKey, ['team', 'team_id'])) {
                $teamId = intval($param);
                if ($teamId) {
                    $team = Team::find($teamId);
                    if ($team) {
                        // team may have company_id or department_id
                        if (isset($team->company_id) && $team->company_id != $user->company_id) {
                            return $this->forbidden($request);
                        }
                        if (isset($team->department_id) && $team->department_id) {
                            $department = Department::find($team->department_id);
                            if ($department && $department->company_id != $user->company_id) {
                                return $this->forbidden($request);
                            }
                        }
                    }
                }
            }
        }

        return $next($request);
    }

    private function forbidden(Request $request)
    {
        // If request expects Inertia response, render Errors/403 page, else abort
        if ($request->header('X-Inertia')) {
            return Inertia::render('Errors/403')->toResponse($request)->setStatusCode(403);
        }
        return abort(403);
    }
}
