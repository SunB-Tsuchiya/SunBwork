<script setup>
import { ref } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '例：2026/12/05' },
    id: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const nativePicker = ref(null);

function openPicker() {
    nativePicker.value?.showPicker?.();
}

// YYYY/MM/DD → YYYY-MM-DD（native input 用）
function toNative(val) {
    if (!val || val.length < 10) return '';
    return val.replace(/\//g, '-');
}

function onNativeChange(e) {
    const v = e.target.value; // YYYY-MM-DD
    if (!v) { emit('update:modelValue', ''); return; }
    emit('update:modelValue', v.replace(/-/g, '/'));
}

function onInput(e) {
    // 数字とスラッシュ以外を除去
    let val = e.target.value.replace(/[^\d/]/g, '');
    emit('update:modelValue', val);
}

function resolveYear(month, day) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const y = today.getFullYear();
    return new Date(y, month - 1, day) >= today ? y : y + 1;
}

function onBlur(e) {
    const raw = e.target.value.trim();
    if (!raw) return;

    // 数字のみ抽出して桁数で分岐
    const digits = raw.replace(/\D/g, '');

    // 8桁 YYYYMMDD
    if (digits.length === 8) {
        const year  = parseInt(digits.slice(0, 4), 10);
        const month = parseInt(digits.slice(4, 6), 10);
        const day   = parseInt(digits.slice(6, 8), 10);
        if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
            emit('update:modelValue', `${year}/${String(month).padStart(2,'0')}/${String(day).padStart(2,'0')}`);
            return;
        }
    }

    // 4桁 MMDD → 年を自動補完
    if (digits.length === 4) {
        const month = parseInt(digits.slice(0, 2), 10);
        const day   = parseInt(digits.slice(2, 4), 10);
        if (month >= 1 && month <= 12 && day >= 1 && day <= 31) {
            const year = resolveYear(month, day);
            emit('update:modelValue', `${year}/${String(month).padStart(2,'0')}/${String(day).padStart(2,'0')}`);
            return;
        }
    }

    // MM/DD → 年を自動補完
    const mmdd = raw.match(/^(\d{1,2})\/(\d{1,2})$/);
    if (mmdd) {
        const month = parseInt(mmdd[1], 10);
        const day   = parseInt(mmdd[2], 10);
        if (month < 1 || month > 12 || day < 1 || day > 31) return;
        const year = resolveYear(month, day);
        emit('update:modelValue', `${year}/${String(month).padStart(2,'0')}/${String(day).padStart(2,'0')}`);
        return;
    }

    // YYYY/MM/DD → ゼロ埋め正規化
    const full = raw.match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/);
    if (full) {
        const mm = String(parseInt(full[2], 10)).padStart(2, '0');
        const dd = String(parseInt(full[3], 10)).padStart(2, '0');
        emit('update:modelValue', `${full[1]}/${mm}/${dd}`);
    }
}
</script>

<template>
    <div class="relative flex items-center">
        <input
            :id="id"
            :value="modelValue"
            type="text"
            :placeholder="placeholder"
            class="w-full rounded border border-gray-300 px-3 py-2 pr-9 text-sm focus:border-green-600 focus:outline-none"
            @input="onInput"
            @blur="onBlur"
        />
        <button
            type="button"
            tabindex="-1"
            class="absolute right-2 text-gray-400 hover:text-green-600"
            @click="openPicker"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </button>
        <!-- ネイティブ日付ピッカー（透明・呼び出し用） -->
        <input
            ref="nativePicker"
            type="date"
            :value="toNative(modelValue)"
            class="pointer-events-none absolute h-0 w-0 opacity-0"
            tabindex="-1"
            @change="onNativeChange"
        />
    </div>
</template>
