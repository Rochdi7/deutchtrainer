<script>
/**
 * Grammar-tagged text: "[Meine Familie|poss n] [ist|v] klein." → each chunk gets a small label on top
 * (case colour: Nominativ blue, Akkusativ red, Dativ green). Tags are checked by ContentLoader::GRAMMAR_TAGS.
 * Labels are short on phones and long from sm: up; tapping a chunk shows the full name in German and Arabic.
 */
const CASES = { n: 'Nom.', a: 'Akk.', d: 'Dat.' };
const LABELS = {
    pp: 'Pers.-Pron.', ip: 'Indef.-Pron.', rp: 'Refl.-Pron.', poss: 'Poss.-Art.', neg: 'Neg.-Art.', q: 'W-Wort', p: 'Präp.',
    v: 'Verb', mv: 'Modalverb', hv: 'Hilfsverb', inf: 'Infinitiv', part: 'Partizip II', vs: 'Vorsilbe', imp: 'Imperativ',
    k: 'Konnektor', t: 'Zeit', adj: 'Adjektiv', adv: 'Adverb', ni: 'Negation', gr: 'Gruß',
};
const SHORT = {
    n: 'Nom', a: 'Akk', d: 'Dat', pp: 'Pron', ip: 'Pron', rp: 'Refl', poss: 'Poss', neg: 'kein', q: 'W-Wort', p: 'Präp',
    v: 'Verb', mv: 'Modal', hv: 'Hilfsv', inf: 'Inf', part: 'Part II', vs: 'Vorsilbe', imp: 'Imp',
    k: 'Konn', t: 'Zeit', adj: 'Adj', adv: 'Adv', ni: 'Neg', gr: 'Gruß',
};
const LONG = {
    n: 'Nominativ', a: 'Akkusativ', d: 'Dativ', pp: 'Personalpronomen', ip: 'Indefinitpronomen', rp: 'Reflexivpronomen',
    poss: 'Possessivartikel', neg: 'Negativartikel (kein-)', q: 'Fragewort', p: 'Präposition', v: 'Verb (konjugiert)',
    mv: 'Modalverb', hv: 'Hilfsverb (haben / sein)', inf: 'Infinitiv', part: 'Partizip II', vs: 'trennbare Vorsilbe',
    imp: 'Imperativ', k: 'Konnektor', t: 'Zeitangabe', adj: 'Adjektiv', adv: 'Adverb', ni: 'Negation', gr: 'Anrede / Gruß',
};
const AR = {
    n: 'الفاعل (حالة الرفع)', a: 'المفعول به (حالة النصب)', d: 'حالة الجر', pp: 'ضمير شخصي', ip: 'ضمير غير محدد', rp: 'ضمير انعكاسي',
    poss: 'أداة الملكية', neg: 'أداة النفي', q: 'أداة استفهام', p: 'حرف جر', v: 'فعل', mv: 'فعل مساعد (Modalverb)',
    hv: 'فعل مساعد للماضي', inf: 'مصدر', part: 'اسم المفعول', vs: 'سابقة منفصلة', imp: 'فعل أمر', k: 'أداة ربط',
    t: 'ظرف زمان', adj: 'صفة', adv: 'ظرف', ni: 'نفي', gr: 'تحية',
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
const PLAIN = { label: '', line: 'border-transparent' };

function group(tags) {
    const c = tags.find((x) => CASES[x]);
    if (c) return GROUPS[c];
    if (tags.some((x) => VERBS.includes(x))) return GROUPS.verb;
    return GROUPS[tags.find((x) => GROUPS[x])] || GROUPS.other;
}

function label(tags, names, caseNames, plus) {
    const c = tags.find((x) => CASES[x]);
    const rest = tags.filter((x) => !CASES[x]);
    if (rest.includes('p') && c) return names.p + plus + caseNames[c];
    return [...rest.map((x) => names[x]), c ? caseNames[c] : null].filter(Boolean).join(' · ');
}

/** Tokens: tagged chunks, plain words (both rendered as boxes so they share one baseline) and spaces. */
export function parse(source) {
    const out = [];
    const pushPlain = (text) => {
        for (const piece of text.split(/(\s+)/)) {
            if (piece === '') continue;
            const prev = out[out.length - 1];
            if (/^\s+$/.test(piece)) out.push({ space: piece });
            else if (/^[.,!?:;)“"]+$/.test(piece) && prev && !prev.space) prev.after += piece; // keep punctuation on its word
            else {
                const lead = prev && !prev.space ? piece.match(/^[.,!?:;)“"]+/)?.[0] || '' : '';
                if (lead) prev.after += lead;
                out.push({ text: piece.slice(lead.length), before: '', after: '', cls: PLAIN });
            }
        }
    };
    const re = /\[([^\]|]+)\|([^\]]+)\]/g;
    let last = 0;
    let m;
    while ((m = re.exec(source))) {
        if (m.index > last) pushPlain(source.slice(last, m.index));
        const tags = m[2].split(' ');
        const prev = out[out.length - 1];
        let before = '';
        if (prev && !prev.space && !prev.tags && /^[(„"]+$/.test(prev.text)) {
            before = out.pop().text; // "(" or "„" glued to the next chunk
        }
        out.push({
            text: m[1], before, after: '', tags, cls: group(tags),
            label: label(tags, LABELS, CASES, ' + '),
            short: label(tags, SHORT, SHORT, '+'),
            de: tags.map((x) => LONG[x]).join(' · '),
            ar: tags.map((x) => AR[x]).join(' · '),
        });
        last = re.lastIndex;
    }
    if (last < source.length) pushPlain(source.slice(last));
    return out;
}
</script>

<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    source: { type: String, required: true },
    mode: { type: String, default: 'all' }, // all | cases (labels only on words with a case)
});
const { t } = useI18n();
const parts = computed(() => parse(props.source));
const active = ref(null);
watch(() => props.source, () => (active.value = null));

const showLabel = (s) => s.tags && (props.mode === 'all' || s.tags.some((x) => CASES[x]));
</script>

<template>
    <div>
        <div class="whitespace-pre-line leading-snug">
            <template v-for="(s, i) in parts" :key="i">
                <template v-if="s.space">{{ s.space }}</template>
                <span
                    v-else
                    class="my-0.5 inline-flex flex-col items-center align-bottom"
                    :class="{ 'cursor-pointer': s.tags }"
                    @click="s.tags && (active = active === i ? null : i)"
                >
                    <span
                        class="mb-0.5 whitespace-nowrap rounded px-0.5 text-[9px] font-bold leading-[14px] sm:px-1 sm:text-[10px]"
                        :class="showLabel(s) ? s.cls.label : 'invisible'"
                        aria-hidden="true"
                    ><span class="sm:hidden">{{ s.short || '·' }}</span><span class="hidden sm:inline">{{ s.label || '·' }}</span></span>
                    <span class="whitespace-nowrap rounded" :class="{ 'bg-brand-100 dark:bg-brand-900/50': active === i }">{{ s.before }}<span class="border-b-2" :class="s.cls.line">{{ s.text }}</span>{{ s.after }}</span>
                </span>
            </template>
        </div>
        <div class="mt-3 min-h-[3.25rem] rounded-xl border-2 border-dashed border-gray-200 px-3 py-2 text-sm dark:border-gray-700" aria-live="polite">
            <template v-if="active !== null && parts[active]?.tags">
                <div class="font-bold">„{{ parts[active].text }}“ <span class="font-semibold text-gray-600 dark:text-gray-300">– {{ parts[active].de }}</span></div>
                <div class="ar text-gray-600 dark:text-gray-300" dir="rtl">{{ parts[active].ar }}</div>
            </template>
            <div v-else class="py-1.5 text-xs text-gray-400">👆 {{ t('lek.grammar_tap_hint') }}</div>
        </div>
    </div>
</template>
