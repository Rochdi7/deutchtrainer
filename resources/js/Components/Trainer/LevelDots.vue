<script setup>
defineProps({ level: { type: Number, default: 0 } });
</script>

<template>
    <span class="inline-flex items-center gap-0.5" :title="`Level ${level}/5`" aria-label="Lernlevel">
        <span
            v-for="i in 5"
            :key="i"
            class="h-1.5 w-1.5 rounded-full"
            :class="i <= level ? (level >= 4 ? 'bg-emerald-500' : 'bg-brand-500') : 'bg-gray-300 dark:bg-gray-700'"
        />
    </span>
</template>
