# SunBrain Adminタブ整理 再開

`AGENTS.md` → `JOB_EVALUATION_CONTROL1.md` → `ADMIN_NAV_PLAN1.md` → `ADMIN_NAV_MANAGER1.md` を読み、現在のisolated branchと本番確認版を照合する。作業ツリーは `/tmp/sunbwork-myjob-guide-20261112` の `feature/admin-navigation-review-20261112`。共有 `/home/w229/SunBwork` のClaude変更は触らない。

利用者はサン・ブレーンのAdminタブだけを先に整理し、サンエーは後日別作業とする。まず共通Adminナビを会社別に分ける。SunBrain上段はダッシュボードの直後に日報閲覧、その後に人・組織/記録・分析/権限管理/各種設定を置き、各分類の下段で既存ページへ進む。既存ルート・認可は維持。Leaderは今回変更しない。

現在地は `ADMIN_NAV_MANAGER1.md` 末尾を優先。会社別分離とSunBrainの表示整理はローカル実装・検証済み。General用部品はa975の元ナビとSHA256一致。ESLintエラー0・既存警告5、/members build成功。本番は承認済み固定手順でa975→aa5a28f41へff-only、manifest494件/参照548件/欠落0、一般公開/権限/DB変更なし。**利用者の「成功」で今回の上段順・日報遷移、4分類の下段/遷移、勤務時間/ポートフォリオ見出しを画面確認済み。** SunA/Leader画面は今回の確認外。利用者はlocalStorageの旧URLを気にしていたが、今回URL変更なし、既存404復帰処理あり。一般ユーザーの新式マイジョブ公開は未承認。次の一手はLeader整理または週末の公開準備の優先を相談。SunAのタブ整理は別工程。
