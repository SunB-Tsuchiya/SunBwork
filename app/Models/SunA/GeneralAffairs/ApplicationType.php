<?php

namespace App\Models\SunA\GeneralAffairs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * サンエー印刷 総務: 申請の種類（年末調整など）
 */
class ApplicationType extends Model
{
    protected $table = 'suna_ga_application_types';

    /** 連携できる機能画面（feature_key => 表示名）。機能を追加したらここに足す */
    public const FEATURES = ['nencho' => '年末調整'];

    protected $fillable = ['company_id', 'created_by', 'name', 'description', 'feature_key', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
