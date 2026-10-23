<script setup>
import { Link } from '@inertiajs/vue3';
import NavIcon from '@/Components/Trainer/NavIcon.vue';
import { useI18n } from '@/i18n';

const { t } = useI18n();

const items = [
    { key: 'learn', route: 'course.dashboard', match: 'course.*', label: 'nav.learn', icon: 'learn' },
    { key: 'lektionen', route: 'lektionen.index', match: 'lektionen.*', label: 'nav.lektionen', icon: 'book' },
    { key: 'practice', route: 'practice.index', match: 'practice.*', label: 'nav.practice', icon: 'target' },
    { key: 'revision', route: 'revision.index', match: 'revision.*', label: 'nav.revision', icon: 'repeat' },
    { key: 'stats', route: 'dashboard', match: 'dashboard', label: 'nav.stats', icon: 'chart' },
];
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] dark:border-gray-800 dark:bg-gray-900 lg:hidden"
        aria-label="Hauptnavigation"
    >
        <ul class="mx-auto grid max-w-lg grid-cols-5 px-1">
            <li v-for="item in items" :key="item.key">
                <Link
                    :href="route(item.route)"
                    class="flex min-h-[64px] flex-col items-center justify-center gap-0.5 py-1.5 text-[10px] font-extrabold uppercase tracking-wide transition active:scale-95"
                    :class="route().current(item.match) ? 'text-pop-primary' : 'text-gray-400'"
                    :aria-current="route().current(item.match) ? 'page' : undefined"
                >
                    <span class="grid h-11 w-12 place-items-center rounded-xl border-2 transition" :class="route().current(item.match) ? 'border-brand-200 bg-brand-50 dark:border-brand-800 dark:bg-brand-900/40' : 'border-transparent'">
                        <NavIcon :name="item.icon" class="h-8 w-8" />
                    </span>
                    {{ t(item.label) }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
