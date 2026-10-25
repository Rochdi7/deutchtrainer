<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import SoundToggle from '@/Components/Trainer/SoundToggle.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { useSpeech } from '@/composables/useSpeech';
import { playSound } from '@/composables/useSounds';

const props = defineProps({
    session: Object,
    questions: Array,
    answered: Array,
    passThreshold: Number,
    difficult: { type: Array, default: () => [] },
});

const { t, dateLocale } = useI18n();
const speech = useSpeech();

// Resume: skip questions already answered in this session.
const index = ref(Math.min(props.answered.length, props.questions.length));
const correctCount = ref(props.session.correct);
const history = ref(props.answered.map((a) => a.is_correct)); // per answered question, for the segmented bar
const selected = ref(null);
const typed = ref('');
const feedback = ref(null); // { correct, answer, word, progress, skipped? }
const busy = ref(false);
const result = ref(null);
const typingInput = ref(null);
const shake = ref(false);

// tools
const hidden = ref([]); // options removed by 50/50
const hintPrefix = ref('');
const hintLetters = ref(0);
const usedHint = ref(false);
const hintsUsed = ref(0);
const skipped = ref(0);
const difficultIds = ref(new Set(props.difficult));

// motivation
const combo = ref(0);
const bestCombo = ref(0);
const comboToast = ref(null);
const startedAt = Date.now();
const todayAtStart = usePage().props.stats?.today ?? 0;
const goalReached = ref(false);

const autoNext = ref(true);
let autoTimer = null;
try {
    autoNext.value = localStorage.getItem('quiz.autoNext') !== '0';
} catch (e) {}
function toggleAuto() {
    autoNext.value = !autoNext.value;
    try {
        localStorage.setItem('quiz.autoNext', autoNext.value ? '1' : '0');
    } catch (e) {}
}

const current = computed(() => props.questions[index.value]);
const total = computed(() => props.questions.length);
const finished = computed(() => props.session.finished || index.value >= total.value);
const progressPct = computed(() => Math.round(((index.value + (feedback.value ? 1 : 0)) / total.value) * 100));
const segmented = computed(() => total.value <= 30);
const isTyping = computed(() => current.value && !current.value.options.length);
const visibleOptions = computed(() => (current.value?.options || []).filter((o) => !hidden.value.includes(o)));
const germanPrompt = computed(() => current.value?.prompt_lang === 'de');
const germanAnswer = computed(() => feedback.value?.word?.display || (current.value?.options_lang !== 'ar' ? feedback.value?.answer : null));

const modeTitle = computed(() =>
    props.session.mode === 'lektion' ? t('quiz.title_lektion', { n: props.session.lektion_number }) : props.session.mode === 'weak' ? t('quiz.title_weak') : t('quiz.title_global'),
);

function hint(q) {
    switch (q.hint_key) {
        case 'word_type':
            return t('type.' + q.hint_value);
        case 'typing':
            return t('quiz.hint.typing', { type: t('type.' + q.hint_value) });
        case 'article':
            return t('quiz.hint.article');
        case 'plural':
            return t('quiz.hint.plural');
        case 'arabic':
        case 'topic':
            return q.hint_value;
        default:
            return q.hint;
    }
}

watch(index, () => {
    hidden.value = [];
    hintPrefix.value = '';
    hintLetters.value = 0;
    usedHint.value = false;
    selected.value = null;
    typed.value = '';
});

// ------------------------------------------------------------------ answering

async function submit(answer, { skip = false } = {}) {
    if (busy.value || feedback.value || !current.value) return;
    busy.value = true;
    selected.value = answer;
    try {
        const { data } = await axios.post(route('quiz.answer', props.session.id), {
            question_id: current.value.id,
            answer,
            hinted: usedHint.value,
        });
        feedback.value = { ...data, skipped: skip };
        correctCount.value = data.session.correct;
        history.value.push(data.correct);
        if (skip) skipped.value++;
        onResult(data.correct);
    } catch (e) {
        feedback.value = { correct: false, answer: '?', error: e.response?.data?.message || t('quiz.save_error') };
    } finally {
        busy.value = false;
    }
}

function onResult(correct) {
    playSound(correct ? 'correct' : 'wrong');
    if (!correct) {
        combo.value = 0;
        shake.value = true;
        setTimeout(() => (shake.value = false), 400);
        if (navigator.vibrate) navigator.vibrate([60, 40, 60]);
        return;
    }
    combo.value++;
    bestCombo.value = Math.max(bestCombo.value, combo.value);
    if ([3, 5, 10, 15, 20, 30].includes(combo.value)) {
        comboToast.value = combo.value;
        setTimeout(() => playSound('select'), 250);
        setTimeout(() => (comboToast.value = null), 1600);
    }
    if (autoNext.value && !usedHint.value) autoTimer = setTimeout(next, 1100);
}

function submitTyped() {
    if (typed.value.trim()) submit(typed.value);
}

async function next() {
    if (!feedback.value) return;
    clearTimeout(autoTimer);
    speech.stop();
    feedback.value = null;
    index.value++;
    if (index.value >= total.value) await finish();
    else await nextTick(() => typingInput.value?.focus());
}

async function finish() {
    const { data } = await axios.post(route('quiz.finish', props.session.id));
    result.value = { ...data, seconds: Math.round((Date.now() - startedAt) / 1000) };
    playSound('complete');
    if (data.daily && todayAtStart < data.daily.goal && data.daily.today >= data.daily.goal) {
        goalReached.value = true;
        setTimeout(() => playSound('goal'), 700);
    }
    if (data.passed && navigator.vibrate) navigator.vibrate(30);
}

function retryWrong() {
    router.post(route('quiz.retry', props.session.id));
}

// ------------------------------------------------------------------ tools

const canFifty = computed(() => !feedback.value && !busy.value && !isTyping.value && hidden.value.length === 0 && (current.value?.options.length || 0) >= 3);
const canLetter = computed(() => !feedback.value && !busy.value && isTyping.value);

async function useFifty() {
    if (!canFifty.value) return;
    const { data } = await axios.post(route('quiz.hint', props.session.id), { question_id: current.value.id, kind: 'fifty' });
    hidden.value = data.remove;
    usedHint.value = true;
    hintsUsed.value++;
}

async function useLetter() {
    if (!canLetter.value) return;
    const { data } = await axios.post(route('quiz.hint', props.session.id), { question_id: current.value.id, kind: 'letter', n: hintLetters.value + 1 });
    hintLetters.value++;
    hintPrefix.value = data.prefix;
    if (!usedHint.value) hintsUsed.value++;
    usedHint.value = true;
    typed.value = data.prefix;
    await nextTick(() => {
        typingInput.value?.focus();
        typingInput.value?.setSelectionRange(typed.value.length, typed.value.length);
    });
}

function skip() {
    if (!feedback.value && !busy.value) submit(null, { skip: true });
}

function insertChar(ch) {
    const el = typingInput.value;
    if (!el || feedback.value) return;
    const start = el.selectionStart ?? typed.value.length;
    const end = el.selectionEnd ?? typed.value.length;
    typed.value = typed.value.slice(0, start) + ch + typed.value.slice(end);
    nextTick(() => {
        el.focus();
        el.setSelectionRange(start + ch.length, start + ch.length);
    });
}

async function toggleDifficult(wordId) {
    if (!wordId) return;
    const { data } = await axios.post(route('words.difficult', wordId), {}, { headers: { Accept: 'application/json' } });
    const set = new Set(difficultIds.value);
    data.is_difficult ? set.add(wordId) : set.delete(wordId);
    difficultIds.value = set;
}

function say(text) {
    if (text) speech.speak(text);
}

function quit() {
    if (index.value === 0 || confirm(t('ex.quit_confirm'))) router.visit(backHref.value);
}

// ------------------------------------------------------------------ keyboard

function onKey(e) {
    if (result.value) return;
    const tag = e.target.tagName;
    if (e.key === 'Enter') {
        if (feedback.value) {
            e.preventDefault();
            next();
        } else if (isTyping.value && tag === 'INPUT') {
            e.preventDefault();
            submitTyped();
        }
        return;
    }
    if (e.key === 'Escape') return quit();
    if (tag === 'INPUT' || tag === 'TEXTAREA' || e.ctrlKey || e.metaKey || e.altKey) return;
    const key = e.key.toLowerCase();
    if (key === 'h') return isTyping.value ? useLetter() : useFifty();
    if (key === 's') return skip();
    if (key === 'l') return say(feedback.value ? germanAnswer.value : germanPrompt.value ? current.value.prompt : null);
    const n = parseInt(e.key, 10);
    if (!feedback.value && n >= 1 && n <= visibleOptions.value.length) submit(visibleOptions.value[n - 1]);
}

onMounted(async () => {
    window.addEventListener('keydown', onKey);
    if (finished.value) await finish();
    else await nextTick(() => typingInput.value?.focus());
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    clearTimeout(autoTimer);
    speech.stop();
});

// ------------------------------------------------------------------ view helpers

const articleColors = { der: 'article-der', die: 'article-die', das: 'article-das' };
function optionClass(opt) {
    const article = current.value.type === 'article' ? articleColors[opt] || '' : '';
    if (!feedback.value) return `option ${article} min-h-[64px]`;
    if (opt === feedback.value.answer) return 'option option-correct min-h-[64px]';
    if (opt === selected.value) return 'option option-wrong min-h-[64px]';
    return 'option option-muted min-h-[64px]';
}

const backHref = computed(() => (props.session.lektion_id ? route('lektionen.show', props.session.lektion_id) : route('practice.index')));
const resultEmoji = computed(() => (!result.value ? '' : result.value.score >= 90 ? '🏆' : result.value.passed ? '🎉' : '💪'));
const resultTitle = computed(() => (!result.value ? '' : result.value.score >= 90 ? t('quiz.r_excellent') : result.value.passed ? t('quiz.r_good') : t('quiz.r_keep_going')));
const duration = computed(() => {
    const s = result.value?.seconds || 0;
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
});
</script>

<template>
    <Head :title="modeTitle" />

    <AuthenticatedLayout focus>
        <!-- RESULT -->
        <div v-if="result" class="mx-auto flex min-h-[calc(100vh-2rem)] max-w-xl flex-col justify-center px-4 py-8">
            <div class="animate-pop text-center">
                <div class="text-7xl">{{ resultEmoji }}</div>
                <h1 class="mt-3 text-3xl font-black" :class="result.passed ? 'text-pop-green' : 'text-pop-orange'">{{ resultTitle }}</h1>
                <p class="mt-1 font-bold text-gray-500">{{ modeTitle }} · {{ t('quiz.of', { a: result.correct, b: result.total }) }}</p>
            </div>

            <div class="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="stat-tile border-pop-green"><div class="bg-pop-green">{{ t('ex.accuracy') }}</div><span class="text-pop-green">{{ result.score }} %</span></div>
                <div class="stat-tile border-pop-yellow"><div class="bg-pop-yellow">{{ t('ex.xp_gained') }}</div><span class="text-pop-yellow-dark">⚡ {{ result.correct }}</span></div>
                <div class="stat-tile border-pop-orange"><div class="bg-pop-orange">{{ t('quiz.best_combo') }}</div><span class="text-pop-orange">🔥 {{ bestCombo }}</span></div>
                <div class="stat-tile border-pop-blue"><div class="bg-pop-blue">{{ t('quiz.time') }}</div><span class="text-pop-blue">⏱ {{ duration }}</span></div>
            </div>

            <div v-if="session.mode === 'lektion'" class="mt-4 text-center">
                <span v-if="result.passed" class="badge bg-brand-100 px-3 py-1 text-brand-800">{{ t('quiz.passed', { pct: passThreshold }) }}</span>
                <span v-else class="badge bg-orange-100 px-3 py-1 text-orange-800">{{ t('quiz.not_passed', { pct: passThreshold }) }}</span>
            </div>
            <p v-if="hintsUsed || skipped" class="mt-3 text-center text-xs font-bold text-gray-400">
                <span v-if="hintsUsed">💡 {{ t('quiz.hints_used', { n: hintsUsed }) }}</span>
                <span v-if="hintsUsed && skipped"> · </span>
                <span v-if="skipped">⏭ {{ t('quiz.skipped', { n: skipped }) }}</span>
            </p>

            <div v-if="goalReached" class="mt-4 animate-pop rounded-2xl border-2 border-pop-green bg-brand-50 p-4 text-center dark:bg-brand-900/30">
                <div class="text-lg font-black text-pop-green">🏆 {{ t('goal.reached_title') }}</div>
                <div class="text-sm font-bold text-gray-500">{{ t('goal.reached_sub', { goal: result.daily.goal }) }}</div>
            </div>
            <div v-else-if="result.daily" class="mt-4">
                <div class="mb-1 flex justify-between text-xs font-extrabold uppercase tracking-wide text-gray-400">
                    <span>🎯 {{ t('goal.title') }}</span><span class="tabular-nums">{{ result.daily.today }} / {{ result.daily.goal }} XP</span>
                </div>
                <div class="progress !h-3"><span :style="{ width: Math.min(100, Math.round((result.daily.today * 100) / result.daily.goal)) + '%' }"></span></div>
            </div>

            <div v-if="result.wrong.length" class="card mt-6 overflow-hidden">
                <h2 class="px-4 py-3 text-sm uppercase tracking-wide text-gray-500">{{ t('quiz.wrong_words') }} ({{ result.wrong.length }})</h2>
                <ul class="max-h-72 divide-y-2 divide-gray-100 overflow-auto dark:divide-gray-800">
                    <li v-for="w in result.wrong" :key="w.word_id + w.correct_answer" class="flex items-center gap-2 px-4 py-2.5">
                        <div class="min-w-0 flex-1">
                            <Link v-if="w.word_id" :href="route('words.show', w.word_id)" class="font-bold hover:underline">{{ w.display }}</Link>
                            <span v-else class="font-bold">{{ w.display }}</span>
                            <div class="text-xs text-gray-500">
                                <span class="text-pop-green">✓ {{ w.correct_answer }}</span> · <span class="text-pop-red">✗ {{ w.given_answer || '–' }}</span>
                            </div>
                        </div>
                        <ArabicText v-if="w.arabic" :text="w.arabic" tag="div" class="shrink-0 text-sm" />
                        <button v-if="speech.supported.value" type="button" class="tool-btn" :aria-label="t('quiz.t_listen')" @click="say(w.display)">🔊</button>
                        <button v-if="w.word_id" type="button" class="tool-btn" :aria-pressed="difficultIds.has(w.word_id)" :title="t('quiz.t_difficult')" @click="toggleDifficult(w.word_id)">
                            {{ difficultIds.has(w.word_id) ? '⭐' : '☆' }}
                        </button>
                    </li>
                </ul>
            </div>

            <div class="mt-6 grid gap-3">
                <button v-if="result.wrong.length" type="button" class="btn-primary w-full" @click="retryWrong">{{ t('quiz.retry_wrong', { n: result.wrong.length }) }}</button>
                <div class="grid grid-cols-2 gap-3">
                    <Link v-if="session.lektion_id" :href="route('quiz.lektion', session.lektion_id)" :class="result.wrong.length ? 'btn-secondary' : 'btn-primary'">↻ {{ t('common.again') }}</Link>
                    <Link v-else :href="route(session.mode === 'weak' ? 'quiz.weak' : 'quiz.global')" :class="result.wrong.length ? 'btn-secondary' : 'btn-primary'">↻ {{ t('common.again') }}</Link>
                    <Link :href="route('practice.index')" class="btn-secondary">🎯 {{ t('nav.practice') }}</Link>
                </div>
                <Link :href="route('course.dashboard')" class="btn-ghost w-full">{{ t('course.title', { level: 'A1' }) }}</Link>
            </div>
        </div>

        <!-- QUESTION -->
        <div v-else-if="current" class="flex min-h-screen flex-col">
            <!-- top bar -->
            <div class="mx-auto flex w-full max-w-2xl items-center gap-3 px-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6">
                <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-2xl text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" :aria-label="t('quiz.quit')" @click="quit">✕</button>
                <div v-if="segmented" class="flex flex-1 gap-1" role="progressbar" :aria-valuenow="index" :aria-valuemax="total">
                    <span
                        v-for="i in total"
                        :key="i"
                        class="h-3 flex-1 rounded-full transition-colors duration-300"
                        :class="i - 1 < history.length ? (history[i - 1] ? 'bg-pop-green' : 'bg-pop-red') : i - 1 === index ? 'bg-pop-primary/40' : 'bg-gray-200 dark:bg-gray-800'"
                    ></span>
                </div>
                <div v-else class="progress"><span :style="{ width: progressPct + '%' }"></span></div>
                <span class="flex shrink-0 items-center gap-1 text-sm font-extrabold tabular-nums" :class="combo >= 3 ? 'text-pop-orange' : 'text-gray-400'" :title="t('quiz.combo')">
                    <span :class="{ grayscale: combo < 3 }">🔥</span>{{ combo }}
                </span>
            </div>

            <div class="mx-auto mt-2 flex w-full max-w-2xl items-center justify-between gap-2 px-4 sm:px-6">
                <span class="truncate text-xs font-extrabold uppercase tracking-widest text-pop-primary">{{ modeTitle }} · {{ index + 1 }}/{{ total }}</span>
                <div class="flex shrink-0 items-center gap-1">
                    <span class="me-1 text-sm font-extrabold tabular-nums text-pop-yellow-dark">⚡ {{ correctCount }}</span>
                    <button type="button" class="pill !min-h-[36px] text-xs" :class="{ 'pill-active': autoNext }" :aria-pressed="autoNext" :title="t('quiz.auto_next')" @click="toggleAuto">⏩ <span class="hidden sm:inline">{{ t('quiz.auto_next') }}</span></button>
                    <SoundToggle class="!min-h-[36px] !min-w-[36px]" />
                </div>
            </div>

            <div class="relative mx-auto w-full max-w-2xl flex-1 px-4 pb-52 pt-5 sm:px-6">
                <!-- combo toast -->
                <div v-if="comboToast" class="pointer-events-none absolute left-1/2 top-0 z-20 animate-rise whitespace-nowrap rounded-2xl bg-pop-orange px-4 py-2 text-sm font-black text-white shadow-lg">
                    🔥 {{ t('quiz.combo_toast', { n: comboToast }) }}
                </div>

                <!-- prompt card -->
                <div class="card p-5 text-center sm:p-7" :class="{ 'animate-shake': shake }">
                    <div class="text-xs font-extrabold uppercase tracking-widest text-gray-400">{{ t('quiz.q.' + current.type) }}</div>
                    <div class="mt-3 flex items-center justify-center gap-2">
                        <ArabicText v-if="current.prompt_lang === 'ar'" :text="current.prompt" tag="div" class="text-3xl font-bold sm:text-4xl" />
                        <div v-else class="font-black" :class="current.prompt.length > 40 ? 'text-xl sm:text-2xl' : 'text-3xl sm:text-4xl'">{{ current.prompt }}</div>
                        <button v-if="germanPrompt && speech.supported.value" type="button" class="tool-btn shrink-0 text-pop-blue" :aria-label="t('quiz.t_listen')" @click="say(current.prompt)">🔊</button>
                    </div>
                    <div v-if="hint(current)" class="mt-2 text-sm font-bold text-gray-400" :class="{ ar: current.hint_key === 'arabic' }" :dir="current.hint_key === 'arabic' ? 'rtl' : 'ltr'">
                        {{ hint(current) }}
                    </div>
                </div>

                <!-- options -->
                <div v-if="!isTyping" class="mt-5 grid gap-3" :class="current.type === 'article' ? 'grid-cols-3' : 'grid-cols-1 sm:grid-cols-2'">
                    <button v-for="(opt, i) in visibleOptions" :key="opt" type="button" data-no-sound :class="optionClass(opt)" :disabled="busy || !!feedback" @click="submit(opt)">
                        <span v-if="current.type !== 'article'" class="option-key">{{ i + 1 }}</span>
                        <span v-if="current.options_lang === 'ar'" dir="rtl" lang="ar" class="ar flex-1 text-lg">{{ opt }}</span>
                        <span v-else class="flex-1" :class="current.type === 'article' ? 'text-center text-2xl font-black' : ''">{{ opt }}</span>
                    </button>
                </div>

                <!-- typing -->
                <form v-else class="mt-5 space-y-3" @submit.prevent="submitTyped">
                    <div class="flex gap-2">
                        <input
                            ref="typingInput"
                            v-model="typed"
                            class="input min-h-[60px] text-xl"
                            :class="{ '!border-pop-green': feedback?.correct, '!border-pop-red': feedback && !feedback.correct }"
                            :placeholder="t('quiz.type_placeholder')"
                            autocomplete="off"
                            autocapitalize="off"
                            autocorrect="off"
                            spellcheck="false"
                            enterkeyhint="done"
                            lang="de"
                            :disabled="!!feedback"
                        />
                        <button type="submit" class="btn-primary shrink-0" :disabled="busy || !!feedback || !typed.trim()">{{ t('quiz.check') }}</button>
                    </div>
                    <div class="flex flex-wrap gap-1.5" :aria-label="t('quiz.umlauts')">
                        <button v-for="ch in ['ä', 'ö', 'ü', 'ß', 'Ä', 'Ö', 'Ü']" :key="ch" type="button" class="chip !min-h-[40px] min-w-[44px] justify-center font-black" :disabled="!!feedback" @mousedown.prevent @click="insertChar(ch)">{{ ch }}</button>
                    </div>
                    <p v-if="hintPrefix" class="text-sm font-bold text-pop-yellow-dark">💡 {{ t('quiz.starts_with', { p: hintPrefix }) }}</p>
                </form>

                <!-- tools -->
                <div v-if="!feedback" class="mt-6 flex flex-wrap items-center justify-center gap-2">
                    <button v-if="!isTyping" type="button" class="tool-chip" :disabled="!canFifty" :title="t('quiz.t_fifty_hint')" @click="useFifty">✂️ 50/50</button>
                    <button v-else type="button" class="tool-chip" :disabled="!canLetter" :title="t('quiz.t_letter_hint')" @click="useLetter">💡 {{ t('quiz.t_letter') }}</button>
                    <button type="button" class="tool-chip" :disabled="busy" @click="skip">⏭ {{ t('quiz.t_skip') }}</button>
                    <button v-if="current.word_id" type="button" class="tool-chip" :class="{ 'tool-chip-on': difficultIds.has(current.word_id) }" :aria-pressed="difficultIds.has(current.word_id)" @click="toggleDifficult(current.word_id)">
                        {{ difficultIds.has(current.word_id) ? '⭐' : '☆' }} {{ t('quiz.t_difficult') }}
                    </button>
                </div>
                <p v-if="usedHint && !feedback" class="mt-2 text-center text-xs font-semibold text-gray-400">{{ t('quiz.hint_note') }}</p>
                <p class="mt-6 hidden text-center text-xs font-semibold text-gray-400 sm:block">{{ t('quiz.keys_full') }}</p>
            </div>

            <!-- BOTTOM BAR: feedback -->
            <transition name="sheet">
                <div
                    v-if="feedback"
                    class="fixed inset-x-0 bottom-0 z-40 border-t-2 pb-[max(1rem,env(safe-area-inset-bottom))] pt-4"
                    :class="feedback.correct ? 'border-brand-200 bg-brand-100 dark:border-brand-900 dark:bg-brand-900/40' : 'border-red-200 bg-red-100 dark:border-red-900 dark:bg-red-950/60'"
                    role="status"
                    aria-live="polite"
                >
                    <div v-if="feedback.correct && autoNext && !usedHint" class="absolute inset-x-0 top-0 h-1 overflow-hidden">
                        <div class="quiz-auto-next h-full bg-pop-green"></div>
                    </div>
                    <div class="mx-auto flex max-w-2xl flex-col gap-3 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-white text-2xl font-black dark:bg-gray-900" :class="feedback.correct ? 'text-pop-green' : 'text-pop-red'">{{ feedback.correct ? '✓' : feedback.skipped ? '⏭' : '✗' }}</span>
                            <div class="min-w-0 text-sm" :class="feedback.correct ? 'text-brand-800 dark:text-brand-100' : 'text-pop-red-dark dark:text-red-100'">
                                <div class="text-lg font-black">{{ feedback.correct ? t('quiz.right') : feedback.skipped ? t('quiz.skipped_title') : t('quiz.wrong') }}</div>
                                <div v-if="!feedback.correct" class="font-bold">
                                    {{ t('quiz.answer') }} <span :dir="current.options_lang === 'ar' ? 'rtl' : 'ltr'" :class="{ ar: current.options_lang === 'ar' }">{{ feedback.answer }}</span>
                                </div>
                                <div v-if="feedback.word && feedback.word.display !== feedback.answer" class="mt-0.5 opacity-80">
                                    {{ feedback.word.display }}<span v-if="feedback.word.plural && current.type !== 'plural'"> · Pl. {{ feedback.word.plural }}</span> · <ArabicText :text="feedback.word.arabic" class="font-bold" />
                                </div>
                                <div v-if="feedback.progress" class="mt-0.5 text-xs opacity-70">{{ t('quiz.next_review', { level: feedback.progress.level, date: new Date(feedback.progress.next_review_at).toLocaleDateString(dateLocale) }) }}</div>
                                <div v-else-if="feedback.correct && usedHint" class="mt-0.5 text-xs opacity-70">💡 {{ t('quiz.hint_note') }}</div>
                                <div v-if="feedback.error" class="mt-0.5 text-xs">{{ feedback.error }}</div>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button v-if="germanAnswer && speech.supported.value" type="button" class="tool-btn bg-white/70 dark:bg-gray-900/60" :aria-label="t('quiz.t_listen')" @click="say(germanAnswer)">🔊</button>
                            <button v-if="current.word_id" type="button" class="tool-btn bg-white/70 dark:bg-gray-900/60" :aria-pressed="difficultIds.has(current.word_id)" :title="t('quiz.t_difficult')" @click="toggleDifficult(current.word_id)">
                                {{ difficultIds.has(current.word_id) ? '⭐' : '☆' }}
                            </button>
                            <button type="button" class="flex-1 sm:min-w-[160px]" :class="feedback.correct ? 'btn-primary' : 'btn-danger'" @click="next">{{ index + 1 >= total ? t('quiz.result') : t('quiz.next') }}</button>
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <div v-else class="card m-6 p-6 text-center text-gray-500">{{ t('common.loading') }}</div>
    </AuthenticatedLayout>
</template>

<style scoped>
.sheet-enter-active,
.sheet-leave-active {
    transition: transform 0.2s ease-out, opacity 0.2s;
}
.sheet-enter-from,
.sheet-leave-to {
    transform: translateY(24px);
    opacity: 0;
}
</style>

<style>
/* Countdown strip for auto-next (unscoped: Vue renames keyframes inside scoped blocks). */
.quiz-auto-next {
    transform-origin: left;
    animation: quiz-auto-next 1.1s linear forwards;
}
@keyframes quiz-auto-next {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}
</style>
