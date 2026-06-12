<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyGroupMember extends Model
{
    protected $fillable = ['company_group_id', 'company_id'];

    public function companyGroup()
    {
        return $this->belongsTo(CompanyGroup::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
