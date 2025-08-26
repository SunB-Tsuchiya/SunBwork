```markdown
# ProjectSchedule Calendar 作業まとめ

作成日: 2025-08-26
目的: ProjectSchedules のカレンダー/Gantt PoC 実装、クライアント側の Ziggy ルート利用、権限制御、統合テストを含む一連の作業経緯を次の担当者へ引き継ぐための要約。

要点（短く）
- フロント: `resources/js/Components/Calendar.vue` を Ziggy の名前付きルートで更新し、カレンダー上のドラッグ/リサイズ/進捗変更を PATCH で送信するよう実装。
- バックエンド: `Coordinator/ProjectSchedulesCalendarController` を実装（index, update）。update はルート引数の id を受け取り `ProjectSchedule::findOrFail($id)` で明示的にモデルを解決してから権限チェック・更新を行うようにした（テスト環境での暗黙バインディングの不整合を回避するため）。
- 権限: `ProjectSchedulePolicy` を実装・ `AuthServiceProvider` に登録し、許可されたユーザーのみ更新可能にした（Coordinator/leader/admin または割当メンバー等）。
- テスト: `tests/Feature/ProjectScheduleCalendarIntegrationTest.php` を追加。割当あり/なしのシナリオで PATCH の成功/403 を検証。カレンダー関連の統合テストは合格。

デバッグで見つかった問題と対応
- 問題: 統合テスト実行時にコントローラが unpersisted な Eloquent インスタンス（exists=false）と生の id 引数の両方を受け取り、save() が INSERT を試みて NOT NULL 制約エラーになるケースを確認。これはテスト環境のルート解決の挙動差に起因。
- 対応: 一時的に存在チェックでフォールバックするロジックを入れて問題を確認した後、最終的にコントローラの update シグネチャを id 受け取りに変更し `findOrFail` で明示的に解決する実装に差し替え（安定性向上）。

現状と推奨次ステップ
- カレンダー更新エンドポイントとクライアント呼び出しは実装済み。カレンダー統合テストはパス。
- リポジトリ全体のフルテストでは別領域（認証・パスワードリセット・外部キー周り）で失敗が見られたため、CI 上での総合確認を推奨。
- 次の担当者への短期タスク:
  1. CI でフルテストを実行して環境差異を確認する。
  2. ルートパラメータ命名をリポジトリで一貫化（`{project_schedule}`）して暗黙バインディングに戻すか、明示的 id 解決を標準にするか決定する。
  3. UI の PoC を FullCalendar に統合（現状の PoC 実装を移行）し、Broadcast の PoC を追加して共同編集フローを確認する。

関連ファイル（実装した/影響を受けた）
- `resources/js/Components/Calendar.vue` — Ziggy ルート呼び出しを使用して PATCH を送信
- `app/Http/Controllers/Coordinator/ProjectSchedulesCalendarController.php` — index, update（update は id 解決 via findOrFail）
- `app/Policies/ProjectSchedulePolicy.php` — 権限チェック
- `tests/Feature/ProjectScheduleCalendarIntegrationTest.php` — assigned/unassigned の統合テスト
- `routes/web.php` — `PATCH coordinator/project_schedules/{project_schedule}/calendar` ルート（名前付きルートあり）

備考
- 一連のデバッグ過程でコントローラ内のデバッグ出力や一時的フォールバックは削除済み。コードは明示的な解決とシンプルなバリデーションに整えられている。

---
このファイルは次の AI エージェントに渡すための要約です。必要なら更に細かいコミット単位の変更ログや、失敗していたテストのスタックトレース（全文）を添付できます。
```
