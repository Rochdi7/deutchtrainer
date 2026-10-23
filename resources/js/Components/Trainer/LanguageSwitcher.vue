<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { useI18n, LOCALE_NAMES } from '@/i18n';

const page = usePage();
const { locale, t } = useI18n();

// Twemoji flag SVGs (CC-BY 4.0) already shipped under public/images/vocab.
const flags = { de: '/images/vocab/1f1e9-1f1ea.svg', en: '/images/vocab/1f1ec-1f1e7.svg' };

function set(code) {
    if (code === locale.value) return;
    router.post(route('locale.update'), { locale: code }, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <div class="inline-flex gap-1 rounded-2xl border-2 border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-900" role="group" :aria-label="t('nav.language')">
        <button
            v-for="code in page.props.locales"
            :key="code"
            type="button"
            @click="set(code)"
            class="flex min-h-[36px] items-center gap-1.5 rounded-xl px-2 text-xs font-extrabold uppercase transition"
            :class="code === locale ? 'bg-brand-50 text-pop-primary dark:bg-brand-900/40' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
            :title="LOCALE_NAMES[code]"
            :aria-pressed="code === locale"
        >
            <img :src="flags[code]" :alt="LOCALE_NAMES[code]" class="h-5 w-5 rounded-sm" />
            <span class="hidden sm:inline">{{ code }}</span>
        </button>
    </div>
</template>
