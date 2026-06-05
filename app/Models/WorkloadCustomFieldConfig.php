<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkloadCustomFieldConfig extends Model
{
    protected $fillable = ['company_id', 'department_id', 'label'];
}
