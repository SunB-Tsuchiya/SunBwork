<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    /** @use HasFactory<\Database\Factories\TeamFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'personal_team',
        'company_id',
        'department_id',
        'team_type',
        'user_id',
        'leader_id',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
        ];
    }

    /**
     * チームの会社
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * チームの部署
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Ensure pivot rows are removed when a team is deleted.
     */
    protected static function booted()
    {
        static::deleting(function ($team) {
            // detach any users from the team to clean up the pivot table
            if (method_exists($team, 'users')) {
                try {
                    $team->users()->detach();
                } catch (\Throwable $_ex) {
                    logger()->warning('Team::deleting failed to detach users', ['team_id' => $team->id, 'error' => $_ex->getMessage()]);
                }
            } else {
                // fallback: delete rows directly if pivot table exists
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('team_user')) {
                        \Illuminate\Support\Facades\DB::table('team_user')->where('team_id', $team->id)->delete();
                    }
                } catch (\Throwable $_ex) {
                    logger()->warning('Team::deleting fallback failed to delete team_user rows', ['team_id' => $team->id, 'error' => $_ex->getMessage()]);
                }
            }

            // If any users had this team as their current_team_id, null it so views don't error
            try {
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'current_team_id')) {
                    \Illuminate\Support\Facades\DB::table('users')
                        ->where('current_team_id', $team->id)
                        ->update(['current_team_id' => null, 'updated_at' => now()]);
                }
            } catch (\Throwable $_ex) {
                logger()->warning('Team::deleting failed to null current_team_id for users', ['team_id' => $team->id, 'error' => $_ex->getMessage()]);
            }
        });
    }


    /**
     * 部署チーム（個人チーム以外）のスコープ
     */
    public function scopeDepartmentTeams($query)
    {
        return $query->where('team_type', 'department');
    }

    /**
     * 個人チームのスコープ
     */
    public function scopePersonalTeams($query)
    {
        return $query->where('personal_team', true);
    }
}
