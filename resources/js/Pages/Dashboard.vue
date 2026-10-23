<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/Trainer/StatCard.vue';
import ProgressChart from '@/Components/Trainer/ProgressChart.vue';
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import LevelDots from '@/Components/Trainer/LevelDots.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({
    lektionen: Array,
    global: Object,
    chart: Object,
    weakest: Array,
    recent: Array,
    course: { type: Object, default: null },
});

const { t, dateLocale } = useI18n();

function practiceWeakest() {
    router.post(route('quiz.words'), { word_ids: props.weakest.map((w) => w.word_id) });
}
</script>

<template>
    <Head :title="t('dash.title')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl space-y-6 px-4 py-4 sm:space-y-8 sm:px-6 sm:py-6 lg:px-8">
            <!-- Hero / quick actions -->
            <section class="overflow-hidden rounded-2xl bg-pop-primary p-5 text-white sm:p-6" style="box-shadow: 0 6px 0 0 #6243d6">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <div class="text-sm/5 text-brand-100">{{ t('dash.due_today') }}</div>
                        <div class="text-5xl font-extrabold tabular-nums">{{ global.due_today }}</div>
                        <div class="mt-1 text-sm text-brand-100">🔥 {{ global.streak }} · {{ t('dash.streak_sub') }} · {{ global.xp }} XP</div>
                    </div>
                    <div class="grid shrink-0 gap-2">
                        <Link :href="route('quiz.global')" class="btn min-h-[52px] bg-white px-5 text-pop-primary" style="--btn-edge: #d1d1d1">{{ t('dash.start_quiz') }}</Link>
                        <Link :href="route('quiz.weak')" class="btn min-h-[44px] border-2 border-white/50 bg-white/15 text-white hover:bg-white/25" style="--btn-edge: rgba(0,0,0,.2)">{{ t('dash.weak_words') }}</Link>
                    </div>
                </div>
            </section>

            <!-- A1 course entry -->
            <section v-if="course" class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ t('course.kicker') }}</div>
                    <div class="text-lg font-semibold">{{ t('course.title', { level: course.level }) }} · {{ course.completion }} %</div>
                    <div class="text-sm text-gray-500">
                        {{ t('course.lessons_done', { done: course.lessons_completed, total: course.lessons_total }) }}
                        <span v-if="course.current"> · {{ t('course.current') }}: {{ t('lek.lektion', { n: course.current.number }) }} – {{ course.current.title }}</span>
                    </div>
                    <div class="mt-2 h-2 max-w-md overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800"><div class="h-full rounded-full bg-brand-500" :style="{ width: course.completion + '%' }"></div></div>
                </div>
                <div class="flex shrink-0 gap-2">
                    <Link v-if="course.current" :href="route('lektionen.show', course.current.id)" class="btn-primary min-h-[48px]">▶ {{ t('course.resume') }}</Link>
                    <Link :href="route('course.dashboard')" class="btn-secondary min-h-[48px]">{{ t('nav.course') }}</Link>
                </div>
            </section>

            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard :label="t('dash.total_words')" :value="global.total_words" icon="📚" accent="blue" />
                <StatCard :label="t('dash.mastered')" :value="global.mastered" :sub="t('dash.mastered_sub')" accent="emerald" icon="🏆" />
                <StatCard :label="t('dash.streak')" :value="global.streak" :sub="t('dash.streak_sub')" accent="amber" icon="🔥" />
                <StatCard :label="t('dash.xp')" :value="global.xp" :sub="t('dash.sessions', { n: global.sessions })" accent="purple" icon="⚡" />
            </section>

            <section>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">{{ t('dash.lektionen') }}</h2>
                    <Link :href="route('lektionen.index')" class="text-sm font-medium text-brand-600 hover:underline dark:text-brand-400">{{ t('dash.show_all') }}</Link>
                </div>
                <div v-if="lektionen.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link v-for="l in lektionen" :key="l.id" :href="route('lektionen.show', l.id)" class="card block p-4 transition hover:ring-brand-400 active:scale-[0.99]">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ t('lek.lektion', { n: l.number }) }}</div>
                                <div class="font-semibold">{{ l.title }}</div>
                            </div>
                            <span v-if="l.completed" class="badge bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">{{ t('dash.completed') }}</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                            <div class="h-full rounded-full bg-emerald-500 transition-all" :style="{ width: l.mastered_percent + '%' }"></div>
                        </div>
                        <div class="mt-2 flex justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ t('dash.words_mastered', { words: l.words_count, pct: l.mastered_percent }) }}</span>
                            <span v-if="l.best_score !== null">{{ t('dash.best', { pct: l.best_score }) }}</span>
                        </div>
                    </Link>
                </div>
                <div v-else class="card p-6 text-center text-gray-500">
                    {{ t('dash.no_lektion') }}
                    <Link v-if="$page.props.auth.user?.is_admin" :href="route('import.index')" class="ms-1 font-medium text-brand-600 hover:underline">{{ t('dash.import_pdf') }}</Link>
                </div>
            </section>

            <section class="card p-4 sm:p-6">
                <h2 class="mb-3 text-lg font-semibold">{{ t('dash.last_30') }}</h2>
                <ProgressChart :chart="chart" />
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="card p-4 sm:p-6">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-semibold">{{ t('dash.weakest') }}</h2>
                        <button v-if="weakest.length" type="button" class="btn-primary !min-h-[40px] !px-3 !py-1 text-xs" @click="practiceWeakest">{{ t('dash.practice_these') }}</button>
                    </div>
                    <table v-if="weakest.length" class="w-full text-sm">
                        <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                            <tr><th class="py-1">{{ t('dash.col_word') }}</th><th class="py-1 text-right">العربية</th><th class="py-1 text-center">{{ t('dash.col_ratio') }}</th><th class="hidden py-1 sm:table-cell">{{ t('common.level') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="w in weakest" :key="w.word_id">
                                <td class="py-2.5"><Link :href="route('words.show', w.word_id)" class="font-medium hover:underline">{{ w.display }}</Link></td>
                                <td class="py-2.5"><ArabicText :text="w.arabic" tag="div" /></td>
                                <td class="py-2.5 text-center tabular-nums"><span class="text-emerald-600">{{ w.correct }}</span> / <span class="text-red-600">{{ w.wrong }}</span></td>
                                <td class="hidden py-2.5 sm:table-cell"><LevelDots :level="w.level" /></td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-500">{{ t('dash.no_errors') }}</p>
                </section>

                <section class="card p-4 sm:p-6">
                    <h2 class="mb-3 text-lg font-semibold">{{ t('dash.recent') }}</h2>
                    <ul v-if="recent.length" class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <li v-for="s in recent" :key="s.id">
                            <Link :href="route('quiz.show', s.id)" class="flex min-h-[48px] items-center justify-between gap-2 py-1.5">
                                <span>
                                    {{ t('mode.' + s.mode) }}<span v-if="s.lektion"> {{ s.lektion }}</span>
                                    <span class="ms-2 text-xs text-gray-500">{{ new Date(s.finished_at).toLocaleString(dateLocale, { dateStyle: 'short', timeStyle: 'short' }) }}</span>
                                </span>
                                <span class="font-semibold tabular-nums" :class="s.score >= 80 ? 'text-emerald-600' : 'text-amber-600'">{{ s.score }} %</span>
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500">{{ t('dash.no_sessions') }}</p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
