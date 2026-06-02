# WORKLOAD_UI2_MANAGER1 — 進捗管理

## 進捗テーブル

| # | タスク | ファイル | 状態 |
|---|--------|---------|------|
| 1 | PLAN / MANAGER / PROMPT 作成 | z_instructions/ | ✅ 完了 |
| 2 | バグ修正: buildGroupConfig の null 強制追加を削除 | Index.vue / Edit.vue | ⬜ 未着手 |
| 3 | コントローラー: index() に部署使用情報付与・edit() リダイレクト化 | WorkloadSettingController.php | ⬜ 未着手 |
| 4 | Index.vue 大改修: 編集モード・部署バッジ・▲▼並べ替え・グループ管理 | Index.vue | ⬜ 未着手 |
| 5 | Edit.vue リダイレクト化 | Edit.vue | ⬜ 未着手 |
| 6 | ビルド・コミット・デプロイ | — | ⬜ 未着手 |

---

## 作業ログ

### 2026-06-02
- 要件整理・設計確定
- 部署バッジ: 情報表示のみ（DB 変更なし）
- 並べ替え: ▲▼ボタン方式（外部ライブラリ不使用）
- バグ原因特定: buildGroupConfig が null を無条件追加
- Edit.vue は廃止ではなく Index へリダイレクト（ルート変更なし）
