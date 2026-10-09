# SALES_UI1_PROMPT.md — 新セッション開始用プロンプト

このファイルは、本作業を別セッション（別のClaude Code起動）で再開する場合に、最初に貼り付けて
使うためのものです。

---

## 必読ファイル

1. `z_instructions/SALES_UI_PLAN1.md` — 設計書（最重要。方針・画面別改修内容・受入基準）
2. `z_instructions/SALES_UI_MANAGER1.md` — 進捗管理（どこまで終わっているか）
3. `z_instructions/SALES_ANALYSIS_PLAN1.md` / `SALES_ANALYSIS_MANAGER1.md` — 売上分析機能全体の
   設計書・進捗（本件はこの上に乗る追加のUI改修のため、全体像の把握に必要）
4. `z_instructions/CONSOLIDATED_01_layout_and_ui.md` — レイアウト規約（`SalesAnalysisShell.vue`の
   使い方、売上分析がAppLayoutを使わない独立レイアウトである経緯を含む）

## 背景（1行で）

売上分析配下14画面をAppLayoutを使わない全幅レイアウト（`SalesAnalysisShell.vue`）へ移行した結果、
「表が横に伸びて見にくい」というフィードバックを受け、2カラム化・構成比可視化導入でコンパクトにする
再設計に着手した（2026-10-09）。

## 現在の状態（2026-10-09時点でほぼ完了）

- `ShareCompositionBar.vue`（構成比を横棒積み上げ1本で見せる新規共通コンポーネント。円グラフは
  `dataviz`skillの指針により不採用とした）を新設し、以下へ組み込み済み:
  - MonthlyAnalysis.vue（完成見本。ユーザー承認済み）
  - AnnualAnalysis.vue / FiscalYearAnalysis.vue（得意先別・分類/項目タブを2カラム化。
    期別分析の経費セクションは表が14列あるため、2カラム化せず表の上に構成比バーのみ追加）
  - ClientAnalysis.vue / ProductAnalysis.vue（個別詳細カードは既に2表を内包する密な構成の
    ため変更せず、「ランキングカード自体」を2カラム化してShareCompositionBarを併設する形に
    PLANから調整。詳細は`SALES_UI_MANAGER1.md`2節タスク6参照）
- **未着手（優先度低、保留中）**: 同月比較（SameMonthComparison.vue）・左右比較
  （SideBySideComparison.vue）への構成比可視化追加。必要になったらPLAN3.3節を見て着手する。
- npm run build・SalesAnalysis配下テスト（355件）は各ステップで実施済み、全て成功。

## 作業再開時の最初のステップ

1. `SALES_UI_MANAGER1.md`の「1. 全体進捗」表を見て、どこまで終わっているか確認する
   （上記のとおり主要5画面は完了済み。残りはタスク7の同月比較・左右比較のみ）。
2. タスク7に着手する場合は`SALES_UI_PLAN1.md`3.3節の該当箇所を読んでから実装する。
3. 画面を1つ改修したら`npm run build`を実行し、`SALES_UI_MANAGER1.md`に作業ログを追記する。
4. 本番デプロイは別途ユーザー指示があるまで行わない
   （売上分析機能全体がまだ本番未デプロイ、Phase17までしかデプロイされていない点に注意。
   `SALES_ANALYSIS_MANAGER1.md`参照）。

## 注意点

- 新規バックエンドAPI呼び出しを増やさない（既存レスポンスの`share_pct`等をそのまま使う設計）。
- 時系列を表す表・グラフ（月別推移、得意先×年マトリクス）、列数の多い一覧表（取込履歴11列、
  経費月別表14列）は無理に2カラム化しない。
- `lg:grid-cols-2`はlg以上の画面幅でのみ適用し、モバイルでは単カラムに戻ることを確認する。
