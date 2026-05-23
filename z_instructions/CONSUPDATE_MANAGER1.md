# CONSUPDATE 作業管理書 第1版 — CONSOLIDATED ファイル更新
作成日: 2026-05-23

---

## 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/CONSUPDATE_PLAN1.md` | 詳細仕様・変更内容一覧 |
| `CLAUDE.md` | プロジェクト全体のルール（必読） |

---

## 作業フロー

```
STEP 1: CONSUPDATE_PLAN1.md を読む → 仕様把握
STEP 2: 対象ファイルを Read → 現状把握
STEP 3: 実装（各 CONSOLIDATED ファイルを更新）
STEP 4: 変更内容をユーザーに報告 → 確認
STEP 5: npm run build は不要（JS ファイルは変更なし）
```

---

## 進捗一覧

### フェーズ1：計画書作成

| ID | タスク | ステータス | 備考 |
|----|--------|-----------|------|
| CU-01 | CONSUPDATE_PLAN1.md 作成 | ✅ 完了 | 2026-05-23 |
| CU-02 | CONSUPDATE_MANAGER1.md 作成 | ✅ 完了 | 本ファイル |
| CU-03 | CONSUPDATE1_PROMPT.md 作成 | ✅ 完了 | |

### フェーズ2：CONSOLIDATED ファイル更新

| ID | タスク | ステータス | 備考 |
|----|--------|-----------|------|
| CU-04 | CONSOLIDATED_01 全面書き直し | ✅ 完了 | AppLayout パターン・ボタン配置・ロール色等 |
| CU-05 | CONSOLIDATED_05 UTC/JST・TimelineDiary 追記 | ✅ 完了 | CalculatesEventTime Trait・R5-16 |
| CU-06 | CONSOLIDATED_09 新機能セクション追加 | ✅ 完了 | イルカ・ゴースト・更新ログ・工程・スクリプト・clientCode |
| CU-07 | CONSOLIDATED_07 軽微修正 | ✅ 完了 | first_prompt.md 参照削除 |
| CU-08 | CONSOLIDATED_SUMMARY 追記 | ✅ 完了 | 新機能列挙 |

---

## ステータス凡例

| 記号 | 意味 |
|------|------|
| 🔲 未着手 | まだ始めていない |
| 🔨 実装中 | コード変更中 |
| ✅ 完了 | ユーザー確認済み |

---

## 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-05-23 | CU-01〜03 | 計画書・管理書・プロンプトファイル作成 | Claude |
| 2026-05-23 | CU-04〜08 | 全 CONSOLIDATED ファイル更新完了 | Claude |
