/**
 * Synthesised sound effects (Web Audio API) — no audio files to download.
 * The AudioContext is created on the first user gesture, as browsers require.
 */
const STORAGE_KEY = 'mm:sound-enabled';

function readPreference() {
    try {
        return localStorage.getItem(STORAGE_KEY) !== '0';
    } catch {
        return true;
    }
}

export class SoundBoard {
    constructor() {
        this.ctx = null;
        this.master = null;
        this.enabled = readPreference();
    }

    setEnabled(enabled) {
        this.enabled = enabled;
        try {
            localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
        } catch {
            // Storage unavailable (private mode): preference lasts for this page only.
        }
        if (enabled) this.unlock();
    }

    unlock() {
        if (typeof window === 'undefined') return;
        if (!this.ctx) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            this.ctx = new Ctx();
            this.master = this.ctx.createGain();
            this.master.gain.value = 0.55;
            this.master.connect(this.ctx.destination);
        }
        if (this.ctx.state === 'suspended') this.ctx.resume();
    }

    tone(frequency, durationMs = 220, { type = 'triangle', gain = 0.22, delayMs = 0, slideTo = null } = {}) {
        if (!this.enabled) return;
        this.unlock();
        if (!this.ctx) return;

        const start = this.ctx.currentTime + delayMs / 1000;
        const end = start + durationMs / 1000;
        const osc = this.ctx.createOscillator();
        const amp = this.ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(frequency, start);
        if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, end);

        // Short attack and exponential release avoid clicks.
        amp.gain.setValueAtTime(0.0001, start);
        amp.gain.exponentialRampToValueAtTime(gain, start + 0.012);
        amp.gain.exponentialRampToValueAtTime(0.0001, end);

        osc.connect(amp).connect(this.master);
        osc.start(start);
        osc.stop(end + 0.02);
    }

    tile(frequency, durationMs) {
        this.tone(frequency, Math.min(Math.max(durationMs, 160), 600), { type: 'triangle', gain: 0.26 });
        this.tone(frequency * 2, Math.min(durationMs, 300), { type: 'sine', gain: 0.05 });
    }

    correct() {
        this.tone(1318.5, 90, { type: 'sine', gain: 0.12 });
    }

    wrong() {
        this.tone(196, 380, { type: 'sawtooth', gain: 0.12, slideTo: 98 });
        this.tone(207.7, 380, { type: 'square', gain: 0.05, slideTo: 103 });
    }

    levelUp() {
        [523.25, 659.25, 783.99, 1046.5].forEach((f, i) => this.tone(f, 180, { type: 'triangle', gain: 0.2, delayMs: i * 90 }));
    }

    personalBest() {
        [783.99, 987.77, 1174.66, 1567.98].forEach((f, i) => this.tone(f, 220, { type: 'sine', gain: 0.18, delayMs: 420 + i * 110 }));
    }

    gameOver() {
        [392, 329.63, 261.63].forEach((f, i) => this.tone(f, 260, { type: 'triangle', gain: 0.2, delayMs: i * 160 }));
    }

    click() {
        this.tone(880, 45, { type: 'sine', gain: 0.07 });
    }

    tick() {
        this.tone(660, 110, { type: 'square', gain: 0.07 });
    }

    go() {
        this.tone(1046.5, 260, { type: 'triangle', gain: 0.2 });
    }
}

export const sounds = new SoundBoard();
