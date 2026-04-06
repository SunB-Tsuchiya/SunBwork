<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Subcontractor extends Model
{
    protected $fillable = ['name', 'company_name', 'email', 'phone', 'notes', 'company_id'];

    /** 管理担当のCoordinator（多対多） */
    public function coordinators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subcontractor_coordinators', 'subcontractor_id', 'user_id');
    }

    /** 割当（JobBox） */
    public function assignments()
    {
        return $this->hasMany(ProjectJobAssignment::class);
    }

    /** 同会社内でのスコープ */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /** ログインCoordinatorが管理する外注先に絞る */
    public function scopeManagedBy($query, int $userId)
    {
        return $query->whereHas('coordinators', fn ($q) => $q->where('users.id', $userId));
    }
}
