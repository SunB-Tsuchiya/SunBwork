# 受信依頼の完了連動 再開用1

更新: 2026-11-09 JST。
AGENTS.md → JOB_EVALUATION_CONTROL1.md → REQUEST_JOB_COMPLETION_PLAN1.md / MANAGER1.md → 親MYJOB_UNIFIED_BOX資料を読む。

利用者は『取り込んだマイジョブ完了→元依頼完了→依頼主/案件リーダーへ1回通知』にyes。具体設計3資料を作成、実装前確認待ち。まだコード変更/検証/build/配備/完了連動画面確認はしていない。
保存基準1174f8ef7、branch feature/myjob-category-default-20261108、tree /tmp/sunbwork-myjob-category-default-20261108。再開時は存在とGit差分を確認。origin/main b1d57e1ddは古い。本番最後の証拠428250c09は最新と断定せず、配備時に正確な承認済みSSHで再確認。

次の一手はPLAN1の具体設計を利用者へ示し実装前確認。承認後は通常旧式のみ依頼取り込みも試用限定で共通完了へ。明示supersedes元の本人/案件/所属を検証し、状態とDB通知をdefault DBで原子的に確定。再操作重複なし、子兄弟非更新、校正/進行専用処理保持、MGinbonはoutboxでcommit後。NULL依頼主は推測せず既知リーダーのみ。詳細はPLAN1。

受信登録のBOX/カレンダー一致・元登録済・候補除外は利用者成功済み。再試験しない。完了/通知まで成功扱いにしない。共有Claude作業/CLAUDE.mdは未編集。一般公開未承認、sales DB禁止、業務完了や実通知を試験のため勝手に作らない。
