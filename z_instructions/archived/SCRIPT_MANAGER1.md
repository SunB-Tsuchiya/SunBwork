# SCRIPT_MANAGER1.md — スクリプトページ機能 進捗管理

作成日: 2026-05-16  
担当: Claude Code

---

## 作業フロー

1. SCRIPT_PLAN1.md を確認・承認
2. Phase 1 から順に実装
3. 各フェーズ完了後に `npm run build` を実行して確認
4. migration は `docker compose exec laravel bash -lc "php artisan migrate"` で実行
5. 完了後にユーザー確認

---

## 進捗テーブル

| # | フェーズ | タスク | 状態 | 備考 |
|---|---------|--------|------|------|
| 1 | Phase 1 | migration: scripts テーブル作成 | ✅ 完了 | 2026-05-16 |
| 2 | Phase 1 | migration: leader_permissions に script_access 追加 | ✅ 完了 | 2026-05-16 |
| 3 | Phase 1 | app/Models/Script.php 作成 | ✅ 完了 | |
| 4 | Phase 1 | app/Models/LeaderPermission.php 更新 | ✅ 完了 | script_access 追加 |
| 5 | Phase 1 | app/Http/Controllers/ScriptController.php 作成 | ✅ 完了 | |
| 6 | Phase 1 | routes/web.php 更新 | ✅ 完了 | scripts.index, scripts.show |
| 7 | Phase 1 | HandleInertiaRequests.php 更新 | ✅ 完了 | canAccessScripts を auth 直下に追加 |
| 8 | Phase 1 | LeaderPermissionController.php 更新 | ✅ 完了 | script_access 対応 |
| 9 | Phase 1 | LeaderPermissions/Edit.vue 更新 | ✅ 完了 | script_access トグル追加 |
| 10 | Phase 2 | AppLayout.vue 更新 | ✅ 完了 | スクリプトアイコン追加（auth.canAccessScripts 参照） |
| 11 | Phase 2 | Scripts/Index.vue 作成 | ✅ 完了 | |
| 12 | Phase 2 | Scripts/Show.vue 作成 | ✅ 完了 | 動的コンポーネント（componentMap方式） |
| 13 | Phase 2 | npm run build & 動作確認 | ✅ 完了 | |
| 14 | Phase 3a | npm install papaparse xlsx | ✅ 完了 | |
| 15 | Phase 3a | ImageRenamer.vue: CSV/Excel解析 | ✅ 完了 | Shift-JIS自動判定・PapaParse・xlsx |
| 16 | Phase 3b | ImageRenamer.vue: フォルダ選択・ファイル列挙・照合 | ✅ 完了 | File System Access API |
| 17 | Phase 3c | ImageRenamer.vue: プレビューテーブル | ✅ 完了 | ok/warn/skip 分類 |
| 18 | Phase 3d | ImageRenamer.vue: リネーム実行・Undoマニフェスト | ✅ 完了 | move()フォールバック付き |
| 19 | Phase 3 | npm run build & 動作確認 | ✅ 完了 | ビルド成功 |
| 20 | Phase 4 | エラーハンドリング強化・Shift-JIS対応・進捗バー | ✅ 完了 | ImageRenamer.vue に組込み済み |
| 21 | Phase 4 | 最終 npm run build & 総合確認 | ✅ 完了 | 2026-05-16 |

**凡例:** ⬜ 未着手 / 🔄 作業中 / ✅ 完了 / ⚠️ 問題あり

---

## 各フェーズのゴール

### Phase 1 完了条件
- migration が本番・ローカルで実行可能な状態
- `/scripts` にアクセスすると superadmin/admin は一覧が見える
- leader は権限がない場合 403、ある場合は一覧が見える
- LeaderPermissions の編集画面に「スクリプトツール」トグルが表示される

### Phase 2 完了条件
- ヘッダーにスクリプトアイコンが表示される（権限あるユーザーのみ）
- `/scripts` で一覧ページが表示される
- `/scripts/image-renamer` でプレースホルダー（空コンポーネント or ローディング）が表示される

### Phase 3 完了条件
- CSV（UTF-8・BOM付き・セミコロン区切り）が正常に読み込める
- Excel（.xlsx）が正常に読み込める
- フォルダ選択でファイル一覧が取得できる
- プレビューテーブルに before/after が表示される
- 「実行」後にファイルがリネームされる
- Undoマニフェスト JSON がダウンロードされる

### Phase 4 完了条件
- Chrome以外のブラウザで「非対応」メッセージが出る
- Shift-JIS のCSVが読み込める
- リネーム失敗ファイルが個別にエラー表示される
- 進捗バーが表示される

---

## 作業ログ

| 日時 | 内容 |
|------|------|
| 2026-05-16 | 設計開始。PLAN/MANAGER/PROMPT 作成。ユーザーとの仕様確認完了。 |
| 2026-05-16 | Phase 1〜4 全タスク実装完了。npm run build 成功。 |

---

## 注意事項

- migration実行後はさくらへのデプロイ前に必ず `php artisan migrate` を忘れない（CLAUDE.md ③参照）
- `leader_permissions` テーブルへのカラム追加なので既存のLeaderPermissionレコードには `script_access = false` がデフォルトで入る
- Phase 3 の `npm install papaparse xlsx` は実行前に確認を取る
- 本番デプロイ時は `DEPLOY_SAKURA.md` の手順に従うこと
