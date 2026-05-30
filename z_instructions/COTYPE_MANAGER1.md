# COTYPE_MANAGER1.md — 会社タイプ別機能分離 進捗管理 v3

## ステータス凡例
| 記号 | 意味 |
|---|---|
| ⬜ | 未着手 |
| 🔄 | 作業中 |
| ✅ | 完了 |
| ⏸ | 保留 |

---

## Phase 1: DB + モデル + ミドルウェア

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 1-1 | migration: companies に company_type 追加 | ✅ | SUNBRAIN → sunbrain の更新を migration 内に含める |
| 1-2 | migration: departments に module 追加 | ✅ | 情報出版→publishing, 製版→prepress を migration 内に含める |
| 1-3 | migration: users に home_company_id 追加 | ✅ | nullable / nullOnDelete |
| 1-4 | Company モデル更新 | ✅ | company_type fillable + isSunbrain() 等のメソッド |
| 1-5 | Department モデル更新 | ✅ | module fillable |
| 1-6 | User モデル更新 | ✅ | homeCompany リレーション |
| 1-7 | CheckCompanyType ミドルウェア作成 | ✅ | |
| 1-8 | bootstrap/app.php にエイリアス登録 | ✅ | 'company_type' → CheckCompanyType |

## Phase 2: ルート保護 + SuperAdmin コンテキスト

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 2-1 | SuperAdmin/ContextController.php 作成 | ✅ | POST /superadmin/switch-context |
| 2-2 | routes/web.php に switch-context ルート追加 | ✅ | middleware: auth + superadmin |
| 2-3 | sunbrain 専用ルートグループ作成 | ✅ | ProofCoordinator + Prepress に company_type:sunbrain 追加 |
| 2-4 | 動作確認（SUNBRAIN 全通 / サンエー印刷 403） | ⬜ | Phase 5 でサンエー印刷ユーザー作成後に確認 |

## Phase 3: モジュールレジストリ + フロントエンド

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 3-1 | HandleInertiaRequests に companyType / departmentModule / superAdminContextId / switchableCompanies 追加 | ✅ | SuperAdmin は session を参照 |
| 3-2 | CompanyModules/sunbrain.js 作成 | ✅ | proof_coordinator + prepress の extraRoles 定義 |
| 3-3 | CompanyModules/general.js 作成 | ✅ | extraRoles なし |
| 3-4 | CompanyModules/index.js 作成 | ✅ | レジストリ |
| 3-5 | CompanyModuleNavButtons.vue 作成 | ✅ | |
| 3-6 | SuperAdminContextSwitcher.vue 作成 | ✅ | ヘッダー右側に配置。会社一覧ドロップダウン + グローバル切り替え |
| 3-7 | AppLayout.vue 修正 | ✅ | proof_coordinator/prepress ハードコード削除 → CompanyModuleNavButtons + SuperAdminContextSwitcher 組み込み |
| 3-8 | npm run build + 動作確認 | ✅ | ビルド成功。実ブラウザ確認は Phase 5 で |

## Phase 4: SuperAdmin 管理 UI

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 4-1 | 会社管理画面に company_type セレクト追加 | ✅ | Create.vue / Edit.vue 両方 |
| 4-2 | 部署管理画面に module セレクト追加 | ✅ | Edit.vue の部署行に機能セレクト（sunbrain 時のみ表示）|

## Phase 5: 会社登録・データ整備・動作確認

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 5-1 | SuperAdmin ユーザーの home_company_id → サン・ブレーン | ✅ | company_id=2 のまま維持（変更リスク回避） |
| 5-2 | SuperAdmin Company を削除（または非表示化） | ⏸ | 運用的な作業。ブラウザから実施可 |
| 5-3 | 株式会社サンエー印刷を登録（company_type: general） | ✅ | ID=3 で登録済み |
| 5-4 | サンエー印刷のテストユーザー作成 | ✅ | sanee-admin@test.local / sanee-user@test.local |
| 5-5 | SuperAdmin コンテキスト切り替えの動作確認 | ⬜ | ブラウザで確認 |
| 5-6 | サン・ブレーンで情報出版・製版・その他部署の表示確認 | ⬜ | ブラウザで確認 |

## Phase 6: featureFlags — 部署別ジョブフロー UI ガード

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 6-1 | HandleInertiaRequests に `featureFlags` 追加 | ✅ | proofRequest / prepressBoard |
| 6-2 | MyJobBox/Show.vue 校正依頼ボタンのガード | ✅ | `featureFlags.proofRequest` |
| 6-3 | User/ProjectJobs/Show.vue 校正依頼セクションのガード | ✅ | 同上 |
| 6-4 | Coordinator/ProjectJobs/Show.vue 校正依頼履歴セクションのガード | ✅ | 同上 |
| 6-5 | npm run build 成功 | ✅ | ブラウザ確認はユーザー側で実施 |
| 6-6 | ProgressCell.vue — proof_v2「校正管理へ依頼」オプションのガード | ✅ | usePage()でfeatureFlagsを参照 |
| 6-7 | ProgressSheets/Show.vue — ProofRequestModal + 締切延長モーダルのガード | ✅ | |
| 6-8 | WorkflowSheets/Show.vue — ProofRequestModal + 締切延長モーダルのガード | ✅ | |
| 6-9 | npm run build 成功 | ✅ | ブラウザ確認はユーザー側で実施 |
| 6-10（将来）| ProofJobs/* ルート保護（部署ミドルウェア） | ⏸ | 別途実施 |
| 6-11（将来）| ColumnTreeEditor の proof_v2/proof_user 型追加制限 | ⏸ | 別途実施 |

## Phase 7: SuperAdmin UX 改善

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 7-A | DashboardController — IrukaBoard をコンテキスト会社でフィルタ | ✅ | ResolvesContextCompany トレイト追加。グローバルモード時は「会社を選択してください」表示 |
| 7-B1 | SuperAdmin/UserController — companies + filter_company 対応 | ✅ | filter_company クエリパラメータでコンテキストとは独立してフィルタ |
| 7-B2 | SuperAdmin/Users/Index.vue — 会社タブ追加 | ✅ | 全て / 各会社のタブボタン。アクティブ時黄色 |
| 7-C | CompanyController — code 自動生成 + フォーム追加 | ✅ | 2026-05-30 実装済み |
| 7-X | npm run build 成功 | ✅ | ブラウザ確認はユーザー側で実施 |

## さくら本番デプロイ

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| D-1 | migration を先に実行（3本） | ⬜ | **コード deploy より前に必ず実行** |
| D-2 | SUNBRAIN が sunbrain になっているか確認 | ⬜ | |
| D-3 | ファイル deploy + npm run build | ⬜ | VITE_APP_BASE_PATH 切り替え必須 |

---

## 作業ログ

| 日時 | 内容 |
|---|---|
| 2026-05-29 | v1 設計（publishing/general タイプ） |
| 2026-05-29 | v2 設計（printing タイプ追加・モジュールレジストリ方式採用） |
| 2026-05-29 | v3 設計（最終確定）。組織構造を整理。sunbrain/general タイプへ集約。部署レベル module 制御追加。SuperAdmin コンテキスト切り替え機能追加 |
| 2026-05-29 | Phase 1〜3 実装完了。migration 3本実行済み。CompanyModules レジストリ + AppLayout 更新 + ContextController 作成。npm run build 成功 |
| 2026-05-29 | Phase 4〜5（コード部分）完了。会社管理UI に company_type/module セレクト追加。サンエー印刷登録・テストユーザー作成・ルート保護ロジック確認済み |
| 2026-05-29 | ナビメニュー修正: 校正ボタン visibilityCheck を Leader+情報出版のみに絞り込み（Coordinator/User は非表示）。ラベルを "Proof Admin"/"Prepress" に戻し、group='beforeUser'/'afterUser' で位置も元通りに。featureFlags 設計追加 |
| 2026-05-29 | Phase 6 完了(6-1〜6-5): featureFlags 追加・校正依頼ボタン/セクションを情報出版部署のみに制限。Admin/SuperAdmin は companyType=sunbrain なら常時表示。npm run build 成功 |
| 2026-05-30 | Phase 7 完了(7-A〜7-C): DashboardController に ResolvesContextCompany 追加・IrukaBoard コンテキスト対応、SuperAdmin ユーザー一覧に会社タブ追加（filter_company クエリパラメータ）、CompanyController code 自動生成 + フォーム改善。npm run build 成功 |
| 2026-05-30 | Phase 7-C 完了: CompanyController に generateCode() 追加、Create.vue/Edit.vue にコード入力欄追加、Edit.vue に部署追加ボタン追加、テストユーザーパスワードを password123 にリセット |
| 2026-05-29 | Phase 6-B 完了(6-6〜6-9): 進行表・管理シートの校正ガード実装。ProgressCell の「校正管理へ依頼」オプション・ProgressSheets/WorkflowSheets の ProofRequestModal + 締切延長モーダルを featureFlags.proofRequest でガード。npm run build 成功 |

---

## リスク管理

| リスク | 対処 |
|---|---|
| さくら migration 漏れ | migration 3本を一括実行。コード deploy より前に実施 |
| SUNBRAIN が general のまま | migration 内に UPDATE 文を含める |
| SuperAdmin が company_id=null のまま個人記録できない | home_company_id に サン・ブレーン ID をセット。日報・工数の保存時は home_company_id を参照 |
| コンテキスト切り替え中に別タブで操作 | session ベースなので全タブに即時反映。要注意点として運用ドキュメントに記載 |
| Clerk ルートが sunbrain 専用に入っていない | Phase 2 開始時に routes/web.php で確認して判断 |

---

## 完成後チートシート

### 新グループ会社追加
```
SuperAdmin → 会社追加（company_type: 'general' or 新 type）
新 type が必要なら: CompanyModules/{type}.js + index.js 1行 + web.php 1グループ
```

### サン・ブレーンに新部署（新機能）追加
```
SuperAdmin → 部署追加（module: 'ondemand' 等）
CompanyModules/sunbrain.js の extraRoles に追加
専用ページ・ルートを実装
→ AppLayout.vue は変更不要
```
