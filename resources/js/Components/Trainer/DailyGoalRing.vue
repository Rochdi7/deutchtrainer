<script setup>
import { computed } from 'vue';

const props = defineProps({
    today: { type: Number, default: 0 },
    goal: { type: Number, default: 20 },
    size: { type: Number, default: 36 },
    stroke: { type: Number, default: 4 },
});

const r = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * r.value);
const pct = computed(() => (props.goal ? Math.min(1, props.today / props.goal) : 0));
const met = computed(() => props.today >= props.goal);
</script>

<template>
    <span class="relative inline-grid shrink-0 place-items-center" :style="{ width: size + 'px', height: size + 'px' }">
        <svg :width="size" :height="size" class="-rotate-90" aria-hidden="true">
            <circle :cx="size / 2" :cy="size / 2" :r="r" fill="none" :stroke-width="stroke" class="stroke-gray-200 dark:stroke-gray-700" />
            <circle
                :cx="size / 2"
                :cy="size / 2"
                :r="r"
                fill="none"
                :stroke-width="stroke"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="circumference * (1 - pct)"
                :class="met ? 'stroke-pop-green' : 'stroke-pop-primary'"
                style="transition: stroke-dashoffset 0.6s ease"
            />
        </svg>
        <span class="absolute inset-0 grid place-items-center" :style="{ fontSize: size * 0.42 + 'px' }">
            <slot>{{ met ? '✓' : '🎯' }}</slot>
        </span>
    </span>
</template>
