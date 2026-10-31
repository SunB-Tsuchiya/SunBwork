<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadProject extends Model
{
    protected $fillable = [
        'company_id', 'department_id', 'name', 'client_id', 'fiscal_year', 'is_active', 'parent_id',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function pathLabel(): string
    {
        $labels = []; $seen = []; $node = $this;
        while ($node && ! isset($seen[$node->id]) && count($seen) < 3) {
            if ((int) $node->company_id !== (int) $this->company_id
                || (int) $node->department_id !== (int) $this->department_id) break;
            $seen[$node->id] = true;
            $labels[] = $node->name.($node->fiscal_year ? '（'.$node->fiscal_year.'年度）' : '');
            $node = $node->parent;
        }
        return implode(' ／ ', array_reverse($labels));
    }

    public function activePath(): bool
    {
        $seen = []; $node = $this;
        while ($node) {
            if (isset($seen[$node->id]) || count($seen) >= 3 || ! $node->is_active
                || (int) $node->company_id !== (int) $this->company_id
                || (int) $node->department_id !== (int) $this->department_id) return false;
            $seen[$node->id] = true;
            if ($node->parent_id && ! $node->parent) return false;
            $node = $node->parent;
        }
        return true;
    }
}
