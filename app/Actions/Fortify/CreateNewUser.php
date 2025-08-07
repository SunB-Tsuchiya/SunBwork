<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use App\Models\Department;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     * 
     * 【重要】Fortifyによる新規ユーザー登録処理
     * カスタムフィールド: company_id, department_id, role_id, user_role
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // フォーム入力値のバリデーション
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => $this->passwordRules(),
            'company_id' => ['required', 'exists:companies,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'role_id' => ['required', 'exists:roles,id'],
            'user_role' => ['required', 'in:admin,owner,user'], // 権限レベル
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        return DB::transaction(function () use ($input) {
            return tap(User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'company_id' => $input['company_id'],
                'department_id' => $input['department_id'],
                'role_id' => $input['role_id'],
                'user_role' => $input['user_role'],
            ]), function (User $user) use ($input) {
                $this->createTeam($user, $input);
            });
        });
    }

    /**
     * Create a personal team for the user.
     * 
     * 【重要】ユーザーを適切な部署チームに所属させる処理
     * Jetstreamのチーム機能を使用
     */
    protected function createTeam(User $user, array $input): void
    {
        // 適切なチームを見つけて参加させる
        $department = \App\Models\Department::find($input['department_id']);
        $team = Team::firstOrCreate([
            'company_id' => $input['company_id'],
            'department_id' => $input['department_id'],
            'team_type' => 'department',
        ], [
            'user_id' => $user->id,
            'name' => $department->company->name . ' - ' . $department->name,
            'personal_team' => false,
        ]);

        if ($team) {
            $user->teams()->attach($team, ['role' => 'editor']);
            $user->current_team_id = $team->id;
            $user->save();
        }
    }
}
