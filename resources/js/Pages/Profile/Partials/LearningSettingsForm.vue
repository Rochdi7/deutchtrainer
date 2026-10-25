<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/i18n';
import { useSounds } from '@/composables/useSounds';
import { usePush } from '@/composables/usePush';

const { t } = useI18n();
const page = usePage();
const user = computed(() => page.props.auth.user);
const vapidKey = page.props.push?.vapid_public_key;
const sounds = useSounds();
const push = usePush(vapidKey);
const testMessage = ref(null);

const goals = [10, 20, 30, 50];
const browserTimezone = (() => {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch (e) {
        return null;
    }
})();

const form = useForm({
    daily_goal: user.value.daily_goal || 20,
    reminders_enabled: !!user.value.reminders_enabled,
    reminder_hour: user.value.reminder_hour ?? 18,
    timezone: browserTimezone || user.value.timezone,
});

function save() {
    form.patch(route('settings.learning'), { preserveScroll: true });
}

async function toggleReminders() {
    form.reminders_enabled = !form.reminders_enabled;
    if (form.reminders_enabled && push.supported && vapidKey && !push.subscribed.value) {
        try {
            await push.subscribe();
        } catch (e) {}
    }
    save();
}

async function sendTest() {
    testMessage.value = null;
    try {
        const { data } = await axios.post(route('push.test'));
        testMessage.value = { ok: true, text: data.message };
    } catch (e) {
        testMessage.value = { ok: false, text: e.response?.data?.message || 'Error' };
    }
}

const hourLabel = (h) => String(h).padStart(2, '0') + ':00';
</script>

<template>
    <section id="goal" class="scroll-mt-20 space-y-6">
        <header>
            <h2 class="text-lg font-medium">{{ t('settings.title') }}</h2>
        </header>

        <!-- daily goal -->
        <div>
            <div class="text-sm font-bold">🎯 {{ t('settings.goal') }}</div>
            <p class="text-xs text-gray-500">{{ t('settings.goal_hint') }}</p>
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button
                    v-for="g in goals"
                    :key="g"
                    type="button"
                    class="option flex-col !items-center !gap-0 !py-3 text-center"
                    :class="{ 'option-selected': form.daily_goal === g }"
                    :aria-pressed="form.daily_goal === g"
                    @click="form.daily_goal = g; save()"
                >
                    <span class="text-xl font-black">{{ g }} XP</span>
                    <span class="text-xs font-bold text-gray-500">{{ t('settings.goal_' + g) }}</span>
                </button>
            </div>
        </div>

        <!-- sounds -->
        <div class="flex items-center justify-between gap-4">
            <div>
                <div class="text-sm font-bold">🔊 {{ t('settings.sounds') }}</div>
                <p class="text-xs text-gray-500">{{ t('settings.sounds_hint') }}</p>
            </div>
            <button type="button" role="switch" data-no-sound :aria-checked="sounds.enabled.value" class="relative h-8 w-14 shrink-0 rounded-full transition" :class="sounds.enabled.value ? 'bg-pop-green' : 'bg-gray-300 dark:bg-gray-700'" @click="sounds.setEnabled(!sounds.enabled.value)">
                <span class="absolute top-1 h-6 w-6 rounded-full bg-white shadow transition-all" :class="sounds.enabled.value ? 'left-7' : 'left-1'"></span>
            </button>
        </div>

        <!-- reminders -->
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-bold">⏰ {{ t('settings.reminders') }}</div>
                    <p class="text-xs text-gray-500">{{ t('settings.reminders_hint') }}</p>
                </div>
                <button type="button" role="switch" :aria-checked="form.reminders_enabled" class="relative h-8 w-14 shrink-0 rounded-full transition" :class="form.reminders_enabled ? 'bg-pop-green' : 'bg-gray-300 dark:bg-gray-700'" :disabled="push.busy.value" @click="toggleReminders">
                    <span class="absolute top-1 h-6 w-6 rounded-full bg-white shadow transition-all" :class="form.reminders_enabled ? 'left-7' : 'left-1'"></span>
                </button>
            </div>

            <template v-if="form.reminders_enabled">
                <label class="flex items-center gap-2 text-sm">
                    {{ t('settings.reminder_time') }}
                    <select v-model.number="form.reminder_hour" class="input inline-block w-28" @change="save">
                        <option v-for="h in 24" :key="h - 1" :value="h - 1">{{ hourLabel(h - 1) }}</option>
                    </select>
                    <span v-if="form.timezone" class="text-xs text-gray-400">{{ form.timezone }}</span>
                </label>

                <div class="card-flat space-y-2 p-3 text-sm">
                    <p v-if="!vapidKey" class="text-gray-500">{{ t('settings.push_unconfigured') }}</p>
                    <p v-else-if="!push.supported" class="text-gray-500">{{ t('settings.push_unsupported') }}</p>
                    <p v-else-if="push.permission.value === 'denied'" class="font-semibold text-pop-red">{{ t('settings.push_denied') }}</p>
                    <template v-else-if="push.subscribed.value">
                        <p class="font-semibold text-pop-green">✓ {{ t('settings.push_on') }}</p>
                        <button type="button" class="btn-secondary btn-sm" @click="sendTest">🔔 {{ t('settings.push_test') }}</button>
                    </template>
                    <button v-else type="button" class="btn-primary btn-sm" :disabled="push.busy.value" @click="push.subscribe()">🔔 {{ t('settings.push_enable') }}</button>
                    <p v-if="testMessage" class="text-xs font-semibold" :class="testMessage.ok ? 'text-pop-green' : 'text-pop-red'">{{ testMessage.text }}</p>
                </div>
            </template>
        </div>
    </section>
</template>
