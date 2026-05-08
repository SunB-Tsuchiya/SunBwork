# SunBWork ガイド改定 作業管理書
作成日: 2026-05-06
更新日: 2026-05-06

---

## ■ この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「GUIDE-U-01 を始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（GUIDE_MANAGER.md）を読む
2. `z_instructions/GUIDE_PLAN.md` を読む（各ガイドの詳細仕様・タブ構成・修正方針が記載されている）
3. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
4. 以下の「作業フロー」に従って進める

---

## ■ 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/GUIDE_PLAN.md` | ガイド改定計画の詳細仕様・タブ構成・セクション構成・修正方針 |
| `CLAUDE.md` | プロジェクト全体のルール（必読） |
| `z_instructions/CONSOLIDATED_09_domain_rules.md` | ドメインルール（権限・JobBox・ステータス等） |
| `resources/js/Components/Tabs/AdminNavigationTabs.vue` | Admin タブ構成（現状確認用） |
| `resources/js/Components/Tabs/LeaderNavigationTabs.vue` | Leader タブ構成（現状確認用） |
| `resources/js/Components/Tabs/CoordinatorNavigationTabs.vue` | Coordinator タブ構成（現状確認用） |
| `resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue` | ProofAdmin タブ構成（現状確認用） |
| `resources/js/Components/Tabs/UserNavigationTabs.vue` | User タブ構成（現状確認用） |
| `resources/js/Pages/Guide/` | 既存ガイドページ（改定の参照元） |

---

## ■ 作業フロー（Claude はこの手順を厳守すること）

各作業項目（GUIDE-xx）は以下のステップで進める。

```
STEP 1: 計画書を読む
  → GUIDE_PLAN.md の該当項目を読み、仕様・タブ構成・修正方針を把握する
  → 既存ガイドファイル（Pages/Guide/*.vue）を読んで現状を確認する
  → 対応するタブコンポーネントを読んで現在のタブ一覧を確認する

STEP 2: 設計・方針の提示（ユーザーに確認を取る）
  → 変更ファイル一覧・変更内容の概要・影響範囲を提示
  → 不明点があれば1つずつ質問する（複数同時に質問しない）
  → ユーザーの「OK」を確認してから次のステップへ

STEP 3: 実装
  → 承認された設計に従って実装する
  → Vue/JSファイルを変更したら npm run build を実行
  → GUIDE-P-01（ProofAdmin 新規）の場合は PHP/routes も変更後にビルド

STEP 4: 動作確認の依頼
  → 変更内容を簡潔に報告する
  → ユーザーに動作確認をお願いする（「ブラウザで /guide を開いて確認してください」）

STEP 5: 完了記録
  → ユーザーから「OK」が出たら進捗一覧のステータスを「✅ 完了」に更新
  → 備考欄に変更したファイル名を記録
  → 次の推奨作業を提示する
```

### ⚠️ 安全ルール（必ず守ること）
- STEP 2 でユーザーの確認なしに実装を始めない
- 新規ルート追加（GUIDE-P-01）は必ず別途確認を取る
- 1つの作業が完了するまで次の作業に移らない
- 既存ガイドの構成・デザインフォーマットを崩さない

---

## ■ 進捗一覧

### ガイド改定作業

| ID | 対象ロール | 内容 | ステータス | 備考 |
|----|-----------|------|-----------|------|
| GUIDE-U-01 | User | ユーザーガイド改定（タブ名修正・ステータス統一・校正系追加） | ✅ 完了 | Pages/Guide/User.vue |
| GUIDE-A-01 | Admin | Adminガイド改定（案件総覧・会議設定・権限管理説明追加） | ✅ 完了 | Pages/Guide/Admin.vue |
| GUIDE-L-01 | Leader | Leaderガイド改定（権限タブ可視性・新規タブ説明追加） | ✅ 完了 | Pages/Guide/Leader.vue |
| GUIDE-C-01 | Coordinator | Coordinatorガイド改定（旧記述削除・新機能追加・ステータス統一） | ✅ 完了 | Pages/Guide/Coordinator.vue |
| GUIDE-P-01 | ProofAdmin | ProofAdminガイド新規作成（ルート・コントローラー・ Vue・ Index追加） | ✅ 完了 | GuideController.php / routes/web.php / Pages/Guide/ProofCoordinator.vue / Pages/Guide/Index.vue |

---

## ■ ステータス凡例

| 記号 | 意味 |
|------|------|
| 🔲 未着手 | まだ始めていない |
| 🔍 調査中 | コード調査・仕様確認中 |
| 📝 設計中 | 設計・方針をユーザーと確認中 |
| 🔨 実装中 | コード変更・ビルド中 |
| ✅ 完了 | ユーザー確認済み |
| ⏸ 保留 | 依存関係・仕様未定のため一時停止 |
| ❌ スキップ | 不要と判断、またはユーザー判断でスキップ |

---

## ■ 推奨着手順

| 順序 | ID | 理由 |
|------|-----|------|
| 1番目 | GUIDE-U-01 | 変更量が少なく、現状との差異が明確なため着手しやすい |
| 2番目 | GUIDE-A-01 | タブが多いが構成が明確 |
| 3番目 | GUIDE-L-01 | 権限によるタブ可視性の説明が重要 |
| 4番目 | GUIDE-P-01 | バックエンド新規追加があるため独立して実施 |
| 5番目 | GUIDE-C-01 | 最もボリュームが多く複雑なため最後に実施 |

---

## ■ 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-05-06 | — | GUIDE_PLAN.md・GUIDE_MANAGER.md・GUIDE_PROMPT.md 作成 | Claude |
| 2026-05-06 | GUIDE-U-01 | Pages/Guide/User.vue 改定（タブ順整理・タブ名修正・ステータス4段階統一・校正状況・校正ジョブ追加） | Claude |
| 2026-05-06 | GUIDE-A-01 | Pages/Guide/Admin.vue 改定（案件総覧・会議設定セクション追加・タブ一覧更新・セクション番号繰り上げ） | Claude |
| 2026-05-06 | GUIDE-L-01 | Pages/Guide/Leader.vue 改定（案件総覧移動・クライアント管理・作業項目設定・会議設定追加・タブ順整合・カレンダー表記統一） | Claude |
| 2026-05-06 | GUIDE-C-01 | Pages/Guide/Coordinator.vue 改定（タブ8個更新・外注先管理/案件詳細5タブ/進行表一覧/進行レポート/設定を新規追加・ステータス4段階化・ガントチャート/メッセージ削除） | Claude |
| 2026-05-06 | GUIDE-P-01 | GuideController.php・web.php・ Pages/Guide/ProofCoordinator.vue(新規)・ Pages/Guide/Index.vueに ProofAdminカード追加 | Claude |
