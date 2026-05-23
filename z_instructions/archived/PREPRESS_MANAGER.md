# SunBWork Prepress（製版）エリア 作業管理書
作成日: 2026-04-29
更新日: 2026-04-29（フェーズ1・2 全完了）

---

## ■ この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「P-01を始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- フェーズ2以降の機能は `PREPRESS_PLAN.md` の「将来追加予定」セクションに追記し、Claude に伝えてください
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（PREPRESS_MANAGER.md）を読む
2. `z_instructions/PREPRESS_PLAN.md` を読む（詳細仕様・変更ファイル一覧が記載されている）
3. `CLAUDE.md` を読む（プロジェクト全体ルール・必読）
4. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
5. 以下の「作業フロー」に従って進める

---

## ■ 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/PREPRESS_PLAN.md` | Prepress の詳細仕様・変更ファイル・対応内容 |
| `z_instructions/PREPRESS_PROMPT.md` | 新しい Claude セッションへの引き継ぎプロンプト |
| `z_instructions/CONSOLIDATED_09_domain_rules.md` | ドメインルール（権限・JobBox・通知等） |
| `z_instructions/CONSOLIDATED_01_layout_and_ui.md` | UI ルール詳細 |
| `CLAUDE.md` | プロジェクト全体のルール（必読） |

---

## ■ 作業フロー（Claude はこの手順を厳守すること）

各作業項目（P-xx）は以下のステップで進める。

```
STEP 1: 設計書を読む
  → PREPRESS_PLAN.md の該当項目を読み、仕様を把握する
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
- `department.name` の値が '製版' であることを DB か Admin 画面で確認してから実装する

---

## ■ 進捗一覧

### フェーズ1：ベース実装（インフラ）

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| P-01 | AppLayout への Prepress タブリンク追加 | ✅ 完了 | 2026-04-29 |
| P-02 | PrepressNavigationTabs.vue 作成（ダッシュボード / 伝票ボード / 伝票一覧） | ✅ 完了 | 2026-04-29 |
| P-03 | Prepress ダッシュボード（Dashboard.vue） | ✅ 完了 | 2026-04-29 |
| P-04 | routes/web.php 全ルート追加 + php artisan migrate | ✅ 完了 | 2026-04-29 |
| P-05 | HandleInertiaRequests.php に isPrepressDepartment フラグ追加 | ✅ 完了 | 2026-04-29 |

### フェーズ2：伝票管理機能

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| P2-01 | 伝票ボード（Board.vue + BoardController + D&D） | ✅ 完了 | 2026-04-29（D&D Inertia バグ修正含む） |
| P2-02 | 伝票一覧（Tickets/Index.vue + TicketController::index） | ✅ 完了 | 2026-04-29 |
| P2-03 | 伝票登録（Tickets/Create.vue + TicketController::store） | ✅ 完了 | 2026-04-29 |
| P2-04a | DB migration（prepress_tickets テーブル） | ✅ 完了 | 2026-04-29 migrate 実行済み |
| P2-04b | PrepressTicket モデル | ✅ 完了 | 2026-04-29 |
| P2-04c | PrepressImageService（画像→JPG変換） | ✅ 完了 | 2026-04-29 |
| P2-04d | PrepressDashboardController | ✅ 完了 | 2026-04-29 |
| P2-04e | TicketController | ✅ 完了 | 2026-04-29 |

---

## ■ 実装順序（現状の推奨）

フェーズ1 + フェーズ2 を一括実装する。以下の順で進める。

```
STEP 1: 既作成ファイル確認（migration / model / service / controllers）
  → 問題なければそのまま。不備があれば修正。ユーザーに確認後に次へ。

STEP 2: BoardController 作成
  → app/Http/Controllers/Prepress/BoardController.php（新規）

STEP 3: routes/web.php に全ルート追加
  → GET /prepress/dashboard, board, tickets, tickets/create
  → POST /prepress/tickets（store）
  → PATCH /prepress/tickets/{ticket}/status（updateStatus）
  → DELETE /prepress/tickets/{ticket}
  → php artisan migrate 実行

STEP 4: PrepressNavigationTabs.vue 作成
  → タブ: ダッシュボード / 伝票ボード / 伝票一覧

STEP 5: AppLayout.vue に Prepress タブリンク追加
  → roleNavClass に prepress 追加
  → currentRouteContext に prepress. 判定追加
  → 全ロール template に Prepress リンク追加（条件付き）
  → tabs slot に PrepressNavigationTabs 追加
  → レスポンシブナビにも追加

STEP 6: Dashboard.vue 作成

STEP 7: Board.vue 作成（kanban D&D）

STEP 8: Tickets/Index.vue 作成（伝票一覧）

STEP 9: Tickets/Create.vue 作成（伝票登録 + 画像アップロード）

STEP 10: npm run build → 動作確認依頼
```

---

## ■ department.name 確認チェック

Prepress タブの表示条件 `department.name === '製版'` の前提確認。

| 確認項目 | 確認方法 | 結果 |
|---------|---------|------|
| DB の department.name 値 | tinker で確認済み（id:1=情報出版, id:2=製版, id:3=オンデマンド） | ✅ 確認済み |
| '製版' というレコードが存在するか | id:2 として存在 | ✅ 確認済み |
| code カラムで識別すべきか | Department モデルの fillable 確認済み（code カラムあり） | — |

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
| 2026-04-29 | — | 設計書（PREPRESS_PLAN.md）・管理書（PREPRESS_MANAGER.md）・プロンプト（PREPRESS_PROMPT.md）作成 | Claude |
| 2026-04-29 | P-05 | HandleInertiaRequests.php に isPrepressDepartment フラグ追加 | Claude |
| 2026-04-29 | P2-04a | migration ファイル作成 + php artisan migrate 実行 | Claude |
| 2026-04-29 | P2-04b | PrepressTicket モデル作成 | Claude |
| 2026-04-29 | P2-04c | PrepressImageService 作成 | Claude |
| 2026-04-29 | P2-04d | PrepressDashboardController 作成 | Claude |
| 2026-04-29 | P2-04e | TicketController 作成 | Claude |
| 2026-04-29 | P-01〜P-04 | AppLayout・PrepressNavigationTabs・Dashboard・routes 実装 | Claude |
| 2026-04-29 | P2-01 | Board.vue + BoardController 実装（D&D オプティミスティック更新） | Claude |
| 2026-04-29 | P2-02 | Tickets/Index.vue 実装（フィルター・ページネーション） | Claude |
| 2026-04-29 | P2-03 | Tickets/Create.vue 実装（画像アップロード・ライトボックス） | Claude |
| 2026-04-29 | BUG | Board D&D: router.patch → axios.patch に修正（Inertia JSON エラー解消） | Claude |

---

## ■ 次の推奨作業

**フェーズ1・2 は全て完了。** 現時点での推奨は以下のいずれか：

- **フェーズ3（伝票詳細・編集）:** 伝票の詳細表示（Show.vue）・編集（Edit.vue）機能の追加
- **フェーズ4（担当者・期日）:** 担当者割当・納期管理カラム追加
- **現状維持:** 今のボード・一覧・登録で運用しながら要望が出たら追加

ユーザーから次の指示が出たら PREPRESS_PLAN.md の「将来追加予定」に追記し、通常の作業フロー（5ステップ）で進める。

---

## ■ フェーズ2 機能追加時のルール

ユーザーから製版固有の機能追加指示が出た場合：
1. PREPRESS_PLAN.md の「将来追加予定」セクションに要件を追記する
2. 本管理書「フェーズ2以降」の進捗一覧に ID を割り当てる（P-10〜）
3. 通常の作業フロー（5ステップ）に従って実装する
