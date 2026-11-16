<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\BroadProjectTree;
use App\Services\PersonalWorkSchedule;
use Illuminate\Http\Request;

class QuickBroadProjectController extends Controller
{
    public function __construct(private BroadProjectTree $tree, private PersonalWorkSchedule $schedule) {}

    private function authorizeContext(Request $request): array
    {
        $user = $request->user();
        $context = $request->session()->get('superadmin_context.company_id');
        abort_unless($this->schedule->canViewDepartmentWork($user, $context === null ? null : (int) $context), 403);
        $this->tree->authorizeBrowse($user);
        return ['company_id' => (int) $user->company_id, 'department_id' => (int) $user->department_id];
    }

    public function options(Request $request)
    {
        $scope = $this->authorizeContext($request);
        return response()->json(['scope' => $scope, 'projects' => $this->tree->rows($request->user())]);
    }

    public function store(Request $request)
    {
        $scope = $this->authorizeContext($request);
        $expected = $request->validate(['scope.company_id' => ['required','integer'], 'scope.department_id' => ['required','integer']]);
        abort_unless((int) $expected['scope']['company_id'] === $scope['company_id']
            && (int) $expected['scope']['department_id'] === $scope['department_id'], 409, '会社・部署が変わっています。画面を開き直してください。');
        $node = $this->tree->save($request->user(), $request->only(['name','group_type','parent_id','fiscal_year','client_ids']), null, true);
        $project = collect($this->tree->rows($request->user()))->firstWhere('id', $node->id);
        return response()->json(['project' => $project, 'scope' => $scope], 201);
    }
}
