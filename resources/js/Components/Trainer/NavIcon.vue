<script setup>
/** Colourful, rounded navigation icons (inline SVG, no external assets). */
defineProps({ name: { type: String, required: true } });
</script>

<template>
    <svg viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <template v-if="name === 'learn'">
            <path d="M4 10 16 4l12 6-12 6L4 10Z" fill="#7c5cff" />
            <path d="M8 13.5V21c0 2 4 4 8 4s8-2 8-4v-7.5l-8 4-8-4Z" fill="#6243d6" />
            <path d="M27 11v8" stroke="#6243d6" stroke-width="2.5" stroke-linecap="round" />
        </template>
        <template v-else-if="name === 'book'">
            <rect x="6" y="5" width="20" height="22" rx="3" fill="#4f6df5" />
            <rect x="9" y="5" width="3" height="22" fill="#3b55d6" />
            <path d="M15 11h7M15 15h7M15 19h5" stroke="#fff" stroke-width="2" stroke-linecap="round" />
        </template>
        <template v-else-if="name === 'target'">
            <circle cx="16" cy="16" r="12" fill="#f0506e" />
            <circle cx="16" cy="16" r="8" fill="#fff" />
            <circle cx="16" cy="16" r="4" fill="#f0506e" />
        </template>
        <template v-else-if="name === 'repeat'">
            <path d="M8 13a8 8 0 0 1 14-4l2 2" stroke="#9b6bf2" stroke-width="3.5" stroke-linecap="round" />
            <path d="M24 19a8 8 0 0 1-14 4l-2-2" stroke="#9b6bf2" stroke-width="3.5" stroke-linecap="round" />
            <path d="M24 6v6h-6" stroke="#7f4fd6" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M8 26v-6h6" stroke="#7f4fd6" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
        </template>
        <template v-else-if="name === 'bolt'">
            <path d="M18 3 7 18h8l-1 11 11-15h-8l1-11Z" fill="#f6b73c" />
            <path d="M18 3 7 18h8l-1 11" stroke="#d99a1e" stroke-width="1.5" stroke-linejoin="round" />
        </template>
        <template v-else-if="name === 'chart'">
            <rect x="5" y="17" width="6" height="10" rx="1.5" fill="#4f6df5" />
            <rect x="13" y="10" width="6" height="17" rx="1.5" fill="#1fb57a" />
            <rect x="21" y="5" width="6" height="22" rx="1.5" fill="#fb8c4b" />
        </template>
        <template v-else-if="name === 'user'">
            <circle cx="16" cy="11" r="6" fill="#fb8c4b" />
            <path d="M5 28a11 11 0 0 1 22 0Z" fill="#fdb380" />
        </template>
        <template v-else-if="name === 'upload'">
            <rect x="5" y="19" width="22" height="8" rx="2" fill="#a1a1aa" />
            <path d="M16 5v12M11 10l5-5 5 5" stroke="#3f3f46" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />
        </template>
        <template v-else>
            <circle cx="16" cy="16" r="10" fill="#e4e4e7" />
        </template>
    </svg>
</template>
