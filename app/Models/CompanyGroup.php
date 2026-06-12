<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyGroup extends Model
{
    protected $fillable = ['name', 'description', 'group_key', 'active', 'created_by'];

    protected $casts = ['active' => 'boolean'];

    public function members()
    {
        return $this->hasMany(CompanyGroupMember::class);
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_group_members');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
