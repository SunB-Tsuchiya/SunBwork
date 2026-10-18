<?php

namespace App\Services\SunA\GeneralAffairs\Nencho;

use App\Models\SunA\GeneralAffairs\Nencho\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 年末調整の同時編集の防止（編集ロック）。
 *  - 画面（タブ）ごとにトークンを発行し、保存のたびに検証する。画面を開いている間は定期的に更新（ハートビート）し、
 *    TTL 秒更新がなければ自動で失効する（ブラウザを閉じ忘れても詰まらない）
 *  - 同じ人・同じモード（本人／代理）の再取得は、そのまま引き継ぐ（再読み込み・ステップ移動）。古い画面のトークンは無効になる
 *  - 別の人、または同じ人の別モード（本人⇔代理）が編集中のときは、取得できない（読み取り専用）。引き継ぎ（force）は呼び出し側が可否を判断する
 */
class EditLockService
{
    public const TTL_SECONDS = 180;

    public const MODE_LABELS = ['self' => '本人', 'proxy' => '総務の代理'];

    /** 現在有効なロックの保持者。なければ null */
    public function holder(Submission $s): ?array
    {
        if (! $s->edit_locked_at || $s->edit_locked_at->lt(now()->subSeconds(self::TTL_SECONDS))) {
            return null;
        }

        return [
            'user_id' => $s->edit_lock_user_id,
            'name' => $s->edit_lock_name,
            'mode' => $s->edit_lock_mode,
            'mode_label' => self::MODE_LABELS[$s->edit_lock_mode] ?? '',
            'since' => $s->edit_locked_at->timezone('Asia/Tokyo')->format('H:i'),
        ];
    }

    /** @return array{ok: bool, token: ?string, holder: ?array} */
    public function acquire(Submission $s, User $user, string $mode, bool $force = false): array
    {
        return DB::transaction(function () use ($s, $user, $mode, $force) {
            $row = Submission::whereKey($s->id)->lockForUpdate()->first();
            $holder = $this->holder($row);
            $sameOwner = $holder && (int) $holder['user_id'] === (int) $user->id && $holder['mode'] === $mode;
            if ($holder && ! $sameOwner && ! $force) {
                return ['ok' => false, 'token' => null, 'holder' => $holder];
            }
            $token = Str::random(40);
            $row->update([
                'edit_lock_user_id' => $user->id, 'edit_lock_name' => $user->name, 'edit_lock_mode' => $mode,
                'edit_lock_token' => $token, 'edit_locked_at' => now(),
            ]);
            $s->setRawAttributes($row->getAttributes(), true);

            return ['ok' => true, 'token' => $token, 'holder' => null];
        });
    }

    /** トークンが、現在有効なロックのものか */
    public function verify(Submission $s, ?string $token): bool
    {
        $s->refresh();

        return $token !== null && $token !== '' && $this->holder($s) !== null && hash_equals((string) $s->edit_lock_token, $token);
    }

    /** ハートビート: 有効なトークンならロックを延長する */
    public function touch(Submission $s, ?string $token): bool
    {
        if (! $this->verify($s, $token)) {
            return false;
        }
        $s->update(['edit_locked_at' => now()]);

        return true;
    }

    /** ロックを解除する（トークンが一致するときだけ。ほかの人のロックは外さない） */
    public function release(Submission $s, ?string $token): void
    {
        $s->refresh();
        if ($token && $s->edit_lock_token && hash_equals((string) $s->edit_lock_token, $token)) {
            $s->update(['edit_lock_user_id' => null, 'edit_lock_name' => null, 'edit_lock_mode' => null, 'edit_lock_token' => null, 'edit_locked_at' => null]);
        }
    }

    /** 提出など、編集が終わったとき: 誰のロックでも解除する */
    public function clear(Submission $s): void
    {
        $s->update(['edit_lock_user_id' => null, 'edit_lock_name' => null, 'edit_lock_mode' => null, 'edit_lock_token' => null, 'edit_locked_at' => null]);
    }
}
