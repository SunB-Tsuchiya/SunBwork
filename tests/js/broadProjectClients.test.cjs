const fs = require('fs');
const os = require('os');
const path = require('path');
const assert = require('node:assert/strict');
const { build } = require('esbuild');
const { parse, compileScript } = require('@vue/compiler-sfc');
const { createRenderer, createSSRApp, h, reactive } = require('vue');
const { renderToString } = require('vue/server-renderer');
const root = path.resolve(__dirname, '../..');
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'sb-broad-clients-'));
const calls = [];
global.document = {addEventListener() {}, removeEventListener() {}};
global.route = (name, params) => name + JSON.stringify(params || {});
global.__broadForm = (initial) => Object.assign(reactive(initial), {
    errors: {}, processing: false, put: (url, options) => calls.push(['put', url, options]), post: (url) => calls.push(['post', url]), reset() {}, clearErrors() {this.errors={};}, setError(k,v) {this.errors[k]=v;},
});
global.__broadAxios = { get: async () => ({data: [{id: 2, name: 'Beta'}]}) };
async function component(file, behavior = false) {
    const dest = path.join(output, path.basename(file) + behavior + '.cjs');
    await build({entryPoints: [path.join(root, file)], outfile: dest, bundle: true, platform: 'node', format: 'cjs', plugins: [{name: 'vue-test', setup(b) {
        b.onResolve({filter: /^vue$/}, a => ({path: require.resolve(a.path), external: true}));
        b.onResolve({filter: /^@\//}, a => ({path: path.join(root, 'resources/js', a.path.slice(2) + (path.extname(a.path) ? '' : '.js'))}));
        b.onResolve({filter: /^(axios|ziggy-js|@inertiajs\/vue3)$/}, a => ({path: a.path, namespace: 'mock'}));
        b.onLoad({filter: /.*/, namespace: 'mock'}, a => ({loader: 'js', contents: a.path === 'axios' ? 'export default global.__broadAxios;' : a.path === 'ziggy-js' ? 'export const route=global.route;' : 'export const useForm=global.__broadForm;'}));
        b.onLoad({filter: /\.vue$/}, a => {
            let source = fs.readFileSync(a.path, 'utf8');
            if (behavior && a.path.endsWith('BroadProjectForm.vue')) source = source.replace('</script>', '\nglobal.__broadState={form,selectedClients,clientSearch,chooseClient,removeClient,searchClients,save,cancel,inlineSaving,inlineError,similarProjects};\n</script>');
            if (behavior) source = source.replace(/<template>[\s\S]*<\/template>/, '<template><div /></template>');
            const {descriptor} = parse(source, {filename: a.path});
            if (behavior) descriptor.template.content = '<div />';
            return {loader: 'js', contents: compileScript(descriptor, {id: 'broad-test', inlineTemplate: true}).content};
        });
    }}]});
    return require(dest).default;
}
const renderer = createRenderer({createElement: () => ({addEventListener() {}, removeEventListener() {}, tagName: 'INPUT'}), createText: () => ({}), createComment: () => ({}), setText() {}, setElementText() {}, patchProp() {}, insert() {}, remove() {}, parentNode() {}, nextSibling() {}});
(async () => {
    const form = await component('resources/js/Pages/SuperAdmin/BroadProjects/BroadProjectForm.vue', true);
    const app = renderer.createApp({render: () => h(form, {project: {id: 7, name: 'Calendar', clients: [{id: 1, name: 'Alpha'}]}, routePrefix: 'user.work.broad_projects'})});
    app.mount({}); const state = global.__broadState;
    assert.deepEqual([...state.form.client_ids], [1]);
    state.clientSearch.value = 'Beta'; await state.searchClients();
    assert.deepEqual([...state.form.client_ids], [1]);
    state.chooseClient({id: 2, name: 'Beta'}); state.chooseClient({id: 2, name: 'Beta'});
    assert.deepEqual([...state.form.client_ids], [1, 2]);
    state.save(); assert.equal(calls[0][1], 'user.work.broad_projects.update{"broadProject":7}');
    assert.ok(!('client_id' in state.form)); assert.ok(!('parent_id' in state.form));
    state.removeClient(1); assert.deepEqual([...state.form.client_ids], [2]);
    state.removeClient(2); assert.deepEqual([...state.form.client_ids], []); app.unmount();
    const events=[];
    const inlineApp=renderer.createApp({render:()=>h(form,{inlineCreate:true,allowStatus:false,initialName:'検索した名前',scope:{company_id:1,department_id:1},projects:[{id:1,parent_id:null,name:'大',selectable:true}],onSaved:p=>events.push(p),onCancel:()=>events.push('cancel')})});
    global.document.querySelector=()=>({getAttribute:()=> 'csrf-test'});inlineApp.mount({});
    const inline=global.__broadState;assert.equal(inline.form.name,'検索した名前');assert.equal(inline.form.group_type,'middle');assert.equal(inline.form.parent_id,null);
    inline.form.parent_id=1;inline.form.fiscal_year=2027;inline.form.client_ids=[2];
    let resolvePost;const posts=[];global.__broadAxios.post=(...args)=>{posts.push(args);return new Promise(resolve=>{resolvePost=resolve;});};
    const pending=inline.save();await inline.save();assert.equal(posts.length,1);assert.equal(posts[0][0],'user.work.broad_projects.quick_store{}');assert.equal(posts[0][2].headers['X-CSRF-TOKEN'],'csrf-test');
    assert.deepEqual(posts[0][1].scope,{company_id:1,department_id:1});assert.equal(posts[0][1].parent_id,1);
    resolvePost({data:{project:{id:8,selectable:true},scope:{company_id:1,department_id:1}}});await pending;assert.equal(events[0].id,8);assert.equal(inline.inlineSaving.value,false);
    global.__broadAxios.post=async()=>{throw {response:{data:{message:'同名があります',errors:{name:['同名の分類があります']}}}};};
    await inline.save();assert.equal(inline.form.name,'検索した名前');assert.equal(inline.form.errors.name,'同名の分類があります');assert.equal(inline.form.fiscal_year,2027);assert.deepEqual([...inline.form.client_ids],[2]);assert.equal(events.length,1);
    global.__broadAxios.post=async()=>{throw Error('network');};await inline.save();assert.ok(inline.inlineError.value.includes('入力は残っています'));
    const previousConfirm=global.confirm;inline.form.isDirty=true;global.confirm=()=>false;inline.cancel();assert.equal(events.length,1);global.confirm=()=>true;inline.cancel();assert.equal(events.at(-1),'cancel');global.confirm=previousConfirm;
    inlineApp.unmount();
    const actualForm = await component('resources/js/Pages/SuperAdmin/BroadProjects/BroadProjectForm.vue');
    const formHtml = await renderToString(createSSRApp(actualForm, {project: {id:7,name:'Calendar',clients:[{id:1,name:'Alpha'}, {id:2,name:'Beta'}]}}));
    assert.ok(formHtml.includes('Alpha')); assert.ok(formHtml.includes('Beta')); assert.ok(formHtml.includes('複数選択可'));
    const display = await component('resources/js/Components/ProjectJobs/BroadProjectClients.vue');
    const html = await renderToString(createSSRApp(display, {clients: [{id:1,name:'Alpha'}, {id:2,name:'<Beta>'}, {id:3,name:'Gamma'}]}));
    assert.ok(html.includes('ほか1社')); assert.ok(html.includes('<details')); assert.ok(html.includes('Gamma')); assert.ok(html.includes('&lt;Beta&gt;'));
    const picker = await component('resources/js/Components/ProjectJobs/BroadProjectPicker.vue');
    const pickerHtml = await renderToString(createSSRApp(picker, {modelValue:7, options:[{id:7,label:'Calendar',active:true,clients:[{id:1,name:'Alpha'}]}]}));
    assert.ok(pickerHtml.includes('Alpha')); assert.ok(pickerHtml.includes('クライアント名'));
    console.log('Broad clients Vue: existing selection, search retention, deduplication, explicit clear, named route, metadata payload, disclosure and escaped client names passed');
})().catch(e => {console.error(e); process.exitCode = 1;});
