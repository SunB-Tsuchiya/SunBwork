# STAGECHECK1_PROMPT.md — 新セッション開始用プロンプト

新しいClaude Codeセッションでこの作業を続ける場合は、以下をそのまま貼り付けてください。

---

製版ボード（`/prepress/board`）のカード詳細モーダルの「作業チェック」機能を、初校・再校・三校・下版の4工程に対応させる作業を継続する。

設計・進捗は `z_instructions/STAGECHECK_PLAN1.md` と `z_instructions/STAGECHECK_MANAGER1.md` を参照。設計方針（3点）はユーザー確認済み:
1. 4工程それぞれに同じ7項目チェックを繰り返し表示し、作業者セレクターは工程ごとに1人
2. 既存の `prepress_tickets.check_*` データは「初校」行に移行してから旧カラムを削除
3. モーダル内は4工程を縦に並べて全て展開表示（タブ・アコーディオンなし）

`STAGECHECK_MANAGER1.md` の進捗サマリー表を見て、⬜（未着手）または🔄（作業中）の最初のタスクから着手すること。各タスク完了ごとに状態を✅に更新する。

実装完了後は必ず: `npm run build` → ローカル `php artisan migrate` → ブラウザで4工程分のチェック・作業者選択・保存・再読込確認 → `ChangelogSeeder`追記 → 完了ファイルを `z_instructions/archived/` へ移動。
