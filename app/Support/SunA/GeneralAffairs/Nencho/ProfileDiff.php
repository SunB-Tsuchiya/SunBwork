<?php

namespace App\Support\SunA\GeneralAffairs\Nencho;

use App\Models\SunA\GeneralAffairs\Nencho\EmployeeProfile;
use App\Models\SunA\GeneralAffairs\Nencho\Submission;

/**
 * 提出された申告（本人情報）と、社員情報（EmployeeProfile）の照合。
 * 状態: matched=一致 / unregistered=社員情報が未登録 / diff=差異あり / resolved=差異を確認して対応済み
 * 総務が「どちらが正しいか」を選ぶ単位（グループ）: 氏名・フリガナ / 生年月日 / 住所 / 世帯主。
 */
class ProfileDiff
{
    public const GROUPS = [
        'name' => ['label' => '氏名・フリガナ', 'fields' => ['name' => '氏名', 'name_kana' => 'フリガナ']],
        'birthday' => ['label' => '生年月日', 'fields' => ['birthday' => '生年月日']],
        'address' => ['label' => '住所', 'fields' => ['postal_code' => '郵便番号', 'prefecture' => '都道府県', 'city' => '市区町村', 'street' => '町域・丁目・番地・建物名']],
        'household' => ['label' => '世帯主', 'fields' => ['household_head_name' => '世帯主の氏名', 'household_head_relation' => '世帯主との続柄', 'household_head_relation_other' => '続柄（その他）']],
    ];

    /**
     * @return array{status: string, groups: array, signature: string, profile_exists: bool, resolved: ?array}
     */
    public static function compute(Submission $s, ?EmployeeProfile $profile = null): array
    {
        $profile ??= $s->user_id ? EmployeeProfile::where('user_id', $s->user_id)->first() : null;
        $groups = [];
        $anyDiff = false;
        $sig = [];
        foreach (self::GROUPS as $key => $g) {
            $rows = [];
            $differs = false;
            foreach ($g['fields'] as $f => $label) {
                $a = self::norm($f, $profile?->{$f});
                $b = self::norm($f, $s->{$f});
                $rowDiffers = $a !== $b;
                $differs = $differs || $rowDiffers;
                $rows[$f] = ['label' => $label, 'profile' => self::display($f, $profile?->{$f}), 'submission' => self::display($f, $s->{$f}), 'differs' => $rowDiffers];
                $sig[$f] = [$a, $b];
            }
            $anyDiff = $anyDiff || $differs;
            $groups[$key] = ['label' => $g['label'], 'differs' => $differs, 'fields' => $rows];
        }
        $signature = md5(json_encode($sig, JSON_UNESCAPED_UNICODE));
        $resolved = $s->profile_sync;
        $status = match (true) {
            ! $anyDiff => 'matched',
            $resolved && ($resolved['signature'] ?? null) === $signature => 'resolved',
            ! $profile => 'unregistered',
            default => 'diff',
        };

        return ['status' => $status, 'groups' => $groups, 'signature' => $signature, 'profile_exists' => (bool) $profile, 'resolved' => $status === 'resolved' ? $resolved : null];
    }

    /** 比較用に値をそろえる（空文字・null は同じ。日付は Y-m-d、続柄は整数） */
    private static function norm(string $field, $value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if ($value === null || $value === '') {
            return null;
        }
        if ($field === 'household_head_relation') {
            return (int) $value;
        }

        return trim((string) $value);
    }

    private static function display(string $field, $value): ?string
    {
        $v = self::norm($field, $value);
        if ($v === null) {
            return null;
        }
        if ($field === 'household_head_relation') {
            return NenchoRules::HOUSEHOLD_RELATIONS[$v] ?? (string) $v;
        }

        return (string) $v;
    }
}
