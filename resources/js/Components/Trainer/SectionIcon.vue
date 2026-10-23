<script setup>
/** White line icons for lesson-path nodes and section tabs (inline SVG, consistent on every OS). */
defineProps({ name: { type: String, required: true } });
</script>

<template>
    <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <template v-if="name === 'objectives'">
            <circle cx="16" cy="16" r="11" />
            <circle cx="16" cy="16" r="6" />
            <circle cx="16" cy="16" r="1.5" fill="currentColor" />
        </template>
        <template v-else-if="name === 'vocab'">
            <rect x="5" y="7" width="22" height="18" rx="3" />
            <circle cx="12" cy="13" r="2.2" />
            <path d="m6 22 6-6 5 5 3-3 6 6" />
        </template>
        <template v-else-if="name === 'grammar'">
            <path d="M6 20 20 6l6 6L12 26H6v-6Z" />
            <path d="m16 10 2 2M13 13l2 2M10 16l2 2" />
        </template>
        <template v-else-if="name === 'dialog'">
            <path d="M6 8a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-8l-6 5v-5H9a3 3 0 0 1-3-3V8Z" />
            <path d="M11 11h10M11 15h6" />
        </template>
        <template v-else-if="name === 'reading'">
            <path d="M16 9c-2-2-5-3-10-3v18c5 0 8 1 10 3 2-2 5-3 10-3V6c-5 0-8 1-10 3Z" />
            <path d="M16 9v18" />
        </template>
        <template v-else-if="name === 'exercises'">
            <path d="m7 25 1-5L21 7l4 4L12 24l-5 1Z" />
            <path d="m18 10 4 4" />
        </template>
        <template v-else-if="name === 'revision'">
            <path d="M24 13a8.5 8.5 0 0 0-15.5-3M8 19a8.5 8.5 0 0 0 15.5 3" />
            <path d="M24 6v7h-7M8 26v-7h7" />
        </template>
        <template v-else-if="name === 'test'">
            <path d="M8 27V5" />
            <path d="M8 6h15l-3 5 3 5H8" />
        </template>
        <template v-else-if="name === 'trophy'">
            <path d="M10 5h12v7a6 6 0 0 1-12 0V5Z" />
            <path d="M10 8H6a4 4 0 0 0 4 6M22 8h4a4 4 0 0 1-4 6M16 18v5M11 27h10M13 23h6" />
        </template>
        <template v-else>
            <circle cx="16" cy="16" r="4" fill="currentColor" />
        </template>
    </svg>
</template>
