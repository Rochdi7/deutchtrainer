<script setup>
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import { useI18n } from '@/i18n';

defineProps({ lines: { type: Array, required: true } });
const { t } = useI18n();
</script>

<template>
    <div class="card overflow-hidden">
        <!-- Mobile: stacked cards -->
        <ul class="divide-y divide-gray-100 dark:divide-gray-800 md:hidden">
            <li v-for="l in lines" :key="l.id" class="space-y-1 p-4">
                <div class="text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">{{ l.topic }}</div>
                <div v-if="l.question_formal" class="text-sm"><span class="text-gray-500">Sie:</span> {{ l.question_formal }}</div>
                <div v-if="l.question_informal" class="text-sm"><span class="text-gray-500">du:</span> {{ l.question_informal }}</div>
                <div class="text-sm font-medium">→ {{ l.answer }}</div>
                <ArabicText :text="l.arabic" tag="div" class="text-sm text-gray-600 dark:text-gray-300" />
            </li>
        </ul>
        <!-- Desktop: table -->
        <table class="hidden w-full text-sm md:table">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2">{{ t('lek.topic') }}</th>
                    <th class="px-3 py-2">{{ t('lek.q_formal') }}</th>
                    <th class="px-3 py-2">{{ t('lek.q_informal') }}</th>
                    <th class="px-3 py-2">{{ t('lek.answer') }}</th>
                    <th class="px-3 py-2 text-right">العربية</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                <tr v-for="l in lines" :key="l.id">
                    <td class="px-3 py-2 font-medium">{{ l.topic }}</td>
                    <td class="px-3 py-2">{{ l.question_formal }}</td>
                    <td class="px-3 py-2">{{ l.question_informal }}</td>
                    <td class="px-3 py-2">{{ l.answer }}</td>
                    <td class="px-3 py-2"><ArabicText :text="l.arabic" tag="div" /></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
