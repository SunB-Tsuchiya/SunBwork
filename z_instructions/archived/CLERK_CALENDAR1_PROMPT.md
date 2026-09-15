# Clerkカレンダー 実装後の引き継ぎ

2026-09-15、ユーザーの承認を受けCodexが実装済み。再実装を開始しないでください。

## 読むもの

- AGENTS.md / CLAUDE.md
- z_instructions/archived/CLERK_CALENDAR_PLAN1.md（§0が最終仕様）
- z_instructions/archived/CLERK_CALENDAR_MANAGER1.md（検証結果・画面確認待ち一覧）
- z_instructions/CONSOLIDATED_01_layout_and_ui.md / CONSOLIDATED_05_calendar_and_jobbox.md

## 実装

- ClerkScheduleCalendar.vue、useClerkCalendarStrip.js、clerkCalendarDates.jsで日曜始まりの年間連続スクロールと5週枠、年月選択を実装。
- Clerk/Calendar/Settings.vueに設定メニュー、Holidays.vueに年別CRUDを追加。AppLayout・headerの戻るリンク・白カード規則を踏襲。
- ClerkCalendarHolidayController / ClerkCalendarHolidaysサービス / 年・祝日モデル / 追加migrationで会社別保存を実装。
- resources/data/clerk_holidays.jsonは2026/2027の公表済み初期値。年の初期化マーカーにより削除した祝日が復活しない。
- 既存の予定保存形式、週間プランナーのISO週と掲示板、他画面のuseJapaneseHolidaysは変更していない。
- 締め日設定は未依頼の将来項目。設定メニューへ追加できるが未実装。

## 状態

ローカルsunbworkへのmigration、今回の更新履歴clerk-calendar-4だけの反映は完了。PHP7テスト43アサーション・JS4テストをJST/米国TZで成功、最終npm run build成功。
実画面・ドラッグ・5週の見え方はユーザー確認待ち。本番反映・commit・pushは未実施。

## 続きの作業

ユーザーの確認結果を受けて必要な修正を行う。ブラウザ操作は明示依頼がある場合のみ。修正時はMANAGERの状態を更新し、Vue/JS変更後はbuild。
本番反映依頼時はDEPLOY_SAKURA.mdを読み、正確なSSHコマンドを提示して確認。祝日用migrationを忘れない。
作業ルートは/home/w229/SunBwork。既存の売上分析、ChangelogSeeder、build成果物等に他作業の差分があるため、一括add・reset・上書きをしない。
Tests\TestCaseはsales接続を削除するため、この機能のテストで使わない。`docker compose exec laravel php vendor/bin/phpunit tests/Unit/ClerkCalendarHolidayTest.php`は専用インメモリSQLiteを使う。
