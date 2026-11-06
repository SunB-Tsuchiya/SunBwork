# 大まかな案件・複数クライアント 進捗

2026-11-06 JST。基準543978c12、隔離/tmp/sunbwork-myjob-client-plan-20261106。共有Claude作業は編集しない。

| 段階 | 状態 |
| --- | --- |
| まとまり優先・複数関連・検索の方針 | 利用者OK、確定 |
| 調査 | 既存CSV330案件＋本番読取336案件。初期Excel作成済み |
| 3資料 | 初版作成済み。詳細設計の実装前確認待ち |
| コード・migration・テスト・build | 未実施 |
| company_clients照合・登録対象固定 | 未実施 |
| 限定配備・初期関連登録 | 未承認・未実施 |
| 新差分の利用者画面確認・上長照合 | 未実施 |
| 一般公開 | 未承認 |

初回読取の本番証拠0c9434f67988b8d8dc28988c2b0b37e193d70141、2026-11-06 10:51:58 JST。追加SSHなし。旧Excelのクライアント軸再整理案は比較資料に下げ、分類主体の複数取引先案を優先。

注意: Client::forCompanyはcompany_clients参照。初回読取はclients.company_id条件であるため、候補表だけで登録適格性を断定しない。権限や売上接続を広げない。次の一手はPLAN1の具体設計を提示し、実装前確認後に旧client_idの全利用先調査と追加対応表の実装。


## 2026-11-06 複数クライアント実装・コード検証完了（未配備）

利用者「進めてください」「あなたの決めた方針で進めていいですよ」により3資料の実装案を承認。中断後も同じ隔離ツリーの差分を確認し継続。共有Claude作業/CLAUDE.mdには触れていない。

実装: broad_project_clients追加migration、BroadProject関連、BroadProjectClients保存/会社関連検証、Treeの直接関連と親の有効子集約/休止込み集約を分離。旧client_idを保持し、client_ids未指定保持/空配列解除、旧単一更新では他の関連を保持、同時矛盾422、既存休眠保持/解除・新規追加拒否。名称/年度が変わらない旧重複は編集可能とし、新規/名称年度変更/移動では同名同年度の増殖を防ぐ。分類ID/階層/順序/お気に入り/実案件client_id/担当は一括変更しない。

既存フォームの複数選択、一覧2社＋ほかN社のnative details、共通tree検索、進行管理の分類選択、簡易候補API/表示/検索に反映。過去流用は具体案件がある場合その実client_idを優先し、分類だけの場合は関連クライアントで検索できるが実client_idを捏造しない。新式の取引先必須化なし。一般公開未承認/role変更なし。

初期関連追加専用broad-projects:import-clientsを実装。対象mysql DB名一致/会社部署/確認表の分類名親/会社関連/休眠条件を全件検証してから同一transactionで追加だけ実施。dry-runは書込なし、再実行は追加0。既存単一値は適格なら対応表へ補完、先頭取引先をprimaryとして自動選択しない。専用BroadProjectClientsChangelogSeederをChangelogSeederから呼び、限定配備時は専用だけ実行する。versionは30文字以内。

検証証拠: 対象PHP Unit+Integration 65テスト839断言成功。既存関連Unit全体100テスト744断言成功（前者との重複あり、合算しない）。SQLite :memory:を明示、network none、Tests\TestCase/RefreshDatabase不使用。PHP変更11ファイル構文成功。実Vue scriptで複数選択保持/重複防止/解除/新payload/名前付きroute、実SSRで複数名表示/展開/エスケープを確認。共通tree検索、既存unifiedMyJobInputとworkJobDetailsの実Vue退行検証成功、対象ESLintエラー0。

members build終了0。manifest468エントリ・参照資産498・欠落0、全既存resourcesエントリ保持、members base確認。既存build資産を復元し削除差分なし。ガイドの旧パス/home/tchirosb/SunBWorkは本環境に存在せず、共有作業保護のため隔離ツリーでVITE_APP_BASE_PATH=/membersをコマンド環境指定。共有.env/共有buildは未変更、ローカル設定の書換えなし。routes変更なし。

初期データはprivate/codex-client-review-20261106/分類クライアント対応_未適格照合_20261106.draft.jsonに63分類・直接関連候補93組を準備。親集約は保存値に重複追加せず、判定不能/その他を候補から除外。会社関連と休眠未照合のため実登録不可の下書き状態。追加読取用/tmp/broad-project-client-eligibility-20261106.sh（SHA256 a588432bcca0b5cb4bcef616dca4a8c1b99bddf2d52718de4e5ae1fa554687c4）を準備し、privateにも復元用保存。既取得取引先65IDの会社関連/休眠と現行分類を同じINFOスコープの読取専用transactionで照合する。PHP/sh構文成功、未実行。売上接続なし。

状態: 実装/コード検証/build完了、追加読取未承認、初期登録/配備/今回差分の利用者画面確認/上長照合は未実施。成功済みの旧画面試験を再要求しない。次の一手は正確なSSH読取コマンド承認→適格性結果で初期登録表を固定→限定配備・初期登録手順の提示。一般公開は別承認のまま。


## 2026-11-06 取引先適格性の本番読取成功・初期対応固定

利用者yesで正確な読取コマンドを承認し、/tmp/broad-project-client-eligibility-20261106.shを実行、exit0。2026-11-06 18:49:00 JST、本番revision 0c9434f67988b8d8dc28988c2b0b37e193d70141、mysql/INFO会社2部署1一致。65取引先IDのうち64件が同社会社関連あり・非休眠。会社関連なしの1件は既に除外していた「その他」で、登録候補への混入なし。現在63分類は初回読取とID/名称/親/旧取引先/年度/休止/順序が意味的に全一致（JSON boolとDB整数の表現差は変更と数えない）。追加読取のみで書込なし。

private/codex-client-review-20261106/分類クライアント対応_登録用_20261106.jsonを固定。SHA256 b25f469548abd1fd555cdf52b894227b4719e64bbf9c6edca172b340002d31c3。63分類の直接候補93組、既存旧単一1組の対応表補完を含め初回追加予定94組。親集約を保存時に二重登録せず、分類名・所属を保護する。実データmanifestはGit未登録。

同じID構造を合成名称へ置換したSQLiteメモリ検証で、63分類、dry-run94/書込0、本登録94、再実行0、全分類メタデータ不変を確認。実DBへは未登録。コードは4071c60b8に保存・push済み。

次の一手は固定本番元0c9434f67から専用migration・限定コード・初期関連94組だけを扱う配備スクリプトを完成し、正確なSSHコマンドを提示して承認を得る。既存未追跡5件/共有Claude変更の保護、対象DB確認、privateバックアップ、manifest hash・dry-run期待件数・全分類メタデータ/既存参照保持・asset欠落0を検証する。一般公開未承認。配備/今回画面確認/上長照合は未実施。


## 2026-11-06 限定配備スクリプト準備完了（承認待ち）

/tmp/broad-project-clients-deploy-20261106.shを作成、sh構文成功、埋込事前事後PHP構文成功、JSON63分類のhash一致、Laravel artisan helpで専用コマンド認識を合成環境で確認。スクリプトSHA256 42e983063eea828644f201e90d019ba8eec22259bac42a5c43612475a6eec996。private/codex-client-review-20261106にも保存。実行は未承認・未実施。

固定元0c9434f67988b8d8dc28988c2b0b37e193d70141、固定先e856d0c5a207530c0934ac723e9bc69d50a1dab5、取得ブランチfeature/myjob-client-plan-20261106。source34ファイルの許可一覧一致/削除なし/追跡変更なし/未追跡5件厳密一致を確認して進める。main全体をpullしない。

事前に既定mysql/silverlamb759_sunbwork/INFO会社2部署1/63分類の現在状態と候補適格性を再確認し、privateに全分類の既存列と限定参照hashを保存する。追加migration1本のみ--database=mysql/--path/--realpath/--forceで適用、固定先へff-only。専用importのdry-run63分類94組、本登録94組、再dry-run0組を厳密確認。専用Seederだけ--database=mysql --force、config/cache/route/view clear。事後は全分類列・既存ProjectJob/簡易記録/星の分類参照hash保持、期待94組完全一致、資産498/欠落0/members base、既存未追跡5件保持を確認する。

次の一手: 正確な `ssh -T silverlamb759@silverlamb759.sakura.ne.jp 'sh -s' < /tmp/broad-project-clients-deploy-20261106.sh` の承認後に同じスクリプトを実行。元版/作業状態が違えば停止し、他作業へ合わせるため勝手にreset/pullしない。今回の画面確認は未実施、一般公開未承認。
