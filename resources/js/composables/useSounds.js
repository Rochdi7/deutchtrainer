import { ref } from 'vue';

/**
 * UI sound effects synthesised with the Web Audio API (no audio files to download).
 * The on/off choice is a per-device preference kept in localStorage.
 */
const STORAGE_KEY = 'sounds';
export const soundsEnabled = ref(true);
try {
    soundsEnabled.value = localStorage.getItem(STORAGE_KEY) !== 'off';
} catch (e) {}

let ctx = null;
function audio() {
    if (typeof window === 'undefined') return null;
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    ctx ??= new Ctx();
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
}

/** One enveloped oscillator note. `at` is seconds from now. */
function tone(ac, { freq, to = null, at = 0, dur = 0.12, type = 'sine', gain = 0.18 }) {
    const t0 = ac.currentTime + at;
    const osc = ac.createOscillator();
    const amp = ac.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t0);
    if (to) osc.frequency.exponentialRampToValueAtTime(to, t0 + dur);
    amp.gain.setValueAtTime(0.0001, t0);
    amp.gain.exponentialRampToValueAtTime(gain, t0 + 0.01);
    amp.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    osc.connect(amp).connect(ac.destination);
    osc.start(t0);
    osc.stop(t0 + dur + 0.02);
}

const SOUNDS = {
    tap: (ac) => tone(ac, { freq: 660, to: 520, dur: 0.05, type: 'triangle', gain: 0.07 }),
    select: (ac) => tone(ac, { freq: 520, to: 700, dur: 0.07, type: 'triangle', gain: 0.1 }),
    correct: (ac) => {
        tone(ac, { freq: 784, dur: 0.12, type: 'triangle' });
        tone(ac, { freq: 1175, at: 0.09, dur: 0.22, type: 'triangle' });
    },
    wrong: (ac) => {
        tone(ac, { freq: 220, to: 160, dur: 0.18, type: 'sawtooth', gain: 0.07 });
        tone(ac, { freq: 196, to: 140, at: 0.13, dur: 0.24, type: 'sawtooth', gain: 0.07 });
    },
    complete: (ac) => [523, 659, 784, 1047].forEach((f, i) => tone(ac, { freq: f, at: i * 0.1, dur: 0.24, type: 'triangle', gain: 0.15 })),
    goal: (ac) => {
        [784, 988, 1175, 1568].forEach((f, i) => tone(ac, { freq: f, at: i * 0.08, dur: 0.2, type: 'square', gain: 0.06 }));
        tone(ac, { freq: 1568, at: 0.36, dur: 0.5, type: 'triangle', gain: 0.14 });
    },
    mic: (ac) => tone(ac, { freq: 880, to: 1320, dur: 0.1, type: 'sine', gain: 0.12 }),
    micOff: (ac) => tone(ac, { freq: 1320, to: 880, dur: 0.1, type: 'sine', gain: 0.1 }),
};

export function playSound(name) {
    if (!soundsEnabled.value || !SOUNDS[name]) return;
    try {
        const ac = audio();
        if (ac) SOUNDS[name](ac);
    } catch (e) {}
}

export function setSoundsEnabled(value) {
    soundsEnabled.value = value;
    try {
        localStorage.setItem(STORAGE_KEY, value ? 'on' : 'off');
    } catch (e) {}
    if (value) playSound('select');
}

/**
 * Soft click for every enabled button / button-styled link in the app.
 * Elements can opt out with data-no-sound (e.g. answer options that play their own sound).
 */
export function installClickSounds() {
    if (typeof document === 'undefined') return;
    document.addEventListener(
        'click',
        (e) => {
            const el = e.target.closest?.('button, a.btn, a[class*="btn-"], [role="button"], .path-node, .pill');
            if (!el || el.disabled || el.getAttribute('aria-disabled') === 'true' || el.closest('[data-no-sound]')) return;
            playSound('tap');
        },
        { capture: true },
    );
}

export function useSounds() {
    return { enabled: soundsEnabled, play: playSound, setEnabled: setSoundsEnabled };
}
