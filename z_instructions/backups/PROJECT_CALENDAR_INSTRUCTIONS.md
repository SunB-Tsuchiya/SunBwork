## プロジェクトカレンダー引き継ぎドキュメント

目的: 本ドキュメントは、AssignedProject（jobproject）に実装したプロジェクト単位のカレンダー／メモ機能の全体仕様、実装の流れ、よく起きるエラーとその対処、レイアウト/UXルール、検証手順を次のAI（もしくは開発者）にわかりやすい形式でまとめたものです。

=== 要点サマリ ===

- メモは日時ではなく「日付単位」で扱う（UIは日付入力のみ、時間は選ばない）。
- メモは個人の日記テーブルとは別で `project_memos` に保存する。
- メモ作成時にサーバーへ送る日時はローカル時刻の13:00（午後1時）を使う。これによりタイムゾーン変換で日付がずれる問題を回避する。
- 編集は日付を変更しない（編集モーダルでは日付入力を表示しない）。
- カレンダーは FullCalendar（vue3ラッパー）を使用。AssignedProject 側は週の開始を月曜（firstDay:1）にし、高さは自動（height:'auto'）にする。
- UI: モーダルベース（作成・表示・編集・削除）。表示モーダルは閲覧権限に応じて編集/削除ボタンを表示する。
- フロントエンドは楽観的UI（optimistic update）を採用：ローカル配列に一時追加して即時表示し、その後 GET で正規化されたサーバーデータに置き換える。

## 仕様（contract）

- 入力: カレンダー操作（新規メモ： date(YYYY-MM-DD)、body(text)、user_id/author など）
- 出力: カレンダーに表示するイベント配列（各イベントは id, title, start（Date or ISO string）, extendedProps { type:'memo'|'schedule', body, author } など）
- データ形状（ProjectMemo API の想定）:
    - id: number
    - project_id: number
    - user_id: number
    - date: ISO datetime string (保存時は YYYY-MM-DDT13:00:00 形式)
    - body: string
    - created_at, updated_at
    - author: { id, name } がコントローラーレスポンスに付与される
- エラーモード: ネットワーク失敗、バリデーションエラー、認可エラー
    - 成功基準: 作成・編集・削除後、カレンダーに正しく反映されること（楽観的表示→サーバー差分で置換）

## 重要ファイル一覧（変更箇所）

- フロントエンド
    - `resources/js/Components/AssignedProjectCalendar.vue` — Assigned project 用カレンダー（メモ CRUD、モーダル、safeDateTime、plainCalendarEvents の扱い、firstDay:1, height:'auto'）
    - `resources/js/Components/ProjectCalendar.vue` — 事業者・コーディネータ用カレンダー（同様の safeDateTime 処理）
    - `resources/js/Pages/User/AssignedProject/Show.vue` — カレンダーを埋め込むページ。ジョブ情報とメンバー表示をモーダル化、メモ一覧テーブル（列幅/縞模様）

- バックエンド
    - `app/Models/ProjectMemo.php` — `user()` リレーション追加（author 情報取得用）
    - `app/Http/Controllers/Coordinator/ProjectMemosController.php` — store/index/show/update/delete の API。レスポンスに `author` を付与。
    - `app/Http/Controllers/User/AssignedProjectController.php` — Inertia に渡す props に memos（user relation 含む）を付与

## 実装上の主要ポイント / ヘルパー

- safeDateTime(dateStr)
    - 目的: UI 上は日付のみ扱うが、サーバーへ送る際の ISO 文字列はローカル日付の 13:00 を使うことで UTC 変換による日付ずれを回避する。
    - 例: safeDateTime('2025-08-31') → '2025-08-31T13:00:00+09:00'（フロントで toISOString() を使う場合は Date を適切に生成）

- fmtDateOnly(value)
    - 目的: FullCalendar の event.start に渡される Date や ISO 文字列から「YYYY-MM-DD」を安定して抽出する小ヘルパー。

- plainCalendarEvents と structuredClone
    - 問題: Vue の reactive Proxy オブジェクトをそのまま FullCalendar に渡すと、内部で予期せぬ挙動をする（イベントが空になる、更新が反映されない等）。
    - 対策: FullCalendar には Proxy でない「プレーン」配列を渡す。可能なら `structuredClone()` を使い、ない環境では自前のディープクローン（Date を復元する）を実装する。

- optimistic UI の流れ
    -   1. ユーザーがメモ作成 → フロントで一時オブジェクトを `localMemos` に push
    -   2. カレンダーは即時更新（ユーザーにフィードバック）
    -   3. サーバーに POST → 成功時に GET して canonical memos で置換、失敗時は localMemos を巻き戻す

## よくあるエラーと対応（FAQ）

1. 日付が保存すると前日になる/1日ズレる
    - 原因: サーバーとブラウザのタイムゾーン差による ISO 文字列の解釈。フロントで 'YYYY-MM-DD' をそのまま Date に渡すと UTC として解釈され、ローカルでは前日表示になる。
    - 対策: safeDateTime を使いローカルの 13:00 を指定して保存。あるいはサーバー側で date-only を扱うエンドポイント（`date` フィールドを日付として保存）を用意する。

2. FullCalendar にイベントを渡すと最初に空になったり反応しない
    - 原因: Vue の reactive Proxy が FullCalendar と相性が悪い（内部で比較/操作する際に Proxy の影響を受ける）。
    - 対策: `structuredClone()` で plain オブジェクトに変換して渡す（プリミティブな Date を壊さないためにも structuredClone が望ましい）。環境によっては `JSON.parse(JSON.stringify(obj))` だと Date が文字列化されるため不可。

3. モーダルを開いて reactive オブジェクトを直接 mutate しても表示が更新されない
    - 原因: 深いオブジェクトの in-place mutation が Vue の再描画トリガーにならない場合がある。
    - 対策: `memoShowData.value = { ... }` のようにオブジェクトを丸ごと置換する。編集モードでは専用の refs（例: editingCommentBody 等）を用意する。

4. ReferenceError: editingCommentId is not defined
    - 原因: 変数/refs を宣言していないか、スコープ外で参照している。
    - 対策: <script setup> 内で `const editingCommentId = ref(null)` など必ず宣言する。

5. structuredClone がない環境でオブジェクトをコピーした結果 Date が文字列になってしまう
    - 対策: カスタムディープクローン関数を実装し、Date オブジェクト（instanceof Date）をそのままコピーするロジックを用意する。

## レイアウト / UX ルール

- カレンダー: `firstDay: 1`（週始まりは月曜）、`height: 'auto'`（内部にスクロールを持たせない）。
- 作成モーダル: 日付選択のみ（カレンダー上で日をクリックしてモーダルを開く）。入力項目はタイトル（遷移先ではタイトル=メモ本文など）、日付、本文。
- 表示モーダル: 編集/削除は権限次第で表示。作成者名（author.id、author.name）を必ず表示する。
- 編集モーダル: 日付は触れない。本文のみ編集可能。
- メモ一覧テーブル（AssignedProject/Show）: 列幅は 1/5（date）・1/5（author）・3/5（memo）で、視認性向上のため一行おきに黒10%（bg-black bg-opacity-10）を適用する。

## テストと検証手順（QA）

1. 作成テスト
    - カレンダーの日付を選択 → モーダルで本文を入力 → 送信
    - 期待: 即座にカレンダーにメモが表示され、サーバー成功後は ID 等が付与されて正規化される
2. 日付ズレ検証
    - ローカル環境のタイムゾーンを UTC と Asia/Tokyo などで切り替え、同じ日付で作成して表示される日付が変わらないことを確認
3. 編集テスト
    - 表示モーダルから編集ボタン → 本文変更 → 保存
    - 期待: 日付は変わらない。カレンダーと一覧で本文が更新される
4. 削除テスト
    - 表示モーダルから削除 → サーバー削除成功 → カレンダーから削除
5. リアクティビティ/初期表示テスト
    - ページロード時に FullCalendar にイベントが正しくロードされるか（空にならない）を確認。必要なら plainCalendarEvents の生成ロジックを点検。

## 推奨改善点 / 次の作業候補

- structuredClone がなければ Date を復元する小さなユーティリティを追加する。
- サーバー側で date-only を明示的に扱う API を提供するとクライアント側の work-around が減る（推奨）。
- カレンダーのイベントフィルタや色ラベルの説明を管理画面に追加する（現在は6色ラベルが保存されている想定で表示側に反映）。

---

このファイルは `z_instructions` に保存されています。追加で細かい実装箇所の抜粋（関数定義やサンプル payload）を入れてほしければ指示してください。
