# 従来の依頼取り込み完了通知 管理1

更新: 2026-11-10 JST。

| 段階 | 状態 |
| --- | --- |
| 読取診断 | 承認済み対象限定READ ONLY、終了0。元9683/取り込み9684とも完了、new_jobだけ1件、完了通知0。 |
| 具体設計 | 利用者yesで承認済み。 |
| 再現試験/実装/検証 | ac363保持版でIntegration81件932断言/実Vue/ナビ/PHP11構文成功。 |
| 配備/利用者実受信 | 未実施。 |
| 新式一般公開 | 未承認、今回の旧経路不具合修正とは分離。 |
| 過去通知補正・今回分再送 | 未承認・未実施。 |

## 2026-11-10 診断の証拠

利用者が提示したproject406/assignment9684を対象に、承認済みPHPスクリプトhash cc0d987d7f92ebc2abfbea5cfb063d9f57e01838c90ee815ae526319bd1c43c3を実行。mysqlのDB名を確認後READ ONLY transactionで元依頼/取り込み/関連role・セル/通知メタデータだけを取得。終了0、writes0/notifications_sent0。本番HEAD b4f585ba66256b9e13971bf5f243d82d3870ae18。診断結果の実名/本文/DBdumpを資料やfixtureに保存しない。
コード基準457a332a4（アプリは本番b4と同じ）。旧カレンダーの自己割当向けnotifyCompleted早期returnと、その後の元依頼完了更新を確認。HTTP経路そのものをDBから断定しない。従来完了の内部Message→担当本人Toastという通知抑制は別系統であり、今回の専用JobNotification修正でも維持する。

次の一手: PLAN1の既存完了経路だけの修正を利用者へ提示し承認を得る。実装を先行せず、一般公開/通知の一括再送/実業務の再完了はしない。共有Claude作業未編集。


## 2026-11-10 修正設計を承認・再現と実装、他作業の最新版保持へ

確定: 利用者yesでLEGACY_RECEIVED_COMPLETION_PLAN1の具体設計を承認。新式公開制限を維持し、既存の担当本人の通常依頼取り込み完了を修正する。自己完了通知なし、過去9683/9684の未送信補正なしを維持。

合成の一般Userカレンダーで元依頼と自己割当が完了しても完了通知0となる失敗を再現した。LegacyReceivedJobCompletionと旧3入口の接続、明示元依頼の認可/ロック/原子的通知、祖先・子兄弟境界、元JobAssignmentMessage/セル同期、銀本のcommit後outbox・JST作業日、担当本人Toastのcommit後/再操作抑止を実装。typed混在は既存guard付き共通完了へ委譲し、単独自作・直接依頼・校正・代理操作の専用経路は維持する。
再現の対象テストは修正後1件12断言成功、その後76件905断言、79件922断言を成功。追加した反映待ちflashテストはレスポンス用sessionとrequest用の試験sessionの違いにより取得先を修正中/最終検証予定。最終成功数として前の途中結果を流用しない。PHP11構文、実Vue入力/ナビ検証成功。JS/ルート/資産の変更はなくPHPのみ。

承認済み同一read SSHで本番ac36333cc4d699d2e362fb47a36edc82a9c74dc4/main/追跡変更なし/既知未追跡5件を確認。b4後の別作業の売上報告基盤（source/route/layout/build）更新が入っている。ローカルgit objectで同じSHAを確認。新しい業務データや売上DBは読まない。この最新版を統合し、今回修正だけをac363から反映できるようにする。共有Claude作業とCLAUDE.mdは未編集。
次の一手は今回版を保存してac363を統合し、最終合成検証・資産不変を確認する。配備と利用者実受信はまだ未実施。一般公開未承認。


## 2026-11-10 ac363保持版の実装・最終検証成功（未配備）

本番ac36333cc4d699d2e362fb47a36edc82a9c74dc4をmergeした保存147b9ad2cで、Integration81件932断言が全成功。新規17件を既存64件へ追加。一般Userのカレンダー/旧MyJob/JobBoxをまたぐ再操作でも元依頼のcompleted通知1件、兼任宛先の重複なし・操作本人除外を確認。通知の2人目保存が失敗した場合の状態/元メッセージ/予定/進行・管理セル/outboxの全rollback、循環/複数supersedes/所属・本人・案件不一致、旧祖先だけ完了/子兄弟維持を確認。直接依頼/単独自己割当/校正/代理操作/対象外の旧直接依頼からの継続は既存専用経路を維持。typed親は一般Userに開かず、試用許可者だけ既存WorkJobMutationへ委譲し、イベント参照もtransaction内で再照合する。

銀本syncは元依頼と自己割当のdefault DB確定後にoutboxから実行し、カレンダーのJST作業日を保持。失敗→既存command再試行で通知重複なし。担当本人Toastはcommit後のみ（dispatch時のtransactionLevel=0を合成で確認）、再操作では再放送せず、内部Message0件を維持。反映待ち時には二重Toastを避けてflash1つにする。fixtureのflash取得先をRedirectResponseのsessionへ修正した対象1件6断言も成功し、その後上記81件全体を成功確認。実Vue入力/ナビ退行も統合後に成功。

PHP11ファイル構文成功。ChangelogSeederの自動merge差分はLegacyReceivedCompletionChangelogSeeder呼出し1行だけで他の登録を保持。専用version legacy-job-notify-20261110は26文字、30上限以内。Vue/JS/routes/設定/依存/migration/CLAUDE/AGENTSの差分0。今回PHPのみなので再build不要、ac363の本番資産を完全保持（manifest481/参照512/欠落0/members base、SHA256 2f75a6555a102c294858e81844594728c2f34064fc070128f572e16580b8f133）。別作業の売上報告の実装/画面/layout/routes/buildは保持し、売上DBを調べたりそのmigrationを再実行したりしない。

元9683/9684は既に両方完了済みなので修正後の再操作でも過去未送信を送らない。今回分の補正・再送は未承認。一般Userの既存完了経路のバグ修正であり、新式入力/BOXの公開制限は保持。共有Claude作業を変更せず、実DBでのテスト登録/完了/通知もしていない。

状態: 設計承認・再現・実装・最終コード検証完了、今回配備/修正後の実通知受信は未実施。次の一手はac363から今回PHP差分だけの固定配備手順を用意し、正確なSSH承認を得る。
