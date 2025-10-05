# ワークロード解析・引き継ぎ（統合）

概要

Workload Analyzer と AI ハンドオーバー向けの要点をまとめたドキュメントです。リーダー／管理者向けの解析画面や設定（係数）の扱い、ハンドオーバー時のチェックリストを含みます。

ワークロード解析

- 設定 (stages/sizes/types/difficulties) に coefficient を持たせ、割当／自己割当の estimated_hours 等と掛け合わせて合算する。
- 設定ページは XHR による保存（JSON 応答）を基本にし、保存成功時はトーストで通知する。
- ルーティングでは静的な `settings` をパラメタライズされたルートより先に定義すること（route cache に注意）。

AI 引き継ぎ（Handover）

- AI 用のプリセット（ai_presets）と会話履歴（ai_conversations / ai_messages）の保存設計を明記。エクスポートは storage/app/exports に出力。
- 初めて作業をする AI エージェントは `first_prompt.md` を必ず読み、レイアウト規則と DB（MySQL）に関する注意を順守すること。

参照元

- workload_analyzer_guidelines.md
- AI_HANDOVER.md
- first_prompt.md

次のアクション

- Workload Analyzer の具体的な SQL（サーバ側集計）サンプルが必要なら追記します。
