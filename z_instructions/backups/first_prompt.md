# first_prompt

## z_instructions ディレクトリ内ファイルの概要

このプロジェクトでAIエージェントが作業を行う際は、z_instructions ディレクトリ内のすべてのファイルを参照し、内容を把握した上で指示に従うこと。以下に各ファイルの概要をまとめる。

---

### 1. layout_guideline_for_ai_agent.md
- サイト全体のレイアウト、UI/UX、配色、main部分の統一仕様を記載。
- Laravel12・Jetstreamの基本レイアウト構造や、参照すべき既存ファイルの指示がある。
- 新規ページやUI作成時は必ず本ファイルのルールを厳守すること。

### 2. site_structure_and_roles_for_ai_agent.md
- サイトの階層構造、各権限（Admin/Leader/Coordinator/User/ゲスト）の役割・操作範囲をまとめている。
- Jetstream Team機能の利用方針や、今後の追加予定機能（暗号化DB、チャット、AI、伝票、タスク・レビュー等）も記載。
- 権限・階層・チーム管理・セキュリティ・拡張性を意識した設計・実装が求められる。

### 3. AUTH_SETUP.md
- Laravel Fortify/Jetstream/Inertia.js/Vue.js を用いた認証機能の実装・設定手順を詳細に記載。
- FortifyServiceProvider, CreateNewUser, Register.vue などの主要ファイルの役割や、DB構造、ルーティング、バリデーション、デバッグ方法、よくある問題と解決策を網羅。
- Ziggyによるルート指定や、認証関連のカスタムルートの注意点も記載。

### 4. error_handling_guideline_for_ai_agent.md
- バリデーション・DB接続・API通信時のエラーハンドリング、ユーザー通知、ログ記録のルールを記載。
- try-catchの徹底、ユーザーへの明示的なエラー通知、入力値の保持、詳細なログ出力など、堅牢なエラー処理の指針。
- サーバー・クライアント双方での一貫したバリデーション・エラー通知を推奨。

### 5. myprompt.txt
- AIエージェントに最初に伝えるべきプロンプト例。
- レイアウト統一・仕様書参照・作業手順・権限/階層/拡張性の意識・英訳時の注意点など、AIが守るべき基本方針を明記。
- サイトのレイアウトやmain部分を新規作成する際に参照すべきファイルや、各権限の役割、Jetstream Team機能の使い方、今後の方針、UI設計時の注意点、英訳時の注意点などを質問形式でまとめている。

---

## AIエージェントへの指示
- 作業開始時は必ず first_prompt を読み、z_instructions 内の全ファイルの内容を把握した上で指示に従うこと。
- layout_guideline_for_ai_agent.md と site_structure_and_roles_for_ai_agent.md を最優先で参照し、他ファイルの内容も適宜活用すること。
- 仕様書の内容やルールを厳守し、既存の統一感・拡張性・セキュリティ・役割分担を意識して作業を進めること。
- 英訳が必要な場合は、曖昧な表現を避け、明確な指示・用語を用いること。

---

この first_prompt をAIエージェントの初期プロンプトとして必ず参照し、z_instructions ディレクトリ内の全ファイルの内容を把握した上で作業を行うこと。
