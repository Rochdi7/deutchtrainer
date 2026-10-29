<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({ level: String, levels: Array, units: Array });
const { t } = useI18n();

const levelStyle = {
    A1: ['#1fb57a', '#178f60', '#e3f7ee'],
    A2: ['#22b8cf', '#1a9bb0', '#dff5f9'],
    B1: ['#c2185b', '#9c1249', '#fbe3ef'],
};

const summary = computed(() =>
    Object.fromEntries(
        props.levels.map((lv) => {
            const us = props.units.filter((u) => u.level === lv);
            const exercises = us.reduce((s, u) => s + u.exercises, 0);
            const done = us.reduce((s, u) => s + u.done, 0);
            return [lv, { units: us.length, exercises, done, pct: exercises ? Math.round((done / exercises) * 100) : 0 }];
        }),
    ),
);

/** Units of the selected level, grouped by book chapter in book order. */
const chapters = computed(() => {
    const groups = [];
    for (const u of props.units.filter((u) => u.level === props.level)) {
        const last = groups[groups.length - 1];
        if (last && last.name === u.chapter) last.units.push(u);
        else groups.push({ name: u.chapter, units: [u] });
    }
    return groups;
});
const pct = (u) => (u.exercises ? Math.round((u.done / u.exercises) * 100) : 0);
</script>

<template>
    <Head :title="t('ga.title') + ' ' + level" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
            <!-- module banner -->
            <div class="mb-6 overflow-hidden rounded-2xl bg-[#c2185b] px-5 py-5 text-white sm:px-7" style="box-shadow: 0 4px 0 0 #9c1249">
                <div class="text-xs font-extrabold uppercase tracking-widest opacity-80">A1 · A2 · B1</div>
                <h1 class="text-2xl font-black sm:text-3xl">{{ t('ga.title') }}</h1>
                <p class="mt-1 max-w-2xl text-sm font-semibold opacity-90">{{ t('ga.subtitle') }}</p>
            </div>

            <!-- level dashboard -->
            <div class="mb-8 grid grid-cols-3 gap-2 sm:gap-4">
                <Link
                    v-for="lv in levels"
                    :key="lv"
                    :href="route('grammatik.index', lv.toLowerCase())"
                    preserve-scroll
                    class="rounded-2xl border-2 p-3 transition sm:p-4"
                    :class="lv === level ? 'bg-white dark:bg-gray-900' : 'border-gray-200 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800'"
                    :style="lv === level ? { borderColor: levelStyle[lv][0], boxShadow: '0 4px 0 0 ' + levelStyle[lv][1] } : {}"
                    :aria-current="lv === level ? 'page' : undefined"
                >
                    <div class="text-2xl font-black sm:text-3xl" :style="{ color: levelStyle[lv][0] }">{{ lv }}</div>
                    <div class="text-xs font-bold text-gray-500 sm:text-sm">{{ t('ga.n_units', { n: summary[lv].units }) }}</div>
                    <div class="hidden text-xs text-gray-400 sm:block">{{ t('ga.n_exercises', { n: summary[lv].exercises }) }}</div>
                    <div class="progress mt-2 !h-2"><span :style="{ width: summary[lv].pct + '%', background: levelStyle[lv][0] }"></span></div>
                </Link>
            </div>

            <p v-if="!chapters.length" class="card p-6 text-gray-500">{{ t('ga.empty') }}</p>

            <!-- chapters as folders -->
            <section v-for="c in chapters" :key="c.name" class="mb-8">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-black uppercase tracking-widest text-gray-500">
                    <span class="text-lg">📁</span>{{ c.name }}
                </h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="u in c.units"
                        :key="u.number"
                        :href="route('grammatik.show', u.number)"
                        class="card flex gap-3 p-4 transition hover:-translate-y-0.5 hover:border-gray-300 dark:hover:border-gray-600"
                    >
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-lg font-black text-white" :style="{ background: levelStyle[u.level][0] }">{{ u.number }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate font-bold">{{ u.topic }}</span>
                                <bdi v-if="u.topic_ar" class="ar shrink-0 truncate text-sm text-gray-400">{{ u.topic_ar }}</bdi>
                            </div>
                            <div class="truncate text-sm text-gray-500">{{ u.title }}</div>
                            <div class="mt-2 flex items-center gap-2">
                                <div class="progress !h-2 flex-1"><span :style="{ width: pct(u) + '%', background: levelStyle[u.level][0] }"></span></div>
                                <span class="shrink-0 text-xs font-bold tabular-nums text-gray-400">{{ u.done }}/{{ u.exercises }}</span>
                            </div>
                            <div v-if="u.total" class="mt-1 text-xs font-bold text-gray-500">{{ t('ga.score', { p: Math.round((u.correct / u.total) * 100) }) }}</div>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
