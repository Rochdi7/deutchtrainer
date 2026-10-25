<script setup>
import { computed } from 'vue';
import DailyGoalRing from '@/Components/Trainer/DailyGoalRing.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';

const { t } = useI18n();
const page = usePage();
const stats = computed(() => page.props.stats || { streak: 0, today: 0, goal: 20 });
const left = computed(() => Math.max(0, stats.value.goal - stats.value.today));
const met = computed(() => stats.value.today >= stats.value.goal);

function practise() {
    router.post(route('exercises.start'), { kind: 'revision', count: 20 });
}
</script>

<template>
    <div class="card p-4">
        <div class="flex items-center gap-4">
            <DailyGoalRing :today="stats.today" :goal="stats.goal" :size="64" :stroke="7" />
            <div class="min-w-0 flex-1">
                <div class="text-xs font-extrabold uppercase tracking-wide text-gray-400">{{ t('goal.title') }}</div>
                <div class="text-2xl font-black" :class="met ? 'text-pop-green' : 'text-pop-primary'">
                    {{ stats.today }} <span class="text-base font-bold text-gray-400">/ {{ stats.goal }} XP</span>
                </div>
                <div class="text-xs font-bold text-gray-500">
                    <template v-if="met">🎉 {{ t('goal.met') }}</template>
                    <template v-else-if="stats.streak && !stats.today">🔥 {{ t('goal.streak_risk', { n: stats.streak }) }}</template>
                    <template v-else>{{ t('goal.left', { n: left }) }}</template>
                </div>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button v-if="!met" type="button" class="btn-primary btn-sm flex-1" @click="practise">⚡ {{ t('goal.practise') }}</button>
            <Link :href="route('profile.edit') + '#goal'" class="btn-secondary btn-sm" :class="{ 'flex-1': met }">{{ t('goal.change') }}</Link>
        </div>
    </div>
</template>
