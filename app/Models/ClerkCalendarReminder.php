<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Services\AnnouncementBlocks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClerkCalendarReminder extends Model
{
    protected $fillable = ['company_id', 'created_by', 'content', 'color_key', 'target_type', 'target_block', 'starts_on', 'ends_on', 'is_active'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeVisibleOn(Builder $query, string $date): Builder
    {
        return $query->where('is_active', true)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date);
    }

    /** 個別選択の宛先ユーザー */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'clerk_calendar_reminder_users', 'reminder_id', 'user_id');
    }

    /** 閲覧者が宛先に含まれるものだけに絞る（all=全員 / employees_only=正社員・契約社員 / individual=選択者） */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        $isEmployee = in_array($viewer->employment_type ?? 'regular', ['regular', 'contract'], true);

        // 対象範囲ブロック（全社 or 閲覧者が含まれるブロック）。admin は全ブロックに含まれる
        $query->whereIn('target_block', ['all', ...AnnouncementBlocks::blocksOf($viewer)]);

        return $query->where(function (Builder $q) use ($viewer, $isEmployee) {
            $q->where('target_type', 'all')
                ->when($isEmployee, fn (Builder $q) => $q->orWhere('target_type', 'employees_only'))
                ->orWhere(function (Builder $q) use ($viewer) {
                    $q->where('target_type', 'individual')
                        ->whereHas('users', fn (Builder $u) => $u->where('users.id', $viewer->id));
                });
        });
    }

    public static function visibleForCompany(int $companyId, ?string $date = null, ?User $viewer = null)
    {
        $date ??= now('Asia/Tokyo')->toDateString();

        return static::forCompany($companyId)
            ->visibleOn($date)
            ->when($viewer, fn (Builder $q) => $q->visibleTo($viewer))
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get(['id', 'content', 'color_key']);
    }
}
