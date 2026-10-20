<script setup>
defineProps({
    label: String,
    value: [String, Number],
    sub: { type: String, default: '' },
    accent: { type: String, default: 'brand' },
});

const colors = {
    brand: 'text-brand-600 dark:text-brand-400',
    emerald: 'text-emerald-600 dark:text-emerald-400',
    amber: 'text-amber-600 dark:text-amber-400',
    rose: 'text-rose-600 dark:text-rose-400',
    purple: 'text-purple-600 dark:text-purple-400',
};
</script>

<template>
    <div class="card p-4">
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ label }}</div>
        <div class="mt-1 text-3xl font-extrabold tabular-nums" :class="colors[accent] || colors.brand">{{ value }}</div>
        <div v-if="sub" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ sub }}</div>
    </div>
</template>
