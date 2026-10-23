<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});
const { t } = useI18n();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout :title="t('auth.login_title')" :subtitle="t('auth.login_sub')">
        <Head :title="t('auth.login')" />

        <div v-if="status" class="mb-4 rounded-xl bg-brand-50 p-3 text-sm font-bold text-brand-700">{{ status }}</div>

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="email" :value="t('auth.email')" />
                <TextInput id="email" type="email" v-model="form.email" required autofocus autocomplete="username" inputmode="email" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" :value="t('auth.password')" />
                <TextInput id="password" type="password" v-model="form.password" required autocomplete="current-password" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-600 dark:text-gray-300">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    {{ t('auth.remember') }}
                </label>
                <Link v-if="canResetPassword" :href="route('password.request')" class="text-sm font-bold text-pop-blue hover:underline">{{ t('auth.forgot') }}</Link>
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">{{ t('auth.login') }}</button>
        </form>

        <div class="mt-5 border-t-2 border-gray-100 pt-4 text-center text-sm font-semibold text-gray-500 dark:border-gray-800">
            {{ t('auth.no_account') }}
            <Link :href="route('register')" class="ms-1 font-extrabold uppercase text-pop-blue hover:underline">{{ t('auth.register') }}</Link>
        </div>
    </GuestLayout>
</template>
