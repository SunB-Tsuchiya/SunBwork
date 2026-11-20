const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const root = path.resolve(__dirname, '../..');
const vue = require(root + '/node_modules/vue');
const { parse, compileScript } = require(root + '/node_modules/@vue/compiler-sfc');
const { build } = require(root + '/node_modules/esbuild');
const out = fs.mkdtempSync(path.join(os.tmpdir(), 'progress-stage-vue-'));
global.route = () => '/synthetic-route';
global.sessionStorage = { getItem: () => null };
const stub = `import {h} from 'vue'; export default {setup(p,{slots}) {return () => h('div', Object.values(slots).flatMap(s=>s()));}};`;
async function component(file, expose = '', template = false) {
    let source = fs.readFileSync(path.join(root, file), 'utf8');
    if (expose) source = source.replace('</script>', `globalThis.__stageHarness = {${expose}};\n</script>`);
    const {descriptor} = parse(source);
    const code = compileScript(descriptor, {id:'stage-test', inlineTemplate:template}).content;
    const outfile = path.join(out, file.replaceAll('/', '_') + '.cjs');
    await build({stdin:{contents:code,resolveDir:root,loader:'js'},outfile,bundle:true,platform:'node',format:'cjs',plugins:[{name:'isolated-components',setup(b){
        b.onResolve({filter:/^vue$/},()=>({path:root+'/node_modules/vue/index.js',external:true}));
        b.onResolve({filter:/\.vue$/},()=>({path:'component',namespace:'stub'}));
        b.onResolve({filter:/^(@inertiajs\/vue3|ziggy-js|axios)$/},a=>({path:a.path,namespace:'stub'}));
        b.onResolve({filter:/^@\//},a=>{
            const p=path.join(root,'resources/js',a.path.slice(2));
            return {path:fs.existsSync(p)?p:p+'.js'};
        });
        b.onLoad({filter:/.*/,namespace:'stub'},a=>({loader:'js',contents:a.path==='component'?stub:a.path==='@inertiajs/vue3'?`import {h} from 'vue'; export const Link={setup(p,{slots}){return()=>h('a',slots.default?.())}}; export const router={reload(){},visit(){},get(){}};export const usePage=()=>({props:{auth:{user:{id:1}},user:{id:1}}});`:a.path==='ziggy-js'?`export const route=()=>'/synthetic-route';`:`export default {post:async()=>({data:{}})};`}));
    }}]});
    const c = require(outfile).default;
    if (!template) c.render = () => null;
    return c;
}
function mount(c, props) {
    const make = (tag, text='') => ({tag,text,children:[],props:{}});
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
    const config=[{key:'initial',children:[{key:'worker',type:'worker'},{key:'proof',type:'proof_v2'}]}];
    const cells=vue.reactive([1,2,3].map(id=>({id,row_id:id,col_key:'worker',assignment_id:id,assignment_completed:true,completed_at:id===3?'2026-11-19 17:00:00':null})));
    const rows=[1,2,3].map(id=>({id}));
    const table=await component('resources/js/Components/ProgressTable.vue','rowCompletionMap,groupProgressMap');
    const t=mount(table,{columnConfig:config,cells,rows});
    const state=global.__stageHarness;
    assert.deepEqual(state.rowCompletionMap.value[1],{done:0,total:2});
    assert.deepEqual(state.groupProgressMap.value.initial,{done:1,total:6});
    const show=fs.readFileSync(root+'/resources/js/Pages/Coordinator/ProgressSheets/Show.vue','utf8');
    const helper=fs.readFileSync(root+'/resources/js/utils/progressStage.js','utf8').replace('export function','function');
    const begin=show.indexOf('function collectAllLeaves('),end=show.indexOf('\n});',show.indexOf('const sheetCompletion',begin))+4;
    const count=new Function('computed','props','localCells',helper+'\n'+show.slice(begin,end)+'\nreturn sheetCompletion.value;');
    assert.deepEqual(count(vue.computed,{sheet:{column_config:config},rows},vue.ref(cells)),{done:1,total:6});
    cells[0].completed_at='2026-11-20 10:00:00';cells[1].completed_at='2026-11-20 10:01:00';
    await vue.nextTick();
    assert.equal(state.groupProgressMap.value.initial.done,3);
    assert.equal(count(vue.computed,{sheet:{column_config:config},rows},vue.ref(cells)).done,3);
    t.app.unmount();
    const cell=await component('resources/js/Components/ProgressCell.vue','workerCellBg,workerCellBorder,proofCellCompleted',true);
    let v=mount(cell,{cell:{id:1,assignment_id:1,assignment_completed:true,value_user_id:1},rowId:1,colDef:{key:'worker',type:'worker'},authUserId:1});
    assert.notEqual(global.__stageHarness.workerCellBg.value,'bg-green-50');
    assert(text(v.host).includes('工程未確定'));assert(text(v.host).includes('完了にする'));
    v.app.unmount();
    v=mount(cell,{cell:{id:1,completed_at:'2026-11-20 10:00:00'},rowId:1,colDef:{key:'worker',type:'worker'}});
    assert.equal(global.__stageHarness.workerCellBg.value,'bg-green-50');v.app.unmount();
    v=mount(cell,{cell:{assignment_completed:true},rowId:1,colDef:{key:'proof',type:'proof_v2'}});
    assert.equal(global.__stageHarness.proofCellCompleted.value,false);v.app.unmount();
    const detail=await component('resources/js/Pages/MyJobBox/WorkDetail.vue','',true);
    const props=vue.reactive({work:{id:1,completed:true,work_date:'2026-11-19'},time:{planned:60},series:{operationsEnabled:true,stagePending:true,items:[],completionTargets:[]}});
    v=mount(detail,props);
    let button=nodes(v.host).find(n=>n.tag==='button'&&text(n)==='工程完了を確定');assert(button);assert(!button.props.disabled);
    button.props.onClick();await vue.nextTick();assert(text(v.host).includes('進行表の工程と完了通知に反映します。'));
    props.series.stagePending=false;await vue.nextTick();assert(!nodes(v.host).some(n=>n.tag==='button'&&text(n)==='工程完了を確定'));v.app.unmount();
    const old=await component('resources/js/Pages/MyJobBox/Show.vue','',true);
    v=mount(old,{projectJob:{id:1},assignment:{id:1,completed:true,user:{id:1}},stagePending:true});
    button=nodes(v.host).find(n=>n.tag==='button'&&text(n)==='工程完了を確定');assert(button);assert(!button.props.disabled);v.app.unmount();
    const eventDetail=await component('resources/js/Pages/Events/Show.vue','',true);
    v=mount(eventDetail,{event:{id:1,project_job_assignment_id:1,project_job_assignment:{id:1,completed:true}},stage_pending:true});
    button=nodes(v.host).find(n=>n.tag==='button'&&text(n)==='工程完了を確定');assert(button);assert(!button.props.disabled);v.app.unmount();
    console.log('PASS: actual Vue stage colors/body/row/group/sheet counts, proof distinction and completed-work confirmation button.');
})().catch(e=>{console.error(e);process.exitCode=1});
