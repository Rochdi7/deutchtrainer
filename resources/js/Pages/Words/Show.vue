<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import LevelDots from '@/Components/Trainer/LevelDots.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({ word: Object, progress: Object, history: Array });
const { t, dateLocale } = useI18n();

function practice() {
    router.post(route('quiz.words'), { word_ids: [props.word.id] });
}
</script>

<template>
    <Head :title="word.display" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <Link :href="route('lektionen.show', word.lektion_id)" class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:underline">
                        ← {{ t('lek.lektion', { n: word.lektion?.number }) }} · {{ word.category?.name }}
                    </Link>
                    <h1 class="text-2xl font-bold tracking-tight">{{ word.display }}</h1>
                </div>
                <button type="button" class="btn-primary min-h-[48px] w-full sm:w-auto" @click="practice">{{ t('word.practice') }}</button>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-4 px-4 py-4 sm:space-y-6 sm:px-6 sm:py-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="card p-5">
                    <ArabicText :text="word.arabic" tag="div" class="text-3xl font-semibold" />
                    <dl class="mt-4 space-y-1.5 text-sm">
                        <div class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">{{ t('word.type') }}</dt><dd>{{ t('type.' + word.type) }}</dd></div>
                        <div v-if="word.plural" class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">{{ t('word.plural') }}</dt><dd>{{ word.plural }} <span v-if="word.plural_suffix" class="text-gray-400">({{ word.plural_suffix }})</span></dd></div>
                        <div v-if="word.example_de" class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">{{ t('word.example') }}</dt><dd class="italic">{{ word.example_de }}<ArabicText v-if="word.example_ar" :text="word.example_ar" tag="div" class="not-italic text-gray-600 dark:text-gray-300" /></dd></div>
                        <div v-if="word.note" class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">{{ t('word.note') }}</dt><dd>{{ word.note }}</dd></div>
                        <div v-if="word.meta" class="flex gap-2"><dt class="w-28 shrink-0 text-gray-500">{{ t('word.meta') }}</dt><dd class="break-all font-mono text-xs">{{ JSON.stringify(word.meta) }}</dd></div>
                    </dl>
                    <div v-if="word.conjugation" class="mt-4 grid grid-cols-2 gap-x-3 gap-y-1 text-sm sm:grid-cols-3">
                        <div><span class="text-gray-500">ich</span> {{ word.conjugation.ich }}</div>
                        <div><span class="text-gray-500">du</span> {{ word.conjugation.du }}</div>
                        <div><span class="text-gray-500">er/sie/es</span> {{ word.conjugation.er_sie_es }}</div>
                        <div><span class="text-gray-500">wir</span> {{ word.conjugation.wir }}</div>
                        <div><span class="text-gray-500">ihr</span> {{ word.conjugation.ihr }}</div>
                        <div><span class="text-gray-500">sie/Sie</span> {{ word.conjugation.sie_Sie }}</div>
                    </div>
                </div>
                <div class="card p-5">
                    <h2 class="mb-3 font-semibold">{{ t('word.progress') }}</h2>
                    <template v-if="progress">
                        <div class="flex items-center gap-3"><LevelDots :level="progress.level" /><span class="text-sm">{{ t('word.level_of', { level: progress.level }) }}</span></div>
                        <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                            <div><dt class="text-gray-500">{{ t('word.correct') }}</dt><dd class="text-xl font-bold text-emerald-600">{{ progress.correct_count }}</dd></div>
                            <div><dt class="text-gray-500">{{ t('word.wrong') }}</dt><dd class="text-xl font-bold text-red-600">{{ progress.wrong_count }}</dd></div>
                            <div><dt class="text-gray-500">{{ t('word.streak') }}</dt><dd class="text-xl font-bold">{{ progress.streak }}</dd></div>
                            <div><dt class="text-gray-500">{{ t('word.next_review') }}</dt><dd class="font-medium">{{ progress.next_review_at ? new Date(progress.next_review_at).toLocaleDateString(dateLocale) : '–' }}</dd></div>
                        </dl>
                    </template>
                    <p v-else class="text-sm text-gray-500">{{ t('word.not_asked') }}</p>
                </div>
            </div>

            <section class="card overflow-hidden">
                <h2 class="px-4 py-3 font-semibold">{{ t('word.history') }}</h2>
                <ul v-if="history.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="h in history" :key="h.id" class="flex items-start gap-3 px-4 py-3 text-sm">
                        <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-bold" :class="h.is_correct ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">{{ h.is_correct ? '✓' : '✗' }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="font-medium">{{ t('quiz.q.' + h.question_type) }} · <span class="text-gray-500">{{ h.prompt }}</span></div>
                            <div class="text-xs text-gray-500">
                                {{ t('word.correct_label', { a: h.correct_answer }) }}<span v-if="!h.is_correct"> · {{ t('word.given_label', { a: h.given_answer || '–' }) }}</span>
                            </div>
                        </div>
                        <Link :href="route('quiz.show', h.session_id)" class="shrink-0 text-xs text-gray-400 hover:underline">{{ new Date(h.answered_at).toLocaleDateString(dateLocale) }}</Link>
                    </li>
                </ul>
                <p v-else class="px-4 pb-4 text-sm text-gray-500">{{ t('word.no_answers') }}</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
