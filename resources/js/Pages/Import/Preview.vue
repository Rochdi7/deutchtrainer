<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({ preview: Object, payload: Object, types: Array, categories: Array, rawTextExcerpt: String });

const page = usePage();
const { t, dateLocale } = useI18n();
const status = ref(props.preview.status);
const chunks = reactive({ done: props.preview.chunks_done, total: props.preview.chunks_total });
const error = ref(props.preview.error);

// Editable copy of the payload.
const words = ref(props.payload.words.map((w) => ({ ...w })));
const conjugations = ref(props.payload.conjugations || []);
const dialogLines = ref(props.payload.dialog_lines || []);
const grammarNotes = ref(props.payload.grammar_notes || []);
const dirty = ref(false);
const saving = ref(false);
const filter = ref('');
const showRaw = ref(false);
const tab = ref('words');

const filtered = computed(() => {
    const q = filter.value.trim().toLowerCase();
    return words.value.filter((w) => !q || w.german.toLowerCase().includes(q) || w.arabic.includes(q) || w.category.toLowerCase().includes(q));
});
const duplicates = computed(() => words.value.filter((w) => w._duplicate).length);
const categoryList = computed(() => [...new Set([...props.categories, ...words.value.map((w) => w.category)])]);

let timer;
async function poll() {
    if (!['pending', 'processing'].includes(status.value)) return;
    try {
        const { data } = await axios.get(route('import.status', props.preview.id));
        status.value = data.status;
        chunks.done = data.chunks_done;
        chunks.total = data.chunks_total;
        error.value = data.error;
        if (data.status === 'ready') router.reload();
    } catch (e) {}
}
onMounted(() => (timer = setInterval(poll, 3000)));
onBeforeUnmount(() => clearInterval(timer));

function removeRow(w) {
    words.value = words.value.filter((x) => x !== w);
    dirty.value = true;
}
function addRow() {
    words.value.unshift({ _key: Date.now(), german: '', arabic: '', type: 'noun', category: 'Nomen', article: '', plural: '', plural_suffix: '', note: '', meta: null, _duplicate: false });
    dirty.value = true;
}
function save() {
    saving.value = true;
    const payload = {
        words: words.value.map((w) => ({
            german: w.german,
            arabic: w.arabic,
            type: w.type,
            category: w.category,
            article: w.article || null,
            plural: w.plural || null,
            plural_suffix: w.plural_suffix || null,
            example_de: w.example_de || null,
            example_ar: w.example_ar || null,
            note: w.note || null,
            meta: w.meta || null,
        })),
        conjugations: conjugations.value,
        dialog_lines: dialogLines.value,
        grammar_notes: grammarNotes.value,
    };
    router.put(route('import.update', props.preview.id), { payload }, {
        preserveScroll: true,
        onSuccess: () => (dirty.value = false),
        onFinish: () => (saving.value = false),
    });
}
function confirmImport() {
    if (dirty.value && !confirm(t('imp.unsaved'))) return;
    if (dirty.value) return save();
    router.post(route('import.confirm', props.preview.id));
}
function retry() {
    router.post(route('import.retry', props.preview.id));
}
</script>

<template>
    <Head :title="t('imp.preview_title', { n: preview.lektion_number, title: preview.title })" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <Link :href="route('import.index')" class="text-xs font-semibold uppercase tracking-wide text-gray-500 hover:underline">← {{ t('nav.import') }}</Link>
                    <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('imp.preview_title', { n: preview.lektion_number, title: preview.title }) }}</h1>
                </div>
                <div v-if="status === 'ready'" class="flex gap-2">
                    <button type="button" class="btn-secondary" :disabled="!dirty || saving" @click="save">{{ t('imp.save_changes') }}</button>
                    <button type="button" class="btn-primary" @click="confirmImport">{{ t('imp.confirm_save') }}</button>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
            <div v-if="page.props.errors?.payload" class="whitespace-pre-line rounded-xl bg-red-50 p-4 text-sm text-red-800 ring-1 ring-red-200">{{ page.props.errors.payload }}</div>

            <div v-if="['pending', 'processing'].includes(status)" class="card p-6 text-center">
                <div class="mx-auto mb-3 h-8 w-8 animate-spin rounded-full border-4 border-brand-200 border-t-brand-600"></div>
                <div class="font-semibold">{{ status === 'pending' ? t('imp.waiting') : t('imp.parsing') }}</div>
                <div class="mt-1 text-sm text-gray-500">{{ t('imp.chunks', { done: chunks.done, total: chunks.total || '?' }) }}</div>
                <p class="mt-3 text-xs text-gray-400">{{ t('imp.worker_q') }}</p>
            </div>

            <div v-else-if="status === 'failed'" class="card p-6">
                <div class="font-semibold text-red-700">{{ t('imp.failed') }}</div>
                <pre class="mt-2 whitespace-pre-wrap text-sm text-red-700/80">{{ error }}</pre>
                <button type="button" class="btn-primary mt-4" @click="retry">{{ t('imp.retry') }}</button>
            </div>

            <div v-else-if="status === 'imported'" class="rounded-xl bg-purple-50 p-4 text-sm text-purple-900 ring-1 ring-purple-200 dark:bg-purple-950/40 dark:text-purple-200">
                {{ t('imp.already', { date: new Date(preview.imported_at).toLocaleString(dateLocale) }) }}
            </div>

            <template v-if="['ready', 'imported'].includes(status)">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <div class="flex gap-1 rounded-xl bg-gray-200/70 p-1 dark:bg-gray-800">
                        <button v-for="t in [['words', t('imp.tab_words', { n: words.length })], ['conjugations', t('imp.tab_conj', { n: conjugations.length })], ['dialog', t('imp.tab_dialog', { n: dialogLines.length })], ['grammar', t('imp.tab_grammar', { n: grammarNotes.length })]]" :key="t[0]" type="button" class="min-h-[36px] rounded-lg px-3 text-sm font-medium" :class="tab === t[0] ? 'bg-white shadow dark:bg-gray-900' : 'text-gray-600 dark:text-gray-300'" @click="tab = t[0]">{{ t[1] }}</button>
                    </div>
                    <span v-if="duplicates" class="badge bg-amber-100 text-amber-800">{{ t('imp.duplicates', { n: duplicates }) }}</span>
                    <button type="button" class="ms-auto text-xs text-gray-500 hover:underline" @click="showRaw = !showRaw">{{ showRaw ? t('imp.hide_raw') : t('imp.show_raw') }}</button>
                </div>

                <pre v-if="showRaw" class="card max-h-72 overflow-auto p-3 text-xs">{{ rawTextExcerpt }}</pre>

                <section v-show="tab === 'words'" class="card overflow-hidden">
                    <div class="flex flex-wrap items-center gap-2 border-b border-gray-100 p-3 dark:border-gray-800">
                        <input v-model="filter" class="input max-w-xs !py-1.5" :placeholder="t('imp.filter')" />
                        <button v-if="status === 'ready'" type="button" class="btn-secondary !min-h-[36px] !py-1" @click="addRow">{{ t('imp.add_row') }}</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                                <tr>
                                    <th class="px-2 py-2">{{ t('imp.col_art') }}</th>
                                    <th class="px-2 py-2">{{ t('wf.german') }}</th>
                                    <th class="px-2 py-2">{{ t('imp.col_suffix') }}</th>
                                    <th class="px-2 py-2">{{ t('imp.col_plural') }}</th>
                                    <th class="px-2 py-2 text-right">العربية</th>
                                    <th class="px-2 py-2">{{ t('imp.col_type') }}</th>
                                    <th class="px-2 py-2">{{ t('imp.col_cat') }}</th>
                                    <th class="px-2 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="w in filtered" :key="w._key" :class="{ 'bg-amber-50 dark:bg-amber-950/20': w._duplicate }">
                                    <td class="px-1 py-1">
                                        <select v-model="w.article" class="input !py-1 !pe-7 !ps-2 text-xs" :disabled="status !== 'ready'" @change="dirty = true">
                                            <option :value="null">–</option><option value="">–</option><option>der</option><option>die</option><option>das</option><option>der/die</option>
                                        </select>
                                    </td>
                                    <td class="px-1 py-1"><input v-model="w.german" class="input !py-1 text-sm" :disabled="status !== 'ready'" @input="dirty = true" /></td>
                                    <td class="w-24 px-1 py-1"><input v-model="w.plural_suffix" class="input !py-1 text-sm" :disabled="status !== 'ready'" @input="dirty = true" /></td>
                                    <td class="px-1 py-1"><input v-model="w.plural" class="input !py-1 text-sm" :disabled="status !== 'ready'" @input="dirty = true" /></td>
                                    <td class="px-1 py-1"><input v-model="w.arabic" dir="rtl" class="input ar !py-1" :disabled="status !== 'ready'" @input="dirty = true" /></td>
                                    <td class="px-1 py-1">
                                        <select v-model="w.type" class="input !py-1 !pe-7 !ps-2 text-xs" :disabled="status !== 'ready'" @change="dirty = true">
                                            <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                                        </select>
                                    </td>
                                    <td class="px-1 py-1">
                                        <select v-model="w.category" class="input !py-1 !pe-7 !ps-2 text-xs" :disabled="status !== 'ready'" @change="dirty = true">
                                            <option v-for="c in categoryList" :key="c" :value="c">{{ c }}</option>
                                        </select>
                                    </td>
                                    <td class="px-1 py-1 text-right">
                                        <button v-if="status === 'ready'" type="button" class="rounded p-1 text-gray-400 hover:text-red-600" @click="removeRow(w)" :aria-label="t('imp.delete_row')">✕</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-show="tab === 'conjugations'" class="card overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th class="px-2 py-2">{{ t('imp.col_verb') }}</th><th class="px-2 py-2">ich</th><th class="px-2 py-2">du</th><th class="px-2 py-2">er/sie/es</th><th class="px-2 py-2">wir</th><th class="px-2 py-2">ihr</th><th class="px-2 py-2">sie/Sie</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="(c, i) in conjugations" :key="i">
                                <td class="px-2 py-1 font-medium">{{ c.german }}</td>
                                <td v-for="k in ['ich', 'du', 'er_sie_es', 'wir', 'ihr', 'sie_Sie']" :key="k" class="px-1 py-1"><input v-model="c[k]" class="input !py-1 text-sm" :disabled="status !== 'ready'" @input="dirty = true" /></td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!conjugations.length" class="p-4 text-sm text-gray-500">{{ t('imp.no_conj') }}</p>
                </section>

                <section v-show="tab === 'dialog'" class="card overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th class="px-2 py-2">{{ t('lek.topic') }}</th><th class="px-2 py-2">Sie</th><th class="px-2 py-2">du</th><th class="px-2 py-2">{{ t('lek.answer') }}</th><th class="px-2 py-2 text-right">العربية</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="(d, i) in dialogLines" :key="i">
                                <td class="px-1 py-1"><input v-model="d.topic" class="input !py-1 text-sm" :disabled="status !== 'ready'" @input="dirty = true" /></td>
                                <td class="px-1 py-1"><input v-model="d.question_formal" class="input !py-1 text-sm" :disabled="status !== 'ready'" @input="dirty = true" /></td>
                                <td class="px-1 py-1"><input v-model="d.question_informal" class="input !py-1 text-sm" :disabled="status !== 'ready'" @input="dirty = true" /></td>
                                <td class="px-1 py-1"><input v-model="d.answer" class="input !py-1 text-sm" :disabled="status !== 'ready'" @input="dirty = true" /></td>
                                <td class="px-1 py-1"><input v-model="d.arabic" dir="rtl" class="input ar !py-1" :disabled="status !== 'ready'" @input="dirty = true" /></td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!dialogLines.length" class="p-4 text-sm text-gray-500">{{ t('imp.no_dialog') }}</p>
                </section>

                <section v-show="tab === 'grammar'" class="space-y-3">
                    <div v-for="(g, i) in grammarNotes" :key="i" class="card p-4">
                        <input v-model="g.title" class="input mb-2 font-semibold" :disabled="status !== 'ready'" @input="dirty = true" />
                        <textarea v-model="g.body_md" rows="8" class="input font-mono text-xs" :disabled="status !== 'ready'" @input="dirty = true"></textarea>
                    </div>
                    <p v-if="!grammarNotes.length" class="text-sm text-gray-500">{{ t('imp.no_grammar') }}</p>
                </section>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
