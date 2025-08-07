<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        $companies = Company::with('departments')->where('active', 1)->get();

        return Inertia::render('Auth/Register', [
            'companies' => $companies
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'company_id' => 'required|exists:companies,id',
            'department_id' => 'required|exists:departments,id',
            'role' => 'required|string|max:255',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'user_role' => 'user', // デフォルトは一般ユーザー
        ]);

        // 適切なチームを見つけて参加させる
        $department = Department::find($request->department_id);
        $team = \App\Models\Team::firstOrCreate([
            'company_id' => $request->company_id,
            'department_id' => $request->department_id,
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

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
