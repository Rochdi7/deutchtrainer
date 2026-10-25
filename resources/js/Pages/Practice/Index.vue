<script setup>
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DailyGoalCard from '@/Components/Trainer/DailyGoalCard.vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({ lektionen: Array, types: Array, sections: Array, counts: Object, finalBest: Number });
const { t } = useI18n();

const SECONDS_PER_ITEM = 20;
const lengths = [10, 20, 30, 40];
const sectionIcons = { vocab: '🖼️', grammar: '🧩', dialog: '💬', reading: '📖', listening: '🎧' };
const typeIcons = { mc: '☑️', article: '🔤', gap: '✏️', order: '🔀', match: '🔗', translate: '🌍', plural: '👥', conjugation: '🔁', dialog: '💬', listen: '🎧', write: '📝' };

// Builder selection, remembered per device so "the usual" practice is one tap away.
const STORAGE_KEY = 'practice.builder';
const saved = (() => {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    } catch (e) {
        return {};
    }
})();
const lessonIds = new Set(props.lektionen.map((l) => l.id));
const selLektionen = ref((saved.lektionen || []).filter((id) => lessonIds.has(id)));
const selSections = ref((saved.sections || []).filter((s) => props.sections.includes(s)));
const selTypes = ref((saved.types || []).filter((k) => props.types.some((ty) => ty.key === k)));
const count = ref(lengths.includes(saved.count) ? saved.count : 20);

watch([selLektionen, selSections, selTypes, count], () => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ lektionen: selLektionen.value, sections: selSections.value, types: selTypes.value, count: count.value }));
    } catch (e) {}
}, { deep: true });

function toggle(list, v) {
    const i = list.value.indexOf(v);
    if (i >= 0) list.value.splice(i, 1);
    else list.value.push(v);
}
function resetBuilder() {
    selLektionen.value = [];
    selSections.value = [];
    selTypes.value = [];
    count.value = 20;
}

const minutes = (n) => Math.max(1, Math.round((n * SECONDS_PER_ITEM) / 60));
const filtersActive = computed(() => selLektionen.value.length + selSections.value.length + selTypes.value.length > 0);
const summary = computed(() => {
    const lessons = selLektionen.value.length ? t('prac.n_lessons', { n: selLektionen.value.length }) : t('prac.all_lessons');
    const focus = [...selSections.value.map((s) => t('set.' + s)), ...selTypes.value.map((k) => t('ex.type_' + k))];
    return [t('prac.n_items', { n: count.value }), lessons, focus.length ? focus.join(', ') : t('prac.everything'), '~' + t('prac.min', { n: minutes(count.value) })].join(' · ');
});

function start(extra = {}) {
    router.post(route('exercises.start'), {
        kind: 'practice',
        lektion_ids: selLektionen.value,
        types: selTypes.value,
        sections: selSections.value,
        count: count.value,
        ...extra,
    });
}
const post = (data) => router.post(route('exercises.start'), data);

const quick = computed(() => [
    { key: 'mix', icon: '⚡', title: t('prac.q_mix'), sub: t('prac.q_mix_sub', { n: minutes(10) }), bg: '#7c5cff', edge: '#6243d6', run: () => post({ kind: 'practice', count: 10 }) },
    { key: 'due', icon: '📚', title: t('prac.due'), sub: t('prac.n_words', { n: props.counts.due }), bg: '#4f6df5', edge: '#3b55d6', run: () => post({ kind: 'revision', count: 20 }) },
    { key: 'speak', icon: '🎤', title: t('speak.title'), sub: t('prac.q_speak_sub'), bg: '#e05aa0', edge: '#c2437f', run: () => post({ kind: 'speak', lektion_ids: selLektionen.value, count: 12 }) },
    { key: 'weak', icon: '🧠', title: t('quiz.title_weak'), sub: t('prac.q_quiz_sub'), bg: '#fb8c4b', edge: '#e0702e', run: () => router.visit(route('quiz.weak')) },
    { key: 'global', icon: '🌍', title: t('quiz.title_global'), sub: t('prac.q_quiz_sub'), bg: '#22b8cf', edge: '#1a9bb0', run: () => router.visit(route('quiz.global')) },
    { key: 'difficult', icon: '⭐', title: t('prac.difficult'), sub: t('prac.n_words', { n: props.counts.difficult }), bg: '#f6b73c', edge: '#d99a1e', disabled: !props.counts.difficult, run: () => start({ difficult: true }) },
    { key: 'wrong', icon: '🔁', title: t('prac.wrong'), sub: t('prac.n_exercises', { n: props.counts.wrong }), bg: '#f0506e', edge: '#d43a58', disabled: !props.counts.wrong, run: () => start({ wrong: true }) },
    { key: 'final', icon: '🏁', title: t('course.final'), sub: props.finalBest !== null ? t('course.test_best', { pct: props.finalBest }) : t('prac.q_final_sub'), bg: '#1fb57a', edge: '#178f60', run: () => post({ kind: 'final' }) },
]);
</script>

<template>
    <Head :title="t('nav.practice')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl space-y-8 px-4 py-4 pb-40 sm:px-6 sm:py-6 lg:px-8 lg:pb-10">
            <!-- header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-black tracking-tight">🎯 {{ t('nav.practice') }}</h1>
                    <p class="mt-1 text-sm font-semibold text-gray-500">{{ t('prac.intro') }}</p>
                </div>
                <DailyGoalCard class="sm:w-80" />
            </div>

            <!-- quick start -->
            <section>
                <h2 class="mb-3 text-xs font-extrabold uppercase tracking-widest text-gray-400">{{ t('prac.quick') }}</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <button
                        v-for="q in quick"
                        :key="q.key"
                        type="button"
                        class="launch-tile min-h-[112px]"
                        :style="{ '--tile-bg': q.bg, '--tile-edge': q.edge }"
                        :disabled="q.disabled"
                        @click="q.run()"
                    >
                        <span class="text-3xl drop-shadow-sm">{{ q.icon }}</span>
                        <span class="text-base font-black leading-tight">{{ q.title }}</span>
                        <span class="text-xs font-bold opacity-90">{{ q.sub }}</span>
                    </button>
                </div>
            </section>

            <!-- builder -->
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between gap-3 border-b-2 border-gray-100 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h2 class="text-lg font-black">🛠️ {{ t('prac.custom') }}</h2>
                        <p class="text-xs font-semibold text-gray-500">{{ t('prac.custom_sub') }}</p>
                    </div>
                    <button v-if="filtersActive" type="button" class="btn-ghost btn-sm" @click="resetBuilder">↺ {{ t('prac.reset') }}</button>
                </div>

                <div class="space-y-6 p-5">
                    <!-- 1 lessons -->
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-2">
                            <h3 class="text-sm font-extrabold"><span class="step-dot">1</span> {{ t('prac.by_lesson') }}</h3>
                            <div class="flex gap-1">
                                <button type="button" class="pill !min-h-[32px] text-xs" @click="selLektionen = lektionen.map((l) => l.id)">{{ t('prac.select_all') }}</button>
                                <button type="button" class="pill !min-h-[32px] text-xs" :disabled="!selLektionen.length" @click="selLektionen = []">{{ t('prac.select_none') }}</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                            <button
                                v-for="l in lektionen"
                                :key="l.id"
                                type="button"
                                class="select-card"
                                :class="{ 'select-card-on': selLektionen.includes(l.id) }"
                                :aria-pressed="selLektionen.includes(l.id)"
                                :title="l.title"
                                @click="toggle(selLektionen, l.id)"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-xs font-black" :class="selLektionen.includes(l.id) ? 'bg-pop-primary text-white' : 'bg-gray-100 text-gray-500 dark:bg-gray-800'">
                                        {{ selLektionen.includes(l.id) ? '✓' : l.number }}
                                    </span>
                                    <span class="min-w-0 truncate text-sm font-bold">{{ l.title }}</span>
                                </span>
                                <span class="flex items-center gap-2">
                                    <span class="progress !h-2 flex-1"><span :style="{ width: l.completion + '%' }"></span></span>
                                    <span class="text-[11px] font-bold tabular-nums text-gray-400">{{ l.completion }} %</span>
                                </span>
                            </button>
                        </div>
                        <p v-if="!selLektionen.length" class="mt-1.5 text-xs font-semibold text-gray-400">{{ t('prac.all_hint') }}</p>
                    </div>

                    <!-- 2 focus -->
                    <div>
                        <h3 class="mb-2 text-sm font-extrabold"><span class="step-dot">2</span> {{ t('prac.focus') }}</h3>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="s in sections"
                                :key="s"
                                type="button"
                                class="tool-chip"
                                :class="{ '!border-pop-primary !bg-brand-50 !text-pop-primary dark:!bg-brand-900/40': selSections.includes(s) }"
                                :aria-pressed="selSections.includes(s)"
                                @click="toggle(selSections, s)"
                            >
                                {{ sectionIcons[s] || '•' }} {{ t('set.' + s) }}
                            </button>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="ty in types"
                                :key="ty.key"
                                type="button"
                                class="tool-chip !text-xs"
                                :class="{ '!border-pop-primary !bg-brand-50 !text-pop-primary dark:!bg-brand-900/40': selTypes.includes(ty.key) }"
                                :aria-pressed="selTypes.includes(ty.key)"
                                :disabled="!ty.count"
                                @click="toggle(selTypes, ty.key)"
                            >
                                {{ typeIcons[ty.key] || '•' }} {{ t('ex.type_' + ty.key) }} <span class="font-bold opacity-60">{{ ty.count }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- 3 length -->
                    <div>
                        <h3 class="mb-2 text-sm font-extrabold"><span class="step-dot">3</span> {{ t('prac.length') }}</h3>
                        <div class="grid grid-cols-4 gap-2 sm:max-w-md">
                            <button v-for="n in lengths" :key="n" type="button" class="option !flex-col !items-center !gap-0 !px-2 !py-2 text-center" :class="{ 'option-selected': count === n }" :aria-pressed="count === n" @click="count = n">
                                <span class="text-lg font-black">{{ n }}</span>
                                <span class="text-[11px] font-bold text-gray-400">~{{ t('prac.min', { n: minutes(n) }) }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- summary + start (desktop) -->
                <div class="hidden items-center justify-between gap-4 border-t-2 border-gray-100 bg-gray-50 px-5 py-4 dark:border-gray-800 dark:bg-gray-900/60 lg:flex">
                    <p class="min-w-0 text-sm font-bold text-gray-500">{{ summary }}</p>
                    <button type="button" class="btn-primary shrink-0 px-8" @click="start()">▶ {{ t('prac.start') }}</button>
                </div>
            </section>
        </div>

        <!-- sticky start bar (mobile / tablet) -->
        <div class="fixed inset-x-0 bottom-[calc(64px+env(safe-area-inset-bottom))] z-30 border-t-2 border-gray-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 lg:hidden">
            <div class="mx-auto flex max-w-md items-center gap-3">
                <p class="min-w-0 flex-1 truncate text-xs font-bold text-gray-500">{{ summary }}</p>
                <button type="button" class="btn-primary shrink-0" @click="start()">▶ {{ t('prac.start_short') }}</button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.step-dot {
    @apply me-1 inline-grid h-5 w-5 place-items-center rounded-full bg-pop-primary text-[11px] font-black text-white;
}
</style>
