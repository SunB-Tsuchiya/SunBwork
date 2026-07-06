# STAGECHECK_PLAN1.md — 製版ボード 作業チェック 工程別化

最終更新: 2026-07-06
ステータス: 設計中（ユーザー確認待ち）

---

## 概要

製版ボード（`/prepress/board`）のカード詳細モーダルにある「作業チェック」（仕上がりサイズ・トンボ・面付・色数・線数・Nマークのトラップ処理・色調補正の7項目チェックボックス）を、単一セットから「初校・再校・三校・下版」の4工程に拡張する。各工程は同じ7項目チェックを持ち、さらに工程ごとに作業者（製版部署に所属するユーザー）を1名選択できるようにする。

表示イメージ:
```
作業チェック：初校　[作業者セレクター▼]
☐仕上がりサイズ ☐トンボ ☐面付 ☐色数 ☐線数 ☐Nマークのトラップ処理 ☐色調補正

作業チェック：再校　[作業者セレクター▼]
☐仕上がりサイズ ...

作業チェック：三校　[作業者セレクター▼]
...

作業チェック：下版　[作業者セレクター▼]
...
```

## 確認済みの設計方針（ユーザー回答）

| 項目 | 決定 |
|------|------|
| チェック項目構成 | 4工程それぞれに同じ7項目を繰り返し表示。作業者セレクターは項目ごとではなく**工程ごとに1人** |
| 既存チェック済みデータの移行 | 既存 `prepress_tickets.check_*` の値は新テーブルの**「初校」行**に引き継ぐ |
| モーダルの表示方法 | 初校/再校/三校/下版を**縦に並べて全て展開表示**（タブ・アコーディオンにはしない） |

---

## DB設計

### 新規テーブル `prepress_ticket_stage_checks`

```
id                        bigint PK
prepress_ticket_id        FK prepress_tickets (cascade delete)
stage                     enum('初校','再校','三校','下版')
check_finish_size         boolean default false
check_trim_marks          boolean default false
check_imposition          boolean default false
check_color_count         boolean default false
check_screen_ruling       boolean default false
check_n_mark_trap         boolean default false
check_color_correction    boolean default false
user_id                   FK users nullable (nullOnDelete)   -- 作業者
timestamps
UNIQUE(prepress_ticket_id, stage)
```

- チケット作成時に4行を必ず作るのではなく、**チェック操作が最初に行われた時点で `firstOrCreate` する**（未操作の工程はフロント側でデフォルト値表示、DB行は存在しない）。これにより既存の空チケットを一括で4倍化する不要な書き込みを避ける。

### `prepress_tickets` テーブルの変更

- 既存の `check_finish_size` / `check_trim_marks` / `check_imposition` / `check_color_count` / `check_screen_ruling` / `check_n_mark_trap` / `check_color_correction` の7カラムを**削除**（新テーブルの「初校」行に移行済みのため）
- `indesign_version` / `illustrator_version` / `check_memo` は工程共通情報のため**そのまま残す**（変更なし）

### マイグレーション（3本、順番厳守）

1. `create_prepress_ticket_stage_checks_table` — 新テーブル作成
2. `backfill_prepress_ticket_stage_checks_table` — データ移行。`prepress_tickets` を全件走査し、`check_*` のいずれかが true のチケット（あるいは全チケット）について `stage='初校'` の行を1件 insert し、既存の7カラムの値をコピー。`down()` は該当行を削除
3. `drop_check_fields_from_prepress_tickets_table` — 旧7カラムを削除。`down()` で再追加（値は復元しない旨をコメントに明記）

---

## Model設計

### 新規 `app/Models/PrepressTicketStageCheck.php`

```php
class PrepressTicketStageCheck extends Model
{
    const STAGE_FIRST  = '初校';
    const STAGE_SECOND = '再校';
    const STAGE_THIRD  = '三校';
    const STAGE_FINAL  = '下版';
    const STAGES = [self::STAGE_FIRST, self::STAGE_SECOND, self::STAGE_THIRD, self::STAGE_FINAL];

    protected $fillable = [
        'prepress_ticket_id', 'stage',
        'check_finish_size', 'check_trim_marks', 'check_imposition',
        'check_color_count', 'check_screen_ruling', 'check_n_mark_trap', 'check_color_correction',
        'user_id',
    ];

    protected $casts = [
        'check_finish_size'      => 'boolean',
        'check_trim_marks'       => 'boolean',
        'check_imposition'       => 'boolean',
        'check_color_count'      => 'boolean',
        'check_screen_ruling'    => 'boolean',
        'check_n_mark_trap'      => 'boolean',
        'check_color_correction' => 'boolean',
    ];

    public function ticket() { return $this->belongsTo(PrepressTicket::class, 'prepress_ticket_id'); }
    public function user()   { return $this->belongsTo(User::class); }
}
```

### `app/Models/PrepressTicket.php` の変更

- `$fillable` / `$casts` から旧 `check_*` 7項目を削除
- `hasMany(PrepressTicketStageCheck::class, 'prepress_ticket_id')` の `stageChecks()` リレーションを追加

---

## コントローラー設計 (`app/Http/Controllers/Prepress/BoardController.php`)

- `index()`:
  - `PrepressTicket::with(['salesRepEntry:id,name,company', 'stageChecks'])` に変更
  - `select([...])` から旧 `check_*` 7カラムを削除
  - フロントには `ticket.stage_checks`（0〜4件の配列。各要素に `stage`, 7項目, `user_id`）がそのまま渡る
- `updateChecks()` を2つに分割:
  - `updateMeta(Request $request, PrepressTicket $ticket)` — `indesign_version` / `illustrator_version` / `check_memo` のみを扱う（既存ロジックそのまま、対象フィールドを絞るだけ）
  - `updateStageCheck(Request $request, PrepressTicket $ticket, string $stage)` — 新規
    ```php
    abort_unless(in_array($stage, PrepressTicketStageCheck::STAGES, true), 404);
    $validated = $request->validate([
        'check_finish_size'      => ['sometimes', 'boolean'],
        'check_trim_marks'       => ['sometimes', 'boolean'],
        'check_imposition'       => ['sometimes', 'boolean'],
        'check_color_count'      => ['sometimes', 'boolean'],
        'check_screen_ruling'    => ['sometimes', 'boolean'],
        'check_n_mark_trap'      => ['sometimes', 'boolean'],
        'check_color_correction' => ['sometimes', 'boolean'],
        'user_id'                => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
    ]);
    abort_if(empty($validated), 422, '保存するフィールドが指定されていません');
    $ticket->stageChecks()->firstOrCreate(['stage' => $stage])->update($validated);
    return response()->json(['ok' => true]);
    ```

---

## ルート設計 (`routes/web.php`)

既存 `PATCH board/{ticket}/checks` (`prepress.board.updateChecks`) を以下2本に置き換え:

```
PATCH board/{ticket}/meta                    prepress.board.updateMeta
PATCH board/{ticket}/stage-checks/{stage}    prepress.board.updateStageCheck
```

---

## フロントエンド設計 (`resources/js/Pages/Prepress/Board.vue`)

- `CHECK_ITEMS`（7項目定義）はそのまま維持し、4工程で再利用する
- 新規定数 `const STAGES = ['初校', '再校', '三校', '下版'];`
- `localChecks` を2つに分割:
  - `localMeta`（`indesign_version` / `illustrator_version` / `check_memo` のみ）
  - `localStageChecks`（`{ 初校: {check_finish_size:false,...,user_id:null}, 再校: {...}, 三校: {...}, 下版: {...} }`）
- `openDetail(ticket)`: `ticket.stage_checks` 配列を stage 名でインデックス化し、存在しない工程はデフォルト値（全項目false・user_id null）で補完して `localStageChecks` を構築
- `saveStageCheck(stage, field)`: `PATCH prepress.board.updateStageCheck({ticket, stage})` に `{[field]: val}` を送信。楽観的更新＋失敗時ロールバック（既存 `saveCheck` と同じパターン）
- `saveStageUser(stage, userId)`: 同ルートに `{user_id}` を送信
- `saveCheck(field)` は `updateMeta` ルートを呼ぶよう変更（indesign/illustrator/memo用として残す）
- テンプレート: 既存の単一「作業チェック」ブロック（Board.vue 1593〜1611行目）を `v-for="stage in STAGES"` でループする4ブロックに変更。各ブロックの見出しを「作業チェック：{{ stage }}」とし、見出し行に作業者セレクター（`<select>`、options = `prepressUsers`、既存の担当色セレクター [Board.vue 1470行目付近] と同じマークアップパターン）を配置。その下に7項目チェックボックスを配置（既存の `flex flex-wrap` レイアウトを維持）

---

## 変更ファイル一覧

### 新規作成
- `database/migrations/2026_07_06_000001_create_prepress_ticket_stage_checks_table.php`
- `database/migrations/2026_07_06_000002_backfill_prepress_ticket_stage_checks_table.php`
- `database/migrations/2026_07_06_000003_drop_check_fields_from_prepress_tickets_table.php`
- `app/Models/PrepressTicketStageCheck.php`

### 既存変更
- `app/Models/PrepressTicket.php`
- `app/Http/Controllers/Prepress/BoardController.php`
- `routes/web.php`
- `resources/js/Pages/Prepress/Board.vue`

### 影響範囲確認済み
- `check_finish_size` 等7カラムは `BoardController.php` / `PrepressTicket.php` / `Board.vue` / 関連migration以外から参照されていない（grep確認済み、他のレポート・CSV出力等への影響なし）
- 同一チェックUIを持つ他ファイルは存在しない（Board.vueが唯一の実装場所）

---

## 実装後の作業

1. `docker compose exec laravel bash -lc "php artisan migrate"`（ローカル）
2. `npm run build`
3. ブラウザで製版ボードのカードを開き、4工程分のチェック・作業者セレクター動作を確認（保存・再読み込み後の値保持・ロールバック）
4. `ChangelogSeeder` への追記（本件は3ファイル基準の大規模作業のため、完了後にルール通り追記する）
