<script setup>
import NSystemDemoLayout from '@/layouts/NSystemDemoLayout.vue'
import { ref, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps({
    school: Object,
    daimons: Array,
    tab: String,
    mode: String,
    subjectLabels: Object,
    availableSubjects: Array,
    highlightTerms: Array,
    isGuest: Boolean,
})

// ---- Scroll-aware fixed nav ----
const navRef = ref(null)
const fixedNavVisible = ref(false)
let lastScrollY = 0

function handleScroll() {
    const y = window.scrollY
    const isUp = y < lastScrollY
    const navOutOfView = navRef.value ? navRef.value.getBoundingClientRect().bottom < 0 : false

    fixedNavVisible.value = navOutOfView && isUp
    lastScrollY = y
}

// ---- Tab / mode helpers ----
function tabHref(code) {
    return route('n-demo.school', { id: props.school.id, tab: code, mode: props.mode })
}

function modeHref(m) {
    return route('n-demo.school', { id: props.school.id, tab: props.tab, mode: m })
}

const BADGE = {
    '共学': 'bg-emerald-100 text-emerald-800',
    '男子': 'bg-blue-100 text-blue-800',
    '女子': 'bg-pink-100 text-pink-800',
    '地方': 'bg-gray-100 text-gray-600',
}

function badgeClass(cat) {
    return BADGE[cat] ?? 'bg-gray-100 text-gray-600'
}

function tabClass(code, compact = false) {
    const active = props.tab === code
    const available = props.availableSubjects.includes(code)
    if (compact) {
        return [
            'rounded px-3 py-1 text-xs transition-colors',
            active ? 'bg-[#1a3a6b] text-white'
                : available ? 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                : 'pointer-events-none cursor-default bg-gray-50 text-gray-300',
        ]
    }
    return [
        'rounded-t-md border border-b-0 px-5 py-1.5 text-sm transition-colors',
        active ? 'border-[#1a3a6b] bg-[#1a3a6b] text-white'
            : available ? 'border-gray-300 bg-gray-100 text-gray-700 hover:bg-gray-200'
            : 'pointer-events-none cursor-default border-gray-200 bg-gray-50 text-gray-300',
    ]
}

function modeClass(m, compact = false) {
    const active = props.mode === m
    if (compact) {
        return ['rounded px-3 py-1 text-xs transition-colors', active ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']
    }
    return [
        'rounded border px-4 py-1 text-sm transition-colors',
        active ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-gray-300 bg-gray-50 text-gray-700 hover:bg-gray-100',
    ]
}

// ---- Search-term highlight ----
function applyHighlight() {
    nextTick(() => {
        const hash = decodeURIComponent(window.location.hash.slice(1))
        const target = hash ? document.getElementById(hash) : null
        if (!target || !props.highlightTerms?.length) return
        props.highlightTerms.forEach(term => highlightInNode(target, term))
        requestAnimationFrame(() => target.scrollIntoView({ block: 'start' }))
    })
}

function highlightInNode(container, term) {
    const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            if (!node.nodeValue.trim()) return NodeFilter.FILTER_REJECT
            if (node.parentElement?.closest('script,style,mark,rt,rp')) return NodeFilter.FILTER_REJECT
            return NodeFilter.FILTER_ACCEPT
        },
    })
    const nodes = []
    let text = ''
    while (walker.nextNode()) {
        nodes.push({ node: walker.currentNode, start: text.length })
        text += walker.currentNode.nodeValue
    }
    const haystack = text.toLocaleLowerCase('ja')
    const needle = term.toLocaleLowerCase('ja')
    if (!needle) return
    const matches = []
    let pos = 0
    while ((pos = haystack.indexOf(needle, pos)) !== -1) {
        matches.push({ start: pos, end: pos + needle.length })
        pos += needle.length
    }
    nodes.forEach(({ node, start }) => {
        const end = start + node.nodeValue.length
        const segments = matches
            .filter(m => m.start < end && m.end > start)
            .map(m => ({ start: Math.max(0, m.start - start), end: Math.min(node.nodeValue.length, m.end - start) }))
            .sort((a, b) => b.start - a.start)
        segments.forEach(({ start: s, end: e }) => {
            const after = node.splitText(e)
            const matched = node.splitText(s)
            const mark = document.createElement('mark')
            mark.className = 'ndemo-search-hit'
            matched.parentNode.insertBefore(mark, matched)
            mark.appendChild(matched)
            if (!after.nodeValue) after.remove()
        })
    })
}

onMounted(() => {
    lastScrollY = window.scrollY
    window.addEventListener('scroll', handleScroll, { passive: true })
    if (props.highlightTerms?.length) applyHighlight()
})

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
})
</script>

<template>
    <NSystemDemoLayout :title="`${school.name} ${school.year}年度`" :is-guest="isGuest">

        <a :href="route('n-demo.index')" class="mb-4 inline-block text-sm text-[#1a3a6b] hover:underline">
            ← 学校一覧に戻る
        </a>

        <!-- School header -->
        <div class="mb-5 rounded-lg border border-gray-200 bg-white px-5 py-4">
            <h2 class="text-xl font-bold text-[#1a3a6b]">{{ school.name }}</h2>
            <div class="mt-1 flex items-center gap-3 text-sm text-gray-500">
                <span :class="['inline-block rounded-full px-2 py-0.5 text-xs font-semibold', badgeClass(school.category)]">
                    {{ school.category }}
                </span>
                <span>{{ school.year }}年度</span>
                <span>Nコード {{ school.code }}</span>
            </div>
        </div>

        <!-- In-flow navigation (normal scroll position) -->
        <div ref="navRef" class="mb-4">
            <div class="flex gap-1">
                <a
                    v-for="(label, code) in subjectLabels"
                    :key="code"
                    :href="tabHref(code)"
                    :class="tabClass(code)"
                >{{ label }}</a>
            </div>
            <div class="flex gap-2 border-t-2 border-[#1a3a6b] pt-3">
                <a :href="modeHref('Q')" :class="modeClass('Q')">問題</a>
                <a :href="modeHref('A')" :class="modeClass('A')">解答</a>
            </div>
        </div>

        <!-- Fixed navigation: 上スクロール時のみスライドイン -->
        <Transition name="slide-nav">
            <div
                v-if="fixedNavVisible"
                class="fixed inset-x-0 top-0 z-40 border-b border-gray-200 bg-white/95 px-4 py-2 shadow-md backdrop-blur-sm"
            >
                <div class="mx-auto flex max-w-[1100px] flex-wrap items-center gap-x-4 gap-y-1.5">
                    <span class="text-sm font-semibold text-[#1a3a6b]">{{ school.name }}</span>
                    <div class="flex gap-1">
                        <a
                            v-for="(label, code) in subjectLabels"
                            :key="code"
                            :href="tabHref(code)"
                            :class="tabClass(code, true)"
                        >{{ label }}</a>
                    </div>
                    <div class="flex gap-1">
                        <a :href="modeHref('Q')" :class="modeClass('Q', true)">問題</a>
                        <a :href="modeHref('A')" :class="modeClass('A', true)">解答</a>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Content: empty state -->
        <div
            v-if="daimons.length === 0"
            class="rounded-lg border border-gray-200 bg-white px-6 py-10 text-center text-gray-400"
        >
            {{ subjectLabels[tab] }} の{{ mode === 'Q' ? '問題' : '解答' }}データがありません
        </div>

        <!-- Content: daimons -->
        <template v-else>
            <div
                v-for="daimon in daimons"
                :key="daimon.id"
                :id="`daimon-${daimon.daimon_index}`"
                class="mb-5 scroll-mt-5 rounded-lg border border-gray-200 bg-white px-6 py-5"
            >
                <div class="mb-2 text-xs text-gray-400">大問 {{ daimon.daimon_index }}</div>
                <div class="ndemo-body" v-html="daimon.body_html" />
            </div>
        </template>

    </NSystemDemoLayout>
</template>

<style scoped>
/* Fixed nav のスライドアニメーション */
.slide-nav-enter-active,
.slide-nav-leave-active {
    transition: transform 0.22s ease;
}
.slide-nav-enter-from,
.slide-nav-leave-to {
    transform: translateY(-100%);
}

/* v-html 内のコンテンツ */
:deep(.ndemo-body img) {
    max-width: 100%;
    height: auto;
}

:deep(.ndemo-search-hit) {
    background: #fde047;
    color: #111827;
    padding: 1px 2px;
    border-radius: 3px;
    box-shadow: 0 0 0 1px rgba(202, 138, 4, 0.2);
}

:deep([id^='daimon-']:target) {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    animation: target-highlight 2.5s ease-out;
}

@keyframes target-highlight {
    from { background: #dbeafe; }
    to   { background: #fff; }
}
</style>
