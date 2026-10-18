<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * サンエー印刷 総務: 年末調整の受付（年度ごと）
 */
class Campaign extends Model
{
    protected $table = 'suna_ga_nencho_campaigns';

    public const STATUSES = ['open' => '受付中', 'closed' => '受付終了'];

    protected $fillable = [
        'company_id', 'application_type_id', 'fiscal_year', 'title', 'opens_on', 'due_on', 'message', 'status',
        'tax_office_name', 'municipality_name', 'payer_name', 'payer_corporate_number', 'payer_postal_code',
        'payer_prefecture', 'payer_city', 'payer_street', 'created_by', 'calendar_reminder_id',
    ];

    protected function casts(): array
    {
        return ['opens_on' => 'date:Y-m-d', 'due_on' => 'date:Y-m-d', 'fiscal_year' => 'integer'];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(Target::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }
}
