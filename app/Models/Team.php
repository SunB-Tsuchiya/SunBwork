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
