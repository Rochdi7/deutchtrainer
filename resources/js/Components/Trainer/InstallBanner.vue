<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from '@/i18n';

/**
 * "Install the app" banner: uses the browser's install prompt where available (Android/Chrome)
 * and shows the Share → "Add to Home Screen" hint on iOS. Hidden once installed or dismissed.
 */
const { t } = useI18n();
const visible = ref(false);
const ios = ref(false);
let deferred = null;

function standalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}
function dismissed() {
    try {
        return localStorage.getItem('install.dismissed') === '1';
    } catch (e) {
        return false;
    }
}
function onPrompt(e) {
    e.preventDefault();
    deferred = e;
    if (!dismissed() && !standalone()) visible.value = true;
}
async function install() {
    if (!deferred) return;
    deferred.prompt();
    await deferred.userChoice.catch(() => {});
    deferred = null;
    visible.value = false;
}
function dismiss() {
    visible.value = false;
    try {
        localStorage.setItem('install.dismissed', '1');
    } catch (e) {}
}
onMounted(() => {
    window.addEventListener('beforeinstallprompt', onPrompt);
    const ua = navigator.userAgent || '';
    ios.value = /iphone|ipad|ipod/i.test(ua) && !standalone();
    if (ios.value && !dismissed()) visible.value = true;
});
onBeforeUnmount(() => window.removeEventListener('beforeinstallprompt', onPrompt));
</script>

<template>
    <div v-if="visible" class="mx-auto mt-3 max-w-6xl px-4 sm:px-6 lg:hidden">
        <div class="flex items-center gap-3 rounded-2xl border-2 border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-900 dark:bg-indigo-950/40">
            <img src="/assets/deutschtrainer-logo.png" alt="" class="h-12 w-12" />
            <div class="min-w-0 flex-1 text-sm">
                <div class="font-black">{{ t('pwa.title') }}</div>
                <div class="text-xs font-semibold text-gray-500">{{ ios ? t('pwa.ios_hint') : t('pwa.sub') }}</div>
            </div>
            <button v-if="!ios" type="button" class="btn-blue btn-sm shrink-0" @click="install">{{ t('pwa.install') }}</button>
            <button type="button" class="shrink-0 px-1 text-xl text-gray-400" :aria-label="t('common.close')" @click="dismiss">✕</button>
        </div>
    </div>
</template>
