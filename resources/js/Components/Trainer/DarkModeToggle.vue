<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from '@/i18n';

const { t } = useI18n();
const dark = ref(false);

function apply(value) {
    dark.value = value;
    document.documentElement.classList.toggle('dark', value);
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', value ? '#111827' : '#1b57f5');
    try {
        localStorage.setItem('theme', value ? 'dark' : 'light');
    } catch (e) {}
}

onMounted(() => {
    dark.value = document.documentElement.classList.contains('dark');
});
</script>

<template>
    <button
        type="button"
        @click="apply(!dark)"
        class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
        :aria-label="dark ? t('nav.theme_light') : t('nav.theme_dark')"
        :title="dark ? t('nav.theme_light') : t('nav.theme_dark')"
    >
        <svg v-if="dark" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="4" />
            <path stroke-linecap="round" d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41m11.32-11.32 1.41-1.41" />
        </svg>
        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79Z" />
        </svg>
    </button>
</template>
