# COTYPE1_PROMPT.md — 新セッション開始用プロンプト v3（最終版）

以下を新しいセッションの最初にそのまま貼り付けて使用すること。

---

## プロンプト本文

```
会社タイプ別機能分離プロジェクト（COTYPE）を継続します。

## 組織構造（確定）

株式会社サンエー印刷（新規・親会社 / company_type: 'general'）
- 総務 / 経理 / 営業 → ベース機能のみ

株式会社サン・ブレーン（既存 SUNBRAIN / company_type: 'sunbrain'）
- 情報出版部署（department.module: 'publishing'）→ ProofCoordinator / JobBox / GhostUsers
- 製版部署（department.module: 'prepress'）       → Prepress
- オンデマンド部署（将来 / department.module: 'ondemand'）

SuperAdmin Company → 廃止
- SuperAdmin ユーザー: company_id = NULL / home_company_id = サン・ブレーン ID
- コンテキスト切り替えでサン・ブレーン Admin または全社管理として操作

## アーキテクチャ

【会社レベル制御】
- companies.company_type: 'sunbrain' | 'general'
- CheckCompanyType ミドルウェア（bootstrap/app.php でエイリアス 'company_type' 登録）
- routes/web.php: sunbrain 専用ルートグループ middleware(['auth', 'company_type:sunbrain'])

【部署レベル制御（サン・ブレーン内）】
- departments.module: 'publishing' | 'prepress' | 'ondemand' | null
- auth.departmentModule で参照（HandleInertiaRequests.php が共有）

【フロントエンド: モジュールレジストリ方式】
- CompanyModules/sunbrain.js: proof_coordinator + prepress の extraRoles 定義
- CompanyModules/index.js:    レジストリ
- CompanyModuleNavButtons.vue: auth.companyType で対象モジュールを選び、extraRoles をフィルタして描画
- AppLayout.vue のハードコード（proof_coordinator/prepress 計20箇所）を削除してこのコンポーネントに集約
→ 新部署・新機能追加 = sunbrain.js の extraRoles に1エントリ追加するだけ（AppLayout 変更不要）

【SuperAdmin コンテキスト切り替え】
- セッション session('superadmin_context.company_id'): null = グローバル / company_id = 会社 Admin モード
- POST /superadmin/switch-context で切り替え
- SuperAdminContextSwitcher.vue: ヘッダー右側に配置したドロップダウン
- auth.companyType: SuperAdmin の場合はセッションのコンテキスト会社の type を返す（'global' / 'sunbrain' / 'general'）
- 個人記録（日報・工数）は home_company_id = サン・ブレーンに紐づく

## 新規ファイル一覧
- database/migrations/: 3本（company_type / department.module / users.home_company_id）
- app/Http/Middleware/CheckCompanyType.php
- app/Http/Controllers/SuperAdmin/ContextController.php
- resources/js/CompanyModules/sunbrain.js
- resources/js/CompanyModules/general.js
- resources/js/CompanyModules/index.js
- resources/js/Components/CompanyModuleNavButtons.vue
- resources/js/Components/SuperAdminContextSwitcher.vue

## 変更ファイル一覧
- bootstrap/app.php（ミドルウェアエイリアス登録）
- app/Models/Company.php / Department.php / User.php
- app/Http/Middleware/HandleInertiaRequests.php（companyType / departmentModule / superAdminContextId 追加）
- routes/web.php（sunbrain グループ + switch-context ルート）
- resources/js/layouts/AppLayout.vue（ハードコード削除 + 2コンポーネント組み込み）

## 現在の進捗
z_instructions/COTYPE_MANAGER1.md の進捗テーブルを確認してください。

## 今回お願いしたいこと
[ここに具体的な作業指示を書く]
例:
- Phase 1（DB migration 3本 + モデル + ミドルウェア）を実装してください
- Phase 2（ルート保護 + ContextController）を実装してください
- Phase 3（モジュールレジストリ + AppLayout 修正）を実装してください
```

---

## 参照ファイル

| ファイル | 役割 |
|---|---|
| `z_instructions/COTYPE_PLAN1.md` | 詳細設計・DB設計・コードサンプル・フェーズ別変更ファイル |
| `z_instructions/COTYPE_MANAGER1.md` | 進捗管理・作業ログ・リスク管理・チートシート |
| `z_instructions/COTYPE1_PROMPT.md` | このファイル（新セッション用） |
| `app/Models/Company.php` | Company モデル |
| `app/Http/Middleware/HandleInertiaRequests.php` | Inertia 共有データ（修正対象） |
| `resources/js/layouts/AppLayout.vue` | メインレイアウト 847行（修正対象） |
| `routes/web.php` | 全ルート（修正対象） |
| `bootstrap/app.php` | ミドルウェア登録（修正対象） |
