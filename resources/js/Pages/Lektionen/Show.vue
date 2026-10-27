<script setup>
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import LevelDots from '@/Components/Trainer/LevelDots.vue';
import Flashcards from '@/Components/Trainer/Flashcards.vue';
import ConjugationTable from '@/Components/Trainer/ConjugationTable.vue';
import DialogTable from '@/Components/Trainer/DialogTable.vue';
import MarkdownNote from '@/Components/Trainer/MarkdownNote.vue';
import WordForm from '@/Components/Trainer/WordForm.vue';
import VocabImage from '@/Components/Trainer/VocabImage.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { useSpeech } from '@/composables/useSpeech';

const props = defineProps({
    lektion: Object,
    prev: Object,
    next: Object,
    categories: Array,
    canonicalCategories: Array,
    types: Array,
    words: Array,
    dialogLines: Array,
    grammarNotes: Array,
    readings: Array,
    writingTasks: Array,
    sections: Array,
    sets: Array,
    progress: Object,
    bestScore: Number,
    completed: Boolean,
    vocabModes: Array,
});

const { t, locale } = useI18n();
const speech = useSpeech();

// ---------- sections / tabs
const sectionIcons = { objectives: '🎯', vocab: '🖼️', grammar: '📐', dialog: '💬', reading: '📖', writing: '✍️', exercises: '✏️', revision: '🔁', test: '🏁' };
const initial = (() => {
    try {
        const hash = window.location.hash.replace('#', '');
        if (hash && props.sections.some((s) => s.key === hash)) return hash;
    } catch (e) {}
    return props.sections.find((s) => !props.progress[s.key]?.completed)?.key || 'objectives';
})();
const tab = ref(initial);
const tabIndex = computed(() => props.sections.findIndex((s) => s.key === tab.value));
const doneCount = computed(() => props.sections.filter((s) => props.progress[s.key]?.completed).length);
const completion = computed(() => (props.sections.length ? Math.round((doneCount.value * 100) / props.sections.length) : 0));

function go(key) {
    tab.value = key;
    try {
        history.replaceState(null, '', '#' + key);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (e) {}
}
function nextSection() {
    const n = props.sections[tabIndex.value + 1];
    if (n) go(n.key);
}
function markDone(key, data = {}) {
    router.post(route('course.section', [props.lektion.id, key]), { data }, { preserveScroll: true, preserveState: true });
}
function markAndNext(key) {
    markDone(key);
    nextSection();
}

// ---------- vocabulary
const search = ref('');
const activeCategory = ref(null);
const showArabic = ref(true);
const vocabView = ref('cards'); // cards | list | flashcards | verbs
const grouped = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.categories
        .map((c) => ({
            ...c,
            words: props.words.filter(
                (w) =>
                    w.category_id === c.id &&
                    (!q || w.german.toLowerCase().includes(q) || (w.english || '').toLowerCase().includes(q) || w.arabic.includes(q) || (w.plural || '').toLowerCase().includes(q)),
            ),
        }))
        .filter((c) => c.words.length && (!activeCategory.value || c.id === activeCategory.value));
});
const verbs = computed(() => props.words.filter((w) => w.type === 'verb' && w.conjugation));
const wordsWithImages = computed(() => props.words.filter((w) => w.image).length);
const learnedWords = computed(() => props.words.filter((w) => w.level >= 1).length);

const modeLabels = {
    image_word: 'vocab.mode_image_word', word_image: 'vocab.mode_word_image', en_de: 'vocab.mode_en_de', de_en: 'vocab.mode_de_en',
    typing: 'vocab.mode_typing', article: 'vocab.mode_article', plural: 'vocab.mode_plural', sentence: 'vocab.mode_sentence',
    spelling: 'vocab.mode_spelling', match: 'vocab.mode_match', speak: 'vocab.mode_speak', mixed: 'vocab.mode_mixed',
};
function startVocab(mode) {
    router.post(route('exercises.start'), { kind: 'vocab', lektion_id: props.lektion.id, mode, count: mode === 'mixed' ? 16 : 12 });
}
function startSet(section) {
    router.post(route('exercises.start'), { kind: 'set', lektion_id: props.lektion.id, section });
}
function startTest() {
    router.post(route('exercises.start'), { kind: 'test', lektion_id: props.lektion.id });
}
function toggleDifficult(w) {
    router.post(route('words.difficult', w.id), {}, { preserveScroll: true, preserveState: true });
}

// ---------- word editing (existing feature)
const editing = ref(null);
const showForm = ref(false);
const formCategory = ref('Nomen');
function openCreate(categoryName = 'Nomen') {
    editing.value = null;
    formCategory.value = categoryName;
    showForm.value = true;
}
function openEdit(w) {
    editing.value = w;
    showForm.value = true;
}
function remove(w) {
    if (confirm(t('lek.confirm_delete', { word: w.display }))) router.delete(route('words.destroy', w.id), { preserveScroll: true });
}
const categoryChoices = computed(() => {
    const names = new Set([...props.canonicalCategories, ...props.categories.map((c) => c.name)]);
    return [...names].map((n) => ({ id: props.categories.find((c) => c.name === n)?.id ?? null, name: n }));
});

// ---------- readings
const openTranslation = ref({}); // reading id -> 'en' | 'ar' | null
const showAr = ref({ objectives: true, grammar: false, revision: false });
function toggleTranslation(id, lang) {
    openTranslation.value[id] = openTranslation.value[id] === lang ? null : lang;
}

// ---------- writing (drafts are stored in lesson_progress.data.texts of the 'writing' section)
const savedTexts = computed(() => props.progress.writing?.data?.texts || {});
const texts = ref({ ...savedTexts.value });
const openModel = ref({}); // task id -> bool
const savingWriting = ref(false);
const writingDirty = computed(() => props.writingTasks.some((w) => (texts.value[w.key] || '') !== (savedTexts.value[w.key] || '')));
function wordCount(s) {
    return (s || '').trim().split(/\s+/).filter(Boolean).length;
}
function writingTarget(w) {
    // "40–60" / "ca. 30" are word counts; "Formular", "6 Fragen" … are shown as they are
    return /^(ca\. )?\d+(–\d+)?$/.test(w.data.words || '') ? t('lek.writing_target', { n: w.data.words }) : w.data.words;
}
function saveWriting(complete = false) {
    savingWriting.value = true;
    router.post(
        route('course.section', [props.lektion.id, 'writing']),
        { data: { texts: texts.value }, complete },
        { preserveScroll: true, preserveState: true, onFinish: () => (savingWriting.value = false) },
    );
}

const typeBadge = {
    noun: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
    verb: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    adjective: 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-200',
    adverb: 'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-900/40 dark:text-fuchsia-200',
    expression: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
    country: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    language: 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-200',
    nationality: 'bg-lime-100 text-lime-800 dark:bg-lime-900/40 dark:text-lime-200',
};
const sets = computed(() => props.sets.map((s) => ({ ...s, best: props.progress.exercises?.data?.sets?.[s.section] ?? null })));
</script>

<template>
    <Head :title="t('lek.lektion', { n: lektion.number }) + ' – ' + lektion.title" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ lektion.level }} · {{ t('lek.lektion', { n: lektion.number }) }}</div>
                    <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">{{ lektion.title }}</h1>
                    <div v-if="lektion.subtitle" class="truncate text-xs text-gray-500">{{ lektion.subtitle }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="completed" class="badge bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">✓ {{ t('dash.completed') }}</span>
                    <span v-else class="badge bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">{{ completion }} %</span>
                    <Link v-if="prev" :href="route('lektionen.show', prev.id)" class="btn-secondary !min-h-[40px] !px-3" :title="prev.title">‹ {{ prev.number }}</Link>
                    <Link v-if="next" :href="route('lektionen.show', next.id)" class="btn-secondary !min-h-[40px] !px-3" :title="next.title">{{ next.number }} ›</Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <!-- Section tabs -->
            <div class="sticky top-14 z-20 -mx-4 mb-4 bg-gray-50/95 px-4 py-2 backdrop-blur dark:bg-gray-950/95 sm:mx-0 sm:mb-5 sm:rounded-2xl sm:px-0 lg:top-0">
                <div class="overflow-x-auto">
                    <ol class="flex w-max gap-1 rounded-2xl border-2 border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-900">
                        <li v-for="(s, i) in sections" :key="s.key">
                            <button
                                type="button"
                                @click="go(s.key)"
                                class="pill"
                                :class="{ 'pill-active': tab === s.key }"
                                :aria-current="tab === s.key ? 'page' : undefined"
                            >
                                <span class="grid h-6 w-6 place-items-center rounded-full text-[11px] font-black" :class="progress[s.key]?.completed ? 'bg-pop-green text-white' : 'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-200'">
                                    {{ progress[s.key]?.completed ? '✓' : i + 1 }}
                                </span>
                                {{ t('sec.' + s.key) }}
                            </button>
                        </li>
                    </ol>
                </div>
            </div>

            <!-- LERNZIELE -->
            <section v-if="tab === 'objectives'" class="space-y-4">
                <div class="card p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">🎯 {{ t('sec.objectives') }}</h2>
                            <p class="mt-1 text-sm text-gray-500">{{ t('lek.objectives_intro') }}</p>
                        </div>
                        <button type="button" class="btn-secondary btn-sm shrink-0" :class="{ 'pill-active': showAr.objectives }" @click="showAr.objectives = !showAr.objectives">{{ t('lek.arabic') }}</button>
                    </div>
                    <ul class="mt-4 space-y-2">
                        <li v-for="(o, i) in lektion.objectives" :key="i" class="flex gap-3 rounded-xl bg-gray-50 p-3 dark:bg-gray-800/60">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-600 text-sm font-bold text-white">{{ i + 1 }}</span>
                            <div>
                                <div class="font-medium">{{ o.de }}</div>
                                <div class="text-sm text-gray-500">{{ o.en }}</div>
                                <ArabicText v-if="showAr.objectives && o.ar" :text="o.ar" tag="div" class="mt-1 text-base text-gray-700 dark:text-gray-200" />
                            </div>
                        </li>
                    </ul>
                    <div v-if="lektion.source_pages" class="mt-4 text-xs text-gray-400">
                        {{ t('lek.source') }}: DaF kompakt neu A1 – Kursbuch S. {{ lektion.source_pages.kursbuch?.join('–') }}, Übungsbuch S. {{ lektion.source_pages.uebungsbuch?.join('–') }}
                    </div>
                    <div class="mt-5 grid gap-2 sm:flex">
                        <button type="button" class="btn-primary" @click="markAndNext('objectives')">{{ t('lek.start_lesson') }} →</button>
                    </div>
                </div>
                <div class="card p-4 text-sm text-gray-600 dark:text-gray-300">
                    <strong>{{ t('lek.how_it_works') }}</strong> {{ t('lek.how_text') }}
                </div>
            </section>

            <!-- WORTSCHATZ -->
            <section v-if="tab === 'vocab'" class="space-y-4 pb-16 sm:pb-0">
                <div class="card p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-lg font-semibold">🖼️ {{ t('sec.vocab') }}</h2>
                            <p class="text-sm text-gray-500">{{ t('vocab.intro', { n: words.length, img: wordsWithImages, learned: learnedWords }) }}</p>
                        </div>
                        <div class="flex gap-1 rounded-2xl border-2 border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-900">
                            <button v-for="v in ['cards', 'list', 'flashcards', 'verbs']" :key="v" type="button" class="pill !min-h-[36px] text-xs" :class="{ 'pill-active': vocabView === v }" @click="vocabView = v">
                                {{ t('vocab.view_' + v) }}
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button v-for="m in vocabModes" :key="m" type="button" class="btn-sm" :class="m === 'mixed' ? 'btn-primary' : 'btn-secondary'" @click="startVocab(m)">
                            {{ t(modeLabels[m]) }}
                        </button>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">{{ t('vocab.modes_hint') }}</p>
                </div>

                <template v-if="vocabView === 'cards' || vocabView === 'list'">
                    <div class="flex flex-wrap items-center gap-2">
                        <input v-model="search" class="input min-h-[44px] flex-1 sm:max-w-xs" :placeholder="t('lek.search')" />
                        <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300"><input v-model="showArabic" type="checkbox" class="rounded" /> العربية</label>
                        <button type="button" class="btn-secondary !min-h-[40px] text-xs" @click="openCreate()">+ {{ t('lek.add_word') }}</button>
                    </div>
                    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                        <div class="flex w-max gap-1.5">
                            <button type="button" class="badge min-h-[32px] px-3 ring-1" :class="!activeCategory ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-gray-700 ring-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700'" @click="activeCategory = null">{{ t('common.all') }}</button>
                            <button v-for="c in categories" :key="c.id" type="button" class="badge min-h-[32px] px-3 ring-1" :class="activeCategory === c.id ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-gray-700 ring-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700'" @click="activeCategory = activeCategory === c.id ? null : c.id">{{ c.name }}</button>
                        </div>
                    </div>

                    <div v-for="c in grouped" :key="c.id" class="space-y-2">
                        <h3 class="flex items-center justify-between px-1 pt-2 font-semibold">
                            <span>{{ c.name }} <span class="text-sm font-normal text-gray-500">({{ c.words.length }})</span></span>
                            <button type="button" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400" @click="openCreate(c.name)">+ {{ t('common.add') }}</button>
                        </h3>

                        <!-- Card view: images large -->
                        <div v-if="vocabView === 'cards'" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            <article v-for="w in c.words" :key="w.id" class="card group relative flex flex-col p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <VocabImage :src="w.image" :alt="w.display" :label="w.german" size="lg" class="mx-auto" />
                                </div>
                                <div class="mt-2 min-w-0 text-center">
                                    <div class="font-semibold leading-tight">
                                        <span v-if="w.article" class="text-gray-500">{{ w.article }} </span>{{ w.german }}
                                    </div>
                                    <div v-if="w.plural" class="text-xs text-gray-500">Pl. {{ w.plural }}</div>
                                    <div class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ w.english }}</div>
                                    <ArabicText v-if="showArabic" :text="w.arabic" tag="div" class="text-base text-gray-600 dark:text-gray-300" />
                                    <div v-if="w.example_de" class="mt-1 text-xs italic text-gray-500">{{ w.example_de }}</div>
                                    <div v-if="w.example_de && w.example_en" class="text-[11px] text-gray-400">{{ w.example_en }}</div>
                                </div>
                                <div class="mt-auto flex items-center justify-between pt-2">
                                    <LevelDots :level="w.level" />
                                    <div class="flex gap-0.5">
                                        <button v-if="speech.supported.value" type="button" class="grid h-8 w-8 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800" :aria-label="t('ex.listen')" @click="speech.speak(w.display)">🔊</button>
                                        <button type="button" class="grid h-8 w-8 place-items-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800" :class="w.is_difficult ? 'text-amber-500' : 'text-gray-300'" :aria-label="t('vocab.mark_difficult')" :title="t('vocab.mark_difficult')" @click="toggleDifficult(w)">★</button>
                                        <button type="button" class="grid h-8 w-8 place-items-center rounded-lg text-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800" @click="openEdit(w)" :aria-label="t('common.edit')">✎</button>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <!-- List view -->
                        <div v-else class="card overflow-hidden">
                            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                <li v-for="w in c.words" :key="w.id" class="flex items-center gap-3 px-3 py-2.5 sm:px-4">
                                    <VocabImage :src="w.image" :alt="w.display" :label="w.german" size="sm" />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <Link :href="route('words.show', w.id)" class="font-semibold hover:underline"><span v-if="w.article" class="me-1 text-gray-500">{{ w.article }}</span>{{ w.german }}</Link>
                                            <span v-if="w.plural" class="text-sm text-gray-500">, {{ w.plural_suffix || w.plural }}<span v-if="(w.plural_suffix || '').startsWith('-') || (w.plural_suffix || '').startsWith('¨')"> → {{ w.plural }}</span></span>
                                            <span class="badge hidden sm:inline-flex" :class="typeBadge[w.type] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'">{{ t('type.' + w.type) }}</span>
                                            <LevelDots :level="w.level" />
                                        </div>
                                        <div class="text-sm text-gray-700 dark:text-gray-200">{{ w.english }}</div>
                                        <div v-if="w.example_de || w.note" class="text-xs text-gray-500"><span v-if="w.example_de" class="italic">{{ w.example_de }}</span><span v-if="w.example_de && w.note"> · </span><span v-if="w.note">{{ w.note }}</span></div>
                                    </div>
                                    <ArabicText v-if="showArabic" :text="w.arabic" tag="div" class="hidden max-w-[35%] shrink-0 text-lg sm:block" />
                                    <div class="flex shrink-0 gap-0.5">
                                        <button type="button" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800" :class="w.is_difficult ? 'text-amber-500' : 'text-gray-300'" @click="toggleDifficult(w)" :aria-label="t('vocab.mark_difficult')">★</button>
                                        <button type="button" class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800" @click="openEdit(w)" :aria-label="t('common.edit')">✎</button>
                                        <button type="button" class="grid h-9 w-9 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" @click="remove(w)" :aria-label="t('common.delete')">🗑</button>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </template>

                <Flashcards v-if="vocabView === 'flashcards'" :words="words" />

                <template v-if="vocabView === 'verbs'">
                    <ConjugationTable v-if="verbs.length" :verbs="verbs" />
                    <p v-else class="text-gray-500">{{ t('lek.no_verbs') }}</p>
                </template>

                <div class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ t('vocab.done_hint') }}</span>
                    <button type="button" class="btn-primary" @click="markAndNext('vocab')">{{ t('lek.section_done') }} →</button>
                </div>
            </section>

            <!-- GRAMMATIK -->
            <section v-if="tab === 'grammar'" class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">{{ t('lek.grammar_intro') }}</p>
                    <button type="button" class="btn-secondary btn-sm shrink-0" :class="{ 'pill-active': showAr.grammar }" @click="showAr.grammar = !showAr.grammar">{{ t('lek.arabic') }}</button>
                </div>
                <article v-for="n in grammarNotes" :key="n.id" class="card p-4 sm:p-6">
                    <h2 class="mb-2 text-lg font-semibold">{{ n.title }}</h2>
                    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0"><MarkdownNote :source="n.body_md" /></div>
                    <div v-if="showAr.grammar && n.body_ar_md" class="ar card-flat mt-4 p-4 text-[15px]" dir="rtl"><MarkdownNote :source="n.body_ar_md" /></div>
                    <div v-if="n.source_page" class="mt-2 text-[11px] text-gray-400">{{ t('lek.source') }}: S. {{ n.source_page }}</div>
                </article>
                <p v-if="!grammarNotes.length" class="text-gray-500">{{ t('lek.no_grammar') }}</p>
                <div v-if="verbs.length" class="card p-4 sm:p-6">
                    <h2 class="mb-3 text-lg font-semibold">{{ t('lek.tab_verbs') }}</h2>
                    <ConjugationTable :verbs="verbs" />
                </div>
                <div class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <button type="button" class="btn-secondary" @click="startSet('grammar')">✏️ {{ t('lek.practice_grammar') }}</button>
                    <button type="button" class="btn-primary" @click="markAndNext('grammar')">{{ t('lek.section_done') }} →</button>
                </div>
            </section>

            <!-- SÄTZE UND DIALOGE -->
            <section v-if="tab === 'dialog'" class="space-y-4">
                <p class="text-sm text-gray-500">{{ t('lek.dialog_intro') }}</p>
                <DialogTable v-if="dialogLines.length" :lines="dialogLines" />
                <p v-else class="text-gray-500">{{ t('lek.no_dialog') }}</p>
                <div v-if="speech.supported.value" class="card p-4">
                    <h3 class="mb-2 font-semibold">🔊 {{ t('lek.listen_lines') }}</h3>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="l in dialogLines" :key="'s' + l.id" type="button" class="btn-secondary !min-h-[36px] !px-3 text-xs" @click="speech.speak((l.question_formal || l.question_informal || '') + ' ' + l.answer)">{{ l.topic }}</button>
                    </div>
                </div>
                <div class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <button type="button" class="btn-secondary" @click="startSet('dialog')">💬 {{ t('lek.practice_dialog') }}</button>
                    <button type="button" class="btn-primary" @click="markAndNext('dialog')">{{ t('lek.section_done') }} →</button>
                </div>
            </section>

            <!-- LESEN -->
            <section v-if="tab === 'reading'" class="space-y-4">
                <p class="text-sm text-gray-500">{{ t('lek.reading_intro') }}</p>
                <article v-for="r in readings" :key="r.id" class="card p-4 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-lg font-semibold">{{ r.title }}</h2>
                        <div class="flex gap-2">
                            <button v-if="speech.supported.value" type="button" class="btn-secondary !min-h-[36px] !px-3 text-xs" @click="speech.speak(r.text_md.replace(/[*_#>]/g, ''))">🔊 {{ t('ex.listen') }}</button>
                            <button type="button" class="btn-secondary btn-sm" :class="{ 'pill-active': openTranslation[r.id] === 'en' }" @click="toggleTranslation(r.id, 'en')">🇬🇧 EN</button>
                            <button v-if="r.translation_ar_md" type="button" class="btn-secondary btn-sm" :class="{ 'pill-active': openTranslation[r.id] === 'ar' }" @click="toggleTranslation(r.id, 'ar')">{{ t('lek.arabic') }}</button>
                        </div>
                    </div>
                    <MarkdownNote :source="r.text_md" class="mt-3" />
                    <div v-if="openTranslation[r.id] === 'en'" class="card-flat mt-3 p-3 text-sm text-gray-600 dark:text-gray-300"><MarkdownNote :source="r.translation_md" /></div>
                    <div v-if="openTranslation[r.id] === 'ar'" class="ar card-flat mt-3 p-4 text-[15px]" dir="rtl"><MarkdownNote :source="r.translation_ar_md" /></div>
                    <div class="mt-2 text-[11px] text-gray-400">{{ t('lek.reading_note') }}<span v-if="r.source_page"> · S. {{ r.source_page }}</span></div>
                </article>
                <p v-if="!readings.length" class="text-gray-500">{{ t('lek.no_reading') }}</p>
                <div class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ t('lek.reading_questions', { n: readings.reduce((a, r) => a + (r.questions?.length || 0), 0) }) }}</span>
                    <button type="button" class="btn-primary" @click="startSet('reading')">📖 {{ t('lek.practice_reading') }}</button>
                </div>
            </section>

            <!-- SCHREIBEN -->
            <section v-if="tab === 'writing'" class="space-y-4">
                <p class="text-sm text-gray-500">{{ t('lek.writing_intro') }}</p>
                <article v-for="(w, i) in writingTasks" :key="w.id" class="card p-4 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">✍️ {{ i + 1 }}<span v-if="w.data.text_type"> · {{ w.data.text_type }}</span></div>
                            <h2 class="text-lg font-semibold">{{ w.data.title || t('sec.writing') + ' ' + (i + 1) }}</h2>
                        </div>
                        <div class="flex gap-2">
                            <button v-if="w.data.translation" type="button" class="btn-secondary btn-sm" :class="{ 'pill-active': openTranslation['w' + w.id] === 'en' }" @click="toggleTranslation('w' + w.id, 'en')">🇬🇧 EN</button>
                            <button v-if="w.data.translation_ar" type="button" class="btn-secondary btn-sm" :class="{ 'pill-active': openTranslation['w' + w.id] === 'ar' }" @click="toggleTranslation('w' + w.id, 'ar')">{{ t('lek.arabic') }}</button>
                        </div>
                    </div>
                    <p class="mt-2 whitespace-pre-line">{{ w.prompt }}</p>
                    <div v-if="openTranslation['w' + w.id] === 'en'" class="card-flat mt-3 p-3 text-sm text-gray-600 dark:text-gray-300">{{ w.data.translation }}</div>
                    <div v-if="openTranslation['w' + w.id] === 'ar'" class="ar card-flat mt-3 p-4 text-[15px]" dir="rtl">{{ w.data.translation_ar }}</div>

                    <div v-if="w.data.points?.length || w.data.phrases?.length" class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div v-if="w.data.points?.length" class="card-flat p-3">
                            <div class="text-xs font-extrabold uppercase tracking-wide text-gray-400">{{ t('lek.writing_points') }}</div>
                            <ul class="mt-1 list-disc space-y-0.5 ps-5 text-sm">
                                <li v-for="p in w.data.points" :key="p">{{ p }}</li>
                            </ul>
                        </div>
                        <div v-if="w.data.phrases?.length" class="card-flat p-3">
                            <div class="text-xs font-extrabold uppercase tracking-wide text-gray-400">{{ t('lek.writing_phrases') }}</div>
                            <ul class="mt-1 space-y-0.5 text-sm italic">
                                <li v-for="p in w.data.phrases" :key="p">{{ p }}</li>
                            </ul>
                        </div>
                    </div>

                    <textarea v-model="texts[w.key]" rows="7" class="input mt-3 text-base" :placeholder="t('ex.write_placeholder')"></textarea>
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <span class="text-xs text-gray-500">{{ t('lek.writing_count', { n: wordCount(texts[w.key]) }) }}<span v-if="w.data.words"> · {{ writingTarget(w) }}</span></span>
                        <button type="button" class="btn-secondary btn-sm" :class="{ 'pill-active': openModel[w.id] }" @click="openModel[w.id] = !openModel[w.id]">{{ openModel[w.id] ? t('lek.hide_model') : t('lek.show_model') }}</button>
                    </div>
                    <div v-if="openModel[w.id]" class="card-flat mt-3 p-4">
                        <div class="text-xs font-extrabold uppercase tracking-wide text-gray-400">{{ t('ex.model_answer') }}</div>
                        <div class="mt-1 whitespace-pre-line text-sm font-semibold">{{ w.data.model_answer }}</div>
                        <template v-if="w.data.checklist?.length">
                            <div class="mt-3 text-xs font-extrabold uppercase tracking-wide text-gray-400">{{ t('ex.checklist') }}</div>
                            <ul class="mt-1 space-y-1 text-sm">
                                <li v-for="c in w.data.checklist" :key="c" class="flex gap-2"><span class="text-gray-400">☐</span>{{ c }}</li>
                            </ul>
                        </template>
                    </div>
                    <div v-if="w.source_page" class="mt-2 text-[11px] text-gray-400">{{ t('lek.source') }}: S. {{ w.source_page }}</div>
                </article>
                <p v-if="!writingTasks.length" class="text-gray-500">{{ t('lek.no_writing') }}</p>
                <div class="card flex flex-wrap items-center justify-between gap-3 p-4">
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ writingDirty ? t('lek.writing_unsaved') : t('lek.writing_saved') }}</span>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary" :disabled="!writingDirty || savingWriting" @click="saveWriting()">💾 {{ t('lek.writing_save') }}</button>
                        <button type="button" class="btn-primary" @click="saveWriting(true); nextSection()">{{ t('lek.section_done') }} →</button>
                    </div>
                </div>
            </section>

            <!-- ÜBUNGEN -->
            <section v-if="tab === 'exercises'" class="space-y-4">
                <p class="text-sm text-gray-500">{{ t('lek.exercises_intro') }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="s in sets" :key="s.section" class="card flex items-center justify-between gap-3 p-4">
                        <div>
                            <div class="font-semibold">{{ t('set.' + s.section) }}</div>
                            <div class="text-xs text-gray-500">{{ t('lek.items', { n: s.count }) }}<span v-if="s.best !== null"> · {{ t('course.test_best', { pct: s.best }) }}</span></div>
                        </div>
                        <button type="button" class="btn-primary !min-h-[44px]" @click="startSet(s.section)">{{ s.best !== null ? t('common.again') : t('lek.start') }}</button>
                    </div>
                </div>
                <div class="card p-4 text-sm text-gray-600 dark:text-gray-300">{{ t('lek.exercises_hint') }}</div>
            </section>

            <!-- WIEDERHOLUNG -->
            <section v-if="tab === 'revision'" class="space-y-4">
                <article class="card p-4 sm:p-6">
                    <div class="mb-3 flex justify-end">
                        <button v-if="lektion.revision_ar_md" type="button" class="btn-secondary btn-sm" :class="{ 'pill-active': showAr.revision }" @click="showAr.revision = !showAr.revision">{{ t('lek.arabic') }}</button>
                    </div>
                    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0"><MarkdownNote :source="lektion.revision_md || ''" /></div>
                    <div v-if="showAr.revision && lektion.revision_ar_md" class="ar card-flat mt-4 p-4 text-[15px]" dir="rtl"><MarkdownNote :source="lektion.revision_ar_md" /></div>
                </article>
                <div class="grid gap-3 sm:grid-cols-3">
                    <button type="button" class="btn-secondary min-h-[48px]" @click="startVocab('mixed')">🖼️ {{ t('lek.revise_vocab') }}</button>
                    <Link :href="route('quiz.lektion', lektion.id)" class="btn-secondary min-h-[48px]">⚡ {{ t('lek.final_quiz') }}</Link>
                    <button type="button" class="btn-primary min-h-[48px]" @click="markAndNext('revision')">{{ t('lek.section_done') }} →</button>
                </div>
            </section>

            <!-- LEKTIONSTEST -->
            <section v-if="tab === 'test'" class="space-y-4">
                <div class="card p-5 sm:p-6">
                    <h2 class="text-lg font-semibold">🏁 {{ t('sec.test') }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ t('lek.test_intro') }}</p>
                    <div v-if="progress.test?.data?.best !== undefined" class="mt-3 text-sm">
                        {{ t('course.test_best', { pct: progress.test.data.best }) }} · {{ t('lek.attempts', { n: progress.test.data.attempts }) }}
                        <span v-if="progress.test?.completed" class="badge ms-2 bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">{{ t('quiz.passed', { pct: 80 }) }}</span>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <button type="button" class="btn-primary min-h-[52px] px-6" @click="startTest">{{ t('lek.start_test') }}</button>
                        <Link v-if="next" :href="route('lektionen.show', next.id)" class="btn-secondary min-h-[52px]">{{ t('lek.next_lesson', { n: next.number }) }} →</Link>
                        <Link v-else :href="route('course.dashboard')" class="btn-secondary min-h-[52px]">{{ t('course.title', { level: lektion.level }) }}</Link>
                    </div>
                </div>
            </section>

            <!-- prev / next -->
            <nav class="mt-8 flex items-center justify-between gap-2 text-sm">
                <Link v-if="prev" :href="route('lektionen.show', prev.id)" class="btn-secondary">‹ {{ t('lek.lektion', { n: prev.number }) }}</Link>
                <span v-else></span>
                <Link :href="route('course.dashboard')" class="text-brand-600 hover:underline dark:text-brand-400">{{ t('course.title', { level: lektion.level }) }}</Link>
                <Link v-if="next" :href="route('lektionen.show', next.id)" class="btn-secondary">{{ t('lek.lektion', { n: next.number }) }} ›</Link>
                <span v-else></span>
            </nav>
        </div>

        <Modal :show="showForm" max-width="2xl" @close="showForm = false">
            <div class="p-5">
                <h3 class="mb-4 text-lg font-semibold">{{ editing ? t('lek.edit_word') : t('lek.add_word') }}</h3>
                <WordForm :word="editing" :lektion-id="lektion.id" :types="types" :categories="categoryChoices" :default-category="formCategory" @saved="showForm = false" @cancel="showForm = false" />
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
