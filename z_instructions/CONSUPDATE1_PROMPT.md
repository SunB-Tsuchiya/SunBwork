# CONSUPDATE セッション引き継ぎプロンプト 第1版

## タスク概要

`z_instructions/CONSOLIDATED_*.md` を 2026-04-20 以降の変更に合わせて更新する。

## 状態

- 計画書: `z_instructions/CONSUPDATE_PLAN1.md`（詳細仕様）
- 管理書: `z_instructions/CONSUPDATE_MANAGER1.md`（進捗）

## 必読ファイル（順番に読む）

1. `CLAUDE.md` — プロジェクトルール
2. `z_instructions/CONSUPDATE_PLAN1.md` — 変更仕様詳細
3. `z_instructions/CONSUPDATE_MANAGER1.md` — 進捗確認

## 変更対象

| ファイル | 規模 |
|---------|------|
| CONSOLIDATED_01_layout_and_ui.md | 全面書き直し |
| CONSOLIDATED_05_calendar_and_jobbox.md | 中規模追記 |
| CONSOLIDATED_09_domain_rules.md | 大規模追記 |
| CONSOLIDATED_07_workload_and_handover.md | 軽微修正 |
| CONSOLIDATED_SUMMARY.md | 軽微追記 |

## 重要原則

- `backups/` や `archived/` の古いファイルの内容を引き継がない
- CLAUDE.md の内容と矛盾しない
- AppLayout パターンは「py-12 > max-w-7xl はAppLayout内部提供。ページ側はカードをスロットに直接入れる」が正
