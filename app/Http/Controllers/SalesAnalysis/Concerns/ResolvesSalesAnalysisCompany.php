<?php

namespace App\Http\Controllers\SalesAnalysis\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * 会社別データ分離（2026-09-05）。既存の他機能（案件・クライアント等）が使っている
 * `session('superadmin_context.company_id') ?? $user->company_id`という共通パターンを
 * 売上分析にも適用する（`ResolvesContextCompany`と同じ考え方）。
 * SuperAdminが会社を切り替えていない（グローバル状態）場合はnullを返し、呼び出し側で
 * 「会社を選んでください」という案内を表示する（全社合算はしない。部署区分自体が
 * 会社ごとに異なるため合算に意味がないため）。
 */
trait ResolvesSalesAnalysisCompany
{
    protected function salesAnalysisCompanyId(): ?int
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return session('superadmin_context.company_id');
        }

        return $user->company_id;
    }

    /**
     * API/JSON系エンドポイント用。会社未選択（SuperAdminのグローバル状態）でここに到達するのは
     * フロント側のガード漏れなので、想定外として422で止める（index()側は`hasCompanySelected`を
     * 見て「会社を選んでください」という案内画面を出し、そもそもAPIを呼ばせない設計にする）。
     */
    protected function requireSalesAnalysisCompanyId(): int
    {
        $companyId = $this->salesAnalysisCompanyId();

        abort_if($companyId === null, 422, '会社が選択されていません。画面右上の会社切替から選択してください。');

        return $companyId;
    }
}
