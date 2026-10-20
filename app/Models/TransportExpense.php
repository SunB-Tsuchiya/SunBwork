<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TransportExpense extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'department_id',
        'charge_department_id',
        'department_code',
        'billing_date',
        'billing_month',
        'total_amount',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_comment',
        'billed_at',
    ];

    protected $casts = [
        'billing_date' => 'date:Y-m-d',
        'total_amount' => 'integer',
        'department_code' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'billed_at' => 'datetime',
    ];

    // 状態: 下書き → 申請済み → 受理／差戻し → 請求済み
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_RETURNED  = 'returned';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_BILLED    = 'billed';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT     => '下書き',
        self::STATUS_SUBMITTED => '申請済み',
        self::STATUS_RETURNED  => '差戻し',
        self::STATUS_APPROVED  => '受理',
        self::STATUS_BILLED    => '請求済み',
    ];

    /** 1 枚（1 ページ）に載せる明細行数（原本 SB交通費金銭請求伝票_23新（PDF・Excel）は 30 行。PDF と Excel で共通） */
    public const ROWS_PER_PAGE = 30;

    /** 申請者が編集・削除できる状態（下書き・差戻し） */
    public function isEditableByApplicant(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED], true);
    }

    /** 対象月の月初・月末（'Y-m-d'）。例: 2026-08 → ['2026-08-01', '2026-08-31'] */
    public static function monthRange(string $ym): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $ym . '-01')->startOfDay();
        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public const DEPARTMENT_CODES = [
        0  => '共通',
        10 => '情報出版',
        20 => '制作',
        30 => '製版',
        50 => 'オンデマンド',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** 請求先の部門（会社に登録された部署。NULL = 共通） */
    public function chargeDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'charge_department_id');
    }

    /** 部署名に対応する旧・数字コード（サン・ブレーンの用紙用）。対応が無ければ null */
    public static function codeForDepartmentName(?string $name): ?int
    {
        $code = array_search($name, self::DEPARTMENT_CODES, true);
        return $code === false ? null : (int) $code;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TransportExpenseEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransportExpenseItem::class)->orderBy('sort_order');
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items()->sum('amount');
        $this->save();
    }
}
