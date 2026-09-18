# DIARYATT_PLAN1 — 日報添付ファイルの独立化（本文埋め込み廃止）

## 背景・目的

2026-09-17 18時半ごろ、日報に画像を添付しようとした際にログイン画面へ戻され、入力内容が消失する事象が発生した。
原因調査（フェーズ1で対応済み）の過程で、現行の日報添付ファイル実装には以下の設計上の問題があることが判明した。

1. 画像を Quill エディタの本文 HTML に直接 `insertEmbed` で埋め込んでおり、独立した添付ファイルとして管理されていない。
2. `/api/uploads` でアップロードされた画像は `Attachment` レコードとして DB には作成されるが、**同期処理（`status: 'ready'`）で完了した場合、日報とのポリモーフィック紐付け（`attachmentables` ピボット）が一切作成されない**。
   - 紐付けが発生するのは「非同期フォールバック時に挿入される `[[attachment:id:filename]]` プレースホルダを `DiaryController::store/update` が正規表現でスキャンするケース」のみ。同期処理（`ProcessUploadJob::dispatchSync`、通常はこちらが常に成功する）ではプレースホルダを挿入せず直接 `insertEmbed` するため、この経路を通らない。
   - 結果、添付した画像は本文には表示されるが、`Diary::attachments`（`Diaries/Show.vue` の「添付ファイル」一覧セクション）には現れず、`Diary` 削除時の添付ファイルクリーンアップ対象にもならない（孤児化）。
3. `Diaries/Show.vue` には既に「添付ファイル一覧＋プレビューモーダル」の実装があるが、本文中の `<img>` タグを抽出するロジックが無いため、埋め込み画像はこの一覧に反映されない。

これらを踏まえ、ユーザー要望（画像を本文に入れず、添付ファイルとして別枠管理・モーダル閲覧できるようにする）に沿って設計を変更する。

## 方針

- 日報作成・編集時、ファイル添付は **本文（Quill content）に埋め込まない**。アップロード完了後は本文とは独立した「添付ファイル」欄に一覧表示する（ステージング）。
- 保存時、ステージング中の添付ファイル ID 一覧（`attachment_ids`）をフォームと一緒に送信し、バックエンドで明示的に `attachmentables` ピボットへ紐付ける。プレースホルダのスキャン方式は廃止する。
- 閲覧・編集画面の添付ファイル一覧は、サムネイルクリックでモーダル表示・ダウンロードできるようにする（`Diaries/Show.vue` に既にある実装パターンを流用・共通化する）。
- **既存の日報（過去に本文へ画像が埋め込み済みのもの）は無変更のまま表示され続ける。** データ移行は行わない。本文の HTML はそのまま維持し、`<img>` タグは引き続き本文内にインライン表示される。新規投稿分のみ新方式になる。

## 影響ファイル一覧

| ファイル | 変更内容 |
|---|---|
| `app/Http/Controllers/DiaryController.php` | `store()`/`update()`: `attachment_ids`（配列, 自分がアップロードした `status=ready` の Attachment のみ許可）を受け取り `AttachmentService::attachPivot()` で紐付け。プレースホルダスキャン方式は削除。`edit()`: 添付ファイル一覧を `show()` と同じ形式でフロントに渡すよう修正（共通整形処理を private メソッド化）。 |
| `resources/js/Composables/useDiaryAttachments.js`（新規） | アップロード（`/api/uploads` POST）・ステータスポーリング（`/api/uploads/status/:id`）・プレビューモーダル（blob 取得・表示）・削除（`DELETE /api/attachments/:id`）をまとめた共通コンポーザブル。Create/Edit/Show の3画面から利用する。 |
| `resources/js/Pages/Diaries/Create.vue` | `processAndInsertFile` から `insertEmbed`/`insertText` 呼び出しを削除し、コンポーザブル経由でステージング配列に追加するだけにする。添付ファイル欄をサムネイル一覧＋モーダルプレビュー表示に変更。送信時に `attachment_ids` をフォームに含める。 |
| `resources/js/Pages/Diaries/Edit.vue` | 同上。加えて `props.diary.attachments`（バックエンドから渡される既存添付一覧）をステージング配列の初期値として読み込む。 |
| `resources/js/Pages/Diaries/Show.vue` | 添付ファイル一覧・プレビューモーダル部分をコンポーザブル利用に置き換え（重複コード削減）。挙動は現状維持。 |

## 非対象・データ移行なし

- `diaries` テーブルのスキーマ変更なし。
- 既存日報の `content` 内に埋め込まれた `<img>` タグの抽出・移行は行わない（表示は現状通りインラインのまま）。
- ドラッグ＆ドロップ・貼り付け（paste）によるアップロードは維持するが、挿入先が本文ではなく添付欄になる点のみ変更。

## バックエンド API 仕様変更

### `POST /diaries` (store) / `PUT /diaries/{diary}` (update)

新規パラメータ:
- `attachment_ids`: `array`, nullable
- `attachment_ids.*`: `integer`, `exists:attachments,id`

サーバー側追加検証（バリデーションルールでは表現しづらいため、コントローラ内で実施）:
- 各 `attachment_id` について `Attachment::find($id)` の `user_id` が `Auth::id()` と一致し、`status === 'ready'` であることを確認してから `AttachmentService::attachPivot($id, Diary::class, $diary->id)` を呼ぶ（他人のアップロードを紐付けられないようにする）。

### `GET /diaries/{diary}/edit`

`edit()` アクションのレスポンスに `diary.attachments` を追加する（`show()` と同じ整形ロジックを共通メソッド化して再利用）。

## UI 変更概要

- Create/Edit 画面の「添付ファイル」欄:
    - 現状: `<input type=file>` と「添付済み: N 個」の文字だけ。
    - 変更後: アップロード完了後のファイルをサムネイル（画像）または汎用アイコン＋ファイル名（非画像）のカードで一覧表示。各カードに「プレビュー」「削除（ステージング解除）」ボタン。
    - ドラッグ＆ドロップ／貼り付けも同じステージング欄に追加される（本文には何も挿入しない）。
- 本文エディタの「ここにファイルをドラッグ＆ドロップで添付できます（画像は自動で縮小して本文に埋め込みます）」という説明文を実態に合わせて修正する。

## 想定しない変更（スコープ外）

- 本番 PHP のアップロードサイズ上限確認・調整（別タスク）。
- チャット添付（`MessageArea.vue`）側の実装統合（将来的な共通化候補だが今回は日報のみ対応）。
