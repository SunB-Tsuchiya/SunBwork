<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Company;
use App\Models\Department;

class Size extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'label', 'width', 'height', 'unit', 'sort_order', 'company_id', 'department_id', 'coefficient'];

    /**
     * サイズの会社
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * サイズの部署
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
