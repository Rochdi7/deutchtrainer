<script setup>
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import { useI18n } from '@/i18n';

defineProps({
    verbs: { type: Array, required: true }, // words with .conjugation
});
const { t } = useI18n();

const persons = [
    ['ich', 'ich'],
    ['du', 'du'],
    ['er_sie_es', 'er/sie/es'],
    ['wir', 'wir'],
    ['ihr', 'ihr'],
    ['sie_Sie', 'sie/Sie'],
];
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="v in verbs" :key="v.id" class="card p-3">
            <div class="mb-2 flex items-baseline justify-between gap-2">
                <div>
                    <span class="font-semibold">{{ v.german }}</span>
                    <span v-if="v.meta?.modal" class="badge ms-1 bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200">{{ t('lek.modal') }}</span>
                    <span v-else-if="v.meta?.irregular" class="badge ms-1 bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">{{ t('lek.irregular') }}</span>
                    <span v-if="v.meta?.stem_rule" class="badge ms-1 bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ v.meta.stem_rule }}</span>
                </div>
                <ArabicText :text="v.arabic" class="text-sm text-gray-600 dark:text-gray-300" />
            </div>
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="[key, label] in persons" :key="key" class="border-t border-gray-100 dark:border-gray-800">
                        <td class="py-1 pe-2 text-gray-500 dark:text-gray-400">{{ label }}</td>
                        <td class="py-1 font-medium">{{ v.conjugation?.[key] }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
