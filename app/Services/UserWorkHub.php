<?php

namespace App\Services;

use App\Models\BroadProject;
use App\Models\Client;
use App\Models\JobAssignmentMessage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserWorkHub
{
    public function __construct(private PersonalWorkSchedule $schedule) {}

    public function available(?User $user): bool
    {
        return $user && $this->schedule->available($user)
            && $user->company && $user->company->company_type !== 'general';
    }

    public function authorize(User $user): void
    {
        if (! $this->available($user)) throw new AuthorizationException;
    }

    public function navigation(?User $user): array
    {
        if (! $this->available($user)) return ['enabled' => false, 'unregisteredRequests' => 0];

        // Match the recipient inbox, counting assignments rather than repeated messages.
        $query = JobAssignmentMessage::query()
            ->join('project_job_assignments as a', 'a.id', '=', 'job_assignment_messages.project_job_assignment_id')
            ->join('project_jobs', 'project_jobs.id', '=', 'a.project_job_id')
            ->where('a.user_id', $user->id)
            ->where(fn ($q) => $q->where('job_assignment_messages.sender_id', '!=', $user->id)->orWhereNull('a.sender_id'))
            ->where(fn ($q) => $q->where('a.is_registered', false)->orWhereNull('a.is_registered'))
            ->where(fn ($q) => $q->where('a.completed', false)->orWhereNull('a.completed'))
            ->where(fn ($q) => $q->where('job_assignment_messages.completed', false)->orWhereNull('job_assignment_messages.completed'))
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('project_job_assignments as replacement')
                    ->whereColumn('replacement.supersedes_assignment_id', 'a.id')
                    ->whereColumn('replacement.sender_id', 'replacement.user_id');
            })
            // The visible inbox uses the newest message for each assignment.
            ->where('job_assignment_messages.id', function ($q) use ($user) {
                $q->select('latest.id')->from('job_assignment_messages as latest')
                    ->whereColumn('latest.project_job_assignment_id', 'a.id')
                    ->where(fn ($inbox) => $inbox->where('latest.sender_id', '!=', $user->id)->orWhereNull('a.sender_id'))
                    ->orderByDesc('latest.created_at')->orderByDesc('latest.id')->limit(1);
            });

        return ['enabled' => true, 'unregisteredRequests' => $query->distinct()->count('a.id')];
    }

    public function broadProjects(User $user): Builder
    {
        $this->authorize($user);

        return BroadProject::where('company_id', $user->company_id)->where('department_id', $user->department_id);
    }

    public function clients(User $user, string $term)
    {
        $this->authorize($user);

        return Client::forCompany($user->company_id)->active()
            ->when($term !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->orderBy('name')->limit(30)->get(['id', 'name']);
    }

    public function createBroadProject(User $user, array $input): BroadProject
    {
        $this->authorize($user);
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'min:1'],
            'fiscal_year' => ['nullable', 'integer', 'between:1900,2100'],
        ])->validate();
        $data['name'] = trim(preg_replace('/[\s\x{3000}]+/u', ' ', $data['name']));
        if ($data['name'] === '') throw ValidationException::withMessages(['name' => '名前を入力してください。']);
        if (isset($data['client_id']) && ! Client::forCompany($user->company_id)->active()->whereKey($data['client_id'])->exists()) {
            throw ValidationException::withMessages(['client_id' => 'この取引先は選択できません。']);
        }

        return DB::transaction(function () use ($user, $data) {
            $existing = $this->broadProjects($user)->where('name', $data['name'])
                ->where('client_id', $data['client_id'] ?? null)->where('fiscal_year', $data['fiscal_year'] ?? null)->first();
            if ($existing) throw ValidationException::withMessages([
                'name' => $existing->is_active
                    ? '同じ名前・取引先・年度の案件が登録されています。一覧から確認してください。'
                    : '同じ案件が休止中です。管理画面で確認してください。',
            ]);

            return BroadProject::create($data + [
                'company_id' => $user->company_id, 'department_id' => $user->department_id, 'is_active' => true,
            ]);
        });
    }
}
