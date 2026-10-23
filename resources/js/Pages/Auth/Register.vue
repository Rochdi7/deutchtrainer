<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout :title="t('auth.register_title')" :subtitle="t('auth.register_sub')">
        <Head :title="t('auth.register')" />

        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <InputLabel for="name" :value="t('auth.name')" />
                <TextInput id="name" type="text" v-model="form.name" required autofocus autocomplete="name" />
                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" :value="t('auth.email')" />
                <TextInput id="email" type="email" v-model="form.email" required autocomplete="username" inputmode="email" />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" :value="t('auth.password')" />
                <TextInput id="password" type="password" v-model="form.password" required autocomplete="new-password" />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" :value="t('auth.confirm_password')" />
                <TextInput id="password_confirmation" type="password" v-model="form.password_confirmation" required autocomplete="new-password" />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <button type="submit" class="btn-primary w-full" :disabled="form.processing">{{ t('auth.register') }}</button>
        </form>

        <div class="mt-5 border-t-2 border-gray-100 pt-4 text-center text-sm font-semibold text-gray-500 dark:border-gray-800">
            {{ t('auth.have_account') }}
            <Link :href="route('login')" class="ms-1 font-extrabold uppercase text-pop-blue hover:underline">{{ t('auth.login') }}</Link>
        </div>
    </GuestLayout>
</template>
