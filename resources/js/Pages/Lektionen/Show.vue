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
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({
    lektion: Object,
    categories: Array,
    canonicalCategories: Array,
    types: Array,
    words: Array,
    dialogLines: Array,
    grammarNotes: Array,
    bestScore: Number,
    completed: Boolean,
});

const { t } = useI18n();

const tab = ref('words');
const verbs = computed(() => props.words.filter((w) => w.type === 'verb' && w.conjugation));
const tabs = computed(() => [
    { key: 'words', label: `${t('lek.tab_words')} (${props.words.length})` },
    { key: 'flashcards', label: t('lek.tab_flashcards') },
    { key: 'verbs', label: `${t('lek.tab_verbs')} (${verbs.value.length})` },
    { key: 'grammar', label: `${t('lek.tab_grammar')} (${props.grammarNotes.length})` },
    { key: 'dialog', label: `${t('lek.tab_dialog')} (${props.dialogLines.length})` },
]);

const search = ref('');
const activeCategory = ref(null);
const grouped = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.categories
        .map((c) => ({
            ...c,
            words: props.words.filter(
                (w) => w.category_id === c.id && (!q || w.german.toLowerCase().includes(q) || w.arabic.includes(q) || (w.plural || '').toLowerCase().includes(q)),
            ),
        }))
        .filter((c) => c.words.length && (!activeCategory.value || c.id === activeCategory.value));
});

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

const typeBadge = {
    noun: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
    verb: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    adjective: 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-200',
    adverb: 'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-900/40 dark:text-fuchsia-200',
    country: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    language: 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-200',
    nationality: 'bg-lime-100 text-lime-800 dark:bg-lime-900/40 dark:text-lime-200',
};
</script>

<template>
    <Head :title="t('lek.lektion', { n: lektion.number })" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ t('lek.lektion', { n: lektion.number }) }}</div>
                    <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">{{ lektion.title }}</h1>
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="completed" class="badge bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">{{ t('lek.completed_with', { pct: bestScore }) }}</span>
                    <span v-else-if="bestScore !== null" class="badge bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">{{ t('dash.best', { pct: bestScore }) }}</span>
                    <Link :href="route('quiz.lektion', lektion.id)" class="btn-primary hidden sm:inline-flex">{{ t('lek.final_quiz') }}</Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <!-- Tabs: sticky under the top bar on mobile -->
            <div class="sticky top-14 z-20 -mx-4 mb-4 bg-gray-50/95 px-4 py-2 backdrop-blur dark:bg-gray-950/95 sm:static sm:mx-0 sm:mb-5 sm:bg-transparent sm:px-0 sm:py-0">
                <div class="overflow-x-auto">
                    <div class="flex w-max gap-1 rounded-xl bg-gray-200/70 p-1 dark:bg-gray-800">
                        <button
                            v-for="tb in tabs"
                            :key="tb.key"
                            type="button"
                            @click="tab = tb.key"
                            class="min-h-[40px] whitespace-nowrap rounded-lg px-3 text-sm font-medium transition"
                            :class="tab === tb.key ? 'bg-white text-gray-900 shadow dark:bg-gray-900 dark:text-white' : 'text-gray-600 hover:text-gray-900 dark:text-gray-300'"
                        >
                            {{ tb.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- WORDS -->
            <section v-show="tab === 'words'" class="space-y-4 pb-16 sm:pb-0">
                <div class="flex flex-wrap items-center gap-2">
                    <input v-model="search" class="input min-h-[44px] flex-1 sm:max-w-xs" :placeholder="t('lek.search')" />
                    <button type="button" class="btn-primary min-h-[44px]" @click="openCreate()">+ {{ t('lek.add_word') }}</button>
                </div>

                <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    <div class="flex w-max gap-1.5">
                        <button type="button" class="badge min-h-[32px] px-3 ring-1" :class="!activeCategory ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-gray-700 ring-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700'" @click="activeCategory = null">
                            {{ t('common.all') }}
                        </button>
                        <button
                            v-for="c in categories"
                            :key="c.id"
                            type="button"
                            class="badge min-h-[32px] px-3 ring-1"
                            :class="activeCategory === c.id ? 'bg-brand-600 text-white ring-brand-600' : 'bg-white text-gray-700 ring-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700'"
                            @click="activeCategory = activeCategory === c.id ? null : c.id"
                        >
                            {{ c.name }}
                        </button>
                    </div>
                </div>

                <div v-for="c in grouped" :key="c.id" class="card overflow-hidden">
                    <div class="flex items-center justify-between bg-gray-50 px-4 py-2 dark:bg-gray-800/60">
                        <h2 class="font-semibold">{{ c.name }} <span class="text-sm font-normal text-gray-500">({{ c.words.length }})</span></h2>
                        <button type="button" class="min-h-[36px] text-xs font-medium text-brand-600 hover:underline dark:text-brand-400" @click="openCreate(c.name)">+ {{ t('common.add') }}</button>
                    </div>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="w in c.words" :key="w.id" class="flex items-center gap-2 px-3 py-2.5 sm:gap-3 sm:px-4 sm:py-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <Link :href="route('words.show', w.id)" class="font-semibold hover:underline">
                                        <span v-if="w.article" class="me-1 text-gray-500">{{ w.article }}</span>{{ w.german }}
                                    </Link>
                                    <span v-if="w.plural" class="text-sm text-gray-500">, {{ w.plural_suffix || w.plural }}<span v-if="(w.plural_suffix || '').startsWith('-') || (w.plural_suffix || '').startsWith('¨')"> → {{ w.plural }}</span></span>
                                    <span class="badge hidden sm:inline-flex" :class="typeBadge[w.type] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'">{{ t('type.' + w.type) }}</span>
                                    <LevelDots :level="w.level" />
                                </div>
                                <ArabicText :text="w.arabic" tag="div" class="mt-0.5 text-base text-gray-700 dark:text-gray-200 sm:hidden" />
                                <div v-if="w.example_de || w.note" class="mt-0.5 text-xs text-gray-500">
                                    <span v-if="w.example_de" class="italic">{{ w.example_de }}</span>
                                    <span v-if="w.example_de && w.note"> · </span>
                                    <span v-if="w.note">{{ w.note }}</span>
                                </div>
                                <div v-if="w.type === 'country' && w.meta" class="mt-0.5 text-xs text-gray-500">
                                    {{ w.meta.language }} · {{ w.meta.nationality_m }} / {{ w.meta.nationality_f }}
                                </div>
                            </div>
                            <ArabicText :text="w.arabic" tag="div" class="hidden max-w-[45%] shrink-0 text-lg sm:block" />
                            <div class="flex shrink-0 gap-0.5">
                                <button type="button" class="grid h-10 w-10 place-items-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800" @click="openEdit(w)" :aria-label="t('common.edit')">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4L16.5 3.5Z" /></svg>
                                </button>
                                <button type="button" class="grid h-10 w-10 place-items-center rounded-lg text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40" @click="remove(w)" :aria-label="t('common.delete')">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14" /></svg>
                                </button>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>

            <section v-if="tab === 'flashcards'">
                <Flashcards :words="words" />
            </section>

            <section v-show="tab === 'verbs'">
                <ConjugationTable v-if="verbs.length" :verbs="verbs" />
                <p v-else class="text-gray-500">{{ t('lek.no_verbs') }}</p>
            </section>

            <section v-show="tab === 'grammar'" class="space-y-4">
                <article v-for="n in grammarNotes" :key="n.id" class="card p-4 sm:p-6">
                    <h2 class="mb-2 text-lg font-semibold">{{ n.title }}</h2>
                    <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0"><MarkdownNote :source="n.body_md" /></div>
                </article>
                <p v-if="!grammarNotes.length" class="text-gray-500">{{ t('lek.no_grammar') }}</p>
            </section>

            <section v-show="tab === 'dialog'">
                <DialogTable v-if="dialogLines.length" :lines="dialogLines" />
                <p v-else class="text-gray-500">{{ t('lek.no_dialog') }}</p>
            </section>
        </div>

        <!-- Mobile: floating final-quiz button above the tab bar -->
        <Link :href="route('quiz.lektion', lektion.id)" class="btn-primary fixed bottom-[calc(64px+env(safe-area-inset-bottom)+12px)] right-4 z-30 min-h-[52px] rounded-full px-5 shadow-lg sm:hidden">
            ⚡ {{ t('lek.final_quiz') }}
        </Link>

        <Modal :show="showForm" max-width="2xl" @close="showForm = false">
            <div class="p-5">
                <h3 class="mb-4 text-lg font-semibold">{{ editing ? t('lek.edit_word') : t('lek.add_word') }}</h3>
                <WordForm
                    :word="editing"
                    :lektion-id="lektion.id"
                    :types="types"
                    :categories="categoryChoices"
                    :default-category="formCategory"
                    @saved="showForm = false"
                    @cancel="showForm = false"
                />
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
