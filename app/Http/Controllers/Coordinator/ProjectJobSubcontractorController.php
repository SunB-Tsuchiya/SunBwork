<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\Subcontractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectJobSubcontractorController extends Controller
{
    use ResolvesContextCompany;

    public function edit(ProjectJob $projectJob): Response
    {
        $companyId = $this->contextCompanyId();
        abort_unless($companyId && (int) $projectJob->company_id === $companyId, 404);

        return Inertia::render('Coordinator/ProjectJobs/Subcontractors/Edit', [
            'projectJob' => $projectJob->only(['id', 'jobcode', 'title', 'company_id']),
            'subcontractors' => Subcontractor::query()->forCompany($companyId)->orderBy('name')
                ->get(['id', 'name', 'email', 'phone']),
            'selectedIds' => $projectJob->subcontractors()->pluck('subcontractors.id')->values(),
        ]);
    }

    public function update(Request $request, ProjectJob $projectJob): RedirectResponse
    {
        $companyId = $this->contextCompanyId();
        abort_unless($companyId && (int) $projectJob->company_id === $companyId, 404);
        $data = $request->validate([
            'subcontractor_ids' => ['present', 'array'],
            'subcontractor_ids.*' => [
                'integer', 'distinct',
                Rule::exists('subcontractors', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
        ]);

        $projectJob->subcontractors()->syncWithPivotValues(
            collect($data['subcontractor_ids'])->map(fn ($id) => (int) $id)->all(),
            ['created_by' => $request->user()?->id]
        );

        return redirect()->route('coordinator.project_jobs.show', ['projectJob' => $projectJob->id])
            ->with('success', '案件で使用する外注先を保存しました。');
    }
}
