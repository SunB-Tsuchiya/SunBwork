<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * サンエー印刷 総務: 年末調整の提出物（従業員 1 人×受付 1 件）
 */
class Submission extends Model
{
    protected $table = 'suna_ga_nencho_submissions';

    public const STATUSES = ['draft' => '入力中', 'submitted' => '提出済み', 'returned' => '差戻し'];

    /** 本人情報・区分・所得・共済など、直接入力できる列（マイナンバー・状態は含めない） */
    public const INPUT_FIELDS = [
        'name', 'name_kana', 'birthday', 'postal_code', 'prefecture', 'city', 'street',
        'household_head_name', 'household_head_relation', 'household_head_relation_other',
        'no_category', 'disability_class', 'disability_handbook_type', 'disability_issued_on', 'disability_detail',
        'single_parent_class', 'working_student', 'school_name', 'enrolled_on', 'resident_tax_single_parent_class',
        'has_secondary_job_form', 'category_changes',
        'salary_income', 'salary_income_estimate', 'other_income_estimate', 'total_income_estimate',
        'small_biz_kyosai', 'dc_corporate', 'dc_individual', 'disabled_kyosai', 'life_under23_dependent',
    ];

    protected $guarded = ['id'];

    /** マイナンバーは画面（Inertia）へ渡る配列・JSON に含めない */
    protected $hidden = ['my_number', 'snapshot', 'admin_memo', 'proxy_confirmation', 'edit_lock_token', 'profile_sync'];   // 管理者メモ・代理入力の本人確認は従業員の画面に渡さない

    protected function casts(): array
    {
        return [
            'birthday' => 'date:Y-m-d',
            'disability_issued_on' => 'date:Y-m-d',
            'enrolled_on' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'my_number' => 'encrypted',
            'my_number_provided' => 'boolean',
            'no_category' => 'boolean',
            'working_student' => 'boolean',
            'has_secondary_job_form' => 'boolean',
            'returned_at' => 'datetime',
            'edit_locked_at' => 'datetime',
            'return_steps' => 'array',
            'proxy_confirmation' => 'array',
            'profile_sync' => 'array',
            'category_changes' => 'array',
            'life_under23_dependent' => 'array',
            'snapshot' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function spouse(): HasOne
    {
        return $this->hasOne(Spouse::class);
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(Dependent::class)->orderBy('sort_order')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubmissionEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function insuranceItems(): HasMany
    {
        return $this->hasMany(InsuranceItem::class)->orderBy('kind')->orderBy('sort_order')->orderBy('id');
    }
}
