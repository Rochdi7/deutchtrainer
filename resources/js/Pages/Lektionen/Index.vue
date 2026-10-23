<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

defineProps({ lektionen: Array });
const { t } = useI18n();
</script>

<template>
    <Head :title="t('lek.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold tracking-tight">{{ t('lek.title') }}</h1>
                <Link v-if="$page.props.auth.user?.is_admin" :href="route('import.index')" class="btn-secondary">{{ t('dash.import_pdf') }}</Link>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <div v-if="lektionen.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="l in lektionen" :key="l.id" class="card flex flex-col p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ t('lek.lektion', { n: l.number }) }}</div>
                            <div class="text-lg font-semibold">{{ l.title }}</div>
                        </div>
                        <span v-if="l.completed" class="badge bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">✓</span>
                    </div>
                    <div v-if="l.completion !== undefined" class="mt-3">
                        <div class="mb-1 flex justify-between text-[11px] text-gray-500"><span>{{ t('course.completion') }}</span><span>{{ l.completion }} %</span></div>
                        <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                            <div class="h-full rounded-full bg-brand-500" :style="{ width: l.completion + '%' }"></div>
                        </div>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                        <div class="h-full rounded-full bg-emerald-500" :style="{ width: l.mastered_percent + '%' }"></div>
                    </div>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ t('lek.stats', { words: l.words_count, mastered: l.mastered_count, pct: l.mastered_percent }) }}
                        <span v-if="l.best_score !== null"> · {{ t('lek.final_score', { pct: l.best_score }) }}</span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <Link :href="route('lektionen.show', l.id)" class="btn-secondary min-h-[48px]">{{ t('lek.learn') }}</Link>
                        <Link :href="route('quiz.lektion', l.id)" class="btn-primary min-h-[48px]">{{ t('lek.quiz') }}</Link>
                    </div>
                </div>
            </div>
            <div v-else class="card p-8 text-center text-gray-500">{{ t('lek.none') }}</div>
        </div>
    </AuthenticatedLayout>
</template>
