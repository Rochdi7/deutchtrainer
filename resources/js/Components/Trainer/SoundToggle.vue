<script setup>
import { useI18n } from '@/i18n';
import { useSounds } from '@/composables/useSounds';

const { t } = useI18n();
const { enabled, setEnabled } = useSounds();
</script>

<template>
    <button
        type="button"
        data-no-sound
        @click="setEnabled(!enabled)"
        class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
        :aria-label="enabled ? t('sound.off') : t('sound.on')"
        :aria-pressed="enabled"
        :title="enabled ? t('sound.off') : t('sound.on')"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 5 6 9H2v6h4l5 4V5Z" />
            <template v-if="enabled"><path d="M15.54 8.46a5 5 0 0 1 0 7.07" /><path d="M19.07 4.93a10 10 0 0 1 0 14.14" /></template>
            <path v-else d="m23 9-6 6m0-6 6 6" />
        </svg>
    </button>
</template>
