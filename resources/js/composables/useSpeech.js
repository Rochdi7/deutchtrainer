import { onMounted, ref } from 'vue';

/**
 * German text-to-speech via the browser's SpeechSynthesis API.
 * `supported` is true only when a de-* voice is available, so listening exercises
 * can fall back to showing the transcript instead of pretending to play audio.
 */
export function useSpeech() {
    const supported = ref(false);
    const speaking = ref(false);
    let voice = null;

    function pickVoice() {
        if (typeof window === 'undefined' || !('speechSynthesis' in window)) return;
        const voices = window.speechSynthesis.getVoices();
        voice = voices.find((v) => /^de[-_]DE/i.test(v.lang)) || voices.find((v) => /^de/i.test(v.lang)) || null;
        supported.value = voice !== null;
    }

    onMounted(() => {
        pickVoice();
        if (typeof window !== 'undefined' && 'speechSynthesis' in window) {
            window.speechSynthesis.onvoiceschanged = pickVoice;
        }
    });

    function speak(text, rate = 0.9) {
        if (!supported.value || !text) return false;
        window.speechSynthesis.cancel();
        const u = new SpeechSynthesisUtterance(text);
        u.lang = voice?.lang || 'de-DE';
        if (voice) u.voice = voice;
        u.rate = rate;
        u.onstart = () => (speaking.value = true);
        u.onend = () => (speaking.value = false);
        u.onerror = () => (speaking.value = false);
        window.speechSynthesis.speak(u);
        return true;
    }

    function stop() {
        if (typeof window !== 'undefined' && 'speechSynthesis' in window) window.speechSynthesis.cancel();
        speaking.value = false;
    }

    return { supported, speaking, speak, stop };
}
