const fs = require('fs');
const os = require('os');
const path = require('path');
const assert = require('node:assert/strict');
const { build } = require('esbuild');
const { parse, compileScript } = require('@vue/compiler-sfc');
const { createSSRApp } = require('vue');
const { renderToString } = require('vue/server-renderer');
const root = path.resolve(__dirname, '../..');
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'sb-work-nav-'));
global.route = name => name ? `/members/test/${name}` : {current: () => 'user.myjobbox.unified_preview'};
async function component(file) {
    const dest = path.join(output, path.basename(file) + '.cjs');
    await build({entryPoints:[path.join(root,file)],outfile:dest,bundle:true,platform:'node',format:'cjs',plugins:[{name:'vue-test',setup(b){
        b.onResolve({filter:/^vue$/}, a => ({path:require.resolve(a.path),external:true}));
        b.onResolve({filter:/^@\//}, a => ({path:path.join(root,'resources/js',a.path.slice(2)+(path.extname(a.path)?'':'.js'))}));
        b.onResolve({filter:/^@inertiajs\/vue3$/}, a => ({path:a.path,namespace:'mock'}));
        b.onLoad({filter:/.*/,namespace:'mock'}, () => ({loader:'js',contents:`const {h}=require('vue');export const usePage=()=>({props:global.__navProps});export const router={get(){}};export const Link={props:['href'],setup(p,{slots}){return ()=>h('a',{href:p.href},slots.default?.())}};`}));
        b.onLoad({filter:/\.vue$/}, a => {const {descriptor}=parse(fs.readFileSync(a.path,'utf8'),{filename:a.path});return {loader:'js',contents:compileScript(descriptor,{id:'nav-test',inlineTemplate:true}).content};});
    }}]});
    return require(dest).default;
}
(async()=>{
    const source=fs.readFileSync(path.join(root,'resources/js/utils/workHubNavigation.js'),'utf8');
    const {workHubMyJobRoute}=await import('data:text/javascript;base64,'+Buffer.from(source).toString('base64'));
    const trial={auth:{user:{user_role:'superadmin',company_id:2},companyType:'sunbrain',superAdminContextId:null},userWorkHub:{enabled:true,unregisteredRequests:3}};
    const old='user.myjobbox.index', preview='user.myjobbox.unified_preview';
    assert.equal(workHubMyJobRoute(trial),preview);
    assert.equal(workHubMyJobRoute({...trial,auth:{...trial.auth,superAdminContextId:'2'}}),preview);
    for(const props of [null,{}, {...trial,userWorkHub:{enabled:false}}, {...trial,auth:{...trial.auth,superAdminContextId:9}}, {...trial,auth:{...trial.auth,companyType:'general'}}, {...trial,auth:{...trial.auth,companyType:undefined}}, {...trial,auth:{...trial.auth,user:{user_role:'superadmin'}}}])assert.equal(workHubMyJobRoute(props),old);
    for(const role of ['user','coordinator','leader','clerk','admin'])assert.equal(workHubMyJobRoute({...trial,auth:{...trial.auth,user:{company_id:2,user_role:role}}}),old);
    const outer=await component('resources/js/Components/Tabs/UserNavigationTabs.vue');
    const inner=await component('resources/js/Components/Tabs/UserWorkHubTabs.vue');
    async function render(c,props){global.__navProps=props;const app=createSSRApp(c,{active:'myjob'});app.config.globalProperties.route=global.route;return renderToString(app);}
    let html=await render(outer,trial);
    assert.ok(html.includes(`value="/members/test/${preview}"`),'mobile hub points to preview');
    assert.ok(html.includes(`href="/members/test/${preview}"`),'desktop hub and inner tab point to preview');
    assert.ok(html.includes('仕事・案件'));assert.ok(html.includes('未登録の依頼 3件'));assert.ok(html.includes('aria-current="page"'));
    html=await render(inner,{...trial,auth:{...trial.auth,superAdminContextId:9}});assert.ok(html.includes(`href="/members/test/${old}"`));assert.ok(!html.includes(preview));
    html=await render(outer,{...trial,auth:{...trial.auth,user:{user_role:'user',company_id:2}},userWorkHub:{enabled:false}});assert.ok(html.includes(`href="/members/test/${old}"`));assert.ok(!html.includes(preview));
    html=await render(outer,{...trial,auth:{...trial.auth,companyType:'general'}});assert.ok(!html.includes(preview));assert.ok(!html.includes('仕事・案件'));
    const page=fs.readFileSync(path.join(root,'resources/js/Pages/MyJobBox/UnifiedPreview.vue'),'utf8');assert.ok(page.includes("route('user.myjobbox.index')"));
    console.log('Work hub navigation: trial desktop/mobile/inner targets, own and changed context, ordinary roles/company, active tab, inbox badge and legacy return preserved');
})().catch(e=>{console.error(e);process.exitCode=1;});
