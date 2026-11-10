<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PositionTitle extends Model
{
    protected $fillable = ['name', 'applicable_role', 'company_type', 'sort_order'];

    /**
     * 既定では「全社共通」の役職（company_type が NULL）だけを扱う。サンエー印刷専用の役職（本部長・副本部長）は、
     * サン・ブレーンの画面・登録・取込に出ないようにするため（SUNAROLE_PLAN1.md §4.1）。
     * サンエー印刷用の一覧が要るときは PositionTitle::forCompanyType('general') を使う。
     */
    protected static function booted(): void
    {
        static::addGlobalScope('common_only', fn (Builder $q) => $q->whereNull($q->getModel()->getTable() . '.company_type'));
    }

    /** 全社共通の役職＋その会社タイプ専用の役職（並びは sort_order → id） */
    public static function forCompanyType(?string $companyType)
    {
        $q = static::withoutGlobalScope('common_only');
        if ($companyType === null) {
            return $q->whereNull('company_type')->orderBy('sort_order')->orderBy('id');
        }

        return $q->where(fn ($w) => $w->whereNull('company_type')->orWhere('company_type', $companyType))->orderBy('sort_order')->orderBy('id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
