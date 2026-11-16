# 案件検索内追加 再開

AGENTS.md/JOB_EVALUATION_CONTROL1、MYJOB_INLINE_BROAD_PLAN1/MANAGER1を読む。branch feature/myjob-inline-broad-project-plan-20261116、worktree /tmp/sunbwork-myjob-time-breakdown-header-20261116、基準41b0d2b52。本番直近確認0fa7dade5。共有mainのClaude変更を保護。
依頼は設計。新MyJobの案件検索内に大まかな案件追加モーダルを重ね、保存したIDで即選択し元入力へ戻す。一般User権限と有効固定・親/取引先/重複/部署ロックを既存Serviceで維持、入力全保持/取消/通信失敗とフォーカス・背面誤操作を設計。既存Inertia一覧へredirectすると親が消えるので専用JSON経路を提案。別タブ案は予備。
まだコード変更/配備/DB登録なし。次は具体設計確認後に実装。SSH前の正確なコマンド確認を守り、過去成功済み画面試験を繰り返さない。
