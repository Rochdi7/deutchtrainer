<script setup>
import DarkModeToggle from '@/Components/Trainer/DarkModeToggle.vue';
import LanguageSwitcher from '@/Components/Trainer/LanguageSwitcher.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
const { t } = useI18n();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white dark:bg-gray-950">
        <header class="mx-auto flex w-full max-w-md items-center justify-between px-4 pt-[max(1rem,env(safe-area-inset-top))] sm:max-w-lg">
            <Link href="/" class="flex items-center gap-2 text-xl font-black text-pop-primary">
                <img src="/assets/deutschtrainer-logo.png" alt="" class="h-10 w-10" />
                DeutschTrainer
            </Link>
            <div class="flex items-center gap-1.5">
                <LanguageSwitcher />
                <DarkModeToggle />
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-4 py-8 sm:max-w-lg">
            <div class="mb-6 text-center">
                <img src="/assets/deutschtrainer-logo.png" alt="DeutschTrainer" class="mx-auto mb-3 h-20 w-20" />
                <h1 v-if="title" class="text-3xl">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 font-semibold text-gray-500">{{ subtitle }}</p>
            </div>
            <div class="card p-5 sm:p-7">
                <slot />
            </div>
            <p class="mt-6 text-center text-xs font-semibold text-gray-400">{{ t('auth.tagline') }}</p>
        </main>
    </div>
</template>
