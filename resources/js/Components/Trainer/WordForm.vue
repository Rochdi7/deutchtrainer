<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import { useI18n } from '@/i18n';

const props = defineProps({
    word: { type: Object, default: null }, // null = create
    lektionId: { type: Number, required: true },
    types: { type: Array, required: true },
    categories: { type: Array, required: true },
    defaultCategory: { type: String, default: 'Nomen' },
});
const emit = defineEmits(['saved', 'cancel']);
const { t } = useI18n();

const blank = () => ({
    german: '',
    arabic: '',
    english: '',
    type: 'noun',
    category: props.defaultCategory,
    article: '',
    plural: '',
    plural_suffix: '',
    example_de: '',
    example_ar: '',
    note: '',
    meta: null,
    conjugation: { ich: '', du: '', er_sie_es: '', wir: '', ihr: '', sie_Sie: '' },
});

const form = useForm(blank());

watch(
    () => props.word,
    (w) => {
        const data = blank();
        if (w) {
            Object.assign(data, {
                german: w.german,
                arabic: w.arabic,
                english: w.english || '',
                type: w.type,
                category: props.categories.find((c) => c.id === w.category_id)?.name || w.category || props.defaultCategory,
                article: w.article || '',
                plural: w.plural || '',
                plural_suffix: w.plural_suffix || '',
                example_de: w.example_de || '',
                example_ar: w.example_ar || '',
                note: w.note || '',
                meta: w.meta || null,
                conjugation: { ...data.conjugation, ...(w.conjugation || {}) },
            });
        }
        form.defaults(data);
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

const categoryNames = computed(() => [...new Set(props.categories.map((c) => (typeof c === 'string' ? c : c.name)))]);
const isVerb = computed(() => form.type === 'verb');
const isNoun = computed(() => form.type === 'noun');

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => emit('saved') };
    if (props.word) form.put(route('words.update', props.word.id), opts);
    else form.post(route('words.store', props.lektionId), opts);
}
</script>

<template>
    <form @submit.prevent="submit" class="space-y-3">
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('wf.german') }}</label>
                <input v-model="form.german" class="input" required autofocus />
                <InputError :message="form.errors.german" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('wf.arabic') }}</label>
                <input v-model="form.arabic" class="input ar" dir="rtl" required />
                <InputError :message="form.errors.arabic" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">English</label>
                <input v-model="form.english" class="input" />
                <InputError :message="form.errors.english" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('wf.type') }}</label>
                <select v-model="form.type" class="input">
                    <option v-for="ty in types" :key="ty" :value="ty">{{ t('type.' + ty) }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('wf.category') }}</label>
                <input v-model="form.category" class="input" list="category-options" required />
                <datalist id="category-options">
                    <option v-for="c in categoryNames" :key="c" :value="c" />
                </datalist>
                <InputError :message="form.errors.category" />
            </div>
            <template v-if="isNoun">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('wf.article') }}</label>
                    <select v-model="form.article" class="input">
                        <option value="">–</option>
                        <option>der</option>
                        <option>die</option>
                        <option>das</option>
                        <option>der/die</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('wf.plural_suffix') }}</label>
                        <input v-model="form.plural_suffix" class="input" placeholder="-e, ¨-er, -" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('wf.plural_full') }}</label>
                        <input v-model="form.plural" class="input" :placeholder="t('wf.auto')" />
                    </div>
                </div>
            </template>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('wf.example_de') }}</label>
                <input v-model="form.example_de" class="input" />
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('wf.example_ar') }}</label>
                <input v-model="form.example_ar" class="input ar" dir="rtl" />
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('wf.note') }}</label>
                <input v-model="form.note" class="input" />
            </div>
        </div>

        <div v-if="isVerb" class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/60">
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ t('wf.conjugation') }}</div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <div v-for="[k, label] in [['ich', 'ich'], ['du', 'du'], ['er_sie_es', 'er/sie/es'], ['wir', 'wir'], ['ihr', 'ihr'], ['sie_Sie', 'sie/Sie']]" :key="k">
                    <label class="mb-1 block text-xs text-gray-500">{{ label }}</label>
                    <input v-model="form.conjugation[k]" class="input" />
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
            <button type="button" class="btn-secondary" @click="emit('cancel')">{{ t('common.cancel') }}</button>
            <button type="submit" class="btn-primary" :disabled="form.processing">{{ word ? t('common.save') : t('common.add') }}</button>
        </div>
    </form>
</template>
