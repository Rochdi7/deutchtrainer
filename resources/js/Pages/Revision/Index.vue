<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import LevelDots from '@/Components/Trainer/LevelDots.vue';
import VocabImage from '@/Components/Trainer/VocabImage.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({ revision: Object });
const { t, dateLocale } = useI18n();

function startRevision() {
    router.post(route('exercises.start'), { kind: 'revision', count: 20 });
}
function practiceDifficult() {
    router.post(route('exercises.start'), { kind: 'practice', difficult: true, count: 20 });
}
function practiceWrong() {
    router.post(route('exercises.start'), { kind: 'practice', wrong: true, count: 20 });
}
function practiceWords(words) {
    router.post(route('quiz.words'), { word_ids: words.map((w) => w.word_id) });
}
function toggleDifficult(w) {
    router.post(route('words.difficult', w.word_id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('nav.revision')" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold tracking-tight">🔁 {{ t('nav.revision') }}</h1>
                <span class="badge bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ t('rev.counts', revision.counts) }}</span>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6 px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-pop-purple p-5 text-white sm:p-6" style="box-shadow: 0 6px 0 0 #7f4fd6">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <div class="text-sm text-white/80">{{ t('rev.due_today') }}</div>
                        <div class="text-5xl font-extrabold tabular-nums">{{ revision.due.length }}</div>
                        <div class="mt-1 text-sm text-white/80">{{ t('rev.sr_hint') }}</div>
                    </div>
                    <button type="button" class="btn min-h-[52px] bg-white px-5 text-pop-purple" style="--btn-edge: #d1d1d1" @click="startRevision">{{ t('rev.start') }}</button>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="card overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="font-semibold">{{ t('rev.due_words') }} ({{ revision.due.length }})</h2>
                        <button v-if="revision.due.length" type="button" class="btn-primary !min-h-[36px] !px-3 text-xs" @click="practiceWords(revision.due)">{{ t('dash.practice_these') }}</button>
                    </div>
                    <ul v-if="revision.due.length" class="max-h-96 divide-y divide-gray-100 overflow-auto dark:divide-gray-800">
                        <li v-for="w in revision.due" :key="w.word_id" class="flex items-center gap-3 px-4 py-2 text-sm">
                            <VocabImage :src="w.image" :alt="w.display" :label="w.display" size="sm" />
                            <div class="min-w-0 flex-1"><Link :href="route('words.show', w.word_id)" class="font-medium hover:underline">{{ w.display }}</Link><div class="text-xs text-gray-500">{{ w.english }} · L{{ w.lektion }}</div></div>
                            <ArabicText :text="w.arabic" class="hidden sm:block" />
                            <LevelDots :level="w.level" />
                        </li>
                    </ul>
                    <p v-else class="px-4 pb-4 text-sm text-gray-500">{{ t('rev.nothing_due') }}</p>
                </section>

                <section class="card overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3">
                        <h2 class="font-semibold">★ {{ t('rev.difficult') }} ({{ revision.difficult.length }})</h2>
                        <button v-if="revision.difficult.length" type="button" class="btn-primary !min-h-[36px] !px-3 text-xs" @click="practiceDifficult">{{ t('rev.practice') }}</button>
                    </div>
                    <ul v-if="revision.difficult.length" class="max-h-96 divide-y divide-gray-100 overflow-auto dark:divide-gray-800">
                        <li v-for="w in revision.difficult" :key="w.word_id" class="flex items-center gap-3 px-4 py-2 text-sm">
                            <VocabImage :src="w.image" :alt="w.display" :label="w.display" size="sm" />
                            <div class="min-w-0 flex-1"><Link :href="route('words.show', w.word_id)" class="font-medium hover:underline">{{ w.display }}</Link><div class="text-xs text-gray-500">{{ w.english }} · <span class="text-emerald-600">{{ w.correct }}</span>/<span class="text-red-600">{{ w.wrong }}</span></div></div>
                            <button type="button" class="text-lg" :class="w.is_difficult ? 'text-amber-500' : 'text-gray-300'" :title="t('vocab.mark_difficult')" @click="toggleDifficult(w)">★</button>
                        </li>
                    </ul>
                    <p v-else class="px-4 pb-4 text-sm text-gray-500">{{ t('rev.no_difficult') }}</p>
                </section>
            </div>

            <section class="card overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3">
                    <h2 class="font-semibold">{{ t('rev.wrong_exercises') }} ({{ revision.wrong_exercises.length }})</h2>
                    <button v-if="revision.wrong_exercises.length" type="button" class="btn-primary !min-h-[36px] !px-3 text-xs" @click="practiceWrong">{{ t('rev.practice') }}</button>
                </div>
                <ul v-if="revision.wrong_exercises.length" class="max-h-72 divide-y divide-gray-100 overflow-auto dark:divide-gray-800">
                    <li v-for="e in revision.wrong_exercises" :key="e.exercise_id" class="px-4 py-2 text-sm"><span class="badge me-2 bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">L{{ e.lektion }} · {{ t('ex.type_' + e.type) }}</span>{{ e.prompt }}</li>
                </ul>
                <p v-else class="px-4 pb-4 text-sm text-gray-500">{{ t('rev.no_wrong') }}</p>
            </section>

            <section>
                <h2 class="mb-3 text-lg font-semibold">{{ t('rev.lesson_summaries') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link v-for="l in revision.lessons" :key="l.id" :href="route('lektionen.show', l.id) + '#revision'" class="card p-4 transition hover:ring-brand-400">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ t('lek.lektion', { n: l.number }) }}</div>
                        <div class="font-semibold">{{ l.title }}</div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800"><div class="h-full bg-emerald-500" :style="{ width: l.completion + '%' }"></div></div>
                        <div class="mt-1 text-xs text-gray-500">{{ l.completion }} % · {{ t('sec.revision') }} →</div>
                    </Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
