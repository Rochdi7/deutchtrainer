<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ArabicText from '@/Components/Trainer/ArabicText.vue';
import { useI18n } from '@/i18n';

const props = defineProps({ words: { type: Array, required: true } });
const { t } = useI18n();

const order = ref([]);
const index = ref(0);
const flipped = ref(false);
const front = ref('de'); // 'de' | 'ar'
const dragX = ref(0);
let startX = null;
let startY = null;
let moved = false;

function reshuffle() {
    order.value = [...props.words].sort(() => Math.random() - 0.5);
    index.value = 0;
    flipped.value = false;
}
watch(() => props.words, reshuffle, { immediate: true });

const word = computed(() => order.value[index.value]);

function next() {
    flipped.value = false;
    index.value = (index.value + 1) % order.value.length;
}
function prev() {
    flipped.value = false;
    index.value = (index.value - 1 + order.value.length) % order.value.length;
}
function flip() {
    flipped.value = !flipped.value;
}

// Touch: swipe left/right to browse, tap to flip.
function onTouchStart(e) {
    startX = e.touches[0].clientX;
    startY = e.touches[0].clientY;
    moved = false;
}
function onTouchMove(e) {
    if (startX === null) return;
    const dx = e.touches[0].clientX - startX;
    const dy = e.touches[0].clientY - startY;
    if (Math.abs(dx) > 8 && Math.abs(dx) > Math.abs(dy)) {
        moved = true;
        dragX.value = dx;
    }
}
function onTouchEnd() {
    if (moved) {
        if (dragX.value < -60) next();
        else if (dragX.value > 60) prev();
    }
    dragX.value = 0;
    startX = null;
}
function onClick() {
    if (!moved) flip();
}

function onKey(e) {
    if (e.target.closest('input,textarea,select')) return;
    if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        flip();
    } else if (e.key === 'ArrowRight') next();
    else if (e.key === 'ArrowLeft') prev();
}
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

const cardStyle = computed(() => ({
    transform: dragX.value ? `translateX(${dragX.value}px) rotate(${dragX.value / 40}deg)` : '',
    transition: dragX.value ? 'none' : 'transform 0.2s',
}));
</script>

<template>
    <div v-if="word" class="mx-auto max-w-xl">
        <div class="mb-3 flex items-center justify-between gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span class="tabular-nums">{{ index + 1 }} / {{ order.length }}</span>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary !min-h-[40px] !px-3 !py-1 text-xs" @click="front = front === 'de' ? 'ar' : 'de'">
                    {{ t('fc.front', { side: front === 'de' ? t('fc.de') : t('fc.ar') }) }}
                </button>
                <button type="button" class="btn-secondary !min-h-[40px] !px-3 !py-1 text-xs" @click="reshuffle">{{ t('fc.shuffle') }}</button>
            </div>
        </div>

        <div class="touch-pan-y" :style="cardStyle" @touchstart.passive="onTouchStart" @touchmove.passive="onTouchMove" @touchend="onTouchEnd" @touchcancel="onTouchEnd">
            <div class="flip-card h-72 cursor-pointer select-none sm:h-80" :class="{ flipped }" @click="onClick" role="button" tabindex="0" :aria-label="t('fc.flip')">
                <div class="flip-inner h-full">
                    <div class="flip-face card flex h-full flex-col items-center justify-center p-6 text-center">
                        <template v-if="front === 'de'">
                            <div class="text-3xl font-bold sm:text-4xl">{{ word.display }}</div>
                            <div v-if="word.plural" class="mt-2 text-gray-500">Pl. {{ word.plural }}</div>
                        </template>
                        <ArabicText v-else :text="word.arabic" tag="div" class="text-3xl font-semibold sm:text-4xl" />
                        <div class="mt-5 text-xs text-gray-400">{{ t('fc.tap') }}</div>
                    </div>
                    <div class="flip-face flip-back card flex h-full flex-col items-center justify-center bg-brand-50 p-6 text-center dark:bg-brand-900/20">
                        <template v-if="front === 'de'">
                            <ArabicText :text="word.arabic" tag="div" class="text-3xl font-semibold sm:text-4xl" />
                        </template>
                        <template v-else>
                            <div class="text-3xl font-bold sm:text-4xl">{{ word.display }}</div>
                            <div v-if="word.plural" class="mt-2 text-gray-500">Pl. {{ word.plural }}</div>
                        </template>
                        <div v-if="word.conjugation" class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            ich {{ word.conjugation.ich }} · du {{ word.conjugation.du }} · er/sie/es {{ word.conjugation.er_sie_es }}
                        </div>
                        <div v-if="word.example_de" class="mt-3 text-sm italic text-gray-600 dark:text-gray-300">{{ word.example_de }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
            <button type="button" class="btn-secondary min-h-[52px]" @click="prev">{{ t('fc.prev') }}</button>
            <button type="button" class="btn-primary min-h-[52px]" @click="next">{{ t('fc.next') }}</button>
        </div>
        <p class="mt-2 hidden text-center text-xs text-gray-400 sm:block">{{ t('fc.keys') }}</p>
    </div>
</template>
