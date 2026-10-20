<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { useI18n, LOCALE_NAMES } from '@/i18n';

const page = usePage();
const { locale, t } = useI18n();

function set(code) {
    if (code === locale.value) return;
    router.post(route('locale.update'), { locale: code }, { preserveScroll: true, preserveState: false });
}
</script>

<template>
    <div class="inline-flex overflow-hidden rounded-xl ring-1 ring-gray-300 dark:ring-gray-700" role="group" :aria-label="t('nav.language')">
        <button
            v-for="code in page.props.locales"
            :key="code"
            type="button"
            @click="set(code)"
            class="min-h-[40px] px-2.5 text-xs font-bold uppercase tracking-wide transition"
            :class="code === locale ? 'bg-brand-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
            :title="LOCALE_NAMES[code]"
            :aria-pressed="code === locale"
        >
            {{ code }}
        </button>
    </div>
</template>
