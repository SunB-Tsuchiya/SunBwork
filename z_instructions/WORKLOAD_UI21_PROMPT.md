# WORKLOAD_UI21_PROMPT — 新セッション開始用プロンプト

## このファイルの使い方

新セッションでこの作業を継続する場合、以下をプロンプトとして貼り付ける。

---

## プロンプト本文

`workload-setting` (作業項目設定) の UI 大改修を行う。

**設計:** `z_instructions/WORKLOAD_UI2_PLAN1.md` 参照  
**進捗:** `z_instructions/WORKLOAD_UI2_MANAGER1.md` 参照

**変更ファイル:**
1. `resources/js/Pages/WorkloadSetting/Index.vue` — 編集モード・部署バッジ・並べ替え追加
2. `resources/js/Pages/WorkloadSetting/Edit.vue` — Index へリダイレクト化
3. `app/Http/Controllers/WorkloadSettingController.php` — 部署使用情報付与・edit() リダイレクト

**ポイント:**
- ボタン類は CONSOLIDATED_01 に従い `#headerExtras` スロットへ
- 編集モード ON/OFF トグル（同一ページ内）
- 並べ替えは ▲▼ボタン方式
- 部署バッジ: 情報表示のみ（会社全体スコープ時に各アイテムが部署スコープで使われているか表示）
- バグ修正: buildGroupConfig の null 強制追加を削除（登録アイテムがあるグループのみ表示）
