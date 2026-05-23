# SunBWork 修繕 作業管理書 第2版
作成日: 2026-04-26
更新日: 2026-04-26

---

## ■ この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「N-01を始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（REPAIR_MANAGER2.md）を読む
2. `z_instructions/REPAIR_PLAN2.md` を読む（詳細仕様が記載されている）
3. `z_instructions/REPAIR_MANAGER.md` も参照し、前回修繕の完了事項を把握する
4. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
5. 以下の「作業フロー」に従って進める

---

## ■ 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/REPAIR_PLAN2.md` | 修繕計画2の詳細仕様・対象ファイル・対応内容 |
| `z_instructions/REPAIR_MANAGER.md` | 修繕計画1の管理書（完了済み項目の参照用） |
| `z_instructions/REPAIR_PLAN.md` | 修繕計画1の詳細仕様（参照用） |
| `z_instructions/LAYOUT_GUIDELINES.md` | レイアウトガイドライン |
| `z_instructions/CONSOLIDATED_09_domain_rules.md` | ドメインルール（権限・JobBox・通知等） |
| `CLAUDE.md` | プロジェクト全体のルール（必読） |
| `z_instructions/PROGRESS_SHEET_V2_DESIGN.md` | 進行表V2設計書（完了済み） |

---

## ■ 作業フロー（Claude はこの手順を厳守すること）

各作業項目（N-xx）は以下のステップで進める。

```
STEP 1: 計画書を読む
  → REPAIR_PLAN2.md の該当項目を読み、仕様を把握する
  → 関連ファイルをコードで確認する（推測で作業しない）

STEP 2: 設計・方針の提示（ユーザーに確認を取る）
  → 変更ファイル一覧・変更内容の概要・影響範囲を提示
  → 不明点があれば1つずつ質問する（複数同時に質問しない）
  → ユーザーの「OK」を確認してから次のステップへ

STEP 3: 実装
  → 承認された設計に従って実装する
  → Vue/JSファイルを変更したら npm run build を実行
  → Artisan が必要な場合は docker compose exec 経由で実行

STEP 4: 動作確認の依頼
  → 変更内容を簡潔に報告する
  → ユーザーに動作確認をお願いする（「〜を確認してください」）

STEP 5: 完了記録
  → ユーザーから「OK」が出たら進捗一覧のステータスを「✅ 完了」に更新
  → 備考欄に変更したファイル名を記録
  → 次の推奨作業を提示する
```

### ⚠️ 安全ルール（必ず守ること）
- STEP 2 でユーザーの確認なしに実装を始めない
- DB マイグレーションを伴う変更は必ず別途確認を取る
- 1つの作業が完了するまで次の作業に移らない
- エラーが出た場合は同じ操作を繰り返さず、原因を調べてから対処する

---

## ■ 進捗一覧

### フェーズ1：バグ修正

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| N-06 | ユーザーカレンダー（events）削除時の500エラー＋コーディネーター非同期 | ✅ 完了 | EventController::destroy()修正・PJA-B削除・PJA-A復元・CalendarController未定義変数修正・MyJobBoxステータス列追加 |
| N-07 | ジョブ履歴削除後のリダイレクト先を案件詳細・ジョブ履歴タブに変更 | ✅ 完了 | JobAssign/Show.vue onSuccess・ProjectJobAssignmentsController::destroy() のリダイレクト先を coordinator.project_jobs.show?tab=history に変更 |
| N-09 | ジョブステータス表示の全ページ統一（F-01の4段階基準） | ✅ 完了 | Coordinator/JobBox/Index.vue・Coordinator/ProjectJobs/Show.vue・JobBox/Index.vue・Coordinator/ProjectJobs/JobAssign/Index.vue・User/ProjectJobs/Show.vue のステータス関数・バッジ色・列幅(100px)を統一 |
| N-10 | 「戻る」ボタンが機能しないページの調査・修正 | ✅ 完了 | Events/Show.vue の inline `window.history.back()` を goBack() 関数化して修正。他ページは問題なし確認済み |

### フェーズ2：UI改善

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| N-01 | ジョブ履歴の初期表示を「展開済み」に変更 | ✅ 完了 | Coordinator/ProjectJobs/Show.vue の historyOpen を false→true に変更 |
| N-02 | ジョブ割り振り時の開始時刻初期値を現在時刻（5分刻み）に | ✅ 完了 | AssignmentForm.vue の startTimeHour/startTimeMin を現在時刻（JST 5分刻み切り上げ）に、endTimeHour/endTimeMin を 17:30 に変更 |
| N-05 | 案件詳細タブ構成変更（スケジュールタブを独立） | ✅ 完了 | tabs配列に schedule 追加・順序変更、スケジュールセクションの v-show を schedule に変更 |
| N-11 | 案件カレンダーCSV出力のファイル名に案件名を含める | ✅ 完了 | ProjectSchedulesController::csvExport() のファイル名を {title}_スケジュール.csv 形式に変更 |
| N-12 | 進行管理表の行をクリックで開けるようにする | ✅ 完了 | Coordinator/ProjectJobs/Show.vue の progress タブ tr に @click・cursor-pointer 追加、「開く」Link に @click.stop 追加 |

### フェーズ3：機能改善

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| N-03 | ジョブタイトル命名規則の統一（アンダーバー区切り） | ✅ 完了 | ProgressSheets/Show.vue に normalizeTitle() を追加し buildJobTitle() の return に適用。ー／ｰ／-／－／—／– を _ に置換・連続 _ を圧縮 |
| N-04 | 「詳細を見る（進行表へ）」の遷移先改善（複数シート→モーダル選択） | ✅ 完了 | User/ProjectJobController::progressSheetsJson()追加・routes/web.php にルート追加・MyJobBox/Index.vue・Calendar.vue のモーダル修正（シート選択ステップ追加） |
| N-08 | ジョブ一覧グループ表示記憶＋Coordinator 設定タブ追加（DB） | ✅ 完了 | coordinator_settingsテーブル作成・CoordinatorSetting.php・CoordinatorSettingController.php・routes追加・JobBox/Index.vue DB永続化・CoordinatorNavigationTabs.vue 設定タブ追加・Coordinator/Settings/Index.vue 新規作成 |

### 将来計画（別計画書で実施）

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| GUIDE-01 | ガイド全面書き換え（Admin/Coordinator/Leader/User） | ⏸ 保留 | 全修繕計画完了後に別計画書を策定して実施 |

---

## ■ N-10「戻る」ボタン確認チェックリスト

N-10 の作業時に下記ページの「戻る」ボタンを順次確認すること。

| ページファイル | 戻る先（期待） | 確認済み | 問題あり |
|-------------|--------------|---------|---------|
| `Pages/Events/Show.vue` | カレンダー | ✅ | 修正済み（goBack関数化） |
| `Pages/Coordinator/Events/Show.vue` | 独立ファイルなし（Events/Show.vue を共用） | ✅ | なし |
| `Pages/MyJobBox/Show.vue` | MyJobBox一覧 | ✅ | なし（routeBack()使用） |
| `Pages/User/ProofJobs/Show.vue` | ガイドライン適用済み | ✅ | なし |
| `Pages/User/ProjectJobs/Show.vue` | ガイドライン適用済み | ✅ | なし |
| `Pages/JobBox/Show.vue` | 動作確認済み（ユーザー報告） | ✅ | なし |
| `Pages/Coordinator/JobBox/Index.vue` | jobbox or 案件詳細 | ✅ | なし（getBackLink()使用） |
| `Pages/Coordinator/ProgressSheets/Show.vue` | ガイドライン適用済み | ✅ | なし |

---

## ■ ステータス凡例

| 記号 | 意味 |
|------|------|
| 🔲 未着手 | まだ始めていない |
| 🔍 調査中 | コード調査・仕様確認中 |
| 📝 設計中 | 設計・方針をユーザーと確認中 |
| 🔨 実装中 | コード変更・ビルド中 |
| ✅ 完了 | ユーザー確認済み |
| ⏸ 保留 | 依存関係・仕様未定のため一時停止 |
| ❌ スキップ | 不要と判断、またはユーザー判断でスキップ |

---

## ■ 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-04-26 | — | 計画書（REPAIR_PLAN2.md）・管理書（REPAIR_MANAGER2.md）作成 | Claude |
| 2026-04-28 | N-07 | JobAssign/Show.vue・ProjectJobAssignmentsController.php 修正 | Claude |
| 2026-04-28 | N-09 | Coordinator/JobBox/Index.vue・Coordinator/ProjectJobs/Show.vue・JobBox/Index.vue・Coordinator/ProjectJobs/JobAssign/Index.vue・User/ProjectJobs/Show.vue のステータス表示統一・列幅100px化 | Claude |
| 2026-04-29 | N-10 | Events/Show.vue の「戻る」ボタン修正（inline window 参照 → goBack() 関数化） | Claude |
| 2026-04-29 | N-01 | Coordinator/ProjectJobs/Show.vue の historyOpen 初期値 false→true に変更 | Claude |
| 2026-04-29 | N-02 | AssignmentForm.vue の開始時刻を現在時刻（JST 5分刻み切り上げ）に、終了時刻を 17:30 に変更 | Claude |
| 2026-04-29 | N-05 | Coordinator/ProjectJobs/Show.vue の tabs 配列にスケジュールタブ追加・順序変更、スケジュールセクションの v-show を schedule キーに変更 | Claude |
| 2026-04-29 | N-11 | ProjectSchedulesController::csvExport() のファイル名を {title}_スケジュール.csv 形式に変更（rawurlencode で日本語対応） | Claude |
| 2026-04-29 | N-12 | Coordinator/ProjectJobs/Show.vue の進行管理表タブ tr に @click・cursor-pointer 追加、「開く」Link に @click.stop 追加 | Claude |
| 2026-04-29 | N-03 | ProgressSheets/Show.vue に normalizeTitle() 追加・buildJobTitle() に適用（ー等を _ に統一） | Claude |
| 2026-04-29 | N-04 | User/ProjectJobController::progressSheetsJson()追加・routes/web.php にルート追加・MyJobBox/Index.vue・Calendar.vue のモーダルにシート選択ステップ追加・goToProgressSheet()で直接遷移 | Claude |
| 2026-04-29 | N-08 | coordinator_settingsマイグレーション・CoordinatorSetting.php・CoordinatorSettingController.php・routes追加・JobBox/Index.vue DB永続化・CoordinatorNavigationTabs.vue設定タブ追加・Coordinator/Settings/Index.vue新規作成 | Claude |

---

## ■ 次の推奨作業

**現時点の推奨:** N-04（「詳細を見る（進行表へ）」遷移先改善）。フロント＋バックエンド両方の変更が必要だが、N-08（DB マイグレーション）より先に着手しやすい。

---

## ■ 前回修繕（REPAIR_PLAN.md）との関係

前回修繕（B-01〜B-07 / L-01〜L-02 / F-01〜F-10 / G-01〜G-02 / V-01〜V-16）はすべて完了済み。
今回の修繕（N-01〜N-12）は前回修繕の成果を前提として実施する。特に以下を引き継ぐ：

- **F-01** のジョブステータス4段階（送信→確認済み→セット→完了）→ N-09 の統一基準として使用
- **L-02** のガイドライン適用済みページ → N-10 の確認対象から除外または確認済み扱い
- **F-07** の `?tab=` パラメータ方式 → N-07 のリダイレクト実装で流用
