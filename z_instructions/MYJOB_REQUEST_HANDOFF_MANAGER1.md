# 依頼登録の共通引継ぎ 管理1

更新: 2026-11-09 JST。

| 段階 | 状態 |
| --- | --- |
| 共通化方針と修正着手 | 利用者『あなたの案でOKです。修正しましょう』で確定。 |
| 具体設計 | 利用者yesで承認済み。 |
| 実装 | 共通prefillと各入口/保存検証を実装済み。 |
| コード検証/build | Integration64件790断言、実Vue/ナビ、PHP9、実Ziggy、members build成功。 |
| 配備 | 今回未実施。過去の本番証拠5ef950b77と区別。 |
| 利用者画面 | 比較画面による問題報告あり。修正後確認は未実施。 |
| 上長照合/一般公開 | 未実施/未承認。 |

## 2026-11-09 方針承認と具体設計

MYJOB_REQUEST_HANDOFF_AUDIT1の共通prefill案に利用者OK。旧形式を使いながら入口別の初期値・案件固定・作業日時・元依頼関連を揃える。進行表/校正専用参照を維持。実装前に3資料を作成。具体仕様はPLAN1。今回のstatus入口別差は変更せず、数量算定の方式変更やサイズ矛盾の新規自動補正は保留。

branch feature/myjob-request-handoff-audit-20261109、tree /tmp/sunbwork-request-handoff-audit-20261109。共有Claude変更を保護。コード/DB/本番変更なし。直前完了連動2点への成功回答ではなく、未確認を維持する。
次の一手: 具体設計への実装前確認を得てBへ進む。


## 2026-11-09 共通引継ぎの具体設計承認・実装/検証完了（未配備）

確定: 利用者yesでMYJOB_REQUEST_HANDOFF_PLAN1の具体設計を承認、実装へ進行。作業中の追加yesは継続への回答として受領し、まだ提示していない配備SSHへの承認には拡張しない。

ReceivedJobPrefillを追加し、試用本人宛の未完了・未取り込み元依頼と案件を認可してclient/案件size/概要・種別・工程・難易度・見積/数量単位/file_infoと業務関連を共通化。旧詳細とEventControllerのsource_job_assignment_id経路、Events/Create_Job・旧Create_user・MyJobCreateModalの依頼選択を同じseedへ接続。新GET user.myjobbox.received_prefillは試用外を403、本人以外を404とする。一般roleの既存経路は維持。

依頼選択はGETのみで、最新応答が成功した時だけ案件・候補・初期値を一括更新。失敗時は入力保持、閉じる/切替/遅延応答で前の依頼を混入させず、過去引用へ変更する際も前の案件固定値/revision/file_infoを残さない。ドラッグ日時はprefillEventで保持。共通フォームがsupersedes/revision/coordinator参照を正しく送信する。JobBoxのセルquery組立て順も修正。

保存は元依頼/案件をロックして、認可・同案件・登録競合・HMAC revisionを再照合。セル/校正参照はサーバ由来へ固定し、入力した任意詳細値は上書きしない。イベント・セル保存失敗では作成と登録済み更新をrollback。会社context切替後の新token保存を拒否。一般入口のstatusと数量算定方式は維持、source数量0と未指定を区別。明示/ファイル推定サイズが案件固定値と異なる合成例では、従来詳細と同じ値と固定表示を維持し、別ルールで自動補正しない。

検証: 明示SQLite :memory:/network noneでIntegration64件790断言成功（既存54件を含む）。読取無変更/旧詳細とイベントsourceの同一seed/ドラッグ00:00保持/数量とサイズのfallback/本人・会社部署/context/登録済・完了済拒否/編集値保持/再登録拒否/元依頼と案件の更新検出/通知なしの通常保存/セルの曖昧・別案件拒否/管理表と校正参照/画面の偽セル参照無視/イベント失敗rollbackを確認。実Vue script+SSRで3選択入口の同一seed、client/案件size固定表示、共通フォーム送信値、失敗・遅延・キャンセル・過去引用切替と日時保持を確認。workHub既存ナビ退行も成功。対象ESLintはエラー0、既存未使用変数等のwarning36件。PHP9構文成功。新GETの実route登録と実Ziggy生成を確認（Ziggyのbase_path相対出力先を確認し、検証用生成ファイルは回収済み）。初回合成のNOT NULL試験データとJSON数値型の比較、SSRに必要なmembers試験propsを修正し、最終成功。実DB・業務完了/通知の試験なし。

本番用build終了0、manifest476/参照507資産/欠落0/source entry全保持/members base/内部参照整合、旧資産保持・削除なし。manifest SHA256 5458299fbdc1d631051e95c064e48fd26ec375eaf5b8e944d37ac2e670fefccb。既存glob非推奨/chunk通知のみ。専用ReceivedJobPrefillChangelogSeederとChangelogSeederの委譲を追加。version myjob-request-fill-20261109は27文字で30上限内と確認。

以前承認済み同一read SSHで本番5ef950b7738d558f9e0b062509b712bfac442c83/main/追跡変更なし/既知未追跡5件/manifest差分なしを確認。他Claude変更を保持し共有treeは未編集。新migration/設定/依存変更なし。

状態: 具体設計承認・実装・コード検証/build完了、今回配備/利用者画面/上長照合は未実施。一般公開未承認。前回完了連動2点や実通知も未確認のまま、入力登録の既成功チェックを繰り返さない。次の一手は本番5ef950b77からの固定差分を保存して配備スクリプトを準備し、正確なSSH承認を得る。
