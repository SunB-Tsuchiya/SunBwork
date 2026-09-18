# DIARYATT_MANAGER1 — 進捗管理

対応する設計: [DIARYATT_PLAN1.md](./DIARYATT_PLAN1.md)

## 背景（経緯）

- 2026-09-17 18時半ごろ、日報の画像添付時にログイン画面へ戻され入力内容が消失する報告あり。
- 原因調査の結果、セッション切れ（401、本番ログでは419は否定済み）時に `bootstrap.js` が即座に `window.location.reload()` する挙動が主因と判断。
- **フェーズ1（バグ修正・データ消失防止）は対応済み**: `Create.vue`/`Edit.vue` に localStorage 下書き自動保存、`bootstrap.js` にセッション切れ通知を追加。`npm run build` 実施済み。
- 調査の過程で、現行の添付ファイル実装自体に設計上の欠陥（同期アップロード時に `attachmentables` ピボットが作成されず添付ファイルが孤児化する等）が判明。これを機に、ユーザー要望通り「本文埋め込み→独立した添付欄＋モーダル閲覧」への変更をフェーズ2として実施する。

## 進捗一覧

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 1 | 原因調査（本番ログ確認含む） | ✅ 完了 | 419は本番ログで否定、401セッション切れが有力原因と判断 |
| 2 | フェーズ1: 下書き自動保存バグ修正 | ✅ 完了 | Create.vue / Edit.vue / bootstrap.js、npm run build 済み |
| 3 | フェーズ2 設計・計画書作成 | ✅ 完了 | DIARYATT_PLAN1.md |
| 4 | `useDiaryAttachments.js` コンポーザブル作成 | ✅ 完了 | アップロード・ポーリング・プレビュー・削除を共通化 |
| 5 | `DiaryController.php` 改修（attachment_ids 対応、edit() 添付一覧追加） | ✅ 完了 | `attachUploadedAttachments()` / `formatDiaryAttachments()` を追加、プレースホルダスキャンは削除。`php -l` 済み、`AttachmentUploadTest` はPASS |
| 6 | `Create.vue` 改修（本文埋め込み廃止、添付欄UI） | ✅ 完了 | |
| 7 | `Edit.vue` 改修（同上＋既存添付の初期読込） | ✅ 完了 | submit() の multipart-PUT 回避策も不要になったため簡略化 |
| 8 | `Show.vue` 改修（コンポーザブル利用への置き換え） | ✅ 完了 | プレビューモーダル部分のみ置き換え。添付一覧のマージ・レガシー placeholder ポーリングロジックは既存動作維持のためそのまま残した |
| 9 | `npm run build` | ✅ 完了 | エラーなし |
| 10 | ChangelogSeeder 追記・反映 | ⬜ 未着手 | 3ファイル規模の作業完了後ルールに従う |
| 11 | CONSOLIDATED_08_attachment.md / CONSOLIDATED_09 更新 | ⬜ 未着手 | 日報添付の新方式を追記 |
| 12 | PLAN/MANAGER/PROMPT を archived/ へ移動 | ⬜ 未着手 | 全作業完了後 |

### 実装メモ（次セッション・レビュー用）

- `Show.vue` はスコープを絞り、プレビューモーダル（`previewModal`/`openPreview`/`closePreview`）のみ `useDiaryAttachments` に置き換えた。添付ファイル一覧のマージ処理（`attachmentsList` computed、本文からの抽出、`[[attachment:id:name]]` プレースホルダのポーリング置換）は、過去に保存された日報（旧方式）の表示を壊さないために意図的に変更していない。
- 新規保存分の日報は本文に画像を埋め込まなくなったため、`extractAttachmentsFromBody` によるマークダウン抽出フォールバックは新規分では使われなくなる（`Diary::attachments` の明示リストのみで表示される）。将来的に `Show.vue` 側のレガシー抽出コードを削除してよいか判断するには、DB上に旧方式（`<img>` 直接埋め込み）の日報が残っている間は待つ必要がある。
- `AttachmentController::destroy` の認可ロジックは「アップロード者本人 or 管理者 or 紐付いた日報の所有者」なので、保存前（ステージング中）の添付ファイル削除もアップロード者本人として問題なく通る。

## 作業ログ

- 2026-09-18: 調査実施（サブエージェントによる一次調査＋本番SSHログ確認）。フェーズ1実装・ビルド完了。フェーズ2着手についてユーザー承認取得、計画書作成。
