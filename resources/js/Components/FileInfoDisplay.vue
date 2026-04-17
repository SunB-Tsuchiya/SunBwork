<template>
    <div v-if="fileInfo" class="rounded border border-green-200 bg-green-50 p-4">
        <h3 class="mb-2 font-semibold text-green-800">作業ファイル一覧</h3>

        <!-- 合計サマリー -->
        <div class="mb-3 text-sm font-medium text-green-900">
            合計: {{ fileInfo.total_files }}ファイル
            <template v-if="fileInfo.total_pages"> / {{ fileInfo.total_pages }}ページ</template>
            / {{ formatBytes(fileInfo.total_size_bytes) }}
        </div>

        <!-- 種別ごとのテーブル -->
        <div v-for="(group, type) in fileInfo.groups" :key="type" class="mt-3">
            <div class="mb-1 text-xs font-semibold text-gray-700">
                ── {{ groupLabel(type) }}（{{ group.count }}ファイル
                <template v-if="group.pages"> / {{ group.pages }}ページ</template>
                / {{ formatBytes(group.size_bytes) }}）
            </div>
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th class="px-2 py-1">ファイル名</th>
                        <template v-if="isPageType(type)">
                            <th class="px-2 py-1">ページ数</th>
                            <th class="px-2 py-1">ドキュメントサイズ</th>
                        </template>
                        <template v-else-if="isImageType(type)">
                            <th class="px-2 py-1">幅×高さ(px)</th>
                            <th class="px-2 py-1">カラー</th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(file, fi) in filesForType(type)" :key="fi" class="border-b border-gray-100">
                        <td class="px-2 py-1">{{ file.name }}</td>
                        <template v-if="isPageType(type)">
                            <td class="px-2 py-1">{{ file.pages != null ? file.pages + 'p' : '-' }}</td>
                            <td class="px-2 py-1">{{ file.doc_size ?? '-' }}</td>
                        </template>
                        <template v-else-if="isImageType(type)">
                            <td class="px-2 py-1">{{ file.width && file.height ? `${file.width}×${file.height}` : '-' }}</td>
                            <td class="px-2 py-1">{{ file.extra ?? '-' }}</td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    fileInfo: { type: Object, default: null },
})

const GROUP_LABELS = {
    pdf:         'PDF',
    ai:          'Illustrator (AI)',
    indd:        'InDesign (INDD)',
    indd_legacy: 'InDesign (旧形式)',
    idml:        'InDesign (IDML)',
    docx:        'Word (DOCX)',
    pptx:        'PowerPoint (PPTX)',
    eps:         'EPS',
    xlsx:        'Excel (XLSX)',
    svg:         'SVG',
    html:        'HTML',
    font:        'フォント',
    raw:         'RAWデータ',
    video:       '動画',
    psd:         'Photoshop (PSD)',
    image:       '画像',
    code:        'コード / テキスト',
    other:       'その他',
}
const PAGE_TYPES  = ['pdf', 'ai', 'indd', 'indd_legacy', 'idml', 'docx', 'pptx', 'eps']
const IMAGE_TYPES = ['psd', 'image']

function groupLabel(type) { return GROUP_LABELS[type] ?? type }
function isPageType(type)  { return PAGE_TYPES.includes(type) }
function isImageType(type) { return IMAGE_TYPES.includes(type) }

// ext → 可能なtype一覧（inddはCS4+/legacyどちらにも対応）
const CODE_EXTS = [
    'css','scss','sass','less',
    'js','mjs','cjs','jsx',
    'ts','mts','cts','tsx',
    'vue','svelte','astro',
    'php','rb','py','pyw','go','rs','java','cs','swift','kt','kts',
    'c','cpp','cc','cxx','h','hpp','hxx','m','mm',
    'sh','bash','zsh','fish','ps1','bat','cmd',
    'json','json5','jsonc','yaml','yml','toml','ini','env','conf','cfg','properties',
    'xml','md','mdx','rst','txt','csv','tsv',
    'sql','graphql','gql','dockerfile','tf','tfvars','hcl',
    'lock','gitignore','editorconfig','prettierrc','eslintrc',
]
const EXT_TYPE_MAP = {
    pdf:  ['pdf'],
    ai:   ['ai'],
    indd: ['indd', 'indd_legacy'],   // マジックバイトで判別できないため両候補
    idml: ['idml'],
    docx: ['docx'], doc: ['docx'],
    pptx: ['pptx'], ppt: ['pptx'],
    eps:  ['eps'],
    xlsx: ['xlsx'], xls: ['xlsx'], xlsm: ['xlsx'],
    svg:  ['svg'],
    html: ['html'], htm: ['html'],
    otf:  ['font'], ttf: ['font'], woff: ['font'], woff2: ['font'], eot: ['font'],
    arw:  ['raw'], nef: ['raw'], cr2: ['raw'], cr3: ['raw'], dng: ['raw'],
    raf:  ['raw'], orf: ['raw'], rw2: ['raw'], raw: ['raw'],
    mp4:  ['video'], mov: ['video'], avi: ['video'], mkv: ['video'],
    wmv:  ['video'], flv: ['video'], m4v: ['video'], webm: ['video'],
    psd:  ['psd'], psb: ['psd'],
    jpg:  ['image'], jpeg: ['image'], png: ['image'], gif: ['image'],
    tiff: ['image'], tif: ['image'], bmp: ['image'], webp: ['image'],
    ico:  ['image'], heic: ['image'], heif: ['image'],
    ...Object.fromEntries(CODE_EXTS.map(e => [e, ['code']])),
    // video entries after spread to prevent mts being overwritten by CODE_EXTS
    mts:  ['video'], m2ts: ['video'],
}

function filesForType(type) {
    return (props.fileInfo?.files ?? []).filter(f => {
        if (f.type) return f.type === type
        const ext = f.ext ?? f.name?.split('.').pop()?.toLowerCase() ?? ''
        return (EXT_TYPE_MAP[ext] ?? ['other']).includes(type)
    })
}

function formatBytes(bytes) {
    if (!bytes) return '-'
    if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + 'MB'
    if (bytes >= 1024) return (bytes / 1024).toFixed(0) + 'KB'
    return bytes + 'B'
}
</script>
