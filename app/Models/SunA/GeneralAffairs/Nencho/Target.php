<?php

namespace App\Models\SunA\GeneralAffairs\Nencho;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * サンエー印刷 総務: 受付の対象者
 */
class Target extends Model
{
    protected $table = 'suna_ga_nencho_targets';

    protected $fillable = ['campaign_id', 'user_id', 'user_name', 'is_excluded', 'excluded_reason', 'added_manually'];

    protected function casts(): array
    {
        return ['is_excluded' => 'boolean', 'added_manually' => 'boolean'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
