# カレンダー・JobBox・イベント（統合）

概要

ProjectJob の割当／JobBox、FullCalendar に関する指示や Job → Event の連携仕様をまとめたドキュメントです。JobBox の UI と Event 作成ワークフローは密に連携しているため、実装時は双方を参照してください。

重要ルール

- JobBox 側の "予定を編集" からカレンダーを開く際は URL に `?date=YYYY-MM-DD&user_id=...` を付与し、Calendar 側はそのパラメータを受け取って gotoDate を呼ぶこと。
- FullCalendar を利用する場合、Vue の reactive Proxy をそのまま渡すと問題が出る（空になる等）。structuredClone などで plain オブジェクトを渡す。
- Job を完了にする操作は `events.complete` 等の API に集約し、サーバ側で assignment.completed 等の状態更新を行うこと。

サーバ側要点

- EventController::store は job_id の有無に応じて ProjectJobAssignment を参照し、必要なら Message を作成して通知する。
- CalendarController/index は events/diaries/jobs をマージした props を返す設計が望ましい（フロント側での統合前提）。

参照元

- PROJECT_CALENDAR_INSTRUCTIONS.md
- jobassignment_calendar_spec.md
- jobbox_changes_summary.md
- Event / JobBox 関連メモ（backups の複数ファイル）

注意（DB/環境）

- 日付の扱いはサーバ（UTC）／フロント（JST）での変換差に注意。UI では safeDateTime で 13:00 ローカル基準を使う実装がある。
- Migration を適用する際はコンテナ内で php artisan migrate を実行する（MySQL を前提）。

次のアクション

- PoC や FullCalendar の取り回しサンプルが必要なら小さなコード断片を追加します。
