<script>
/**
 * Grammar-tagged text: "[Meine Familie|poss n] [ist|v] klein." → each chunk gets a small label on top
 * (case colour: Nominativ blue, Akkusativ red, Dativ green). Tags are checked by ContentLoader::GRAMMAR_TAGS.
 */
const CASES = { n: 'Nom.', a: 'Akk.', d: 'Dat.' };
const LABELS = {
    pp: 'Pers.-Pron.', ip: 'Indef.-Pron.', rp: 'Refl.-Pron.', poss: 'Poss.-Art.', neg: 'Neg.-Art.', q: 'W-Wort', p: 'Präp.',
    v: 'Verb', mv: 'Modalverb', hv: 'Hilfsverb', inf: 'Infinitiv', part: 'Partizip II', vs: 'Vorsilbe', imp: 'Imperativ',
    k: 'Konnektor', t: 'Zeit', adj: 'Adjektiv', adv: 'Adverb', ni: 'Negation', gr: 'Gruß',
};
const LONG = {
    n: 'Nominativ', a: 'Akkusativ', d: 'Dativ', pp: 'Personalpronomen', ip: 'Indefinitpronomen', rp: 'Reflexivpronomen',
    poss: 'Possessivartikel', neg: 'Negativartikel (kein-)', q: 'Fragewort', p: 'Präposition', v: 'Verb (konjugiert)',
    mv: 'Modalverb', hv: 'Hilfsverb (haben / sein)', inf: 'Infinitiv', part: 'Partizip II', vs: 'trennbare Vorsilbe',
    imp: 'Imperativ', k: 'Konnektor', t: 'Zeitangabe', adj: 'Adjektiv', adv: 'Adverb', ni: 'Negation', gr: 'Anrede / Gruß',
};
const VERBS = ['v', 'mv', 'hv', 'inf', 'part', 'vs', 'imp'];

// colour groups (also used for the legend)
export const GROUPS = {
    n: { label: 'bg-sky-100 text-sky-800 dark:bg-sky-900/60 dark:text-sky-200', line: 'border-sky-500' },
    a: { label: 'bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-200', line: 'border-rose-500' },
    d: { label: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200', line: 'border-emerald-500' },
    verb: { label: 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200', line: 'border-amber-500' },
    k: { label: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200', line: 'border-slate-400' },
    t: { label: 'bg-violet-100 text-violet-800 dark:bg-violet-900/60 dark:text-violet-200', line: 'border-violet-500' },
    q: { label: 'bg-teal-100 text-teal-800 dark:bg-teal-900/60 dark:text-teal-200', line: 'border-teal-500' },
    other: { label: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300', line: 'border-gray-300 dark:border-gray-600' },
};

function group(tags) {
    const c = tags.find((x) => CASES[x]);
    if (c) return GROUPS[c];
    if (tags.some((x) => VERBS.includes(x))) return GROUPS.verb;
    return GROUPS[tags.find((x) => GROUPS[x])] || GROUPS.other;
}

function label(tags) {
    const c = tags.find((x) => CASES[x]);
    const rest = tags.filter((x) => !CASES[x]);
    if (rest.includes('p') && c) return 'Präp. + ' + CASES[c];
    return [...rest.map((x) => LABELS[x]), c ? CASES[c] : null].filter(Boolean).join(' · ');
}

export function parse(source) {
    const out = [];
    const re = /\[([^\]|]+)\|([^\]]+)\]/g;
    let last = 0;
    let m;
    while ((m = re.exec(source))) {
        if (m.index > last) out.push({ text: source.slice(last, m.index) });
        const tags = m[2].split(' ');
        out.push({ text: m[1], label: label(tags), title: tags.map((x) => LONG[x]).join(' · '), cls: group(tags) });
        last = re.lastIndex;
    }
    if (last < source.length) out.push({ text: source.slice(last) });
    return out;
}
</script>

<script setup>
import { computed } from 'vue';

const props = defineProps({ source: { type: String, required: true } });
const parts = computed(() => parse(props.source));
</script>

<template>
    <div class="whitespace-pre-line leading-[2.4]">
        <template v-for="(s, i) in parts" :key="i">
            <span v-if="s.label" class="mx-px inline-flex flex-col items-center align-bottom leading-tight" :title="s.title">
                <span class="mb-0.5 whitespace-nowrap rounded px-1 text-[10px] font-bold leading-4" :class="s.cls.label">{{ s.label }}</span>
                <span class="border-b-2 pb-px" :class="s.cls.line">{{ s.text }}</span>
            </span>
            <template v-else>{{ s.text }}</template>
        </template>
    </div>
</template>
