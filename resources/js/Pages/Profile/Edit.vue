<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import LearningSettingsForm from './Partials/LearningSettingsForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});
const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth.user);
const stats = computed(() => page.props.stats || { streak: 0, xp: 0, due: 0 });
const initials = computed(() => (user.value?.name || '?').split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase());
</script>

<template>
    <Head :title="t('nav.profile')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl space-y-5 px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
            <!-- profile hero -->
            <section class="card flex flex-col items-center gap-4 p-5 text-center sm:flex-row sm:text-left">
                <div class="grid h-20 w-20 shrink-0 place-items-center rounded-3xl bg-pop-primary text-3xl font-black text-white" style="box-shadow: 0 5px 0 0 #6243d6">{{ initials }}</div>
                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-2xl">{{ user.name }}</h1>
                    <div class="truncate text-sm font-semibold text-gray-500">{{ user.email }}</div>
                    <div class="mt-3 flex flex-wrap justify-center gap-2 sm:justify-start">
                        <span class="badge bg-orange-50 px-3 py-1 text-pop-orange">🔥 {{ stats.streak }} {{ t('stats.streak') }}</span>
                        <span class="badge bg-yellow-50 px-3 py-1 text-pop-yellow-dark">⚡ {{ stats.xp }} XP</span>
                        <span class="badge bg-indigo-50 px-3 py-1 text-pop-blue">📚 {{ stats.due }} {{ t('stats.due') }}</span>
                    </div>
                </div>
                <Link :href="route('dashboard')" class="btn-secondary btn-sm">📊 {{ t('nav.stats') }}</Link>
            </section>

            <div class="card p-5 sm:p-7">
                <LearningSettingsForm />
            </div>

            <div class="card p-5 sm:p-7">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            </div>

            <div class="card p-5 sm:p-7">
                <UpdatePasswordForm />
            </div>

            <div class="card border-red-200 p-5 sm:p-7 dark:border-red-900">
                <DeleteUserForm />
            </div>

            <Link :href="route('logout')" method="post" as="button" class="btn-secondary w-full">{{ t('nav.logout') }}</Link>
        </div>
    </AuthenticatedLayout>
</template>
