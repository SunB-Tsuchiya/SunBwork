const fs=require('fs'),path=require('path'),os=require('os'),assert=require('node:assert/strict');
const {build}=require('esbuild'),{parse,compileScript}=require('@vue/compiler-sfc');
const {createRenderer,h,nextTick}=require('vue');
const root=path.resolve(__dirname,'../..'),out=fs.mkdtempSync(path.join(os.tmpdir(),'todo-workspace-'));
global.crypto ||= require('node:crypto').webcrypto;
global.document={querySelector:()=>({getAttribute:()=> 'csrf-todo'})};
global.window={addEventListener(){},removeEventListener(){},history:{state:{},replaceState(){}}};
global.confirm=()=>true;
let calls=[],get=async()=>({data:workspace()}),post,put,patch;
global.__todoAxios={get:(...a)=>get(...a),post:(...a)=>post(...a),put:(...a)=>put(...a),patch:(...a)=>patch(...a),delete:async(...a)=>{calls.push(a);}};
const renderer=createRenderer({createElement:()=>({}),createText:()=>({}),createComment:()=>({}),insert(){},remove(){},patchProp(){},setText(){},setElementText(){},parentNode(){},nextSibling(){}});
const settle=async()=>{await nextTick();await Promise.resolve();await nextTick();};
function item(){return {id:4,version:3,item_type:'todo',title:'元のTODO',note:'メモ',all_day:true,start_date:null,steps:[{id:crypto.randomUUID(),title:'準備',completed:false}],completed:false};}
function workspace(){return {items:[],view:'all',q:'',sort:'due',list_id:null,page:1,last_page:1,show_completed:false,counts:{},lists:[{id:2,name:'個人',version:1,archived:false},{id:3,name:'次',version:1,archived:false}],settings:{initial_view:'all',default_sort:'due',sidebar_view:'upcoming',sidebar_limit:10,show_completed:false,version:0},today:'2026-11-17'};}
async function compile(file,expose){const dest=path.join(out,path.basename(file)+'.cjs');await build({entryPoints:[path.join(root,file)],outfile:dest,bundle:true,platform:'node',format:'cjs',plugins:[{name:'vue',setup(b){
 b.onResolve({filter:/^vue$/},()=>({path:require.resolve('vue'),external:true}));
 b.onResolve({filter:/^(axios|ziggy-js|@inertiajs\/vue3)$/},a=>({path:a.path,namespace:'mock'}));
 b.onLoad({filter:/.*/,namespace:'mock'},a=>({loader:'js',contents:a.path==='axios'?'export default global.__todoAxios;':a.path==='ziggy-js'?'export const route=(n,p)=>n+JSON.stringify(p||{});':'export const Link={}; export const router={on:()=>()=>{}};'}));
 b.onResolve({filter:/^@\//},a=>a.path.endsWith('.vue')?{path:a.path,namespace:'shell'}:{path:path.join(root,'resources/js',a.path.slice(2)+'.js')});
 b.onLoad({filter:/.*/,namespace:'shell'},()=>({loader:'js',contents:'export default {};'}));
 b.onLoad({filter:/\.vue$/},a=>{const source=fs.readFileSync(a.path,'utf8').replace('</script>',`\nglobal.__state={${expose}};\n</script>`);return {loader:'js',contents:compileScript(parse(source,{filename:a.path}).descriptor,{id:'todo-test'}).content};});
 }}]});const component=require(dest).default;component.render=()=>null;return component;}
(async()=>{
 const editor=await compile('resources/js/Components/Calendar/PersonalTodoEditor.vue','form,busy,error,dirty,save,toggleStep,remove,addStep');
 let emitted=[];let app=renderer.createApp({render:()=>h(editor,{item:item(),today:'2026-11-17',onSaved:i=>emitted.push(i)})});app.mount({});await settle();let s=global.__state;
 patch=async(...a)=>{calls.push(a);return {data:{item:{...item(),steps:a[1].steps}}};};await s.toggleStep(0);assert.equal(calls[0][1].version,3);assert.equal(calls[0][1].steps[0].completed,true);assert.equal(calls[0][1].completed,undefined,'step must not complete parent');assert.equal(emitted.length,1);
 s.form.repeat_frequency='monthly';s.form.repeat_until='2027-12-31';s.form.start_date='2026-11-30';s.form.title='保持する変更';put=async()=>{throw {response:{status:409,data:{message:'競合しています'}}};};await s.save();assert.equal(s.form.title,'保持する変更');assert.equal(s.error.value,'競合しています');const before=calls.length;await s.toggleStep(0);assert.equal(calls.length,before,'dirty form step waits for explicit save');
 put=async(...a)=>{calls.push(a);return {data:{item:item()}};};await s.save();assert.equal(calls.at(-1)[1].start_date,'2026-11-30');assert.deepEqual(calls.at(-1)[1].repeat_rule,{frequency:'monthly',until_date:'2027-12-31'});assert.equal(calls.at(-1)[2].headers['X-CSRF-TOKEN'],'csrf-todo');global.confirm=()=>false;await s.remove();assert.equal(calls.length,before+1);global.confirm=()=>true;app.unmount();
 const page=await compile('resources/js/Pages/PersonalTodo/Index.vue','data,selected,dirty,busy,error,newTitle,quickAdd,choose,closeDetail,patch,settings,saveSettings,listForm,editList,moveList,saveList,pageDirty,load,notice');
 app=renderer.createApp({render:()=>h(page,{workspace:workspace(),back:{date:'2026-11-17',view:'week'}})});app.mount({});await settle();s=global.__state;
 s.newTitle.value='追加する';let finish;post=(...a)=>{calls.push(a);return new Promise(resolve=>finish=resolve);};const pending=s.quickAdd();const count=calls.length;await s.quickAdd();assert.equal(calls.length,count,'double submit blocked');finish({data:{item:item()}});await pending;assert.equal(s.selected.value.id,4);assert.equal(s.newTitle.value,'');
 s.newTitle.value='通信不明';post=async(...a)=>{calls.push(a);throw Error('network');};await s.quickAdd();const key=calls.at(-1)[1].request_key;await s.quickAdd();assert.equal(calls.at(-1)[1].request_key,key,'retry same request key');s.newTitle.value='別の入力';const n=calls.length;await s.quickAdd();assert.equal(calls.length,n,'uncertain create must not change payload');
 patch=async()=>({data:{item:{...item(),completed:true,repeat_result:{status:'next',id:9,start_date:'2026-11-18'}}}});await s.patch(item(),{},true);assert.ok(s.notice.value.includes('2026-11-18'));
 s.dirty.value=true;global.confirm=()=>false;await s.choose({id:9});assert.equal(s.selected.value.id,4,'unsaved input guarded');global.confirm=()=>true;s.dirty.value=false;
 let old;get=()=>new Promise(r=>old=r);const opening=s.choose({id:8});s.closeDetail();old({data:{item:{...item(),id:8}}});await opening;assert.equal(s.selected.value,null,'late response must not reopen closed detail');
 get=async(...a)=>{calls.push(a);return {data:{...workspace(),show_completed:true}};};s.data.value.show_completed=true;await s.load();assert.equal(calls.at(-1)[1].params.show_completed,1,'refresh preserves completed filter');app.unmount();
 app=renderer.createApp({render:()=>h(page,{workspace:workspace(),tab:'settings',back:{}})});app.mount({});await settle();s=global.__state;s.settings.sidebar_limit=20;assert.equal(s.pageDirty.value,true);put=async()=>{throw {response:{status:409,data:{message:'設定の競合'}}};};await s.saveSettings();assert.equal(s.settings.sidebar_limit,20);assert.equal(s.error.value,'設定の競合');assert.equal(s.pageDirty.value,true);app.unmount();
 app=renderer.createApp({render:()=>h(page,{workspace:workspace(),tab:'lists',back:{}})});app.mount({});await settle();s=global.__state;s.editList(s.data.value.lists[0]);s.listForm.name='編集途中';post=async()=>({data:{lists:workspace().lists.map(l=>({...l,version:2}))}});await s.moveList(s.data.value.lists[0],1);assert.equal(s.listForm.name,'編集途中');assert.equal(s.listForm.version,2,'own reorder refreshes version without losing draft');app.unmount();
 const util=await import('file://'+path.join(root,'resources/js/utils/personalTodoRecurrence.js'));assert.ok(util.personalTodoDeleteQuestion({repeat_has_next:true}).includes('次回分は残り'));assert.equal(util.personalTodoCompletionNotice({repeat_result:{status:'undone',retracted:false}}),'完了を取り消しました。');assert.ok(util.personalTodoCompletionNotice({repeat_result:{status:'ended'}}).includes('繰り返しは終了'));
 console.log('TODO actual Vue: independent steps, CSRF, conflict retention, delete confirmation, double submit, uncertain retry keys, dirty navigation, stale detail, completed filter, settings/list draft passed.');
})().catch(e=>{console.error(e);process.exitCode=1;});
