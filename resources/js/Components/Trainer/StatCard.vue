<script setup>
defineProps({
    label: String,
    value: [String, Number],
    sub: { type: String, default: '' },
    accent: { type: String, default: 'brand' },
    icon: { type: String, default: '' },
});

const colors = {
    brand: 'text-pop-primary',
    emerald: 'text-pop-green',
    amber: 'text-pop-orange',
    rose: 'text-pop-red',
    purple: 'text-pop-purple',
    blue: 'text-pop-blue',
};
</script>

<template>
    <div class="card flex items-center gap-3 p-4">
        <div v-if="icon" class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gray-100 text-2xl dark:bg-gray-800">{{ icon }}</div>
        <div class="min-w-0">
            <div class="truncate text-[11px] font-extrabold uppercase tracking-wide text-gray-400">{{ label }}</div>
            <div class="text-2xl font-black tabular-nums" :class="colors[accent] || colors.brand">{{ value }}</div>
            <div v-if="sub" class="truncate text-xs font-bold text-gray-400">{{ sub }}</div>
        </div>
    </div>
</template>
