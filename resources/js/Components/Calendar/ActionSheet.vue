<script setup>
const props = defineProps({
    show:     { type: Boolean, default: false },
    date:     { type: String,  default: '' },
    startMin: { type: Number,  default: null },
    endMin:   { type: Number,  default: null },
});

defineEmits(['close', 'add-event', 'my-job', 'sheet-job', 'diary']);

function pad(n) { return String(n).padStart(2, '0'); }
function hm(min) { return `${pad(Math.floor(min / 60))}:${pad(min % 60)}`; }

function labelText() {
    if (!props.date) return '';
    const [, m, d] = props.date.split('-');
    const label = `${parseInt(m)}/${parseInt(d)}`;
    if (props.startMin != null && props.endMin != null) {
        return `${label}  ${hm(props.startMin)} – ${hm(props.endMin)}`;
    }
    return `${label} の操作`;
}

const ACTIONS = [
    { key: 'add-event', icon: '📅', label: '予定を追加',         color: 'border-emerald-400 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' },
    { key: 'my-job',    icon: '💼', label: 'マイジョブ登録',     color: 'border-indigo-400  bg-indigo-50  text-indigo-800  hover:bg-indigo-100'  },
    { key: 'sheet-job', icon: '📋', label: '進行表・管理表ジョブ', color: 'border-violet-400  bg-violet-50  text-violet-800  hover:bg-violet-100'  },
    { key: 'diary',     icon: '📝', label: '日報入力',            color: 'border-orange-400  bg-orange-50  text-orange-800  hover:bg-orange-100'  },
];
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2">
            <div v-if="show" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
                <div class="absolute inset-0 bg-black/30" @click="$emit('close')" />
                <div class="relative z-10 w-full max-w-xs mx-4 mb-6 sm:mb-0 overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-gray-200">
                    <!-- 日時ラベル -->
                    <div class="border-b border-gray-100 px-4 py-2.5 text-center">
                        <span class="text-sm font-semibold text-gray-700">{{ labelText() }}</span>
                    </div>

                    <!-- アクション一覧 -->
                    <div class="py-1">
                        <button
                            v-for="action in ACTIONS"
                            :key="action.key"
                            class="flex w-full items-center gap-3 border-l-4 px-5 py-3 text-left text-sm font-medium transition-colors"
                            :class="action.color"
                            @click="$emit(action.key)">
                            <span class="text-base leading-none">{{ action.icon }}</span>
                            {{ action.label }}
                        </button>
                    </div>

                    <div class="border-t border-gray-100" />
                    <button
                        class="w-full py-3 text-sm text-gray-400 hover:bg-gray-50 hover:text-gray-600 transition-colors"
                        @click="$emit('close')">
                        キャンセル
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
