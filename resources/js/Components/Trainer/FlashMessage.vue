<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const visible = ref(true);
const flash = computed(() => page.props.flash || {});

watch(
    () => [flash.value.success, flash.value.error],
    () => {
        visible.value = true;
        setTimeout(() => (visible.value = false), 6000);
    },
    { immediate: true },
);
</script>

<template>
    <div v-if="visible && (flash.success || flash.error)" class="mx-auto max-w-6xl px-4 pt-4 sm:px-6 lg:px-8">
        <div
            class="flex items-start justify-between gap-3 rounded-xl px-4 py-3 text-sm"
            :class="flash.error ? 'bg-red-50 text-red-800 ring-1 ring-red-200 dark:bg-red-950/50 dark:text-red-200 dark:ring-red-900' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-200 dark:ring-emerald-900'"
            role="status"
        >
            <span>{{ flash.error || flash.success }}</span>
            <button type="button" class="font-bold" @click="visible = false" aria-label="Schließen">×</button>
        </div>
    </div>
</template>
