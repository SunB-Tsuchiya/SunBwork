# Admin・Leader 日報閲覧タイムテーブル調査 2026-11-27

## 対象と版
利用者はAdmin/Leaderなどの日報一覧から表示される日報本文とタイムテーブルで情報が反映されていない可能性の調査を依頼。今回は調査のみ、アプリ修正・配備・閲覧権限の拡大は未実施。
AGENTS、JOB_EVALUATION_CONTROL1/ROADMAP1、CONSOLIDATED_05の日報タイムテーブル・旧本人試用仕様、直前の完了資料archived/PROGRESS_STAGE_CONFIRM_*と本会話を参照。
2026-11-27に承認済み正確SSHで本番3630c79abdd7fde55b8d51f926a73f32fb0bb4b1/main、追跡変更なし、既知未追跡6件、manifest差分なしを確認。日報本文/予定などの業務データは読んでいない。fetch後のorigin/mainはb1d57e1ddで本番とは異なり、主作業HEAD985e6fffbも古いため、それらだけを基準に判断していない。
前/tmpは消失。前回保存済み3ddb1156bから隔離branch feature/diary-view-audit-20261127 / /tmp/sunbwork-diary-view-audit-20261127を資料用sparseで復元。アプリソースはgit show 3630c79ab:pathで読み、本番と同じソースを調査。主作業のziggy/Claude/売上作業は保護。

## 確定した反映漏れ
1. Diaries/DiaryInteractionController::show（Admin/Leader共通）のpersonalWorkEnabledは「閲覧者と日報の本人が同じ」かつPersonalWorkSchedule::availableであることが条件。他人の日報を読むと常にfalse。
2. availableはSuperAdmin/INFO向けの旧管理試用判定で、一般INFOユーザー向けのpersonalAvailableとは別物。一般公開後も日報閲覧のこの呼出箇所は旧判定のまま。
3. DiaryManager/DiaryInteractionController::showはpersonalWorkEnabled自体を返さず、同じVueのdefault falseになる。日報管理許可者にも同じ欠落がある。
4. Interactions/Show.vueはonMountedでfetchDiaryTimelineEvents(date, diary.user_id, personalWorkEnabled)を呼ぶ。falseではevents.indexだけを取得し、新形式PersonalWorkEntryの取得を行わない。旧ジョブ/会議などeventsにあるものだけが表示される。
5. helperの新形式取得先はschedule.department_work.mineで、送信はstart/endだけ。DepartmentWorkController::mineはrequest.userのownedWorkのみ取得する。フラグを無条件trueへ変えるだけでは、閲覧対象者ではなく閲覧者本人の作業を混ぜるため誤り。
6. falseで取得しない場合、helperのfailedもfalse。取得失敗の警告では気づかないまま新形式の作業が抜ける。

## 日報本文・一覧の切り分け
- DiaryInteractionController::diaryRowはdiaries.contentを直接返し、本文入り表示ではcontentをそのまま付ける。showもDiaryの内容を返し、Interactions/Show.vueはprops.diary.contentを表示する。本文の別保存先やキャッシュを読む箇所はこの経路には見当たらない。保存・表示の実データ一致は未確認なので「本文が正常」とは断定しない。
- Interactions/Index/ByDateはDiaryTableで本文・閲覧操作を表示し、タイムテーブルそのものは詳細Interactions/Showで取得する。ユーザーのいう一覧からの閲覧導線をこの経路として追跡した。別画面を指す場合はURLで追加特定する。
- 日報本文なしの補助行はWorkRecordから作成している。PersonalWorkEntryしかなくDiary/WorkRecordがない日は、その補助行の対象にはならない。これを一覧へ増やすかは別仕様判断で、今回の反映漏れ修正へ勝手に混ぜない。
- 休暇予定/TODOは日報や勤務実績を自動変更しない承認済み仕様。今回はそれらの公開・本文自動生成を提案しない。

## 再現と根拠の範囲
本番と同じhelperをgit showから読み、axios/routeだけを合成に置換してNodeで実行。
- 対象user_id=200、閲覧者=100、includeQuickWork=false: events.indexへの1要求だけで旧形式1件、新形式0件。新形式の合成データがあっても取得要求がない。
- includeQuickWork=trueだけに変更: mineへ対象user_idが送られず、閲覧者100の作業が混ざることを再現。
初回のNode子プロセスのgit読取はsandbox EPERMで停止したため検証成功とはしない。同じ読取・合成再現コマンドを許可済みの環境で再実行してassertions passed。実ユーザー記録/DB/ネットワークAPIは使っていない。本番画面や個人レコードの照合も未実施。

## 修正の提案（未実装）
日報の閲覧認可を通った対象者・対象日の新形式作業を返す読取専用経路を設け、Admin/Leader/日報管理者と本人の日報表示へ同じ基準で接続する。本人mine APIの認可を緩めて流用しない。閲覧者自身の予定との混在を防ぎ、旧eventsと新PersonalWorkEntryを明示IDで重ね、元イベント派生や明示置換による二重表示も既存規則どおり防ぐ。会社/部署/日報閲覧許可、非公開・機密案件の既存表示方針を設計で確認し、部署一覧の表示ON/OFFを日報権限へ無断流用しない。
本文と保存データは変更しない。時間計算や評価点・完了状態も今回の表示取得修正で変えない。修正は複数画面/API/認可を伴うため、着手前にPLAN/MANAGER/PROMPTを作って具体設計を確認する。
次の1手: 調査結果を利用者へ説明し、修正指示後に閲覧認可と表示情報の具体設計を作る。個別本文の不一致がある場合は対象URLで別途読取承認を得て照合する。成功済みの工程確定/校正等を繰り返さない。保留中TODO繰り返し4条件は未確認のまま。

## 合成再現スクリプト
本番版の変更に関係なく、調査時点の3630c79abを読む。DBは使わない。
```js
const assert=require('node:assert/strict');
const cp=require('node:child_process');
const revision='3630c79ab';
const source=cp.execFileSync('git',['show',revision+':resources/js/Helpers/diaryTimelineEvents.js'],{cwd:'/home/w229/SunBwork',encoding:'utf8'});
const code=source.replace(/^import .*;\n/gm,'').replace(/export (async )?function/g,(_,a)=>(a||'')+'function');
async function run(include,viewerId){
 const calls=[];
 const axios={get:async(url,options)=>{calls.push({route:url,params:options.params});return url==='events.index'?{data:[{id:1,title:'旧形式の作業',start:'2026-11-26T09:00:00+09:00',end:'2026-11-26T10:00:00+09:00'}]}:{data:{events:[{id:'work:'+viewerId,title:'取得した本人の新形式作業',starts_at:'2026-11-26T10:00:00+09:00',ends_at:'2026-11-26T11:00:00+09:00'}]}};}};
 const helper=new Function('axios','route','personalWorkColors',code+'\nreturn {fetchDiaryTimelineEvents};')(axios,x=>x,{bg:'#4F46E5',text:'#fff',border:'#4F46E5'});
 const result=await helper.fetchDiaryTimelineEvents('2026-11-26',200,include);
 return {calls,result};
}
(async()=>{
 const missing=await run(false,100);assert.equal(missing.result.events.length,1);assert.equal(missing.calls.length,1);assert.equal(missing.calls[0].params.user_id,200);
 const unsafe=await run(true,100);assert.equal(unsafe.result.events.length,2);assert.equal(unsafe.calls[1].route,'schedule.department_work.mine');assert.equal(unsafe.calls[1].params.user_id,undefined);assert.equal(unsafe.result.events[1].id,'work:100');
 console.log(JSON.stringify({revision,reviewerOfAnotherDiary:{ordinaryEvents:1,newWorkEvents:0,requests:missing.calls},turningFlagOnAlone:{returnsViewerWork:true,targetUserParameterAbsent:true},assertions:'passed',productionDataRead:false},null,2));
})().catch(e=>{console.error(e);process.exitCode=1});

```
