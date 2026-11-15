# レビュー修正 再開

AGENTS.md、JOB_EVALUATION_CONTROL1、MYJOB_REVIEW_20261115、MYJOB_REVIEW_FIX_PLAN1/MANAGER1を読む。隔離feature/info-department-myjob-plan-20261114、2602ee504が基準。本番は同版を直前に配備済み。共有mainのClaude作業を保護。
利用者は指摘1→2→3の修正順を承認。具体設計はedit_version照合とロック、既存旧NULL案件保持、専用作成receiptによるUUID再送防止。アプリ未変更。次は具体設計確認後実装。成功済み画面を反復しない。SSH前は正確なコマンド承認が必要。

2026-11-15現在: 具体設計承認済み、実装済み。WorkJobInput/新creation receipt migration/Controller通常更新と引用/詳細・カレンダー投影/JSを変更。統合111件1147断言と実Vue成功。本番未配備・migration未適用。次は追加境界テストと本番用build、固定版の配備準備。
