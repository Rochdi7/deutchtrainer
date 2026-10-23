<script setup>
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({ lektionen: Array, types: Array, sections: Array, counts: Object, finalBest: Number });
const { t } = useI18n();

const selLektionen = ref([]);
const selTypes = ref([]);
const selSections = ref([]);
const count = ref(20);

const on = 'bg-brand-600 text-white ring-brand-600';
const off = 'bg-white text-gray-700 ring-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700';

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
function toggle(list, v) {
    const i = list.value.indexOf(v);
    if (i >= 0) list.value.splice(i, 1);
    else list.value.push(v);
}
</script>

<template>
    <Head :title="t('nav.practice')" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-bold tracking-tight">🎯 {{ t('nav.practice') }}</h1>
            <p class="text-sm text-gray-500">{{ t('prac.intro') }}</p>
        </template>

        <div class="mx-auto max-w-4xl space-y-6 px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <section class="grid gap-3 sm:grid-cols-3">
                <button type="button" class="card p-4 text-left transition hover:ring-brand-400 disabled:opacity-50" :disabled="!counts.difficult" @click="start({ difficult: true })">
                    <div class="text-3xl font-extrabold text-amber-600">{{ counts.difficult }}</div>
                    <div class="font-semibold">{{ t('prac.difficult') }}</div>
                    <div class="text-xs text-gray-500">{{ t('prac.difficult_sub') }}</div>
                </button>
                <button type="button" class="card p-4 text-left transition hover:ring-brand-400 disabled:opacity-50" :disabled="!counts.wrong" @click="start({ wrong: true })">
                    <div class="text-3xl font-extrabold text-rose-600">{{ counts.wrong }}</div>
                    <div class="font-semibold">{{ t('prac.wrong') }}</div>
                    <div class="text-xs text-gray-500">{{ t('prac.wrong_sub') }}</div>
                </button>
                <button type="button" class="card p-4 text-left transition hover:ring-brand-400" @click="router.post(route('exercises.start'), { kind: 'revision', count: 20 })">
                    <div class="text-3xl font-extrabold text-emerald-600">{{ counts.due }}</div>
                    <div class="font-semibold">{{ t('prac.due') }}</div>
                    <div class="text-xs text-gray-500">{{ t('prac.due_sub') }}</div>
                </button>
            </section>

            <section class="card space-y-4 p-5">
                <h2 class="font-semibold">{{ t('prac.custom') }}</h2>
                <div>
                    <div class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ t('prac.by_lesson') }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        <button v-for="l in lektionen" :key="l.id" type="button" class="badge min-h-[34px] px-3 ring-1" :class="selLektionen.includes(l.id) ? on : off" @click="toggle(selLektionen, l.id)" :title="l.title">
                            {{ l.number }} · {{ l.title }} <span class="ms-1 opacity-70">{{ l.completion }} %</span>
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">{{ t('prac.all_hint') }}</p>
                </div>
                <div>
                    <div class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ t('prac.by_section') }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        <button v-for="s in sections" :key="s" type="button" class="badge min-h-[34px] px-3 ring-1" :class="selSections.includes(s) ? on : off" @click="toggle(selSections, s)">
                            {{ t('set.' + s) }}
                        </button>
                    </div>
                </div>
                <div>
                    <div class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ t('prac.by_type') }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        <button v-for="ty in types" :key="ty.key" type="button" class="badge min-h-[34px] px-3 ring-1" :class="selTypes.includes(ty.key) ? on : off" @click="toggle(selTypes, ty.key)">
                            {{ t('ex.type_' + ty.key) }} <span class="ms-1 opacity-70">{{ ty.count }}</span>
                        </button>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="text-sm">
                        {{ t('prac.count') }}
                        <select v-model="count" class="input ms-1 inline-block w-24">
                            <option v-for="n in [10, 20, 30, 40]" :key="n" :value="n">{{ n }}</option>
                        </select>
                    </label>
                    <button type="button" class="btn-primary min-h-[48px] px-6" @click="start()">{{ t('prac.start') }}</button>
                </div>
            </section>

            <section class="card flex flex-wrap items-center justify-between gap-3 p-5">
                <div>
                    <h2 class="font-semibold">🏁 {{ t('course.final') }}</h2>
                    <p class="text-sm text-gray-500">{{ t('course.final_hint') }}<span v-if="finalBest !== null"> · {{ t('course.test_best', { pct: finalBest }) }}</span></p>
                </div>
                <button type="button" class="btn-primary min-h-[48px]" @click="router.post(route('exercises.start'), { kind: 'final' })">{{ t('course.final_start') }}</button>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
