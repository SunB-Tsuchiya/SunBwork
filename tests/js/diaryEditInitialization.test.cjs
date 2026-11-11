const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { build } = require('esbuild');
const { parse, compileScript } = require('@vue/compiler-sfc');
const { createRenderer, h, nextTick } = require('vue');

const sourcePath = path.resolve(__dirname, '../../resources/js/Pages/Diaries/Edit.vue');
const out = fs.mkdtempSync(path.join(os.tmpdir(), 'sb-diary-edit-'));
global.__diaryTimelineDates = [];
global.window = { addEventListener() {}, removeEventListener() {} };
global.localStorage = { getItem: () => null };

const mocked = {
    '@/Composables/useToasts': 'export default () => ({showToast(){},showValidationErrors(){}});',
    '@/Composables/useDiaryAttachments': 'export const useDiaryAttachments=()=>({attachments:[],previewModal:null,openPreview(){},closePreview(){},uploadAndStage(){},removeAttachment(){},attachmentIds(){return []}});',
    '@/Helpers/diaryTimelineEvents': 'export const diaryTimelineHours=()=>[];export const fetchDiaryTimelineEvents=async date=>{globalThis.__diaryTimelineDates.push(date);return {events:[],failed:false}};',
    '@inertiajs/vue3': 'import {reactive} from "vue";export const useForm=value=>reactive(value);export const usePage=()=>({props:{auth:{user:{id:1}}}});export const router={get(){}};',
    '@vueup/vue-quill': 'export const QuillEditor={};',
    '@vueup/vue-quill/dist/vue-quill.snow.css': '',
    axios: 'export default {put:async()=>{}};',
    'ziggy-js': 'export const route=name=>name;',
};

(async () => {
    await build({
        entryPoints: [sourcePath], outfile: path.join(out, 'edit.cjs'), bundle: true,
        platform: 'node', format: 'cjs', plugins: [{ name: 'diary-edit-mocks', setup(b) {
            b.onResolve({ filter: /^vue$/ }, () => ({ path: require.resolve('vue'), external: true }));
            b.onResolve({ filter: /^(?:@\/|@inertiajs\/vue3$|@vueup\/vue-quill|axios$|ziggy-js$)/ }, a => ({ path: a.path, namespace: 'mock' }));
            b.onLoad({ filter: /.*/, namespace: 'mock' }, a => ({ loader: 'js', contents: mocked[a.path] ?? 'export default {};' }));
            b.onLoad({ filter: /\.vue$/ }, a => {
                const source = fs.readFileSync(a.path, 'utf8').replace('</script>', '\nglobalThis.__diaryEditState={form};\n</script>');
                const { descriptor, errors } = parse(source);
                assert.equal(errors.length, 0);
                return { loader: 'js', contents: compileScript(descriptor, { id: 'diary-edit-init-test' }).content, resolveDir: path.dirname(a.path) };
            });
        } }],
    });
    const component = require(path.join(out, 'edit.cjs')).default;
    component.render = () => null;
    const renderer = createRenderer({ patchProp() {}, insert() {}, remove() {}, createElement: () => ({}), createText: () => ({}), createComment: () => ({}), setText() {}, setElementText() {}, parentNode() { return {}; }, nextSibling() { return null; } });
    const app = renderer.createApp({ render: () => h(component, { diary: { id: 1, date: '2026-11-11', attachments: [] }, worktypes: [] }) });
    assert.doesNotThrow(() => app.mount({}), 'diary edit must initialize its form before watching its date');
    await nextTick();
    assert.deepEqual(global.__diaryTimelineDates, ['2026-11-11']);
    global.__diaryEditState.form.date = '2026-11-12';
    await nextTick();
    assert.deepEqual(global.__diaryTimelineDates, ['2026-11-11', '2026-11-12']);
    app.unmount();
    console.log('Diary edit mounts and reloads its timeline after date changes');
})().catch(error => { console.error(error); process.exitCode = 1; });
