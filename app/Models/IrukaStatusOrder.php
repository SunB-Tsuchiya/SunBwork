<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IrukaStatusOrder extends Model
{
    protected $fillable = ['company_id', 'slug', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    private static array $defaults = [
        'present', 'present_kodai', 'left',
        'meeting', 'discussion', 'client_reception',
        'telework', 'late', 'early_leave',
        'moving', 'out', 'out_nr',
        'paid_leave', 'half_am', 'half_pm',
        'away', 'train_delay', 'special_leave',
    ];

    /**
     * 会社のステータス順序を取得。未設定の場合はデフォルトで初期化する。
     */
    public static function getOrCreateForCompany(int $companyId): \Illuminate\Database\Eloquent\Collection
    {
        $orders = static::where('company_id', $companyId)->orderBy('sort_order')->get();

        if ($orders->isEmpty()) {
            $rows = array_map(fn ($slug, $i) => [
                'company_id' => $companyId,
                'slug'       => $slug,
                'sort_order' => $i,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], static::$defaults, array_keys(static::$defaults));

            static::insert($rows);

            $orders = static::where('company_id', $companyId)->orderBy('sort_order')->get();
        }

        return $orders;
    }
}
