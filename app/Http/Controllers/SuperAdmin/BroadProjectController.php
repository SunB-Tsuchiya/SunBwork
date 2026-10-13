<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BroadProject;
use App\Models\Client;
use App\Services\PersonalWorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BroadProjectController extends Controller
{
    public function __construct(private PersonalWorkSchedule $schedule) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($this->schedule->available($user), 403);

        return Inertia::render('SuperAdmin/BroadProjects/Index', [
            'projects' => BroadProject::where('company_id', $user->company_id)
                ->where('department_id', $user->department_id)
                ->with('client:id,name')
                ->orderByDesc('is_active')->orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function clients(Request $request)
    {
        $user = $request->user();
        abort_unless($this->schedule->available($user), 403);
        $params = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($params['q'] ?? '');

        return response()->json(Client::forCompany($user->company_id)
            ->active()
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name')->limit(30)->get(['id', 'name']));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless($this->schedule->available($user), 403);
        $data = $this->validated($request);
        $this->checkClient($data['client_id'] ?? null, $user->company_id);
        BroadProject::create($data + ['company_id' => $user->company_id, 'department_id' => $user->department_id]);

        return to_route('superadmin.broad_projects.index');
    }

    public function update(Request $request, BroadProject $broadProject)
    {
        $user = $request->user();
        abort_unless($this->schedule->available($user), 403);
        abort_unless((int) $broadProject->company_id === (int) $user->company_id
            && (int) $broadProject->department_id === (int) $user->department_id, 404);
        $data = $this->validated($request);
        $this->checkClient($data['client_id'] ?? null, $user->company_id);
        $broadProject->update($data);

        return to_route('superadmin.broad_projects.index');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')],
            'fiscal_year' => ['nullable', 'integer', 'between:1900,2100'],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['name'] = trim($data['name']);
        if ($data['name'] === '') {
            throw ValidationException::withMessages(['name' => '名前を入力してください。']);
        }
        return $data;
    }

    private function checkClient(?int $clientId, int $companyId): void
    {
        if ($clientId && ! Client::forCompany($companyId)->whereKey($clientId)->exists()) {
            throw ValidationException::withMessages(['client_id' => 'この取引先は選択できません。']);
        }
    }
}
