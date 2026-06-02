<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DiaryTeam extends Model
{
    protected $fillable = ['company_id', 'name', 'description'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function leaders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'diary_team_leaders');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'diary_team_members');
    }
}
