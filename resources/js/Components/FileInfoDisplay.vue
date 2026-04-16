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
    pdf: 'PDF',
    ai: 'Illustrator (AI)',
    indd: 'InDesign (INDD)',
    indd_legacy: 'InDesign (旧形式)',
    docx: 'Word (DOCX)',
    eps: 'EPS',
    psd: 'Photoshop (PSD)',
    image: '画像',
    other: 'その他',
}
const PAGE_TYPES  = ['pdf', 'ai', 'indd', 'indd_legacy', 'docx', 'eps']
const IMAGE_TYPES = ['psd', 'image']

function groupLabel(type) { return GROUP_LABELS[type] ?? type }
function isPageType(type)  { return PAGE_TYPES.includes(type) }
function isImageType(type) { return IMAGE_TYPES.includes(type) }

function filesForType(type) {
    return (props.fileInfo?.files ?? []).filter(f => {
        const ext = f.ext ?? f.name?.split('.').pop()?.toLowerCase() ?? ''
        return guessType(ext) === type
    })
}

function guessType(ext) {
    if (ext === 'pdf') return 'pdf'
    if (ext === 'ai') return 'ai'
    if (ext === 'indd') return 'indd_legacy'
    if (['docx', 'doc'].includes(ext)) return 'docx'
    if (['psd', 'psb'].includes(ext)) return 'psd'
    if (['jpg', 'jpeg', 'png', 'gif', 'tiff', 'tif'].includes(ext)) return 'image'
    if (ext === 'eps') return 'eps'
    return 'other'
}

function formatBytes(bytes) {
    if (!bytes) return '-'
    if (bytes >= 1024 * 1024) return (bytes / 1024 / 1024).toFixed(1) + 'MB'
    if (bytes >= 1024) return (bytes / 1024).toFixed(0) + 'KB'
    return bytes + 'B'
}
</script>
