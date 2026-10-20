<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({
    session: Object,
    questions: Array,
    answered: Array,
    passThreshold: Number,
});

const { t, dateLocale } = useI18n();

// Resume: skip questions already answered in this session.
const index = ref(Math.min(props.answered.length, props.questions.length));
const correctCount = ref(props.session.correct);
const selected = ref(null);
const typed = ref('');
const feedback = ref(null); // { correct, answer, word, progress }
const busy = ref(false);
const result = ref(null);
const typingInput = ref(null);
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
const progressPct = computed(() => Math.round((index.value / total.value) * 100));

const modeTitle = computed(() =>
    props.session.mode === 'lektion'
        ? t('quiz.title_lektion', { n: props.session.lektion_number })
        : props.session.mode === 'weak'
          ? t('quiz.title_weak')
          : t('quiz.title_global'),
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

async function submit(answer) {
    if (busy.value || feedback.value || !current.value) return;
    busy.value = true;
    selected.value = answer;
    try {
        const { data } = await axios.post(route('quiz.answer', props.session.id), {
            question_id: current.value.id,
            answer,
        });
        feedback.value = data;
        correctCount.value = data.session.correct;
        if (!data.correct && navigator.vibrate) navigator.vibrate([60, 40, 60]);
        if (data.correct && autoNext.value) autoTimer = setTimeout(next, 900);
    } catch (e) {
        feedback.value = { correct: false, answer: '?', error: e.response?.data?.message || t('quiz.save_error') };
    } finally {
        busy.value = false;
    }
}

async function next() {
    if (!feedback.value) return;
    clearTimeout(autoTimer);
    feedback.value = null;
    selected.value = null;
    typed.value = '';
    index.value++;
    if (index.value >= total.value) await finish();
    else await nextTick(() => typingInput.value?.focus());
}

async function finish() {
    const { data } = await axios.post(route('quiz.finish', props.session.id));
    result.value = data;
    if (data.passed && navigator.vibrate) navigator.vibrate(30);
}

function retryWrong() {
    router.post(route('quiz.retry', props.session.id));
}

function onKey(e) {
    if (result.value) return;
    const tag = e.target.tagName;
    if (e.key === 'Enter') {
        if (feedback.value) {
            e.preventDefault();
            next();
        } else if (current.value?.type === 'typing' && tag === 'INPUT') {
            e.preventDefault();
            submit(typed.value);
        }
        return;
    }
    if (tag === 'INPUT' || tag === 'TEXTAREA') return;
    const n = parseInt(e.key, 10);
    if (!feedback.value && n >= 1 && n <= (current.value?.options?.length || 0)) {
        submit(current.value.options[n - 1]);
    }
}

onMounted(async () => {
    window.addEventListener('keydown', onKey);
    if (finished.value) await finish();
    else await nextTick(() => typingInput.value?.focus());
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    clearTimeout(autoTimer);
});

function optionClass(opt) {
    const base = 'btn w-full justify-start text-left text-base ring-1 min-h-[60px] px-4 active:scale-[0.98]';
    if (!feedback.value) {
        return base + ' bg-white ring-gray-300 hover:bg-brand-50 hover:ring-brand-400 dark:bg-gray-800 dark:ring-gray-700 dark:hover:bg-gray-700';
    }
    if (opt === feedback.value.answer) return base + ' bg-emerald-100 ring-emerald-500 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-100';
    if (opt === selected.value) return base + ' bg-red-100 ring-red-500 text-red-900 dark:bg-red-900/40 dark:text-red-100';
    return base + ' bg-white ring-gray-200 opacity-60 dark:bg-gray-800 dark:ring-gray-700';
}

const backHref = computed(() => (props.session.lektion_id ? route('lektionen.show', props.session.lektion_id) : route('dashboard')));
</script>

<template>
    <Head :title="modeTitle" />

    <AuthenticatedLayout focus>
        <div class="mx-auto max-w-2xl px-4 py-4 sm:px-6 sm:py-6">
            <!-- RESULT -->
            <div v-if="result" class="space-y-5">
                <div class="card p-6 text-center">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ modeTitle }}</div>
                    <div class="mt-2 text-6xl font-extrabold tabular-nums" :class="result.passed ? 'text-emerald-600' : 'text-amber-600'">{{ result.score }} %</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-300">{{ t('quiz.of', { a: result.correct, b: result.total }) }}</div>
                    <div v-if="session.mode === 'lektion'" class="mt-3">
                        <span v-if="result.passed" class="badge bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">{{ t('quiz.passed', { pct: passThreshold }) }}</span>
                        <span v-else class="badge bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">{{ t('quiz.not_passed', { pct: passThreshold }) }}</span>
                    </div>
                    <div class="mt-5 grid gap-2 sm:flex sm:flex-wrap sm:justify-center">
                        <button v-if="result.wrong.length" type="button" class="btn-primary" @click="retryWrong">{{ t('quiz.retry_wrong', { n: result.wrong.length }) }}</button>
                        <Link v-if="session.lektion_id" :href="route('quiz.lektion', session.lektion_id)" class="btn-secondary">{{ t('common.again') }}</Link>
                        <Link v-else :href="route(session.mode === 'weak' ? 'quiz.weak' : 'quiz.global')" class="btn-secondary">{{ t('common.again') }}</Link>
                        <Link :href="route('dashboard')" class="btn-secondary">{{ t('quiz.dashboard') }}</Link>
                    </div>
                </div>

                <div v-if="result.wrong.length" class="card overflow-hidden">
                    <h2 class="px-4 py-3 font-semibold">{{ t('quiz.wrong_words') }}</h2>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                        <li v-for="w in result.wrong" :key="w.word_id + w.correct_answer" class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <Link v-if="w.word_id" :href="route('words.show', w.word_id)" class="font-semibold hover:underline">{{ w.display }}</Link>
                                <span v-else class="font-semibold">{{ w.display }}</span>
                                <div class="text-xs text-gray-500">{{ t('quiz.correct_is', { a: w.correct_answer }) }} · {{ t('quiz.your_answer', { a: w.given_answer || '–' }) }}</div>
                            </div>
                            <ArabicText v-if="w.arabic" :text="w.arabic" tag="div" class="shrink-0" />
                        </li>
                    </ul>
                </div>
            </div>

            <!-- QUESTION -->
            <div v-else-if="current" class="space-y-4">
                <div class="flex items-center gap-3">
                    <Link :href="backHref" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" :aria-label="t('quiz.quit')">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6" /></svg>
                    </Link>
                    <div class="flex-1">
                        <div class="mb-1 flex justify-between text-xs text-gray-500">
                            <span class="truncate">{{ modeTitle }}</span>
                            <span class="tabular-nums">{{ index + 1 }} / {{ total }} · ✓ {{ correctCount }}</span>
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                            <div class="h-full rounded-full bg-brand-500 transition-all duration-300" :style="{ width: progressPct + '%' }"></div>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-xl px-2 py-1 text-[11px] font-semibold ring-1"
                        :class="autoNext ? 'bg-brand-50 text-brand-700 ring-brand-300 dark:bg-brand-900/30 dark:text-brand-200 dark:ring-brand-800' : 'text-gray-500 ring-gray-300 dark:ring-gray-700'"
                        @click="toggleAuto"
                        :aria-pressed="autoNext"
                    >
                        {{ t('quiz.auto_next') }}
                    </button>
                </div>

                <div class="card p-5 text-center sm:p-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-brand-600 dark:text-brand-400">{{ t('quiz.q.' + current.type) }}</div>
                    <ArabicText v-if="current.prompt_lang === 'ar'" :text="current.prompt" tag="div" class="mt-3 text-3xl font-semibold sm:text-4xl" />
                    <div v-else class="mt-3 text-3xl font-bold sm:text-4xl">{{ current.prompt }}</div>
                    <div
                        v-if="hint(current)"
                        class="mt-2 text-sm text-gray-500"
                        :class="{ ar: current.hint_key === 'arabic' }"
                        :dir="current.hint_key === 'arabic' ? 'rtl' : 'ltr'"
                    >
                        {{ hint(current) }}
                    </div>
                </div>

                <!-- options -->
                <div v-if="current.options.length" class="grid gap-2" :class="current.type === 'article' ? 'grid-cols-3' : 'grid-cols-1 sm:grid-cols-2'">
                    <button
                        v-for="(opt, i) in current.options"
                        :key="opt"
                        type="button"
                        :class="optionClass(opt)"
                        :disabled="busy || !!feedback"
                        @click="submit(opt)"
                    >
                        <span class="me-3 hidden h-7 w-7 shrink-0 place-items-center rounded-lg bg-gray-100 text-xs font-bold text-gray-500 dark:bg-gray-900 sm:grid">{{ i + 1 }}</span>
                        <span v-if="current.options_lang === 'ar'" dir="rtl" lang="ar" class="ar flex-1 text-lg">{{ opt }}</span>
                        <span v-else class="flex-1" :class="current.type === 'article' ? 'text-center text-xl font-bold' : ''">{{ opt }}</span>
                    </button>
                </div>

                <!-- typing -->
                <form v-else @submit.prevent="submit(typed)" class="flex gap-2">
                    <input
                        ref="typingInput"
                        v-model="typed"
                        class="input min-h-[56px] text-lg"
                        :placeholder="t('quiz.type_placeholder')"
                        autocomplete="off"
                        autocapitalize="off"
                        autocorrect="off"
                        spellcheck="false"
                        enterkeyhint="done"
                        :disabled="!!feedback"
                    />
                    <button type="submit" class="btn-primary shrink-0" :disabled="busy || !!feedback || !typed.trim()">{{ t('quiz.check') }}</button>
                </form>

                <p class="hidden text-center text-xs text-gray-400 sm:block">{{ t('quiz.keys') }}</p>

                <!-- feedback: bottom sheet on mobile, inline card on desktop -->
                <transition name="sheet">
                    <div
                        v-if="feedback"
                        class="fixed inset-x-0 bottom-0 z-40 rounded-t-3xl p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-2xl ring-1 sm:static sm:rounded-2xl sm:pb-4 sm:shadow-none"
                        :class="feedback.correct ? 'bg-emerald-50 ring-emerald-300 dark:bg-emerald-950 dark:ring-emerald-800' : 'bg-red-50 ring-red-300 dark:bg-red-950 dark:ring-red-800'"
                        role="status"
                        aria-live="polite"
                    >
                        <div class="mx-auto flex max-w-2xl items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-lg font-bold" :class="feedback.correct ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300'">
                                    {{ feedback.correct ? t('quiz.right') : t('quiz.wrong') }}
                                </div>
                                <div class="mt-1 text-sm">
                                    <span class="text-gray-500">{{ t('quiz.answer') }}</span>
                                    <span class="font-semibold" :dir="current.options_lang === 'ar' ? 'rtl' : 'ltr'" :class="{ ar: current.options_lang === 'ar' }">{{ feedback.answer }}</span>
                                </div>
                                <div v-if="feedback.word && feedback.word.display !== feedback.answer" class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                    {{ feedback.word.display }}<span v-if="feedback.word.plural && current.type !== 'plural'"> · Pl. {{ feedback.word.plural }}</span>
                                    · <ArabicText :text="feedback.word.arabic" class="font-medium" />
                                </div>
                                <div v-if="feedback.progress" class="mt-1 text-xs text-gray-500">
                                    {{ t('quiz.next_review', { level: feedback.progress.level, date: new Date(feedback.progress.next_review_at).toLocaleDateString(dateLocale) }) }}
                                </div>
                                <div v-if="feedback.error" class="mt-1 text-xs text-red-600">{{ feedback.error }}</div>
                            </div>
                            <button type="button" class="btn-primary min-h-[52px] shrink-0 px-5" @click="next">{{ index + 1 >= total ? t('quiz.result') : t('quiz.next') }}</button>
                        </div>
                    </div>
                </transition>
            </div>

            <div v-else class="card p-6 text-center text-gray-500">{{ t('common.loading') }}</div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.sheet-enter-active,
.sheet-leave-active {
    transition: transform 0.2s ease-out, opacity 0.2s;
}
.sheet-enter-from,
.sheet-leave-to {
    transform: translateY(16px);
    opacity: 0;
}
</style>
