import { onBeforeUnmount, ref } from 'vue';

/**
 * German speech-to-text via the browser's SpeechRecognition API (Chrome, Edge, Safari; not Firefox).
 * `listen()` resolves with up to 5 transcript alternatives, or [] when nothing was understood.
 * Chrome sends the audio to its own speech service, so this needs a connection and microphone permission.
 */
export function useSpeechRecognition() {
    const Recognition = typeof window !== 'undefined' ? window.SpeechRecognition || window.webkitSpeechRecognition : null;
    const supported = ref(!!Recognition);
    const listening = ref(false);
    const error = ref(null); // 'not-allowed' | 'no-speech' | 'network' | …
    let rec = null;

    function listen(lang = 'de-DE') {
        if (!Recognition) return Promise.resolve([]);
        stop();
        error.value = null;

        return new Promise((resolve) => {
            let transcripts = [];
            rec = new Recognition();
            rec.lang = lang;
            rec.interimResults = false;
            rec.maxAlternatives = 5;
            rec.continuous = false;
            rec.onresult = (e) => {
                transcripts = Array.from(e.results[0] || []).map((alt) => alt.transcript.trim()).filter(Boolean);
            };
            rec.onerror = (e) => {
                error.value = e.error;
            };
            rec.onend = () => {
                listening.value = false;
                rec = null;
                resolve(transcripts);
            };
            listening.value = true;
            try {
                rec.start();
            } catch (e) {
                listening.value = false;
                error.value = 'start-failed';
                resolve([]);
            }
        });
    }

    function stop() {
        if (rec) rec.stop();
    }

    onBeforeUnmount(() => rec?.abort());

    return { supported, listening, error, listen, stop };
}
