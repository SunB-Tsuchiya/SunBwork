# SUNA1_PROMPT.md — 新セッション開始用プロンプト

```
サンエー印刷専用機能（SunA）プロジェクトを継続します。
まず CLAUDE.md の「サンエー印刷専用機能の配置ルール（SunA）」、z_instructions/SUNA_PLAN1.md、SUNA_MANAGER1.md を読んでください。
前提: z_instructions/COLAYOUT_PLAN1.md（会社別レイアウト分離。company_type = 'general' がサンエー印刷）。

## ルール
- サンエー印刷専用の Controller/Model/Request/Service/Vue/Component は SunA/{GeneralAffairs|Accounting}/ 配下に置く
- DB テーブル接頭辞 suna_（ga = 総務, ac = 経理）。ルートは routes/suna.php、company_type:general で保護
- ルート名は所属エリアの接頭辞を保つ（総務/経理は clerk.）。getTopTabActive が includes('ai') に誤マッチするので申請系は先頭で分岐
- サン・ブレーンに影響させない（general のときだけ出す）。新規ページは AppLayout、CONSOLIDATED_01 に従う

- DB は分けない（同一 DB・同一 users、company_id で区別）。SunA テーブルは company_id 必須＋ResolvesContextCompany で絞り込む（CLAUDE.md の設計判断参照）

## 現状
「申請」= 社員が総務に出す申請の種類（年末調整など）の入口と、その種類を管理する CRUD。
申請フォーム・提出・承認フローは後続（MANAGER の D-1）。進捗は MANAGER の表を参照。

## 後回し事項
MANAGER の D-1〜D-6。新たに後回しにしたことは追記する。

## 注意
- ブランチ: colayout-deploy（origin/main 同一）。ワークロード分析は main + stash@{0} に退避中（COLAYOUT_MANAGER1 参照）
- migration あり → 本番は php artisan migrate --force。Vue 変更時のデプロイは VITE_APP_BASE_PATH 6 ステップ厳守
- Docker: docker compose up -d / exec は --user sail。ブラウザ確認はユーザー
```
