<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import { useI18n } from '@/i18n';

/**
 * One "Grammatik aktiv" exercise: gap (typed blanks), match (pick a letter) or write
 * (free answer, self-checked against the book's solution). Results are posted per exercise.
 */
const props = defineProps({
    unit: { type: Number, required: true },
    exercise: { type: Object, required: true },
    unitLevel: { type: String, required: true },
    last: { type: Object, default: null },
    /** Show the Arabic helper texts (instruction_ar, text_ar). */
    showArabic: { type: Boolean, default: false },
});

const { t } = useI18n();
const ex = props.exercise;
const GAP = '___';

// ---------- text helpers
const escape = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
const inline = (s) => escape(s || '').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\*(.+?)\*/g, '<em>$1</em>');
const norm = (s) => (s || '').normalize('NFC').replace(/[’‘´`]/g, "'").replace(/\s+/g, ' ').trim().replace(/[.,;:!?]+$/, '');

/** All accepted spellings of one gap answer: "a|b", "a/b" and optional "(…)" parts. */
function variants(answer) {
    const out = new Set();
    for (const v of answer.split('|')) {
        const base = [v];
        if (v.includes('/')) base.push(...v.split('/'));
        for (const b of base) {
            out.add(norm(b));
            if (/\(.*\)/.test(b)) {
                out.add(norm(b.replace(/\s*\([^)]*\)\s*/g, ' ')));
                out.add(norm(b.replace(/[()]/g, '')));
            }
        }
    }
    out.delete('');
    return [...out];
}
const NOTHING = ['-', '–', '—'];
function judge(value, answer) {
    const v = norm(value);
    const accepted = variants(answer);
    if (accepted.includes(v)) return 'right';
    // "-" in the key = no word needed (e.g. no article): an empty gap or any dash is right
    if (accepted.some((a) => NOTHING.includes(a)) && (v === '' || NOTHING.includes(v))) return 'right';
    if (v && accepted.some((a) => a.toLowerCase() === v.toLowerCase())) return 'case';
    return 'wrong';
}
const display = (answer) => answer.split('|')[0];

// ---------- items: lines split into text segments and gaps
const items = ex.items.map((item, i) => {
    const lines = (item.text || '').split('\n').map((line) => line.split(GAP));
    let gap = 0;
    const parsed = lines.map((segments) => segments.map((text, s) => ({ text, gap: s < segments.length - 1 ? gap++ : null })));
    return { ...item, index: i, lines: parsed };
});
let n = 0;
for (const it of items) it.label = it.example ? null : ++n;

const scored = items.filter((it) => !it.example);
const values = reactive(items.map((it) => (ex.type === 'gap' ? (it.answers || []).map((a) => (it.example ? display(a) : '')) : it.example ? it.answer || it.solution || '' : '')));
const marks = reactive(items.map(() => null)); // write: 'right' | 'wrong'
const checked = ref(false);
const revealed = ref(false);
const saved = ref(props.last);

const status = computed(() => {
    if (!checked.value) return null;
    return items.map((it, i) => {
        if (ex.type === 'gap') return (it.answers || []).map((a, g) => judge(values[i][g], a));
        if (ex.type === 'match') return [String(it.answer).split('|').includes(values[i]) ? 'right' : 'wrong'];
        return [];
    });
});
const score = computed(() => {
    if (ex.type === 'write') {
        return { correct: marks.filter((m, i) => m === 'right' && !items[i].example).length, total: ex.items.some((it) => it.open) ? 0 : scored.length };
    }
    let correct = 0;
    let total = 0;
    for (const it of scored) {
        for (const s of status.value?.[it.index] || []) {
            total++;
            if (s === 'right') correct++;
        }
    }
    return { correct, total };
});

function save(result) {
    saved.value = result;
    router.post(route('grammatik.result', [props.unit, ex.key]), result, { preserveScroll: true, preserveState: true });
}
function check() {
    checked.value = true;
    save(score.value);
}
function reveal() {
    revealed.value = true;
    if (ex.type === 'write' && isOpen.value) save({ correct: 0, total: 0 });
}
function mark(i, m) {
    marks[i] = m;
    if (scored.every((it) => marks[it.index])) save(score.value);
}
function retry() {
    checked.value = false;
    revealed.value = false;
    items.forEach((it, i) => {
        if (it.example) return;
        if (ex.type === 'gap') values[i] = values[i].map(() => '');
        else values[i] = '';
        marks[i] = null;
    });
}

const isOpen = computed(() => ex.items.some((it) => it.open));
const showAnswers = computed(() => revealed.value || checked.value);
const gapWidth = (answer) => Math.min(28, Math.max(5, display(answer).length + 3)) + 'ch';
const gapClass = (i, g) => {
    const s = status.value?.[i]?.[g];
    if (s === 'right') return 'border-pop-green bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-200';
    if (s === 'case') return 'border-pop-orange bg-orange-50 text-orange-800 dark:bg-orange-900/30 dark:text-orange-200';
    if (s === 'wrong') return 'border-pop-red bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-200';
    return 'border-gray-300 bg-gray-50 dark:border-gray-600 dark:bg-gray-800';
};
const ownLevel = ex.level && ex.level !== props.unitLevel ? ex.level : null;
</script>

<template>
    <article class="card p-4 sm:p-6">
        <header class="mb-4 flex items-start gap-3">
            <span class="grid h-9 min-w-[2.25rem] shrink-0 place-items-center rounded-xl bg-[#c2185b] px-2 text-sm font-black text-white">{{ ex.key }}</span>
            <div class="min-w-0 flex-1">
                <h3 class="font-bold leading-snug" v-html="inline(ex.instruction)"></h3>
                <ArabicText v-if="showArabic && ex.instruction_ar" :text="ex.instruction_ar" tag="div" class="mt-0.5 text-base text-gray-600 dark:text-gray-300" />
                <div class="mt-1 flex flex-wrap gap-1.5">
                    <span v-if="ownLevel" class="badge bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-200">{{ ownLevel }}</span>
                    <span v-if="saved" class="badge bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200">
                        ✓ {{ saved.total ? t('ga.last_result', { c: saved.correct, n: saved.total }) : t('ga.done') }}
                    </span>
                </div>
            </div>
        </header>

        <!-- word box -->
        <div v-if="ex.options?.length" class="mb-4 flex flex-wrap items-center gap-1.5">
            <span class="text-xs font-extrabold uppercase tracking-wide text-gray-400">{{ t('ga.word_box') }}:</span>
            <span v-for="o in ex.options" :key="o" class="rounded-lg border-2 border-gray-200 px-2 py-0.5 text-sm font-semibold dark:border-gray-700">{{ o }}</span>
        </div>

        <!-- picture(s) of the whole exercise, cropped from the book -->
        <figure v-if="ex.image" class="mb-4 overflow-hidden rounded-xl border-2 border-gray-100 bg-white dark:border-gray-800">
            <img :src="route('grammatik.image', ex.image)" :alt="t('ga.picture')" class="mx-auto block max-h-[480px] w-auto max-w-full" loading="lazy" />
        </figure>

        <!-- match: the choices once -->
        <ol v-if="ex.type === 'match'" class="mb-4 space-y-1 rounded-xl bg-gray-50 p-3 text-sm dark:bg-gray-800/60">
            <li v-for="c in ex.choices" :key="c.key" class="flex gap-2">
                <span class="font-black text-[#c2185b]">{{ c.key }}</span><span v-html="inline(c.text)"></span>
            </li>
        </ol>

        <ol class="space-y-4">
            <li v-for="(it, i) in items" :key="i" class="flex gap-3" :class="{ 'opacity-75': it.example }">
                <span class="w-6 shrink-0 pt-1 text-right text-sm font-black text-gray-400">{{ it.label ? it.label + '.' : '' }}</span>
                <div class="min-w-0 flex-1">
                    <div v-if="it.example" class="mb-1 text-xs font-extrabold uppercase tracking-wide text-teal-600">{{ t('ga.example') }}</div>
                    <img v-if="it.image" :src="route('grammatik.image', it.image)" :alt="it.hint || t('ga.picture')" class="mb-2 block max-h-40 w-auto max-w-full rounded-lg border-2 border-gray-100 bg-white dark:border-gray-800" loading="lazy" />

                    <!-- gap: text with inline inputs -->
                    <template v-if="ex.type === 'gap'">
                        <p v-for="(line, l) in it.lines" :key="l" class="leading-[2.6rem]">
                            <template v-for="(seg, s) in line" :key="s">
                                <span v-html="inline(seg.text)"></span>
                                <template v-if="seg.gap !== null">
                                    <input
                                        v-model="values[i][seg.gap]"
                                        type="text"
                                        autocomplete="off"
                                        autocapitalize="off"
                                        spellcheck="false"
                                        class="mx-1 inline-block rounded-lg border-2 px-2 py-1 text-center font-semibold focus:border-pop-blue focus:ring-0"
                                        :class="gapClass(i, seg.gap)"
                                        :style="{ width: gapWidth(it.answers[seg.gap]) }"
                                        :disabled="it.example || checked"
                                        :aria-label="t('ga.your_answer')"
                                        @keydown.enter.prevent="!checked && check()"
                                    />
                                    <span v-if="showAnswers && !it.example && status?.[i]?.[seg.gap] !== 'right'" class="mr-1 rounded bg-green-100 px-1.5 py-0.5 text-sm font-bold text-green-800 dark:bg-green-900/40 dark:text-green-200">
                                        {{ display(it.answers[seg.gap]) }}
                                    </span>
                                </template>
                            </template>
                        </p>
                        <p v-if="checked && status?.[i]?.includes('case')" class="text-xs font-bold text-pop-orange">{{ t('ga.case_hint') }}</p>
                    </template>

                    <!-- match: letter buttons -->
                    <template v-else-if="ex.type === 'match'">
                        <p class="mb-2" v-html="inline(it.text).replace(/\n/g, '<br>')"></p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="c in ex.choices"
                                :key="c.key"
                                type="button"
                                class="h-10 min-w-[2.5rem] rounded-xl border-2 px-2 font-black transition"
                                :class="[
                                    values[i] === c.key ? 'border-pop-blue bg-blue-50 text-pop-blue dark:bg-blue-900/30' : 'border-gray-200 dark:border-gray-700',
                                    checked && String(it.answer).split('|').includes(c.key) ? '!border-pop-green !bg-green-50 !text-green-800 dark:!bg-green-900/30 dark:!text-green-200' : '',
                                    checked && values[i] === c.key && !String(it.answer).split('|').includes(c.key) ? '!border-pop-red !bg-red-50 !text-red-800' : '',
                                    revealed && !checked && String(it.answer).split('|').includes(c.key) ? 'ring-2 ring-pop-green' : '',
                                ]"
                                :disabled="it.example || checked"
                                @click="values[i] = c.key"
                            >
                                {{ c.key }}
                            </button>
                        </div>
                    </template>

                    <!-- write: prompt, optional own answer, solution + self-check -->
                    <template v-else>
                        <p class="mb-2" v-html="inline(it.text).replace(/\n/g, '<br>')"></p>
                        <textarea v-if="!it.example" v-model="values[i]" rows="2" class="input text-sm" :placeholder="t('ga.your_answer')"></textarea>
                        <div v-if="revealed || it.example" class="mt-2 rounded-xl border-2 border-green-200 bg-green-50 p-3 text-sm dark:border-green-900 dark:bg-green-900/20">
                            <div class="mb-1 text-xs font-extrabold uppercase tracking-wide text-green-700 dark:text-green-300">{{ t('ga.solution') }}</div>
                            <div class="whitespace-pre-line font-semibold text-green-900 dark:text-green-100" v-html="inline(it.solution)"></div>
                        </div>
                        <div v-if="revealed && !it.example && !it.open" class="mt-2 flex items-center gap-2">
                            <button type="button" class="btn-sm rounded-xl border-2 px-3 font-bold" :class="marks[i] === 'right' ? 'border-pop-green bg-green-50 text-green-800' : 'border-gray-200 dark:border-gray-700'" @click="mark(i, 'right')">✓ {{ t('ga.right') }}</button>
                            <button type="button" class="btn-sm rounded-xl border-2 px-3 font-bold" :class="marks[i] === 'wrong' ? 'border-pop-red bg-red-50 text-red-800' : 'border-gray-200 dark:border-gray-700'" @click="mark(i, 'wrong')">✗ {{ t('ga.wrong') }}</button>
                        </div>
                    </template>

                    <p v-if="it.hint && !it.image && !ex.image" class="mt-1 text-xs italic text-gray-500">🖼️ {{ it.hint }}</p>
                    <ArabicText v-if="showArabic && it.text_ar" :text="it.text_ar" tag="div" class="mt-1 whitespace-pre-line text-[15px] text-gray-500 dark:text-gray-400" />
                </div>
            </li>
        </ol>

        <footer class="mt-5 flex flex-wrap items-center gap-2 border-t-2 border-gray-100 pt-4 dark:border-gray-800">
            <template v-if="ex.type === 'write'">
                <button v-if="!revealed" type="button" class="btn-primary btn-sm" @click="reveal">{{ t('ga.show_solution') }}</button>
                <span v-else class="text-sm text-gray-500">{{ isOpen ? t('ga.open_task') : t('ga.self_check') }}</span>
                <button v-if="revealed" type="button" class="btn-ghost btn-sm" @click="retry">↺ {{ t('ga.retry') }}</button>
            </template>
            <template v-else>
                <button v-if="!checked" type="button" class="btn-primary btn-sm" @click="check">{{ t('ga.check') }}</button>
                <button v-if="!checked && !revealed" type="button" class="btn-ghost btn-sm" @click="reveal">{{ t('ga.show_solution') }}</button>
                <template v-if="checked">
                    <span class="text-sm font-extrabold" :class="score.correct === score.total ? 'text-pop-green' : 'text-gray-700 dark:text-gray-200'">
                        {{ score.correct === score.total ? '🎉 ' : '' }}{{ t('ga.result', { c: score.correct, n: score.total }) }}
                    </span>
                    <button type="button" class="btn-ghost btn-sm" @click="retry">↺ {{ t('ga.retry') }}</button>
                </template>
            </template>
        </footer>
    </article>
</template>
