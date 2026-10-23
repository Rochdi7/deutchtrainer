<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import MarkdownNote from '@/Components/Trainer/MarkdownNote.vue';
import VocabImage from '@/Components/Trainer/VocabImage.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { useSpeech } from '@/composables/useSpeech';

const props = defineProps({ session: Object, items: Array, answered: Array, passThreshold: Number });
const { t, dateLocale } = useI18n();
const speech = useSpeech();

// Resume: skip items already answered in this session.
const answeredIds = new Set(props.answered.map((a) => a.item_id));
const index = ref(Math.min(props.items.filter((i) => answeredIds.has(i.id)).length, props.items.length));
const correctCount = ref(props.session.correct);
const feedback = ref(null);
const busy = ref(false);
const result = ref(null);
const inputEl = ref(null);

const current = computed(() => props.items[index.value]);
const total = computed(() => props.items.length);
const progressPct = computed(() => Math.round(((index.value + (feedback.value ? 1 : 0)) / total.value) * 100));
const isImageOptions = computed(() => current.value?.data?.options_kind === 'image');
const hasOptions = computed(() => !!current.value?.data?.options && !['order', 'match'].includes(current.value.type));
const isTextInput = computed(() => ['gap', 'plural', 'conjugation', 'translate'].includes(current.value?.type));

// per-type state
const typed = ref('');
const selected = ref(null);
const orderPicked = ref([]);
const matchLeft = ref(null);
const matchPairs = ref([]);
const writeText = ref('');
const showModel = ref(false);
const showPassage = ref(true);
const showTranslation = ref(false);

watch(current, () => reset(), { immediate: true });
function reset() {
    typed.value = '';
    selected.value = null;
    orderPicked.value = [];
    matchLeft.value = null;
    matchPairs.value = [];
    writeText.value = '';
    showModel.value = false;
    showTranslation.value = false;
    feedback.value = null;
    if (current.value?.type === 'listen' && speech.supported.value) setTimeout(() => speech.speak(current.value.data.text), 350);
}

const orderRemaining = computed(() => (current.value?.data?.tokens || []).map((tk, i) => ({ tk, i })).filter(({ i }) => !orderPicked.value.includes(i)));
const matchRightUsed = computed(() => new Set(matchPairs.value.map((p) => p[1])));
const matchLeftUsed = computed(() => new Set(matchPairs.value.map((p) => p[0])));

const canCheck = computed(() => {
    const c = current.value;
    if (!c || feedback.value || busy.value) return false;
    if (hasOptions.value) return selected.value !== null;
    if (isTextInput.value) return typed.value.trim() !== '';
    if (c.type === 'order') return orderRemaining.value.length === 0;
    if (c.type === 'match') return matchPairs.value.length === (c.data.left || []).length;
    return false;
});

function pickLeft(l) {
    if (feedback.value || matchLeftUsed.value.has(l)) return;
    matchLeft.value = matchLeft.value === l ? null : l;
}
function pickRight(r) {
    if (feedback.value || matchRightUsed.value.has(r) || !matchLeft.value) return;
    matchPairs.value.push([matchLeft.value, r]);
    matchLeft.value = null;
}
function undoPair(p) {
    if (feedback.value) return;
    matchPairs.value = matchPairs.value.filter((x) => x !== p);
}

function currentAnswer() {
    const c = current.value;
    if (hasOptions.value) return selected.value;
    if (isTextInput.value) return typed.value;
    if (c.type === 'order') return orderPicked.value.map((i) => c.data.tokens[i]);
    if (c.type === 'match') return matchPairs.value;
    return null;
}
async function check() {
    if (!canCheck.value) return;
    await submit(currentAnswer());
}
async function submit(answer) {
    if (busy.value || feedback.value || !current.value) return;
    busy.value = true;
    try {
        const { data } = await axios.post(route('exercises.answer', props.session.id), { item_id: current.value.id, answer });
        feedback.value = data;
        correctCount.value = data.session.correct;
        if (navigator.vibrate) navigator.vibrate(data.correct ? 30 : [60, 40, 60]);
    } catch (e) {
        feedback.value = { correct: false, answer: '?', error: e.response?.data?.message || t('quiz.save_error') };
    } finally {
        busy.value = false;
    }
}
function submitWrite(ok) {
    submit({ text: writeText.value, self_ok: ok });
}
async function next() {
    if (!feedback.value) return;
    index.value++;
    if (index.value >= total.value) await finish();
    else await nextTick(() => inputEl.value?.focus());
}
async function finish() {
    const { data } = await axios.post(route('exercises.finish', props.session.id));
    result.value = data;
}
function retryWrong() {
    router.post(route('exercises.retry', props.session.id));
}
function onKey(e) {
    if (result.value) return;
    const tag = e.target.tagName;
    if (e.key === 'Enter') {
        e.preventDefault();
        if (feedback.value) next();
        else if (canCheck.value) check();
        return;
    }
    if (tag === 'INPUT' || tag === 'TEXTAREA') return;
    const n = parseInt(e.key, 10);
    const opts = current.value?.data?.options || [];
    if (!feedback.value && hasOptions.value && n >= 1 && n <= opts.length) selected.value = opts[n - 1];
}
onMounted(async () => {
    window.addEventListener('keydown', onKey);
    if (props.session.finished || index.value >= total.value) await finish();
    else await nextTick(() => inputEl.value?.focus());
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    speech.stop();
});

function optionClass(opt) {
    if (!feedback.value) return selected.value === opt ? 'option option-selected' : 'option';
    if (opt === feedback.value.answer) return 'option option-correct';
    if (opt === selected.value) return 'option option-wrong';
    return 'option option-muted';
}
function hintText(h) {
    const k = 'type.' + h;
    const v = t(k);
    return v === k ? h : v;
}
const typeLabel = computed(() => {
    const c = current.value;
    if (!c) return '';
    return c.vocab_mode ? t('vocab.mode_' + c.vocab_mode) : t('ex.type_' + c.type);
});
const backHref = computed(() => (props.session.lektion_id ? route('lektionen.show', props.session.lektion_id) + '#exercises' : props.session.mode === 'revision' ? route('revision.index') : route('practice.index')));
function quit() {
    if (confirm(t('ex.quit_confirm'))) router.visit(backHref.value);
}
const feedbackAnswer = computed(() => {
    const f = feedback.value;
    if (!f) return '';
    if (isImageOptions.value) return current.value.prompt;
    if (Array.isArray(f.answer)) return f.answer.map((p) => (Array.isArray(p) ? p.join(' – ') : p)).join(', ');
    return f.answer;
});
const orderTokens = computed(() => (current.value?.data?.tokens || []).map((tk, i) => ({ tk, i })));
</script>

<template>
    <Head :title="session.label || t('ex.title')" />

    <AuthenticatedLayout focus>
        <!-- RESULT -->
        <div v-if="result" class="mx-auto flex min-h-[calc(100vh-2rem)] max-w-xl flex-col justify-center px-4 py-8">
            <div class="animate-pop text-center">
                <div class="text-7xl">{{ result.passed ? '🎉' : '💪' }}</div>
                <h1 class="mt-3 text-3xl font-black" :class="result.passed ? 'text-pop-green' : 'text-pop-orange'">{{ t('ex.done_title') }}</h1>
                <p class="mt-1 font-bold text-gray-500">{{ session.label }} · {{ t('ex.done_sub') }}</p>
            </div>
            <div class="mt-8 grid grid-cols-2 gap-3">
                <div class="overflow-hidden rounded-2xl border-2 border-pop-yellow">
                    <div class="bg-pop-yellow px-3 py-1 text-center text-[11px] font-black uppercase tracking-widest text-white">{{ t('ex.xp_gained') }}</div>
                    <div class="py-4 text-center text-3xl font-black text-pop-yellow-dark">⚡ {{ result.correct }}</div>
                </div>
                <div class="overflow-hidden rounded-2xl border-2" :class="result.passed ? 'border-pop-green' : 'border-pop-orange'">
                    <div class="px-3 py-1 text-center text-[11px] font-black uppercase tracking-widest text-white" :class="result.passed ? 'bg-pop-green' : 'bg-pop-orange'">{{ t('ex.accuracy') }}</div>
                    <div class="py-4 text-center text-3xl font-black" :class="result.passed ? 'text-pop-green' : 'text-pop-orange'">🎯 {{ result.score }} %</div>
                </div>
            </div>
            <div class="mt-3 text-center text-sm font-bold text-gray-500">{{ t('quiz.of', { a: result.correct, b: result.total }) }}</div>
            <div v-if="['test', 'final'].includes(session.mode)" class="mt-3 text-center">
                <span v-if="result.passed" class="badge bg-brand-100 px-3 py-1 text-brand-800">{{ t('quiz.passed', { pct: passThreshold }) }}</span>
                <span v-else class="badge bg-orange-100 px-3 py-1 text-orange-800">{{ t('quiz.not_passed', { pct: passThreshold }) }}</span>
                <p v-if="session.mode === 'final'" class="mt-2 text-xs text-gray-500">{{ t('ex.final_disclaimer') }}</p>
            </div>

            <div v-if="result.wrong.length" class="card mt-6 overflow-hidden">
                <h2 class="px-4 py-3 text-sm uppercase tracking-wide text-gray-500">{{ t('ex.wrong_items') }} ({{ result.wrong.length }})</h2>
                <ul class="max-h-60 divide-y-2 divide-gray-100 overflow-auto dark:divide-gray-800">
                    <li v-for="w in result.wrong" :key="w.item_id" class="px-4 py-2.5 text-sm">
                        <div class="font-bold">{{ w.prompt }}</div>
                        <div class="text-xs text-gray-500"><span class="text-pop-green">✓ {{ Array.isArray(w.answer) ? w.answer.map((p) => p.join(' – ')).join(', ') : w.answer }}</span> · <span class="text-pop-red">✗ {{ w.given || '–' }}</span></div>
                    </li>
                </ul>
            </div>

            <div class="mt-6 grid gap-3">
                <button v-if="result.wrong.length" type="button" class="btn-primary w-full" @click="retryWrong">{{ t('quiz.retry_wrong', { n: result.wrong.length }) }}</button>
                <Link :href="backHref" :class="result.wrong.length ? 'btn-secondary w-full' : 'btn-primary w-full'">{{ t('ex.continue') }}</Link>
                <Link :href="route('course.dashboard')" class="btn-ghost w-full">{{ t('course.title', { level: 'A1' }) }}</Link>
            </div>
        </div>

        <!-- ITEM -->
        <div v-else-if="current" class="flex min-h-screen flex-col">
            <!-- top bar -->
            <div class="mx-auto flex w-full max-w-2xl items-center gap-4 px-4 pt-[max(1rem,env(safe-area-inset-top))] sm:px-6">
                <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-2xl text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" :aria-label="t('quiz.quit')" @click="quit">✕</button>
                <div class="progress"><span :style="{ width: progressPct + '%' }"></span></div>
                <span class="shrink-0 text-sm font-extrabold tabular-nums text-pop-primary">⚡ {{ correctCount }}</span>
            </div>

            <div class="mx-auto w-full max-w-2xl flex-1 px-4 pb-48 pt-6 sm:px-6">
                <div class="text-xs font-extrabold uppercase tracking-widest text-pop-primary">{{ typeLabel }} · {{ index + 1 }}/{{ total }}</div>

                <!-- reading passage -->
                <div v-if="current.type === 'reading'" class="card mt-3 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-base">📖 {{ current.data.passage.title }}</h2>
                        <div class="flex gap-1">
                            <button type="button" class="btn-secondary btn-sm" @click="showPassage = !showPassage">{{ showPassage ? t('ex.hide_text') : t('ex.show_text') }}</button>
                            <button v-if="feedback" type="button" class="btn-secondary btn-sm" @click="showTranslation = !showTranslation">EN</button>
                        </div>
                    </div>
                    <MarkdownNote v-if="showPassage" :source="current.data.passage.text_md" class="mt-2 max-h-56 overflow-auto text-sm" />
                    <MarkdownNote v-if="showTranslation" :source="current.data.passage.translation_md" class="card-flat mt-2 p-2 text-xs" />
                </div>

                <!-- prompt -->
                <div class="mt-3 flex items-start gap-4">
                    <VocabImage v-if="current.data.image && current.vocab_mode !== 'word_image'" :src="current.data.image" :alt="current.vocab_mode === 'image_word' ? '' : current.prompt" :label="current.vocab_mode === 'image_word' ? '?' : current.prompt" size="lg" class="shrink-0" />
                    <div class="min-w-0 flex-1">
                        <div v-if="current.type === 'listen'" class="mb-3 flex items-center gap-2">
                            <button v-if="speech.supported.value" type="button" class="btn-blue h-16 w-16 !rounded-2xl !px-0 text-3xl" :aria-label="t('ex.play')" @click="speech.speak(current.data.text)">🔊</button>
                            <button v-if="speech.supported.value" type="button" class="btn-secondary h-12 w-12 !rounded-2xl !px-0 text-xl" aria-label="slow" @click="speech.speak(current.data.text, 0.7)">🐢</button>
                            <div v-else class="rounded-2xl border-2 border-pop-yellow bg-yellow-50 p-3 text-sm font-semibold text-yellow-900 dark:bg-yellow-950/40 dark:text-yellow-200">
                                {{ t('ex.no_tts') }}<div class="mt-1 font-black">{{ current.data.text }}</div>
                            </div>
                        </div>
                        <ArabicText v-if="current.data.prompt_lang === 'ar'" :text="current.prompt" tag="h1" class="text-3xl font-bold sm:text-4xl" />
                        <h1 v-else class="text-2xl leading-snug sm:text-3xl" :class="{ 'text-xl sm:text-2xl': current.prompt.length > 60 }">
                            {{ current.prompt }}
                            <button v-if="speech.supported.value && current.type !== 'listen' && current.data.prompt_lang !== 'ar' && !['en_de', 'typing', 'de_en'].includes(current.vocab_mode || '')" type="button" class="ms-1 align-middle text-lg text-pop-blue" :aria-label="t('ex.listen')" @click="speech.speak(current.prompt)">🔊</button>
                        </h1>
                        <div v-if="current.data.hint" class="mt-1 text-sm font-bold text-gray-400">{{ hintText(current.data.hint) }}</div>
                        <p v-if="current.data.translation && ['order', 'gap', 'conjugation'].includes(current.type)" class="mt-1 text-sm text-gray-400">{{ current.data.translation }}</p>
                    </div>
                </div>

                <!-- image options -->
                <div v-if="isImageOptions" class="mt-6 grid grid-cols-2 gap-3">
                    <button v-for="(opt, i) in current.data.options" :key="opt" type="button" :class="optionClass(opt) + ' flex-col !px-3 !py-3'" :disabled="busy || !!feedback" @click="selected = opt">
                        <VocabImage :src="opt" alt="" label="?" size="lg" />
                        <span class="option-key absolute bottom-2 right-2">{{ i + 1 }}</span>
                    </button>
                </div>

                <!-- text options -->
                <div v-else-if="hasOptions" class="mt-6 grid gap-3" :class="current.type === 'article' ? 'grid-cols-3' : 'grid-cols-1'">
                    <button v-for="(opt, i) in current.data.options" :key="opt" type="button" :class="optionClass(opt)" :disabled="busy || !!feedback" @click="selected = opt">
                        <span v-if="current.type !== 'article'" class="option-key">{{ i + 1 }}</span>
                        <span class="flex-1" :class="current.type === 'article' ? 'text-center text-xl font-black' : ''">{{ opt }}</span>
                    </button>
                </div>

                <!-- order -->
                <div v-else-if="current.type === 'order'" class="mt-6 space-y-5">
                    <div class="flex min-h-[64px] flex-wrap items-center gap-2 border-b-2 border-t-2 border-gray-200 py-3 dark:border-gray-700" :class="{ '!border-pop-green': feedback?.correct, '!border-pop-red': feedback && !feedback.correct }">
                        <span v-if="!orderPicked.length" class="text-sm font-bold text-gray-400">{{ t('ex.order_hint') }}</span>
                        <button v-for="i in orderPicked" :key="'p' + i" type="button" class="chip" :disabled="!!feedback" @click="orderPicked = orderPicked.filter((x) => x !== i)">{{ current.data.tokens[i] }}</button>
                    </div>
                    <div class="flex flex-wrap justify-center gap-2">
                        <template v-for="{ tk, i } in orderTokens" :key="'r' + i">
                            <span v-if="orderPicked.includes(i)" class="chip-slot">{{ tk }}</span>
                            <button v-else type="button" class="chip" :disabled="!!feedback" @click="orderPicked.push(i)">{{ tk }}</button>
                        </template>
                    </div>
                </div>

                <!-- match -->
                <div v-else-if="current.type === 'match'" class="mt-6 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <button v-for="l in current.data.left" :key="'l' + l" type="button" class="option min-h-[56px] justify-center text-center text-sm" :class="matchLeftUsed.has(l) ? 'option-muted' : matchLeft === l ? 'option-selected' : ''" :disabled="!!feedback || matchLeftUsed.has(l)" @click="pickLeft(l)">{{ l }}</button>
                        </div>
                        <div class="space-y-2">
                            <button v-for="r in current.data.right" :key="'r' + r" type="button" class="option min-h-[56px] justify-center text-center text-sm" :class="matchRightUsed.has(r) ? 'option-muted' : ''" :disabled="!!feedback || !matchLeft || matchRightUsed.has(r)" @click="pickRight(r)">{{ r }}</button>
                        </div>
                    </div>
                    <ul class="space-y-1 text-sm font-bold">
                        <li v-for="p in matchPairs" :key="p[0]" class="flex items-center justify-between rounded-xl px-3 py-1.5" :class="feedback ? (feedback.answer.some((a) => a[0] === p[0] && a[1] === p[1]) ? 'bg-brand-50 text-brand-700 dark:bg-brand-900/30' : 'bg-red-50 text-pop-red-dark dark:bg-red-950/40') : 'card-flat'">
                            <span>{{ p[0] }} – {{ p[1] }}</span>
                            <button v-if="!feedback" type="button" class="text-xs text-gray-400" @click="undoPair(p)">✕</button>
                        </li>
                    </ul>
                </div>

                <!-- write -->
                <div v-else-if="current.type === 'write'" class="mt-6 space-y-3">
                    <textarea v-model="writeText" rows="6" class="input text-base" :placeholder="t('ex.write_placeholder')" :disabled="!!feedback"></textarea>
                    <div v-if="showModel" class="card space-y-3 p-4">
                        <div class="text-xs font-extrabold uppercase tracking-wide text-gray-400">{{ t('ex.model_answer') }}</div>
                        <div class="whitespace-pre-line text-sm font-semibold">{{ current.data.model_answer_preview }}</div>
                        <p class="text-sm text-gray-500">{{ t('ex.self_check') }}</p>
                        <div v-if="!feedback" class="grid grid-cols-2 gap-2">
                            <button type="button" class="btn-secondary" @click="submitWrite(false)">{{ t('ex.self_no') }}</button>
                            <button type="button" class="btn-primary" @click="submitWrite(true)">{{ t('ex.self_yes') }}</button>
                        </div>
                    </div>
                </div>

                <!-- text input -->
                <form v-else-if="isTextInput" class="mt-6" @submit.prevent="check">
                    <input ref="inputEl" v-model="typed" class="input min-h-[60px] text-xl" :placeholder="current.type === 'translate' && current.data.direction === 'de-en' ? t('ex.type_english') : t('quiz.type_placeholder')" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="done" :disabled="!!feedback" />
                </form>
            </div>

            <!-- BOTTOM BAR: check / feedback -->
            <div class="fixed inset-x-0 bottom-0 z-40 border-t-2 pb-[max(1rem,env(safe-area-inset-bottom))] pt-4 transition-colors" :class="!feedback ? 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900' : feedback.correct ? 'border-brand-200 bg-brand-100 dark:border-brand-900 dark:bg-brand-900/40' : 'border-red-200 bg-red-100 dark:border-red-900 dark:bg-red-950/60'" role="status" aria-live="polite">
                <div class="mx-auto flex max-w-2xl flex-col gap-3 px-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <template v-if="!feedback">
                        <span class="hidden text-sm font-bold text-gray-400 sm:block">{{ hasOptions ? t('ex.select_hint') : '' }}</span>
                        <button v-if="current.type === 'write' && !showModel" type="button" class="btn-primary w-full sm:w-auto sm:min-w-[180px]" :disabled="writeText.trim().length < 10" @click="showModel = true">{{ t('ex.compare') }}</button>
                        <button v-else-if="current.type !== 'write'" type="button" class="btn-primary w-full sm:w-auto sm:min-w-[180px]" :disabled="!canCheck" @click="check">{{ t('ex.check') }}</button>
                    </template>
                    <template v-else>
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-white text-2xl font-black" :class="feedback.correct ? 'text-pop-green' : 'text-pop-red'">{{ feedback.correct ? '✓' : '✗' }}</span>
                            <div class="min-w-0 text-sm" :class="feedback.correct ? 'text-brand-800 dark:text-brand-100' : 'text-pop-red-dark dark:text-red-100'">
                                <div class="text-lg font-black">{{ feedback.correct ? t('ex.correct_title') : t('ex.wrong_title') }}</div>
                                <div v-if="!feedback.correct && current.type !== 'write'" class="font-bold">{{ feedbackAnswer }}</div>
                                <div v-if="feedback.explanation" class="mt-0.5">💡 {{ feedback.explanation }}</div>
                                <div v-if="feedback.translation" class="mt-0.5 opacity-80">{{ feedback.translation }}</div>
                                <div v-if="feedback.transcript" class="mt-0.5 opacity-80">🎧 {{ feedback.transcript }}</div>
                                <div v-if="current.type === 'write' && feedback.checklist" class="mt-0.5"><span class="font-bold">{{ t('ex.checklist') }}:</span> {{ feedback.checklist.join(' · ') }}</div>
                                <div v-if="current.word" class="mt-0.5 opacity-80">{{ current.word.display }} · {{ current.word.english }} · <ArabicText :text="current.word.arabic" class="font-bold" /><span v-if="current.word.plural && current.type !== 'plural'"> · Pl. {{ current.word.plural }}</span></div>
                                <div v-if="feedback.progress" class="mt-0.5 text-xs opacity-70">{{ t('quiz.next_review', { level: feedback.progress.level, date: new Date(feedback.progress.next_review_at).toLocaleDateString(dateLocale) }) }}</div>
                                <div v-if="feedback.error" class="mt-0.5 text-xs">{{ feedback.error }}</div>
                            </div>
                        </div>
                        <button type="button" class="w-full shrink-0 sm:w-auto sm:min-w-[180px]" :class="feedback.correct ? 'btn-primary' : 'btn-danger'" @click="next">{{ index + 1 >= total ? t('quiz.result') : t('ex.continue') }}</button>
                    </template>
                </div>
            </div>
        </div>
        <div v-else class="card m-6 p-6 text-center text-gray-500">{{ t('common.loading') }}</div>
    </AuthenticatedLayout>
</template>
