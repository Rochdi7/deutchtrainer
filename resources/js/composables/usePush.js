import { onMounted, ref } from 'vue';
import axios from 'axios';

function base64UrlToUint8Array(base64) {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
}

async function registration() {
    // The service worker is only auto-registered in production builds; register it on demand for push.
    const existing = await navigator.serviceWorker.getRegistration('/');
    if (!existing) await navigator.serviceWorker.register('/sw.js');
    return navigator.serviceWorker.ready;
}

/** Web Push for this browser: ask permission, subscribe with the server's VAPID key, store the endpoint. */
export function usePush(vapidPublicKey) {
    const supported = typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const permission = ref(supported ? Notification.permission : 'unsupported');
    const subscribed = ref(false);
    const busy = ref(false);

    onMounted(async () => {
        if (!supported) return;
        const reg = await navigator.serviceWorker.getRegistration('/');
        subscribed.value = !!(reg && (await reg.pushManager.getSubscription()));
    });

    async function subscribe() {
        if (!supported || !vapidPublicKey) return false;
        busy.value = true;
        try {
            permission.value = await Notification.requestPermission();
            if (permission.value !== 'granted') return false;
            const reg = await registration();
            const sub =
                (await reg.pushManager.getSubscription()) ||
                (await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64UrlToUint8Array(vapidPublicKey) }));
            const encodings = PushManager.supportedContentEncodings || ['aes128gcm'];
            await axios.post(route('push.subscribe'), { ...sub.toJSON(), content_encoding: encodings.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm' });
            subscribed.value = true;
            return true;
        } finally {
            busy.value = false;
        }
    }

    async function unsubscribe() {
        if (!supported) return;
        busy.value = true;
        try {
            const reg = await navigator.serviceWorker.getRegistration('/');
            const sub = reg && (await reg.pushManager.getSubscription());
            if (sub) {
                await axios.delete(route('push.unsubscribe'), { data: { endpoint: sub.endpoint } });
                await sub.unsubscribe();
            }
            subscribed.value = false;
        } finally {
            busy.value = false;
        }
    }

    return { supported, permission, subscribed, busy, subscribe, unsubscribe };
}
