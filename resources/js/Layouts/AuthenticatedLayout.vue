<script setup>
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import DarkModeToggle from '@/Components/Trainer/DarkModeToggle.vue';
import FlashMessage from '@/Components/Trainer/FlashMessage.vue';
import LanguageSwitcher from '@/Components/Trainer/LanguageSwitcher.vue';
import BottomNav from '@/Components/Trainer/BottomNav.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

defineProps({
    /** Hide the mobile tab bar (used on the quiz screen so the answer panel can sit at the bottom). */
    focus: { type: Boolean, default: false },
});

const { t } = useI18n();

const nav = [
    { label: 'nav.dashboard', route: 'dashboard', match: 'dashboard' },
    { label: 'nav.lektionen', route: 'lektionen.index', match: 'lektionen.*' },
    { label: 'nav.quiz', route: 'quiz.global', match: 'quiz.*' },
];
</script>

<template>
    <div class="min-h-full">
        <nav class="sticky top-0 z-30 border-b border-gray-200 bg-white/90 pt-[env(safe-area-inset-top)] backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-14 items-center justify-between sm:h-16">
                    <div class="flex items-center">
                        <Link :href="route('dashboard')" class="flex items-center gap-2 font-extrabold tracking-tight">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-600 text-white">DT</span>
                            <span>DeutschTrainer</span>
                        </Link>

                        <div class="hidden space-x-6 sm:-my-px sm:ms-8 sm:flex">
                            <NavLink v-for="n in nav" :key="n.route" :href="route(n.route)" :active="route().current(n.match)">
                                {{ t(n.label) }}
                            </NavLink>
                            <NavLink v-if="$page.props.auth.user?.is_admin" :href="route('import.index')" :active="route().current('import.*')">
                                {{ t('nav.import') }}
                            </NavLink>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <LanguageSwitcher />
                        <DarkModeToggle />

                        <div class="hidden sm:flex sm:items-center">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="inline-flex min-h-[44px] items-center rounded-xl px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                                    >
                                        {{ $page.props.auth.user.name }}
                                        <svg class="-me-0.5 ms-2 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('profile.edit')">{{ t('nav.profile') }}</DropdownLink>
                                    <DropdownLink v-if="$page.props.auth.user?.is_admin" :href="route('import.index')">{{ t('nav.import') }}</DropdownLink>
                                    <DropdownLink :href="route('logout')" method="post" as="button">{{ t('nav.logout') }}</DropdownLink>
                                </template>
                            </Dropdown>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <header v-if="$slots.header" class="bg-white dark:bg-gray-900">
            <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 sm:py-5 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <FlashMessage />

        <main :class="focus ? 'pb-4' : 'pb-24 sm:pb-16'">
            <slot />
        </main>

        <BottomNav v-if="!focus" />
    </div>
</template>
