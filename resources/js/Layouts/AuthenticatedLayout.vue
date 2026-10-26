<script setup>
import { computed } from 'vue';
import DarkModeToggle from '@/Components/Trainer/DarkModeToggle.vue';
import FlashMessage from '@/Components/Trainer/FlashMessage.vue';
import LanguageSwitcher from '@/Components/Trainer/LanguageSwitcher.vue';
import BottomNav from '@/Components/Trainer/BottomNav.vue';
import NavIcon from '@/Components/Trainer/NavIcon.vue';
import InstallBanner from '@/Components/Trainer/InstallBanner.vue';
import DailyGoalRing from '@/Components/Trainer/DailyGoalRing.vue';
import SoundToggle from '@/Components/Trainer/SoundToggle.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

defineProps({
    /** Hide navigation chrome (exercise player) so the answer bar can sit at the bottom. */
    focus: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();
const stats = computed(() => page.props.stats || { streak: 0, xp: 0, due: 0, today: 0, goal: 20 });
const user = computed(() => page.props.auth.user);

const nav = computed(() => [
    { label: 'nav.learn', route: 'course.dashboard', match: 'course.*', icon: 'learn' },
    { label: 'nav.lektionen', route: 'lektionen.index', match: 'lektionen.*', icon: 'book' },
    { label: 'nav.practice', route: 'practice.index', match: 'practice.*', icon: 'target' },
    { label: 'nav.revision', route: 'revision.index', match: 'revision.*', icon: 'repeat' },
    { label: 'nav.quiz', route: 'quiz.global', match: 'quiz.*', icon: 'bolt' },
    { label: 'nav.stats', route: 'dashboard', match: 'dashboard', icon: 'chart' },
    { label: 'nav.profile', route: 'profile.edit', match: 'profile.*', icon: 'user' },
    ...(user.value?.is_admin ? [{ label: 'nav.import', route: 'import.index', match: 'import.*', icon: 'upload' }] : []),
]);
</script>

<template>
    <div class="min-h-full lg:flex">
        <!-- Desktop sidebar -->
        <aside v-if="!focus" class="hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-64 lg:shrink-0 lg:flex-col lg:border-r-2 lg:border-gray-200 lg:bg-white lg:px-4 lg:py-6 dark:lg:border-gray-800 dark:lg:bg-gray-900">
            <Link :href="route('course.dashboard')" class="mb-6 flex items-center gap-2 px-2 text-2xl font-black tracking-tight text-pop-primary">
                <img src="/icons/icon-192.png" alt="" class="h-10 w-10" />
                DeutschTrainer
            </Link>
            <nav class="flex flex-1 flex-col gap-1">
                <Link
                    v-for="n in nav"
                    :key="n.route"
                    :href="route(n.route)"
                    class="flex min-h-[52px] items-center gap-3 rounded-2xl border-2 px-3 text-sm font-extrabold uppercase tracking-wide transition"
                    :class="route().current(n.match) ? 'border-brand-200 bg-brand-50 text-pop-primary dark:border-brand-800 dark:bg-brand-900/40 dark:text-brand-200' : 'border-transparent text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'"
                    :aria-current="route().current(n.match) ? 'page' : undefined"
                >
                    <NavIcon :name="n.icon" class="h-8 w-8" />
                    {{ t(n.label) }}
                </Link>
            </nav>
            <div class="mt-4 space-y-3 border-t-2 border-gray-100 pt-4 dark:border-gray-800">
                <div class="flex items-center justify-between gap-2 px-1">
                    <LanguageSwitcher />
                    <div class="flex">
                        <SoundToggle />
                        <DarkModeToggle />
                    </div>
                </div>
                <div class="truncate px-2 text-xs font-bold text-gray-500">{{ user?.name }}</div>
                <Link :href="route('logout')" method="post" as="button" class="btn-ghost btn-sm w-full justify-start px-2 normal-case tracking-normal">{{ t('nav.logout') }}</Link>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <!-- Mobile / tablet top bar: streak · XP · due -->
            <header v-if="!focus" class="sticky top-0 z-30 border-b-2 border-gray-200 bg-white/95 pt-[env(safe-area-inset-top)] backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 lg:hidden">
                <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6">
                    <div class="flex items-center gap-3 text-sm font-extrabold">
                        <span class="flex items-center gap-1 text-pop-orange" :title="t('dash.streak')"><span class="text-xl" :class="{ grayscale: !stats.streak }">🔥</span>{{ stats.streak }}</span>
                        <span class="flex items-center gap-1 text-pop-yellow-dark" :title="t('dash.xp')"><span class="text-xl">⚡</span>{{ stats.xp }}</span>
                        <Link :href="route('revision.index')" class="flex items-center gap-1 text-pop-blue" :title="t('dash.due_today')"><span class="text-xl">📚</span>{{ stats.due }}</Link>
                        <Link :href="route('profile.edit') + '#goal'" class="flex items-center gap-1 text-pop-primary" :title="t('goal.title')"><DailyGoalRing :today="stats.today" :goal="stats.goal" :size="28" :stroke="3" /><span class="tabular-nums">{{ stats.today }}/{{ stats.goal }}</span></Link>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <LanguageSwitcher />
                        <SoundToggle class="hidden sm:inline-flex" />
                        <DarkModeToggle />
                    </div>
                </div>
            </header>

            <!-- Desktop stat strip -->
            <div v-if="!focus" class="hidden lg:block">
                <div class="mx-auto flex max-w-6xl items-center justify-end gap-5 px-8 pt-5 text-sm font-extrabold">
                    <span class="flex items-center gap-1.5 text-pop-orange"><span class="text-2xl" :class="{ grayscale: !stats.streak }">🔥</span>{{ stats.streak }} <span class="font-bold text-gray-400">{{ t('stats.streak') }}</span></span>
                    <span class="flex items-center gap-1.5 text-pop-yellow-dark"><span class="text-2xl">⚡</span>{{ stats.xp }} <span class="font-bold text-gray-400">{{ t('stats.xp') }}</span></span>
                    <Link :href="route('revision.index')" class="flex items-center gap-1.5 text-pop-blue"><span class="text-2xl">📚</span>{{ stats.due }} <span class="font-bold text-gray-400">{{ t('stats.due') }}</span></Link>
                    <Link :href="route('profile.edit') + '#goal'" class="flex items-center gap-1.5 text-pop-primary" :title="t('goal.title')"><DailyGoalRing :today="stats.today" :goal="stats.goal" :size="32" />{{ stats.today }}/{{ stats.goal }} <span class="font-bold text-gray-400">{{ t('goal.xp_today') }}</span></Link>
                </div>
            </div>

            <header v-if="$slots.header" class="mx-auto max-w-6xl px-4 pt-5 sm:px-6 lg:px-8">
                <slot name="header" />
            </header>

            <FlashMessage />
            <InstallBanner v-if="!focus" />

            <main :class="focus ? 'pb-4' : 'pb-24 lg:pb-10'">
                <slot />
            </main>
        </div>

        <BottomNav v-if="!focus" />
    </div>
</template>
