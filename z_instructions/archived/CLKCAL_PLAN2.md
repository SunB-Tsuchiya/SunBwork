# CLKCAL_PLAN2 — Clerk カレンダー 独自仕様（色分け・完了機能）

前フェーズ: `CLKCAL_PLAN1.md`（archived）。今回はその続きで、Clerk 向けの独自仕様を追加する。

## 要件（ユーザー原文をもとに整理）

1. 予定カードに色を選べるようにする。色パレットは
   `https://sun-brain.co.jp/members/prepress/board` のカード担当色選択と同じもの（10〜11色）を使う。
2. 各色に**自由記入のラベル**を設定できる（prepress board はユーザー選択式の select だが、Clerk は自由記入）。
3. 色設定ボタンは、CSV取込ボタンの近くではなく、ボタンバーの右寄せで離して配置する。
4. 予定の作成・編集時に色を選べ、選んだ色がカレンダー表示に反映される。
5. 色選択パネル（作成/編集時・設定時共通）は、添付画像のように「○の下に小さくラベル文字」を表示する形にする。
   ラベルは6文字程度、入りきらなければ省略（`truncate` + `title` 属性でホバー時にフル表示）。
6. 予定の「完了」機能。完了にしたらグレーアウト、またはその色のまま薄くなるなど、視覚的にわかるようにする。

## 参考実装（そのまま移植する）

`resources/js/Pages/Prepress/Board.vue` のカード色選択機能一式:
- `CARD_COLORS` 定数（indigo/blue/cyan/teal/green/yellow/orange/red/pink/purple/gray の11色。
  teal は `bg-teal-500`、green は `bg-green-500`、他は `-400` 系）
- 「担当色変更」パネル（ドラッグで並び替え可能な11色のリスト、`draggable` + `dragstart/dragover/drop/dragend`）
- バックエンド: `PrepressColorAssignment`（`color_key` unique, `user_id` nullable, `sort_order`）+
  `BoardController::updateColorAssignment` / `reorderColorAssignments` / `updateColor`

Clerk 版との違い:
- `user_id`（担当者選択）ではなく **`label`（自由記入の文字列, nullable）** を持つ
- 会社ごとに設定が独立するため **`company_id` でスコープ**する（prepress/operator は全社共通の1セットのみ）

## DB 設計

### 新規テーブル `clerk_calendar_colors`

```php
$table->id();
$table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
$table->string('color_key', 20);
$table->string('label', 20)->nullable();
$table->unsignedTinyInteger('sort_order')->default(0);
$table->timestamps();
$table->unique(['company_id', 'color_key']);
```

会社ごとの11色レコードは、マイグレーションでは作らず（会社は後から増える）、
`ClerkCalendarColorController::index()` で **未作成の color_key を `firstOrCreate` で補完**してから返す
（`PrepressColorAssignment` は全社共通の1セットなのでマイグレーション時にseed可能だが、Clerkは会社ごとに必要なため方式を変える）。

### `clerk_events` テーブルにカラム追加

```php
$table->string('color_key', 20)->nullable()->after('all_day');
$table->timestamp('completed_at')->nullable()->after('color_key');
```

`completed_at` の有無で完了状態を判定する（真偽値カラムではなく日時にしておき、いつ完了したか分かるようにする）。

## カラーパレット（Board.vue の CARD_COLORS と同一キー・同一Tailwindクラス）

| color_key | スウォッチ (Tailwind) | 用途 |
|---|---|---|
| indigo | bg-indigo-400 | |
| blue | bg-blue-400 | |
| cyan | bg-cyan-400 | |
| teal | bg-teal-500 | |
| green | bg-green-500 | |
| yellow | bg-yellow-400 | |
| orange | bg-orange-400 | |
| red | bg-red-400 | |
| pink | bg-pink-400 | |
| purple | bg-purple-400 | |
| gray | bg-gray-400 | |

FullCalendar のイベントバー背景色は Tailwind クラスではなく inline style で塗っているため（既存コードが
`info.el.style.backgroundColor = '#4f46e5'` としている）、上記11色をそれぞれ hex 化した
`CLERK_EVENT_COLORS` マップをフロントに新規定義する（indigo=#818cf8, blue=#60a5fa, cyan=#22d3ee,
teal=#14b8a6, green=#22c55e, yellow=#facc15, orange=#fb923c, red=#f87171, pink=#f472b6, purple=#c084fc,
gray=#9ca3af）。文字色は yellow のみ濃色（#78350f）、それ以外は白。color_key 未設定時は従来どおり indigo。

## 完了表示の方針

- カレンダー上のイベント: `opacity: 0.45` 程度に下げ、タイトルに取り消し線（`text-decoration: line-through`）。
  色は変えず、選択していた色のまま薄くする（ユーザーが挙げた2案のうち「その色のまま薄くなる」を採用。
  グレーアウトすると何色の予定だったか分からなくなるため）。
- スケジュール一覧パネル・週間プランナーの日別イベントチップも同様に薄色・取り消し線。
- 完了/未完了の切り替えは予定の編集モーダルに「完了にする」／「完了を取り消す」ボタンを追加する
  （削除ボタンと横並び）。一覧からの直接トグルは今回のスコープ外。

## バックエンド変更

### 新規: `app/Models/ClerkCalendarColor.php`
`company_id, color_key, label, sort_order` を fillable に持つだけのシンプルなモデル。

### 新規: `app/Http/Controllers/Clerk/ClerkCalendarColorController.php`
- `index()`: 会社の11色レコードを `firstOrCreate` で補完しつつ `sort_order` 順で返す
- `update(Request $request, string $colorKey)`: `label`（nullable, max:20）を更新
- `reorder(Request $request)`: `orders: [{color_key, sort_order}]` を受けて一括更新
  （`BoardController::reorderColorAssignments` と同じ形）

### 変更: `app/Http/Controllers/Clerk/ClerkEventController.php`
- `store` / `update` のバリデーションに `color_key => nullable|string|max:20` を追加
- `index()` のレスポンスに `color_key` と `completed`（`completed_at !== null`）を追加
- 新規メソッド `complete(ClerkEvent $event)`: `completed_at` を now()/null でトグルして返す

## ルート追加（`routes/web.php` の既存 `clerk.` グループ内、`calendar.week_posts.*` の下に追加）

```
GET    /clerk/calendar/colors                clerk.calendar.colors.index
PATCH  /clerk/calendar/colors/{colorKey}      clerk.calendar.colors.update
POST   /clerk/calendar/colors/reorder         clerk.calendar.colors.reorder
PATCH  /clerk/calendar/events/{event}/complete clerk.calendar.events.complete
```

## フロントエンド変更

### 新規: `resources/js/Components/Clerk/ClerkCalendarColorPanel.vue`
Board.vue の「担当色変更パネル」と同じUI（ドラッグ並び替え可能な11行）。ただし各行は
`<select>` ではなく `<input type="text" maxlength="6">` で自由記入ラベルを設定する。
親からは `open`（表示トグル）のみ受け取り、色データの取得・保存は内部で完結させる
（`clerk.calendar.colors.*` を叩く）。保存後 `colors-updated` イベントを emit し、
親（`ClerkScheduleCalendar.vue`）はそれを受けてラベル表示用のカラー設定を再取得する。

### 変更: `resources/js/Components/Clerk/ClerkScheduleCalendar.vue`
- ボタンバー: 「色設定」ボタンを追加。`ml-auto` で他のボタン群から右に切り離して配置
  （CSV出力・CSV取込からは離す）。クリックで `ClerkCalendarColorPanel` を開閉。
- 予定作成・編集モーダルに色ピッカー行を追加: 11色を○＋下にラベル（6文字/truncate/title属性）で並べ、
  クリックで `eventForm.color_key` を設定。選択中の色は枠線で強調（Board.vue の
  `border-gray-700 scale-110` と同じ見た目）。
- 編集モーダルに「完了にする」／「完了を取り消す」ボタンを追加（削除ボタンの隣）。
- FullCalendar `eventDidMount`: `color_key` に応じて背景色・文字色を `CLERK_EVENT_COLORS` から設定
  （未設定時は indigo）。`completed` なら `opacity: 0.45` + タイトルに取り消し線。
- スケジュール一覧パネルの表：色スウォッチ列と完了列（済み/未）を追加。

### 変更: `resources/js/Components/Clerk/ClerkWeekPlanner.vue`
週別イベントチップに色（背景をその色の薄色 `bg-*-50` 的な扱いではなく、既存の indigo 固定スタイルを
`color_key` に応じた色に変更）と、完了時の薄色・取り消し線表示を反映。

## 変更・追加ファイル一覧

### 新規
| ファイル |
|---|
| `database/migrations/xxxx_create_clerk_calendar_colors_table.php` |
| `database/migrations/xxxx_add_color_key_and_completed_at_to_clerk_events_table.php` |
| `app/Models/ClerkCalendarColor.php` |
| `app/Http/Controllers/Clerk/ClerkCalendarColorController.php` |
| `resources/js/Components/Clerk/ClerkCalendarColorPanel.vue` |

### 変更
| ファイル |
|---|
| `routes/web.php` |
| `app/Http/Controllers/Clerk/ClerkEventController.php` |
| `resources/js/Components/Clerk/ClerkScheduleCalendar.vue` |
| `resources/js/Components/Clerk/ClerkWeekPlanner.vue` |

## 今回のスコープ外

- 一覧（スケジュールパネル）からの完了ワンクリック切り替え（今回は編集モーダル経由のみ）
- 完了済み予定のフィルタ表示・非表示切り替え
- 色パレット自体のカスタマイズ（色数・色相の追加）

## 作業完了後に行うこと

1. `ChangelogSeeder` に追記 → `php artisan db:seed --class=ChangelogSeeder`
2. `z_instructions/CONSOLIDATED_09_domain_rules.md` の Clerk カレンダー節を更新
3. `CLKCAL_PLAN2.md` / `CLKCAL_MANAGER2.md` / `CLKCAL2_PROMPT.md` を `z_instructions/archived/` に移動
