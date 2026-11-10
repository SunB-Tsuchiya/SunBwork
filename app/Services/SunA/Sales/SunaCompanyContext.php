<?php

namespace App\Services\SunA\Sales;

use App\Models\Company;
use Closure;

/**
 * サンエー印刷 売上レポート（SunA/Sales）の「いまの会社」。リクエストごとにミドルウェアが設定する。
 *
 * 会社分離は fail-closed: 会社が設定されていない状態で SunA/Sales の Model を使うと例外になる
 * （絞り込み漏れ＝他社データの閲覧になるため。設計: SUNASALES_PLAN1.md §5.3）。
 * 対象はサンエー印刷（company_type='general' かつ SuperAdmin 用の会社でない）だけ。サン・ブレーン等は設定できない。
 */
class SunaCompanyContext
{
    /** SuperAdmin 用の管理会社のコード（company_type が general でも売上レポートの対象にしない） */
    private const SUPERADMIN_COMPANY_CODE = 'SUPERADMIN';

    private static ?int $companyId = null;

    /** 売上レポートの対象になれる会社か（サンエー印刷のような一般会社のみ） */
    public static function isEligible(int $companyId): bool
    {
        $company = Company::find($companyId);

        return $company !== null
            && $company->company_type === 'general'
            && $company->code !== self::SUPERADMIN_COMPANY_CODE;
    }

    public static function set(int $companyId): void
    {
        if (! self::isEligible($companyId)) {
            throw new SunaCompanyNotEligibleException("会社 {$companyId} は売上レポートの対象外です。");
        }
        self::$companyId = $companyId;
    }

    public static function clear(): void
    {
        self::$companyId = null;
    }

    public static function id(): ?int
    {
        return self::$companyId;
    }

    public static function requireId(): int
    {
        if (self::$companyId === null) {
            throw new SunaCompanyContextMissingException('売上レポートの会社が設定されていません（会社を特定できないクエリは実行できません）。');
        }

        return self::$companyId;
    }

    /** 指定した会社のコンテキストで処理を実行し、終わったら元に戻す（コマンド・テスト・保守用） */
    public static function run(int $companyId, Closure $callback): mixed
    {
        $previous = self::$companyId;
        self::set($companyId);
        try {
            return $callback();
        } finally {
            self::$companyId = $previous;
        }
    }
}
