const fs=require('fs');const path=require('path');const os=require('os');const assert=require('node:assert/strict');
const {build}=require('esbuild');const {parse,compileScript}=require('@vue/compiler-sfc');
const {ref,createRenderer,createSSRApp,h}=require('vue');const {renderToString}=require('vue/server-renderer');
const root=path.resolve(__dirname,'../..');const out=fs.mkdtempSync(path.join(os.tmpdir(),'sb-details-'));
let calls=[],fail=false;
global.__detailsAxios={put:async(...args)=>{calls.push(args);if(fail)throw{response:{status:409,data:{message:'他の画面で更新されました'}}};return{data:{id:7,details_version:1}};}};
global.__detailsAnalyzer=()=>{const results=ref([]),analyzing=ref(false),rejectedFiles=ref([]);return{results,analyzing,rejectedFiles,analyzeFiles:async files=>{results.value.push(...files.map(f=>({name:f.name,ext:'pdf',type:'pdf',size:f.size,pages:3})));},buildFileInfo:()=>results.value.length?{files:results.value.map(({analyzing,...f})=>f),groups:{pdf:{count:results.value.length,pages:3,size_bytes:100}},total_files:results.value.length,total_pages:3,total_size_bytes:100}:null};};
async function component(name,behavior=false){const outfile=path.join(out,path.basename(name)+(behavior?'.behavior':'.ssr')+'.cjs');await build({entryPoints:[path.join(root,name)],outfile,bundle:true,platform:'node',format:'cjs',plugins:[{name:'vue',setup(b){
 b.onResolve({filter:/^vue$/},()=>({path:require.resolve('vue'),external:true}));
 b.onResolve({filter:/^(axios|ziggy-js)$/},a=>({path:a.path,namespace:'mock'}));
 b.onResolve({filter:/useFileAnalyzer$/},()=>({path:'analyzer',namespace:'mock'}));
 b.onLoad({filter:/.*/,namespace:'mock'},a=>({loader:'js',contents:a.path==='axios'?'export default globalThis.__detailsAxios;':a.path==='analyzer'?'export const useFileAnalyzer=globalThis.__detailsAnalyzer;':'export const route=(name,params)=>name+JSON.stringify(params);'}));
 b.onResolve({filter:/^@\//},a=>({path:path.join(root,'resources/js',a.path.slice(2))}));
 b.onLoad({filter:/\.vue$/},a=>{let source=fs.readFileSync(a.path,'utf8');if(behavior&&a.path===path.join(root,name))source=source.replace('</script>','\nglobalThis.__details={save,selected,note,error,saving,results,analyzing,emit};\n</script>');const {descriptor}=parse(source);return{loader:'js',contents:compileScript(descriptor,{id:'details',inlineTemplate:!behavior,templateOptions:{ssr:!behavior}}).content,resolveDir:path.dirname(a.path)};});
}}]});const result=require(outfile).default;if(behavior)result.render=()=>null;return result;}
const renderer=createRenderer({patchProp(){},insert(){},remove(){},createElement:()=>({}),createText:()=>({}),createComment:()=>({}),setText(){},setElementText(){},parentNode(){return{};},nextSibling(){return null;}});
(async()=>{
 const help=await component('resources/js/Components/WorkFileInfoHelp.vue');
 const old=await renderToString(createSSRApp(help,{legacyQuantity:true}));const fresh=await renderToString(createSSRApp(help));
 for(const html of [old,fresh])for(const text of ['ファイル本体はサーバーに保存・共有されません','ダウンロードすることはできません','100MB','保存するとジョブに登録'])assert.ok(html.includes(text),text);
 assert.ok(old.includes('<details'));assert.ok(old.includes('<summary'));assert.ok(!/<details[^>]*\sopen[\s=>]/.test(old));assert.ok(!fresh.includes('<details'));
 assert.ok(old.includes('数量（ページ数またはファイル数）に自動反映'));assert.ok(fresh.includes('登録済みの実績数量・予定時刻は、自動で変更しません'));
 const oldForm=fs.readFileSync(path.join(root,'resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue'),'utf8');assert.ok(oldForm.includes('<WorkFileInfoHelp legacy-quantity />'));assert.ok(!oldForm.includes('再アップロードすると更新'));
 const editor=await component('resources/js/Components/WorkJobDetailsEditor.vue',true);let saved=0,cancelled=0;
 const work={id:7,details_version:4,detail_note:'保存済み',actual_quantity:90,file_info:{files:[{name:'以前.pdf',ext:'pdf',type:'pdf',size:50,pages:2}]}};
 const app=renderer.createApp({render:()=>h(editor,{work,onSaved:()=>saved++,onCancel:()=>cancelled++})});app.mount({});let s=global.__details;
 assert.equal(s.results.value[0].name,'以前.pdf');assert.equal(calls.length,0);s.emit('cancel');assert.equal(cancelled,1);assert.equal(calls.length,0);
 await s.selected({target:{files:[{name:'追加.pdf',size:100}],value:'picked'}});assert.equal(s.results.value.length,2);
 const priorCount=s.results.value.length;await s.selected({target:{files:Array.from({length:51},()=>({name:'多数.pdf',size:100})),value:'picked'}});assert.equal(s.results.value.length,priorCount);assert.ok(s.error.value.includes('50件'));
 work.details_version=9; // A concurrent page refresh must not advance this editor's original version.
 s.note.value='変更した説明';fail=true;await s.save();assert.equal(saved,0);assert.equal(s.note.value,'変更した説明');assert.equal(s.results.value.length,2);assert.ok(s.error.value.includes('他の画面'));
 fail=false;await s.save();assert.equal(saved,1);const [url,data]=calls.at(-1);assert.equal(url,'user.myjobbox.entries.details{"entry":7}');assert.equal(data.details_version,4);assert.equal(data.file_info.files.length,2);assert.equal(data.detail_note,'変更した説明');assert.ok(!('actual_quantity'in data));assert.ok(!('completed_at'in data));assert.equal(work.actual_quantity,90);
 const count=calls.length;s.analyzing.value=true;await s.save();assert.equal(calls.length,count);s.analyzing.value=false;
 s.results.value=[];s.note.value='';await s.save();assert.equal(calls.at(-1)[1].file_info,null);app.unmount();
 const rendered=await component('resources/js/Components/WorkJobDetailsEditor.vue');const html=await renderToString(createSSRApp(rendered,{work}));for(const text of ['詳細入力','作業の説明','対象ファイルを選択','一覧から取り除く','詳細を保存','キャンセル','以前.pdf'])assert.ok(html.includes(text),text);
 console.log('Details Vue: old/new help, existing file information, selection, same-ID PUT, metadata-only payload, failure retention, analysis guard, cancel, clear passed');
})().catch(e=>{console.error(e);process.exitCode=1;});
