const fs=require('fs'),path=require('path'),os=require('os'),assert=require('node:assert/strict');
const {parse,compileScript}=require('@vue/compiler-sfc');
const {build}=require('esbuild');
const {createRenderer,h,reactive,nextTick}=require('vue');
const root=path.resolve(__dirname,'../..');
const output=fs.mkdtempSync(path.join(os.tmpdir(),'sb-diary-advice-'));
const pending=[];
globalThis.__diaryAdviceAxios={get:(url,options)=>new Promise((resolve,reject)=>pending.push({url,options,resolve,reject}))};
(async()=>{
 const file=path.join(root,'resources/js/Components/Diaries/DiaryWorkReminder.vue');
 const source=fs.readFileSync(file,'utf8').replace('</script>','\nglobalThis.__diaryAdvice={activeReminder,loadError};\n</script>');
 const script=compileScript(parse(source).descriptor,{id:'diary-advice'}).content;
 const outfile=path.join(output,'advice.cjs');
 await build({stdin:{contents:script,resolveDir:path.dirname(file),loader:'js'},outfile,bundle:true,platform:'node',format:'cjs',external:['vue'],plugins:[{name:'mocks',setup(b){
 b.onResolve({filter:/^vue$/},()=>({path:require.resolve('vue'),external:true}));
 b.onResolve({filter:/^axios$/},()=>({path:'axios',namespace:'mock'}));
 b.onLoad({filter:/.*/,namespace:'mock'},()=>({contents:'export default globalThis.__diaryAdviceAxios;',loader:'js'}));
 }}]});
 const Component=require(outfile).default;Component.render=()=>null;
 const renderer=createRenderer({createElement:()=>({}),createText:()=>({}),createComment:()=>({}),insert(){},remove(){},setText(){},setElementText(){},patchProp(){},parentNode(){},nextSibling(){}});
 const props=reactive({date:'2026-11-13',reminderUrl:'/members/diaries/work-reminder'});
 const app=renderer.createApp({render:()=>h(Component,props)});app.mount({});
 const settle=async()=>{await nextTick();await Promise.resolve();await nextTick();};
 assert.equal(pending[0].options.params.date,'2026-11-13');
 props.date='2026-11-12';await settle();assert.ok(pending[0].options.signal.aborted);
 pending[1].resolve({data:{reminder:{date:'2026-11-12',issues:[{code:'other_without_myjob'}]}}});await settle();
 pending[0].resolve({data:{reminder:{date:'2026-11-13'}}});await settle();
 assert.equal(globalThis.__diaryAdvice.activeReminder.value.date,'2026-11-12');
 props.date='2026-11-11';await settle();assert.equal(globalThis.__diaryAdvice.activeReminder.value,null);
 pending[2].resolve({data:{reminder:null}});await settle();assert.equal(globalThis.__diaryAdvice.activeReminder.value,null);
 props.date='2026-11-10';await settle();pending[3].reject(new Error('offline'));await settle();assert.equal(globalThis.__diaryAdvice.loadError.value,true);
 props.date='2026-11-09';await settle();app.unmount();assert.ok(pending[4].options.signal.aborted);
 // Render the actual template with a fixed reminder fixture, independent of its async fetch.
 const {createSSRApp}=require('vue');const {renderToString}=require('vue/server-renderer');
 const fixtureSource=fs.readFileSync(file,'utf8').replace('</script>','\nactiveReminder.value=props.reminder;\n</script>');
 const fixtureScript=compileScript(parse(fixtureSource).descriptor,{id:'diary-advice-render',inlineTemplate:true}).content;
 const renderFile=path.join(output,'advice-render.cjs');
 await build({stdin:{contents:fixtureScript,resolveDir:path.dirname(file),loader:'js'},outfile:renderFile,bundle:true,platform:'node',format:'cjs',external:['vue'],plugins:[{name:'render-mocks',setup(b){
 b.onResolve({filter:/^vue$/},()=>({path:require.resolve('vue'),external:true}));b.onResolve({filter:/^axios$/},()=>({path:'axios',namespace:'mock'}));b.onLoad({filter:/.*/,namespace:'mock'},()=>({contents:'export default globalThis.__diaryAdviceAxios;',loader:'js'}));
 }}]});
 const RenderComponent=require(renderFile).default;
 const html=await renderToString(createSSRApp(RenderComponent,{date:'2026-11-17',reminder:{issues:[{code:'long_work_overlaps_appointment',title:'1日をまとめた作業枠をご確認ください',bullets:['7時間以上の作業枠','必要な項目だけ確認']}],missing_minutes:60,scheduled_minutes:420,recorded_minutes:360}}));
 assert.match(html,/<strong[^>]*font-bold[^>]*>このままでも日報を保存できます。<\/strong>/);
 assert.ok(html.indexOf('このままでも日報を保存できます。')<html.indexOf('1日をまとめた作業枠'));
 assert.ok(html.includes('<li>7時間以上の作業枠</li>'));assert.ok(html.includes('記録なし：1時間'));assert.ok(!html.includes('この日の作業記録を確認してください'));
 console.log('Diary advice: target date, stale response rejection, no-warning reset, load failure, unmount cancellation passed');
})().catch(e=>{console.error(e);process.exitCode=1});
