# イベント予定機能リニューアル 作業管理書
作成日: 2026-05-02
更新日: 2026-05-02

---

## ■ この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「E-01を始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（EVENT_RENEWAL_MANAGER.md）を読む
2. `z_instructions/SPEC_EVENT_RENEWAL.md` を読む（詳細仕様）
3. `CLAUDE.md` を読む（プロジェクト全体ルール）
4. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
5. 以下の「作業フロー」に従って進める

---

## ■ 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/SPEC_EVENT_RENEWAL.md` | イベントリニューアルの詳細仕様（必読） |
| `z_instructions/LAYOUT_SPEC_V2.md` | レイアウト統一仕様書（必読） |
| `z_instructions/CONSOLIDATED_09_domain_rules.md` | ドメインルール（権限・ロール詳細） |
| `CLAUDE.md` | プロジェクト全体のルール |

---

## ■ 作業フロー（Claude はこの手順を厳守すること）

各作業項目（E-xx）は以下のステップで進める。

```
STEP 1: 計画書を読む
  → SPEC_EVENT_RENEWAL.md の該当セクションを読み、仕様を把握する
  → 関連ファイルをコードで確認する（推測で作業しない）
  → わからないことがあれば必ずユーザーに質問する（1つずつ）

STEP 2: 設計・方針の提示（ユーザーに確認を取る）
  → 変更ファイル一覧・変更内容の概要・影響範囲を提示する
  → 不明点があれば1つずつ質問する（複数同時に質問しない）
  → ユーザーの「OK」を確認してから次のステップへ進む

STEP 3: 実装
  → 承認された設計に従って実装する
  → Vue/JSファイルを変更したら npm run build を実行
  → Artisan が必要な場合は docker compose exec 経由で実行
  → さくら本番を考慮し route() 必須・ハードコードパス禁止

STEP 4: 動作確認の依頼
  → 変更内容を簡潔に報告する
  → ユーザーに動作確認をお願いする（「〜を確認してください」）

STEP 5: 完了記録
  → ユーザーから「OK」が出たら進捗一覧のステータスを「✅ 完了」に更新
  → 備考欄に変更したファイル名を記録
  → 次の推奨作業を提示する
```

### ⚠️ 安全ルール（必ず守ること）
- **STEP 2 でユーザーの確認なしに実装を始めない**
- **DB マイグレーションを伴う変更は必ず別途確認を取る**
- **1つの作業が完了するまで次の作業に移らない**
- **不明点は必ずユーザーに質問する。質問は1つずつ行う**
- エラーが出た場合は同じ操作を繰り返さず、原因を調べてから対処する
- `project_jobs.schedule` カラムはさくら本番に存在しない（`update()` に含めない）

---

## ■ 進捗一覧

### フェーズ1：DB・バックエンド

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| E-01 | DB マイグレーション（events追加カラム・meeting_definitions・来社応対追加） | ✅ 完了 | 4マイグレーション適用済み |
| E-02 | バックエンド（Model・Controller・Routes） | ✅ 完了 | MeetingDefinition, Event, ClientEventController, InternalEventController, Leader/Admin MeetingDefinitionController, routes/web.php |

### フェーズ2：フロントエンド（フォームページ）

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| E-03 | CreateClientEvent.vue（案件打合せ・外出フォーム） | ✅ 完了 | resources/js/Pages/Events/CreateClientEvent.vue |
| E-04 | CreateInternalEvent.vue（社内予定フォーム） | ✅ 完了 | resources/js/Pages/Events/CreateInternalEvent.vue |
| E-05 | Leader/Admin 会議設定（MeetingDefinitions CRUD） | ✅ 完了 | Leader/MeetingDefinitions/{Index,Create,Edit}.vue, Admin/MeetingDefinitions/{Index,Create,Edit}.vue |

### フェーズ3：フロントエンド（接続・ナビ）

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| E-06 | カレンダー・日報ボタンの遷移先変更 | ✅ 完了 | Calendar.vue, Diaries/Show.vue — openClientEventModal/openInternalEventModal に分岐 |
| E-07 | Leader/Admin ナビゲーションタブに会議設定を追加 | ✅ 完了 | LeaderNavigationTabs.vue, AdminNavigationTabs.vue |

### フェーズ4：バグ修正（重複計算）

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| E-08 | 予定イベント同士の重複計算が機能しない問題の修正 | ✅ 完了 | Events/Create.vue の submit() — jobLinked/otherOverlap 分岐を統合 |

---

## ■ 推奨実施順序

```
E-08（独立バグ修正・既存ファイルのみ変更）
  ↓
E-01（DB）→ E-02（バックエンド）
  ↓
E-03 / E-04（フォームページ、並行可）
  ↓
E-05（会議設定）
  ↓
E-06 → E-07（接続・ナビ）
```

> **E-08 は既存の `Events/Create.vue` のみを修正するため、他の作業と独立して先に着手できます。**

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

## ■ 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-05-02 | — | 仕様書（SPEC_EVENT_RENEWAL.md）・管理書（EVENT_RENEWAL_MANAGER.md）・プロンプト（EVENT_RENEWAL_PROMPT.md）作成 | Claude |
| 2026-05-02 | E-08 | Events/Create.vue 重複計算バグ修正（jobLinked/otherOverlap 分岐統合） | GitHub Copilot |
| 2026-05-02 | E-01 | DBマイグレーション 4件適用（project_job_id, destination, meeting_definitions, meeting_definition_members, client_visit） | GitHub Copilot |
| 2026-05-02 | E-02 | MeetingDefinition.php, Event.php, ClientEventController, InternalEventController, Leader/Admin MeetingDefinitionController, routes/web.php 追加・修正 | GitHub Copilot |
| 2026-05-02 | E-03 | CreateClientEvent.vue 作成 | GitHub Copilot |
| 2026-05-02 | E-04 | CreateInternalEvent.vue 作成 | GitHub Copilot |
| 2026-05-02 | E-05 | Leader/Admin MeetingDefinitions/{Index,Create,Edit}.vue 作成 | GitHub Copilot |
| 2026-05-02 | E-06 | Calendar.vue, Diaries/Show.vue ボタン遷移先を client-event / internal-event に分岐 | GitHub Copilot |
| 2026-05-02 | E-07 | LeaderNavigationTabs.vue, AdminNavigationTabs.vue に会議設定タブ追加 | GitHub Copilot |
| 2026-05-02 | — | npm run build 成功（✓ built in 12.15s） | GitHub Copilot |

---

## ■ よくある落とし穴（過去の修正から）

- さくら本番に存在しないカラムを `update()` に含めると壊れる → `Arr::pull()` で除去
- `project_jobs.schedule` カラムはさくらに存在しない
- ナビゲーションタブ（LeaderNavigationTabs.vue）に追加する際はルート名の `leader.` プレフィックスを確認
- `route()` の第2引数はオブジェクト形式: `route('leader.meeting_definitions.index', {})`
- さくら上の CSRF は `document.querySelector('meta[name="csrf-token"]')` から取得（クッキー不可）
- Artisan は必ずコンテナ内: `docker compose exec laravel bash -lc "php artisan ..."`
- Vue/JS を変更したら必ず `npm run build`（プロジェクトルート `/home/tchirosb/SunBWork` で実行）
