<script setup>
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import MarkdownNote from '@/Components/Trainer/MarkdownNote.vue';
import GrammatikExercise from '@/Components/Grammatik/GrammatikExercise.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({ unit: Object, results: [Object, Array], prev: Number, next: Number });
const { t } = useI18n();

// Mobile: rules and exercises as tabs; desktop: rules stay beside the exercises.
const tab = ref('exercises');
const levelColor = { A1: '#1fb57a', A2: '#22b8cf', B1: '#c2185b' }[props.unit.level];

// Arabic helper texts, remembered per device.
const AR_KEY = 'grammatik.arabic';
const showArabic = ref((() => { try { return localStorage.getItem(AR_KEY) === '1'; } catch (e) { return false; } })());
watch(showArabic, (v) => { try { localStorage.setItem(AR_KEY, v ? '1' : '0'); } catch (e) {} });
const hasArabic = computed(() => !!(props.unit.info_ar || props.unit.exercises.some((e) => e.instruction_ar)));
</script>

<template>
    <Head :title="t('ga.unit', { n: unit.number }) + ' · ' + unit.topic" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 lg:px-8 lg:py-6">
            <nav class="mb-3 flex flex-wrap items-center gap-1 text-sm font-bold text-gray-400">
                <Link :href="route('grammatik.index', unit.level.toLowerCase())" class="hover:text-gray-600">{{ t('ga.title') }}</Link>
                <span>›</span>
                <Link :href="route('grammatik.index', unit.level.toLowerCase())" class="hover:text-gray-600">{{ unit.level }}</Link>
                <span>›</span>
                <span>{{ unit.chapter }}</span>
            </nav>

            <header class="mb-5 flex items-start gap-4">
                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl text-2xl font-black text-white" :style="{ background: levelColor }">{{ unit.number }}</span>
                <div class="min-w-0">
                    <h1 class="text-xl font-black leading-tight sm:text-2xl">{{ unit.title }}</h1>
                    <div class="flex flex-wrap items-baseline gap-x-3 font-bold" :style="{ color: levelColor }">
                        <span>{{ unit.topic }}</span>
                        <ArabicText v-if="unit.topic_ar" :text="unit.topic_ar" class="text-base font-semibold text-gray-500" />
                    </div>
                    <div class="mt-1 flex flex-wrap gap-1.5 text-xs">
                        <span class="badge text-white" :style="{ background: levelColor }">{{ unit.level }}</span>
                        <span class="badge bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ t('ga.n_exercises', { n: unit.exercises.length }) }}</span>
                        <span v-if="unit.book_pages" class="badge bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ t('ga.book_pages', { p: unit.book_pages.join('–') }) }}</span>
                    </div>
                </div>
            </header>

            <div class="mb-4 flex items-center gap-1">
                <button type="button" class="pill lg:hidden" :class="{ 'pill-active': tab === 'rules' }" @click="tab = 'rules'">📐 {{ t('ga.rules') }}</button>
                <button type="button" class="pill lg:hidden" :class="{ 'pill-active': tab === 'exercises' }" @click="tab = 'exercises'">✏️ {{ t('ga.exercises') }} ({{ unit.exercises.length }})</button>
                <button v-if="hasArabic" type="button" class="pill ms-auto" :class="{ 'pill-active': showArabic }" :title="t('ga.arabic_hint')" @click="showArabic = !showArabic">
                    <span class="ar text-base leading-none">ع</span> {{ t('ga.arabic') }}
                </button>
            </div>

            <div class="lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:gap-6">
                <aside :class="tab === 'rules' ? '' : 'hidden lg:block'">
                    <div class="card p-4 sm:p-6 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-y-auto">
                        <h2 class="mb-3 text-xs font-extrabold uppercase tracking-widest text-gray-400">📐 {{ t('ga.rules') }}</h2>
                        <figure v-if="unit.info_image" class="mb-3 overflow-hidden rounded-xl border-2 border-gray-100 bg-white dark:border-gray-800">
                            <img :src="route('grammatik.image', unit.info_image)" :alt="t('ga.picture')" class="mx-auto block w-auto max-w-full" loading="lazy" />
                        </figure>
                        <MarkdownNote :source="unit.info_md" />
                        <div v-if="showArabic && unit.info_ar" class="mt-4 border-t-2 border-gray-100 pt-4 dark:border-gray-800" dir="rtl">
                            <MarkdownNote :source="unit.info_ar" class="ar text-[15px]" />
                        </div>
                    </div>
                </aside>

                <section class="space-y-4" :class="tab === 'exercises' ? '' : 'hidden lg:block'">
                    <GrammatikExercise
                        v-for="ex in unit.exercises"
                        :key="unit.number + '-' + ex.key"
                        :unit="unit.number"
                        :unit-level="unit.level"
                        :exercise="ex"
                        :last="results?.[ex.key] || null"
                        :show-arabic="showArabic"
                    />
                </section>
            </div>

            <div class="mt-8 flex items-center justify-between gap-2">
                <Link v-if="prev" :href="route('grammatik.show', prev)" class="btn-secondary btn-sm">← {{ t('ga.unit', { n: prev }) }}</Link>
                <span v-else></span>
                <Link :href="route('grammatik.index', unit.level.toLowerCase())" class="btn-ghost btn-sm">{{ t('ga.all_units') }}</Link>
                <Link v-if="next" :href="route('grammatik.show', next)" class="btn-secondary btn-sm">{{ t('ga.unit', { n: next }) }} →</Link>
                <span v-else></span>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
