const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const root=path.resolve(__dirname,'../..'),vue=require(root+'/node_modules/vue');
const {parse,compileScript}=require(root+'/node_modules/@vue/compiler-sfc'),{build}=require(root+'/node_modules/esbuild');
const out=fs.mkdtempSync(path.join(os.tmpdir(),'transport-date-vue-'));
global.Document=class {};global.ShadowRoot=class {};global.route=name=>name;global.document={addEventListener(){},removeEventListener(){},querySelector(){return{getAttribute(){return'synthetic-token'}}}};
global.window={addEventListener(){},removeEventListener(){},location:{href:''}};
global.__transportAuto=[];global.__transportPosts=[];
async function component(){
 let source=fs.readFileSync(root+'/resources/js/Pages/Billing/Transport/Apply/Index.vue','utf8').replace('</script>','globalThis.__transportHarness={billingDate,loadFromProps,payload,runAutosave,save,autosaveState,submitting};\n</script>');
 const code=compileScript(parse(source).descriptor,{id:'transport-date-test',inlineTemplate:true}).content;
 const outfile=path.join(out,'apply.cjs');await build({stdin:{contents:code,resolveDir:root,loader:'js'},outfile,bundle:true,platform:'node',format:'cjs',plugins:[{name:'transport-mocks',setup(b){
 b.onResolve({filter:/^vue$/},()=>({path:root+'/node_modules/vue/index.js',external:true}));
 b.onResolve({filter:/\.vue$/},()=>({path:'component',namespace:'mock'}));
 b.onResolve({filter:/^(axios|@inertiajs\/vue3)$/},a=>({path:a.path,namespace:'mock'}));
 b.onResolve({filter:/^@\//},a=>{let p=path.join(root,'resources/js',a.path.slice(2));return{path:fs.existsSync(p)?p:p+'.js'}});
 b.onLoad({filter:/.*/,namespace:'mock'},a=>({loader:'js',contents:a.path==='component'?`import {h}from'vue';export default{setup(p,{slots}){return()=>h('div',Object.values(slots).flatMap(s=>s()))}};`:a.path==='axios'?`export default{post:(url,body,options)=>new Promise((resolve,reject)=>globalThis.__transportAuto.push({url,body,options,resolve,reject}))};`:`export const router={on:()=>()=>{},post:(url,body,options)=>globalThis.__transportPosts.push({url,body,options}),get(){}};`}));
 }}]});return require(outfile).default;
}
async function flush(){await Promise.resolve();await Promise.resolve();await vue.nextTick();}
function mount(c, props) {
    const make = (tag, text='') => ({tag,text,children:[],options:[],props:{},addEventListener(){},removeEventListener(){},getAttribute(){return null},getRootNode(){return global.document}});
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
 const page=await component();
 const item={occurrence_date:'2026-11-19',destination:'訪問先',purpose:'outbound',station_from:'駅A',station_to:'駅B',fare_type:'ic',amount:200,base_amount:200};
 const props=vue.reactive({month:'2026-11',months:['2026-11'],periodStart:'2026-11-01',periodEnd:'2026-11-30',defaultBillingDate:'2026-11-20',purposes:{outbound:'往路'},statusLabels:{draft:'下書き',submitted:'申請済み'},expense:{id:1,status:'draft',billing_date:'2026-11-20',items:[item],events:[]},canEdit:true});
 const view=mount(page,props),state=global.__transportHarness;
 assert.equal(state.billingDate.value,'2026-11-20');state.billingDate.value='2026-11-30';await vue.nextTick();
 assert.equal(state.payload().billing_date,'2026-11-30');const input=nodes(view.host).find(n=>n.props.id==='transport-application-date');assert(input);assert.equal(input.value,'2026-11-30');assert.equal(input.props.min,'2026-11-01');assert.equal(input.props.max,'2026-11-30');
 assert(text(view.host).includes('最終営業日（土日祝を除く）'));assert(text(view.host).includes('実際に送信した日時は別'));
 const auto=state.runAutosave();assert.equal(global.__transportAuto.length,1);assert.equal(global.__transportAuto[0].body.billing_date,'2026-11-30');
 state.billingDate.value='2026-11-27';await vue.nextTick();const manual=state.save();assert.equal(global.__transportPosts.length,0);assert(state.submitting.value);
 global.__transportAuto[0].resolve({data:{id:1,status:'draft',saved_at:'10:00'}});await auto;await manual;await flush();
 assert.equal(global.__transportPosts.length,1);const post=global.__transportPosts[0];assert.equal(post.body.billing_date,'2026-11-27');assert.equal(state.billingDate.value,'2026-11-27');
 post.options.onError({billing_date:'対象月の日付を選んでください。'});post.options.onFinish();await flush();assert.equal(state.billingDate.value,'2026-11-27');assert(text(view.host).includes('対象月の日付'));
 props.expense={...props.expense,billing_date:'2026-11-27',status:'submitted'};props.canEdit=false;await flush();assert.equal(state.billingDate.value,'2026-11-27');assert(input.props.disabled);
 view.app.unmount();console.log('PASS: actual transport date input, monthly bounds/future date, autosave payload, waiting for in-flight save, validation preservation and read-only state.');
})().catch(e=>{console.error(e);process.exitCode=1});
