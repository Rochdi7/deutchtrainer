<script setup>
import { computed, ref } from 'vue';

/** Vocabulary image with a graceful fallback (initial letter tile) when no image exists or it fails to load. */
const props = defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg | xl
    label: { type: String, default: '' },
});

const failed = ref(false);
const sizes = { sm: 'h-10 w-10', md: 'h-16 w-16', lg: 'h-28 w-28', xl: 'h-40 w-40' };
const cls = computed(() => sizes[props.size] || sizes.md);
const url = computed(() => (props.src ? (props.src.startsWith('http') || props.src.startsWith('/') ? props.src : '/' + props.src) : null));
const initial = computed(() => (props.label || props.alt || '?').replace(/^(der|die|das|der\/die)\s+/i, '').charAt(0).toUpperCase());
</script>

<template>
    <div class="grid shrink-0 place-items-center rounded-2xl bg-gray-100 p-2 dark:bg-gray-800" :class="cls">
        <img v-if="url && !failed" :src="url" :alt="alt" class="h-full w-full object-contain" loading="lazy" decoding="async" @error="failed = true" />
        <span v-else class="text-2xl font-bold text-gray-400" aria-hidden="true">{{ initial }}</span>
    </div>
</template>
