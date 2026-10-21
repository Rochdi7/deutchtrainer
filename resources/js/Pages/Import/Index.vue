<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const props = defineProps({ previews: Array, lektionen: Array, anthropicConfigured: Boolean, model: String, engine: { type: String, default: 'rules' } });
const { t, dateLocale } = useI18n();

const form = useForm({ pdf: null, number: (props.lektionen.at(-1)?.number || 0) + 1, title: '' });

function submit() {
    form.post(route('import.store'), { forceFormData: true });
}

const statusBadge = {
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    ready: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    imported: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200',
};

function remove(p) {
    if (confirm(t('imp.confirm_delete'))) router.delete(route('import.destroy', p.id));
}
</script>

<template>
    <Head :title="t('imp.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-bold tracking-tight">{{ t('imp.title') }}</h1>
        </template>

        <div class="mx-auto max-w-4xl space-y-6 px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <div v-if="engine === 'claude' && !anthropicConfigured" class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900">
                <strong>{{ t('imp.key_missing') }}</strong> {{ t('imp.key_missing_text') }}
            </div>

            <form @submit.prevent="submit" class="card space-y-4 p-5">
                <h2 class="font-semibold">{{ t('imp.upload_title') }}</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-3">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('imp.pdf_label') }}</label>
                        <input type="file" accept="application/pdf" class="block w-full text-sm file:me-3 file:rounded-xl file:border-0 file:bg-brand-600 file:px-4 file:py-2.5 file:font-semibold file:text-white hover:file:bg-brand-700" @change="form.pdf = $event.target.files[0]" />
                        <InputError :message="form.errors.pdf" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('imp.number') }}</label>
                        <input v-model="form.number" type="number" min="1" class="input" required />
                        <InputError :message="form.errors.number" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">{{ t('imp.title_optional') }}</label>
                        <input v-model="form.title" class="input" :placeholder="t('imp.title_placeholder')" />
                    </div>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-gray-500">{{ engine === 'claude' ? t('imp.pipeline', { model }) : t('imp.pipeline_rules') }}</p>
                    <button type="submit" class="btn-primary" :disabled="form.processing || !form.pdf">{{ t('imp.upload_btn') }}</button>
                </div>
                <div v-if="form.progress" class="h-2 overflow-hidden rounded-full bg-gray-200"><div class="h-full bg-brand-500" :style="{ width: form.progress.percentage + '%' }"></div></div>
            </form>

            <section class="card overflow-hidden">
                <h2 class="px-4 py-3 font-semibold">{{ t('imp.previews') }}</h2>
                <ul v-if="previews.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <li v-for="p in previews" :key="p.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <Link :href="route('import.show', p.id)" class="font-semibold hover:underline">{{ t('lek.lektion', { n: p.lektion_number }) }} – {{ p.title }}</Link>
                            <div class="text-xs text-gray-500">
                                {{ new Date(p.created_at).toLocaleString(dateLocale, { dateStyle: 'short', timeStyle: 'short' }) }}
                                <span v-if="p.chunks_total"> · {{ t('imp.chunks', { done: p.chunks_done, total: p.chunks_total }) }}</span>
                                <span v-if="p.words"> · {{ t('imp.words_n', { n: p.words }) }}</span>
                            </div>
                            <div v-if="p.error" class="mt-1 text-xs text-red-600">{{ p.error }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge" :class="statusBadge[p.status]">{{ p.status }}</span>
                            <button type="button" class="min-h-[36px] text-xs text-gray-400 hover:text-red-600" @click="remove(p)">{{ t('common.delete') }}</button>
                        </div>
                    </li>
                </ul>
                <p v-else class="px-4 pb-4 text-sm text-gray-500">{{ t('imp.no_imports') }}</p>
            </section>

            <p class="text-xs text-gray-500">
                {{ t('imp.cli') }} <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">php artisan import:lektion storage/app/pdfs/lektion-2.pdf 2</code>
                – {{ t('imp.worker') }}
            </p>
        </div>
    </AuthenticatedLayout>
</template>
