<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SectionIcon from '@/Components/Trainer/SectionIcon.vue';
import DailyGoalCard from '@/Components/Trainer/DailyGoalCard.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({ course: Object });
const { t, dateLocale } = useI18n();
const page = usePage();
const stats = computed(() => page.props.stats || { streak: 0, xp: 0, due: 0 });

// One accent per lesson: [fill, edge, soft background]
const palette = [
    ['#7c5cff', '#6243d6', '#efe9ff'],
    ['#4f6df5', '#3b55d6', '#e6eafe'],
    ['#1fb57a', '#178f60', '#e3f7ee'],
    ['#fb8c4b', '#e0702e', '#feece0'],
    ['#f0506e', '#d43a58', '#fde4ea'],
    ['#22b8cf', '#1a9bb0', '#dff5f9'],
    ['#f6b73c', '#d99a1e', '#fdf1dc'],
    ['#e05aa0', '#c2437f', '#fbe3ef'],
];
const offsets = [0, 46, 74, 46, 0, -46, -74, -46];

const currentId = computed(() => props.course.current?.id);
const currentIndex = computed(() => props.course.lessons.findIndex((l) => l.id === currentId.value));

function nodeState(lesson, i, section) {
    if (section.completed) return 'done';
    if (lesson.id === currentId.value && section.key === lesson.next_section) return 'current';
    if (i < currentIndex.value) return 'open';
    return 'locked';
}
function lessonHref(lesson, key) {
    return route('lektionen.show', lesson.id) + '#' + key;
}
function startRevision() {
    router.post(route('exercises.start'), { kind: 'revision', count: 20 });
}
function startFinal() {
    router.post(route('exercises.start'), { kind: 'final' });
}
</script>

<template>
    <Head :title="t('course.title', { level: course.level })" />

    <AuthenticatedLayout>
        <div class="mx-auto flex max-w-6xl gap-8 px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
            <!-- PATH -->
            <div class="min-w-0 flex-1">
                <DailyGoalCard class="mb-4 lg:hidden" />
                <p class="mb-3 text-center text-xs font-bold text-gray-400 lg:text-left">{{ t('course.path_hint') }}</p>

                <section v-for="(l, i) in course.lessons" :key="l.id" class="mb-10">
                    <!-- unit banner -->
                    <div class="sticky top-14 z-20 lg:top-0">
                        <div class="flex items-center justify-between gap-3 rounded-2xl px-5 py-4 text-white" :style="{ background: palette[i % 8][0], boxShadow: '0 4px 0 0 ' + palette[i % 8][1] }">
                            <div class="min-w-0">
                                <div class="text-xs font-extrabold uppercase tracking-wide opacity-90">{{ t('lek.lektion', { n: l.number }) }} · {{ l.completion }} %</div>
                                <div class="truncate text-lg font-black">{{ l.title }}</div>
                                <div v-if="l.subtitle" class="truncate text-xs font-semibold opacity-90">{{ l.subtitle }}</div>
                            </div>
                            <Link :href="route('lektionen.show', l.id)" class="btn btn-sm shrink-0 border-2 border-white/40 bg-white/15 text-white hover:bg-white/25" style="--btn-edge: rgba(0,0,0,.2)">📘 {{ t('course.overview') }}</Link>
                        </div>
                    </div>

                    <!-- nodes -->
                    <ol class="relative mx-auto flex max-w-sm flex-col items-center gap-6 pb-8 pt-16">
                        <li v-for="(s, j) in l.sections" :key="s.key" class="relative" :style="{ transform: 'translateX(' + offsets[j % 8] + 'px)' }">
                            <div v-if="nodeState(l, i, s) === 'current'" class="absolute -top-11 left-1/2 z-10 -translate-x-1/2 animate-bob whitespace-nowrap rounded-xl border-2 bg-white px-3 py-1.5 text-xs font-black uppercase tracking-widest dark:bg-gray-900" :style="{ color: palette[i % 8][0], borderColor: palette[i % 8][0] }">
                                {{ t('course.start_node') }}
                                <span class="absolute -bottom-2 left-1/2 h-3 w-3 -translate-x-1/2 rotate-45 border-b-2 border-r-2 bg-white dark:bg-gray-900" :style="{ borderColor: palette[i % 8][0] }"></span>
                            </div>
                            <Link
                                :href="lessonHref(l, s.key)"
                                class="path-node"
                                :class="{ 'path-node-locked': nodeState(l, i, s) === 'locked', 'ring-8 ring-offset-0': nodeState(l, i, s) === 'current' }"
                                :style="nodeState(l, i, s) === 'locked' ? {} : { '--node-bg': palette[i % 8][0], '--node-edge': palette[i % 8][1], '--tw-ring-color': palette[i % 8][2] }"
                                :title="t('sec.' + s.key) + (nodeState(l, i, s) === 'locked' ? ' – ' + t('course.locked') : '')"
                                :aria-label="t('sec.' + s.key)"
                            >
                                <svg v-if="nodeState(l, i, s) === 'done'" viewBox="0 0 32 32" class="h-9 w-9" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="m7 17 6 6L25 9" /></svg>
                                <SectionIcon v-else :name="s.key" class="h-9 w-9" />
                            </Link>
                            <div class="mt-1.5 text-center text-[11px] font-bold" :class="nodeState(l, i, s) === 'locked' ? 'text-gray-400' : 'text-gray-600 dark:text-gray-300'">{{ t('sec.' + s.key) }}</div>
                        </li>
                        <!-- trophy -->
                        <li class="relative" :style="{ transform: 'translateX(' + offsets[l.sections.length % 8] + 'px)' }">
                            <div class="path-node" :class="{ 'path-node-locked': !l.completed }" :style="l.completed ? { '--node-bg': '#f6b73c', '--node-edge': '#d99a1e' } : {}" :title="t('course.trophy')"><SectionIcon name="trophy" class="h-9 w-9" /></div>
                            <div class="mt-1.5 text-center text-[11px] font-bold text-gray-400">
                                <span v-if="l.test_best !== null">{{ t('course.test_best', { pct: l.test_best }) }}</span>
                                <span v-else>{{ t('sec.test') }}</span>
                            </div>
                        </li>
                    </ol>
                </section>

                <!-- final -->
                <section class="card flex flex-wrap items-center justify-between gap-3 p-5" style="border-color: #f6b73c">
                    <div>
                        <h2 class="text-lg">🏁 {{ t('course.final') }}</h2>
                        <p class="text-sm text-gray-500">{{ t('course.final_hint') }}<span v-if="course.final_best !== null"> · {{ t('course.test_best', { pct: course.final_best }) }}</span></p>
                    </div>
                    <button type="button" class="btn bg-pop-yellow text-pop-text" style="--btn-edge: #d99a1e" @click="startFinal">{{ t('course.final_start') }}</button>
                </section>
            </div>

            <!-- RIGHT RAIL -->
            <aside class="hidden w-80 shrink-0 space-y-4 lg:block">
                <DailyGoalCard />

                <div class="card p-5">
                    <div class="text-xs font-extrabold uppercase tracking-wide text-gray-400">{{ t('course.title', { level: course.level }) }}</div>
                    <div class="mt-1 text-4xl font-black text-pop-primary">{{ course.completion }} %</div>
                    <div class="progress mt-3"><span :style="{ width: course.completion + '%' }"></span></div>
                    <div class="mt-2 text-sm font-bold text-gray-500">{{ t('course.lessons_done', { done: course.lessons_completed, total: course.lessons_total }) }}</div>
                    <Link v-if="course.current" :href="route('lektionen.show', course.current.id)" class="btn-primary mt-4 w-full">▶ {{ t('course.resume') }}</Link>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="card p-3 text-center"><div class="text-2xl">🔥</div><div class="text-xl font-black text-pop-orange">{{ stats.streak }}</div><div class="text-[11px] font-bold text-gray-400">{{ t('stats.streak') }}</div></div>
                    <div class="card p-3 text-center"><div class="text-2xl">⚡</div><div class="text-xl font-black text-pop-yellow-dark">{{ stats.xp }}</div><div class="text-[11px] font-bold text-gray-400">{{ t('stats.xp') }}</div></div>
                    <div class="card p-3 text-center"><div class="text-2xl">📚</div><div class="text-xl font-black text-pop-blue">{{ course.words_due }}</div><div class="text-[11px] font-bold text-gray-400">{{ t('stats.due') }}</div></div>
                </div>

                <div class="card p-5">
                    <h2 class="text-base">{{ t('course.words_learned') }}</h2>
                    <div class="mt-1 text-2xl font-black">{{ course.words_learned }} <span class="text-base font-bold text-gray-400">/ {{ course.words_total }}</span></div>
                    <div class="progress mt-2 !h-3"><span :style="{ width: (course.words_total ? Math.round((course.words_learned * 100) / course.words_total) : 0) + '%' }"></span></div>
                    <div class="mt-2 text-xs font-bold text-gray-500">{{ t('course.words_mastered', { n: course.words_mastered }) }} · {{ t('course.difficult', { n: course.words_difficult }) }}</div>
                    <button type="button" class="btn-blue mt-4 w-full" @click="startRevision">{{ t('course.revise_due', { n: course.words_due }) }}</button>
                </div>

                <div class="card p-5">
                    <h2 class="mb-2 text-base">{{ t('course.recent') }}</h2>
                    <ul v-if="course.recent.length" class="divide-y-2 divide-gray-100 text-sm dark:divide-gray-800">
                        <li v-for="r in course.recent" :key="r.kind + r.id" class="flex items-center justify-between gap-2 py-2">
                            <span class="min-w-0">
                                <Link :href="r.kind === 'exercise' ? route('exercises.show', r.id) : route('quiz.show', r.id)" class="block truncate font-bold hover:underline">{{ r.label }}</Link>
                                <span class="text-xs text-gray-400">{{ new Date(r.finished_at_iso).toLocaleDateString(dateLocale) }}</span>
                            </span>
                            <span class="font-black tabular-nums" :class="r.score >= 80 ? 'text-pop-green' : 'text-pop-orange'">{{ r.score }} %</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500">{{ t('course.no_activity') }}</p>
                </div>
            </aside>
        </div>

        <!-- mobile resume bar -->
        <div v-if="course.current" class="fixed inset-x-0 bottom-[calc(64px+env(safe-area-inset-bottom))] z-30 px-4 lg:hidden">
            <Link :href="route('lektionen.show', course.current.id)" class="btn-primary mx-auto w-full max-w-md shadow-lg">▶ {{ t('course.resume') }} · {{ t('lek.lektion', { n: course.current.number }) }}</Link>
        </div>
    </AuthenticatedLayout>
</template>
