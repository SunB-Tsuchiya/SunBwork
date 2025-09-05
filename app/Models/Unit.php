<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use App\Models\User;

class Unit extends Model
{
    protected $fillable = ['company_id', 'department_id', 'name', 'description', 'leader_id'];

    /**
     * Unit members as User models via unit_members pivot table.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'unit_members', 'unit_id', 'user_id');
    }
}
