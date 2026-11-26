const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const root=path.resolve(__dirname,'../..'),vue=require(root+'/node_modules/vue');
const {parse,compileScript}=require(root+'/node_modules/@vue/compiler-sfc'),{build}=require(root+'/node_modules/esbuild');
const out=fs.mkdtempSync(path.join(os.tmpdir(),'diary-review-vue-'));
global.route=()=>'/synthetic';global.sessionStorage={getItem:()=>null};
global.window={location:{href:''}};global.document={addEventListener(){},removeEventListener(){}};
const passthrough=`import {h} from 'vue';export default{setup(p,{slots}){return()=>h('div',Object.values(slots).flatMap(s=>s()))}};`;
const requests=[];global.__diaryRequests=requests;
async function component(file,expose,template=true){
 let source=fs.readFileSync(path.join(root,file),'utf8').replace('</script>',`globalThis.__diaryHarness={${expose}};\n</script>`);
 const code=compileScript(parse(source).descriptor,{id:'diary-test',inlineTemplate:template}).content;
 const outfile=path.join(out,file.replaceAll('/','_')+'.cjs');
 await build({stdin:{contents:code,resolveDir:root,loader:'js'},outfile,bundle:true,platform:'node',format:'cjs',plugins:[{name:'diary-fixtures',setup(b){
  b.onResolve({filter:/^vue$/},()=>({path:root+'/node_modules/vue/index.js',external:true}));
  b.onResolve({filter:/\.vue$/},()=>({path:'component',namespace:'mock'}));
  b.onResolve({filter:/^(axios|ziggy-js)$/},a=>({path:a.path,namespace:'mock'}));
  b.onResolve({filter:/^@\//},a=>{let p=path.join(root,'resources/js',a.path.slice(2));return{path:fs.existsSync(p)?p:p+'.js'}});
  b.onLoad({filter:/.*/,namespace:'mock'},a=>({loader:'js',contents:a.path==='component'?passthrough:a.path==='ziggy-js'?`export const route=name=>name;`:`export default{get:(url,options)=>new Promise((resolve,reject)=>globalThis.__diaryRequests.push({url,options,resolve,reject}))};`}));
 }}]});let c=require(outfile).default;if(!template)c.render=()=>null;return c;
}
async function flush(){await Promise.resolve();await Promise.resolve();await vue.nextTick();await Promise.resolve();await vue.nextTick();}
function mount(c, props) {
    const make = (tag, text='') => ({tag,text,children:[],props:{},addEventListener(){},removeEventListener(){},getAttribute(){return null}});
    const renderer = vue.createRenderer({
        createElement:tag=>make(tag),createText:text=>make('#text',text),createComment:()=>make('#comment'),
        insert(child,parent,anchor){child.parent=parent;const old=parent.children.indexOf(child);if(old>=0)parent.children.splice(old,1);const i=parent.children.indexOf(anchor);parent.children.splice(i<0?parent.children.length:i,0,child);},
        remove(n){if(n.parent){const i=n.parent.children.indexOf(n);n.parent.children.splice(i,1)}},
        patchProp(n,k,p,v){n.props[k]=v;},setText(n,text){n.text=text},setElementText(n,text){n.text=text;n.children=[]},parentNode:n=>n.parent,nextSibling(n){return n.parent?.children[n.parent.children.indexOf(n)+1]},
    });
    const host=make('root'), app=renderer.createApp({render:()=>vue.h(c,props)});app.config.globalProperties.$page={props:{auth:{user:{id:1},featureFlags:{proofRequest:false}}}};app.mount(host);
    return {host,app};
}
function nodes(n) {return [n,...n.children.flatMap(nodes)];}
function text(n){return n.text+n.children.map(text).join('');}
(async()=>{
 const page=await component('resources/js/Pages/Diaries/Interactions/Show.vue','events,timelineLoadFailed,timelineStartHour,timelineEndHour');
 const work={id:'personal-work-10',title:'対象者の作業',start:'2026-11-26T06:30:00+09:00',end:'2026-11-26T22:15:00+09:00',readOnly:true,color:'#4F46E5'};
 const props=vue.reactive({diary:{id:1,user_id:200,date:'2026-11-26',content:'本文',comments:[]},diaryWorkEvents:[work],personalWorkEnabled:true,routePrefix:'admin'});
 const view=mount(page,props),state=global.__diaryHarness;
 assert.equal(requests.length,1);assert.equal(requests[0].url,'events.index');assert.equal(requests[0].options.params.user_id,200);assert.equal(requests[0].options.params.date,'2026-11-26');
 requests[0].resolve({data:[{id:1,title:'【完了】旧形式',start:'2026-11-26T09:00:00+09:00',end:'2026-11-26T10:00:00+09:00'}]});await flush();
 assert.equal(state.events.value.length,2);assert.equal(state.events.value[0].title,'旧形式');assert.equal(state.events.value[1].id,work.id);assert.equal(state.events.value[1].readOnly,true);
 assert.equal(state.timelineStartHour.value,6);assert.equal(state.timelineEndHour.value,23);assert.equal(state.timelineLoadFailed.value,false);
 props.diary={...props.diary,id:2,user_id:201};props.diaryWorkEvents=[{...work,id:'personal-work-11'}];await vue.nextTick();const stale=requests.at(-1);assert.equal(stale.options.params.user_id,201);
 props.diary={...props.diary,id:3,user_id:202};props.diaryWorkEvents=[{...work,id:'personal-work-12'}];await vue.nextTick();const current=requests.at(-1);
 current.resolve({data:[]});await flush();assert.deepEqual(state.events.value.map(e=>e.id),['personal-work-12']);
 stale.resolve({data:[{id:999,title:'前の人',start:work.start,end:work.end}]});await flush();assert.deepEqual(state.events.value.map(e=>e.id),['personal-work-12']);
 props.diary={...props.diary,id:4,user_id:203};await vue.nextTick();requests.at(-1).reject(Error('synthetic event failure'));await flush();assert.equal(state.timelineLoadFailed.value,true);assert.equal(state.events.value[0].id,'personal-work-12');
 assert(!requests.some(r=>r.url==='schedule.department_work.mine'));view.app.unmount();
 const timeline=await component('resources/js/Components/TimelineDiary.vue','onEventClick',false);
 const opened=[];const table=mount(timeline,{date:'2026-11-26',events:[work],editable:false,onOpenEdit:p=>opened.push(p)});
 global.__diaryHarness.onEventClick(work,{});assert.equal(opened.length,0);
 global.__diaryHarness.onEventClick({id:1,title:'旧形式'},{});assert.equal(opened.length,1);table.app.unmount();
 console.log('PASS: actual diary Vue merges authorized target work/old events, never fetches viewer work, preserves JST/range/readOnly, rejects stale responses and shows load failure.');
})().catch(e=>{console.error(e);process.exitCode=1});
