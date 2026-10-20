<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const { t } = useI18n();

const items = [
    { key: 'dashboard', route: 'dashboard', match: 'dashboard', label: 'nav.dashboard', icon: 'home' },
    { key: 'lektionen', route: 'lektionen.index', match: 'lektionen.*', label: 'nav.lektionen', icon: 'book' },
    { key: 'quiz', route: 'quiz.global', match: 'quiz.*', label: 'nav.quiz', icon: 'bolt' },
    { key: 'profile', route: 'profile.edit', match: 'profile.*', label: 'nav.profile', icon: 'user' },
];
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:hidden"
        aria-label="Hauptnavigation"
    >
        <ul class="grid grid-cols-4">
            <li v-for="item in items" :key="item.key">
                <Link
                    :href="route(item.route)"
                    class="flex min-h-[60px] flex-col items-center justify-center gap-0.5 text-[11px] font-medium transition active:scale-95"
                    :class="route().current(item.match) ? 'text-brand-600 dark:text-brand-400' : 'text-gray-500 dark:text-gray-400'"
                    :aria-current="route().current(item.match) ? 'page' : undefined"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" :stroke-width="route().current(item.match) ? 2.4 : 1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path v-if="item.icon === 'home'" d="M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-8.5Z" />
                        <path v-else-if="item.icon === 'book'" d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15ZM4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5" />
                        <path v-else-if="item.icon === 'bolt'" d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z" />
                        <path v-else d="M20 21a8 8 0 0 0-16 0M12 13a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Z" />
                    </svg>
                    {{ t(item.label) }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
