<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_role',
        'company_id',
        'department_id',
        'assignment_id',
        'position_title_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->user_role === 'admin';
    }

    /**
     * Check if user is leader
     */
    public function isLeader(): bool
    {
        return $this->user_role === 'leader';
    }

    /**
     * Check if user is coordinator
     */
    public function isCoordinator(): bool
    {
        return $this->user_role === 'coordinator';
    }

    /**
     * Check if user is owner (deprecated - use isLeader())
     */
    public function isOwner(): bool
    {
        return $this->isLeader();
    }

    /**
     * Check if user is superadmin
     */
    public function isSuperAdmin(): bool
    {
        return $this->user_role === 'superadmin';
    }

    /**
     * Check if user is regular user
     */
    public function isUser(): bool
    {
        return $this->user_role === 'user';
    }


    /**
     * Admin 権限設定
     */
    public function adminPermission()
    {
        return $this->hasOne(\App\Models\AdminPermission::class);
    }

    /**
     * 役職称号
     */
    public function positionTitle()
    {
        return $this->belongsTo(\App\Models\PositionTitle::class);
    }

    /**
     * ユーザーの担当（アサインメント）
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Assignment::class);
    }

    /**
     * ユーザーの部署
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Department::class);
    }

    /**
     * ユーザーの会社
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }

    /**
     * Get the current team with company and department information
     */
    public function currentTeamWithDetails()
    {
        if (!$this->current_team_id) {
            return null;
        }

        return Team::with(['company', 'department'])
            ->find($this->current_team_id);
    }

    /**
     * Get all available teams for this user, organized by type
     */
    public function availableTeams()
    {
        $personalTeams = $this->ownedTeams()
            ->with(['company', 'department'])
            ->get();

        $memberTeams = $this->teams()
            ->withPivot('role')
            ->with(['company', 'department'])
            ->get();

        // TeamSwitcher.vueが期待する構造で返す
        return [
            'personal' => $personalTeams,
            'department' => $memberTeams
        ];
    }

    /**
     * Get all teams that the user owns or belongs to
     */
    public function allTeams()
    {
        $personalTeams = $this->ownedTeams()
            ->with(['company', 'department'])
            ->get();

        $memberTeams = $this->teams()
            ->withPivot('role')  // この行を追加/確認
            ->get();

        return $personalTeams->merge($memberTeams);
    }

    /**
     * Determine if the user owns the given team.
     *
     * This overrides the HasTeams::ownsTeam behaviour to support multiple owners
     * by checking the team_user pivot for role = 'owner' in addition to the
     * original foreign key ownership.
     */
    public function ownsTeam($team)
    {
        if (is_null($team)) {
            return false;
        }

        // If the conventional foreign-key matches, preserve behaviour
        if ($this->id == $team->{$this->getForeignKey()}) {
            return true;
        }

        // Otherwise check pivot for owner role
        try {
            if (method_exists($team, 'users')) {
                return $team->users()->wherePivot('role', 'owner')->where('users.id', $this->id)->exists();
            }

            return \Illuminate\Support\Facades\DB::table('team_user')
                ->where('team_id', $team->id)
                ->where('user_id', $this->id)
                ->where('role', 'owner')
                ->exists();
        } catch (\Throwable $_ex) {
            return false;
        }
    }

    /**
     * ユーザーの日報
     */
    public function diaries()
    {
        return $this->hasMany(Diary::class);
    }

    /**
     * Get the current team details for team switcher
     */
    public function getCurrentTeamDetails()
    {
        $currentTeam = $this->currentTeamWithDetails();

        if (!$currentTeam) {
            return null;
        }

        return [
            'id' => $currentTeam->id,
            'name' => $currentTeam->name,
            'team_type' => $currentTeam->team_type,
            'company_name' => $currentTeam->company ? $currentTeam->company->name : null,
            'department_name' => $currentTeam->department ? $currentTeam->department->name : null,
        ];
    }
}
