# イベント予定機能リニューアル Claude向けプロンプトファイル
作成日: 2026-05-02

---

## このファイルの使い方

新しい Claude セッションを開始するとき、このファイルの内容をそのまま冒頭に貼り付けてください。
または「EVENT_RENEWAL_PROMPT.md を読んで実装を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれからSunBWorkプロジェクトの「イベント予定機能リニューアル」を行います。

まず以下のファイルを必ず順番に読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール・必読）
2. `/home/tchirosb/SunBWork/z_instructions/EVENT_RENEWAL_MANAGER.md`（作業管理書・フロー・進捗一覧）
3. `/home/tchirosb/SunBWork/z_instructions/SPEC_EVENT_RENEWAL.md`（詳細仕様書）
4. `/home/tchirosb/SunBWork/z_instructions/LAYOUT_SPEC_V2.md`（レイアウト統一仕様書）

読み終えたら、以下を報告してください：
- 現在の進捗状況（未着手・進行中・完了の件数）
- 次に着手すべき推奨作業（理由も添えて）

作業は EVENT_RENEWAL_MANAGER.md に記載された「作業フロー（5ステップ）」と「安全ルール」に従って進めてください。
特に STEP 2（設計・方針の提示）でユーザーの「OK」を得るまで絶対に実装を始めないこと。
不明点があれば必ずユーザーに質問すること。質問は必ず1つずつ行うこと（複数同時に質問しない）。

各E-xx作業の完了・進捗状況は必ず EVENT_RENEWAL_MANAGER.md に記録してください：
- 作業完了時: 進捗一覧のステータスを「✅ 完了」に更新し、作業ログに変更ファイルを記録
- ビルド成功・ユーザー確認待ちの場合: ステータスを「🔨 実装中」に更新
```

---

## 設計サマリー（Claude向け補足）

### プロジェクト背景

- **業種:** 印刷・組版会社向け社内管理システム（Laravel 11 + Vue 3 + Inertia.js）
- **目的:** 「予定作成」ボタンを「案件打合せ・外出」「社内予定」の2種類に分割・リニューアルし、フォームを整備する
- **ボタン名変更:** すでに実装済み（2026-05-01）。今回は**フォーム・DB・会議設定機能・重複計算バグ修正**が対象

### 最重要ルール（CLAUDE.md より）

1. 作業前に必ず関連コードを読む
2. 設計提示 → ユーザー確認 → 実装の順を守る
3. **不明点・仕様の曖昧さがあれば必ず質問。質問は1つずつ**
4. Vue/JSファイル変更後は `npm run build`（プロジェクトルートで実行）
5. Artisan は `docker compose exec laravel bash -lc "php artisan ..."`
6. さくら本番では `route()` 必須・ハードコードパス禁止

### 作業ID一覧

| ID | フェーズ | 内容（短縮） | 依存 |
|----|---------|------------|------|
| E-08 | バグ修正 | 予定イベント同士の重複計算が機能しない問題（独立作業） | なし |
| E-01 | DB | events追加カラム・meeting_definitions・来社応対 event_item_type 追加 | なし |
| E-02 | バックエンド | Model・ClientEventController・InternalEventController・MeetingDefinitionController・Routes | E-01 |
| E-03 | フロント | CreateClientEvent.vue（案件打合せ・外出フォーム） | E-02 |
| E-04 | フロント | CreateInternalEvent.vue（社内予定フォーム） | E-02 |
| E-05 | フロント | Leader/Admin 会議設定 CRUD（MeetingDefinitions） | E-02 |
| E-06 | 接続 | Calendar.vue・Diaries/Show.vue のボタン遷移先変更 | E-03/E-04 |
| E-07 | ナビ | Leader/Admin ナビゲーションタブに会議設定を追加 | E-05 |

### E-08 の詳細（優先度高・既存バグ）

**症状:** `Events/Create.vue` で、重複チェック時に `project_job_assignment_id` を持たない純粋な予定イベント同士では除算計算が行われない。

**原因:** Create.vue の `submit()` 内で重複イベントを `jobLinked`（ジョブ紐付き）と `otherOverlap`（それ以外）に分類し、`jobLinked.length > 0` の場合しか除算ロジックを実行していない。

**修正対象ファイル:**
- `resources/js/Pages/Events/Create.vue`（既存ロジックを全イベント対象に修正）
- `resources/js/Pages/Events/CreateClientEvent.vue`（新規作成時から全イベント対象で実装）
- `resources/js/Pages/Events/CreateInternalEvent.vue`（同上）

**修正内容:** `jobLinked` / `otherOverlap` の分類をなくし、すべての `overlapping` イベントに対して「時間が長い方から短い方を差し引く」ロジックを適用する。バックエンド（EventController）は修正不要。

### イベントフォームの種類分類

**案件打合せ・外出** (`CreateClientEvent.vue`) の種類:
| 表示名 | slug | クライアント/プロジェクト連携 |
|---|---|---|
| 来社応対 | `client_visit` | あり（新規追加） |
| 顧客訪問 | `customer_visit` | あり |
| 外出 | `outing` | なし（外出先テキストのみ） |

**社内予定** (`CreateInternalEvent.vue`) の種類:
| 表示名 | slug | 会議種類セレクター |
|---|---|---|
| 打合せ（社内） | `meeting_internal` | なし |
| 会議 | `conference` | あり（meeting_definitions から） |
| そのほか | `other` | なし |

### DB 変更サマリー

```
events テーブルに追加:
  project_job_id  bigint unsigned nullable FK→project_jobs
  destination     string nullable（外出先）

新規テーブル:
  meeting_definitions（会議定義）
  meeting_definition_members（中間テーブル）

event_item_types に追加:
  来社応対 / slug: client_visit
```

### 会議設定のメンバー取得範囲

- **Leader:** 自部署のユーザーのみ（`users.department_id` = Leader の部署）
- **Admin:** 会社に所属する全ユーザー

### レイアウト基準

`z_instructions/LAYOUT_SPEC_V2.md` に従うこと。`Coordinator/ProjectJobs/` 系ページが基準。

- `#header` スロット必須（戻るボタン + ページ見出し）
- `#headerExtras` に新規作成ボタン（一覧ページのみ）
- コンテンツは `<div class="rounded bg-white p-6 shadow">` で包む
- `py-12 / max-w-7xl` の重複ラップ禁止
- `ToastUnified` の重複配置禁止

### 主要ファイルパス（参照用）

```
app/Http/Controllers/EventController.php              ← E-08 バックエンド確認用
resources/js/Pages/Events/Create.vue                  ← E-08 修正対象・新規ページの参考
resources/js/Pages/Events/CreateClientEvent.vue       ← E-03 新規作成
resources/js/Pages/Events/CreateInternalEvent.vue     ← E-04 新規作成
resources/js/Pages/Coordinator/ProjectJobs/Create.vue ← チームメンバー選択モーダルの参考（E-05）
resources/js/Components/Calendar.vue                  ← E-06 遷移先変更
resources/js/Pages/Diaries/Show.vue                   ← E-06 遷移先変更
resources/js/Components/Tabs/LeaderNavigationTabs.vue ← E-07 タブ追加
routes/web.php                                        ← E-02 ルート追加
database/migrations/                                  ← E-01 マイグレーション追加先
```

### よくある落とし穴

- `project_jobs.schedule` カラムはさくら本番に存在しない → `Arr::pull($data, 'schedule')` で除去
- Coordinator のルート名には必ず `coordinator.` プレフィックス
- Leader のルート名には必ず `leader.` プレフィックス
- さくら上の CSRF は `document.querySelector('meta[name="csrf-token"]')` から取得（クッキー不可）
- `route()` 第2引数はオブジェクト形式: `route('leader.meeting_definitions.index', {})`
