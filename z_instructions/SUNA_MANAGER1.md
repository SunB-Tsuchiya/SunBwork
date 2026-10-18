# SUNA_MANAGER1.md — サンエー印刷専用機能（SunA）進捗管理

## ステータス凡例
⬜ 未着手 / 🔄 作業中 / ✅ 完了 / ⏸ 保留（後回し）

## 作業フロー
1. 着手前に `SUNA_PLAN1.md` と CLAUDE.md「SunA 配置ルール」を確認
2. 後回し事項（D-n）を実装するときは、影響範囲を提示して確認を取る
3. Vue/JS 変更後は `npm run build`。ブラウザ確認はユーザーが行う
4. 新たに後回しにしたことは D-n として追記する

## Phase 1: 設計・合意
| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 1-1 | SunA 配置ルールを CLAUDE.md に記録 | ✅ | 2026-10-16 |
| 1-2 | 申請メニューの設計合意 | ✅ | 申請 = 種類の入口。フォーム・提出・承認は後続 |

## Phase 2: 実装（総務「申請」メニュー）
| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 2-1 | migration + Model + Request | ✅ | |
| 2-2 | Controller 2 本 + routes/suna.php | ✅ | |
| 2-3 | Vue 3 ページ | ✅ | |
| 2-4 | 総務タブ「申請」 | ✅ | `getTopTabActive` の変更は不要と判断（各ページが `#tabs` スロットで `active="applications"` を直接渡すため）。AppLayout は未変更 |
| 2-5 | Accounting 空ディレクトリ用意 | ✅ | |
| 2-6 | Ziggy 再生成 + build + 構文チェック | ✅ | |

## Phase 3: 確認・デプロイ
| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 3-1 | ローカル確認（ユーザー） | ⬜ | ダミー: sanei-soumu@example.invalid / sanei-admin@example.invalid（pw: password） |
| 3-2 | 本番デプロイ（migrate --force 必須） | ⬜ | ユーザー指示後 |
| 3-3 | ChangelogSeeder / CONSOLIDATED_01・09 更新 | ⬜ | |

## 📌 設計判断（変更するときはユーザーに確認）
| 日付 | 判断 | 理由 |
|---|---|---|
| 2026-10-16 | **サンエー印刷とサン・ブレーンで DB は分けない**（同一 DB・同一 users、`company_id` で区別） | 社長と総務・経理が両社を共同管理。ユーザー/ログイン/お知らせ/カレンダーが共通。既存の会社別分離（company_id・SuperAdmin 会社切替）が前提。DB 分割はログイン・権限の二重化、migration・バックアップ・さくら DB 契約数が倍になる。コード/テーブルの区別は SunA ディレクトリと `suna_` 接頭辞で付いている |

**同一 DB のガード:** SunA テーブルは必ず `company_id` + `ResolvesContextCompany` で絞り込み／Model が増えたら会社 global scope トレイトを導入／ルートは `company_type:general`／デプロイ前後のバックアップ。
**個人情報（年末調整など）:** 項目の暗号化とアクセス権（担当者のみ閲覧）を申請フォーム設計時に決める（→ D-8）。
**再検討の条件:** 両社が完全に独立する／厳重な個人情報を技術的に分離したい／片方の負荷・障害を隔離したい。

## 🗂 後回し事項（Deferred Backlog）
| ID | 項目 | 状態 | 詳細 |
|---|---|---|---|
| D-1 | 各申請の入力フォーム・提出・承認フロー | ⏸ | 案が固まってから。年末調整から着手予定 |
| D-2 | 申請種類の「一覧の枠」クリック時の遷移先 | ⏸ | D-1 で決める。今回は表示のみ |
| D-3 | 経理（Accounting）の機能 | ⏸ | ディレクトリのみ用意。要望が出てから |
| D-4 | 申請メニューの経理タブ表示 | ⏸ | 現在は総務のみ（経理タブには出ない）。ただし経理ユーザーも URL 直打ちなら申請画面を開ける（general の clerk は通過）。経理に見せない方針を厳密にするなら、routes/suna.php で clerkKind 判定が必要 |
| D-5 | URL 直打ち対策（COLAYOUT D-1）との整理 | ⏸ | SunA は `company_type:general` で保護済み。COLAYOUT 側の共用ルートは別途 |
| D-6 | 本番にサンプル「年末調整」を登録するか | ⏸ | 自動投入しない。運用で登録。ローカルは company 3 に id=1「年末調整」を登録済み |
| D-8 | 個人情報の暗号化とアクセス権の設計 | ⏸ | 年末調整の扶養家族・マイナンバー等を扱う申請フォームの設計時に決める（D-1 と同時） |
| D-9 | SunA の会社スコープ共通トレイト（global scope） | ⏸ | SunA の Model が増えた段階で導入。絞り込み漏れ防止 |
| D-7 | SuperAdmin が sunbrain コンテキストで申請画面に入れる | ⏸ | company_type:general は SuperAdmin 通過の仕様。sunbrain 会社名義で種類を登録できてしまう。実害は小さい（表示されるタブなし）が、必要なら general コンテキスト限定にする |

## 作業ログ
| 日付 | 内容 |
|---|---|
| 2026-10-16 | CLAUDE.md に SunA 配置ルール追記。申請メニュー設計合意（申請=種類の入口）。PLAN/MANAGER/PROMPT 作成。作業ブランチ: `colayout-deploy`（origin/main と同一位置）。ワークロード分析はローカル `main` + `stash@{0}` に退避中（COLAYOUT_MANAGER1 参照） |
| 2026-10-16 | 実装完了: migration（ローカルのみ `--path` 指定で適用。他の保留 migration は未適用のまま）/ Model / Request / Controller 2 本 / routes/suna.php / Vue 3 ページ / 総務「申請」タブ / Accounting 空ディレクトリ / Ziggy 再生成 / build。**動作確認（別プロセスで実リクエスト）**: サンエー総務・管理者・経理 → 200、サン・ブレーン clerk → 403、SuperAdmin → 200。 |
| 2026-10-16 | 設計判断「DB は分けない」を記録（CLAUDE.md・本ファイル）。D-8（個人情報の暗号化・アクセス権）、D-9（会社スコープ共通トレイト）を追加。 |
| 2026-10-16 | 申請の種類に「連携する機能」（`feature_key`、migration `2026_10_16_110000`）を追加。年末調整トップ（`/clerk/general-affairs/nencho`）と設計ガイド（`…/nencho/guide`）を実装。設計ガイドは Markdown 1 ファイル（`resources/docs/suna/general_affairs/nencho_overview.md`）を表示。申請一覧の「年末調整」枠から年末調整トップへリンク。実リクエストで確認: サンエー総務・管理者・経理 200、サン・ブレーン clerk 403、SuperAdmin 200（HTML 除去・table 描画 OK）。**本番デプロイ時は migration 2 本（100000, 110000）が必要** |
| 2026-10-17 | 年末調整 段階 1 実装（詳細は NENCHO_MANAGER1）。**本番デプロイ時の migration は現時点で 5 本**（100000/110000/2026_10_17_100000/100100/100200）。段階 2 でさらに追加予定 |
| 2026-10-17 | 年末調整 段階 1b（社員情報・権限管理・マイナンバー・閲覧記録・両社対応）を実装。**本番デプロイ時の migration は現時点で 9 本**（NENCHO_MANAGER1 の作業ログ参照）。routes/suna.php に管理者向けルート（`admin.general_affairs.*`）を追加 |
| 2026-10-18 | 年末調整 段階 2（従業員の入力）を実装。**本番デプロイ時の migration は現時点で 13 本**。ユーザー用タブに「年末調整」を追加（対象の受付がある人にだけ。サン・ブレーン従業員にも表示される）。routes/suna.php に従業員ルート（`user.general_affairs.nencho.*`）を追加 |
| 2026-10-18 | 年末調整 段階 3 完了（代理入力・管理者メモ・差戻し・回収状況・操作履歴・国の形式への適合検査）。**本番デプロイ時の migration は現時点で 16 本**。routes/suna.php に代理入力ルート（`clerk.general_affairs.nencho.proxy.*`）を追加 |
| 2026-10-18 | 年末調整: 同時編集の防止（編集ロック）を追加。**本番デプロイ時の migration は現時点で 17 本** |
| 2026-10-18 | 年末調整: 社員情報との差異の照合（差分リスト）を追加。**本番デプロイ時の migration は現時点で 18 本** |
