# 案件検索内追加 再開

AGENTS.md/JOB_EVALUATION_CONTROL1、MYJOB_INLINE_BROAD_PLAN1/MANAGER1を読む。branch feature/myjob-inline-broad-project-plan-20261116、worktree /tmp/sunbwork-myjob-time-breakdown-header-20261116、基準41b0d2b52。本番直近確認0fa7dade5。共有mainのClaude変更を保護。
依頼は設計。新MyJobの案件検索内に大まかな案件追加モーダルを重ね、保存したIDで即選択し元入力へ戻す。一般User権限と有効固定・親/取引先/重複/部署ロックを既存Serviceで維持、入力全保持/取消/通信失敗とフォーカス・背面誤操作を設計。既存Inertia一覧へredirectすると親が消えるので専用JSON経路を提案。別タブ案は予備。
まだコード変更/配備/DB登録なし。次は具体設計確認後に実装。SSH前の正確なコマンド確認を守り、過去成功済み画面試験を繰り返さない。

2026-11-16具体設計を利用者承認。専用JSON Controller/GET・POST、既存Tree追加経路、共通BroadProjectFormのinlineCreate限定保存、3層モーダル/背面inert/フォーカス/元入力保持を実装。既存Inertia保存は維持。今回の合成検証/配備/画面確認は未実施。次は会社部署・親・重複の境界と実Vueの入力保持を検証。

2026-11-16合成検証: 新JSON経路4件と既存分類/お気に入り/紐付け・旧新入力の関連計136件1441断言成功。実Vue共通フォームで従来PUT/複数取引先と新JSON保存/CSRF/二重送信/422・通信失敗入力保持/キャンセル確認を検証。実新MyJob入力で追加窓開閉・新ID選択・全入力維持を確認。共有BroadProjectFormへinlineCreate限定拡張とし従来保存方式は変更しない。Ziggyを一時生成して追加2定義だけ反映、他定義不変。まだ本番反映・今回画面確認なし。

2026-11-16配備準備: 最終/members build成功、生成manifestの全参照欠落0・本番baseを確認。分類追加は共有即時反映だがMyJobは未保存という注意を追加。取消で検索条件を保ったまま候補再読込、成功時は古い候補レスポンスを無効化して新IDを選択。ChangelogSeederコードのみ追加しSeeder実行なし。実装/コード検証済み、配備/利用者画面確認は未実施。次は固定元0fa7dade5からの差分手順を提示し事前承認を得る。
