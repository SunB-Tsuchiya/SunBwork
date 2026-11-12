# SunBrain Adminタブ整理 再開

`AGENTS.md` → `JOB_EVALUATION_CONTROL1.md` → `ADMIN_NAV_PLAN1.md` → `ADMIN_NAV_MANAGER1.md` を読み、現在のisolated branchと本番確認版を照合する。作業ツリーは `/tmp/sunbwork-myjob-guide-20261112` の `feature/admin-navigation-review-20261112`。共有 `/home/w229/SunBwork` のClaude変更は触らない。

利用者はサン・ブレーンのAdminタブだけを先に整理し、サンエーは後日別作業とする。まず共通Adminナビを会社別に分ける。SunBrain上段はダッシュボードの直後に日報閲覧、その後に人・組織/記録・分析/権限管理/各種設定を置き、各分類の下段で既存ページへ進む。既存ルート・認可は維持。Leaderは今回変更しない。

現在地は `ADMIN_NAV_MANAGER1.md` 末尾を優先。会社別分離とSunBrainの表示整理はローカル実装・検証済み。General用部品はa975の元ナビとSHA256一致。ESLintエラー0・既存警告5、ローカルと/members build成功、manifest494件/参照525件/欠落0。本番反映・今回の画面確認は未実施。利用者はlocalStorageの旧URLを気にしているが、今回URL変更なし、既存404復帰処理あり。一般ユーザーの新式マイジョブ公開は未承認。次の一手は隔離ソース保存→本番版のSSH読取確認→他作業と安全に統合した資産再build。
