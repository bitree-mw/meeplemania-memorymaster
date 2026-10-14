import { ref } from 'vue';
import { request } from '@/game/api';

/**
 * Lightweight presence heartbeat.
 *  - Beats every `heartbeat_seconds` only while the page is visible.
 *  - Tabs coordinate through localStorage so one browser sends one heartbeat
 *    per interval; every tab reads the shared counts. (The server also keys
 *    presence per user / session, so duplicates never inflate counts.)
 */
const LAST_KEY = 'mm:presence-last';
const COUNTS_KEY = 'mm:presence-counts';

const counts = ref(null);
const playing = ref(false);
let intervalMs = 45000;
let timer = null;
let started = false;

function readShared() {
    try {
        const stored = JSON.parse(localStorage.getItem(COUNTS_KEY) || 'null');
        if (stored) counts.value = stored;
    } catch {
        // ignore
    }
}

function lastBeat() {
    try {
        return Number(localStorage.getItem(LAST_KEY) || 0);
    } catch {
        return 0;
    }
}

async function beat(force = false) {
    if (document.visibilityState !== 'visible') return;
    if (!force && Date.now() - lastBeat() < intervalMs * 0.8) return readShared();

    try {
        localStorage.setItem(LAST_KEY, String(Date.now()));
    } catch {
        // ignore
    }

    try {
        counts.value = await request('POST', '/api/presence/heartbeat', { playing: playing.value });
        localStorage.setItem(COUNTS_KEY, JSON.stringify(counts.value));
    } catch (error) {
        // Signed out because the account signed in on another device: reload to show why.
        if (error?.status === 401) window.location.reload();
        // Otherwise presence is best effort; try again next interval.
    }
}

function schedule() {
    clearInterval(timer);
    timer = setInterval(() => beat(), intervalMs);
}

export function usePresence() {
    function start(seconds, initialCounts = null) {
        if (initialCounts) counts.value = initialCounts;
        if (started || typeof window === 'undefined') return;
        started = true;
        intervalMs = Math.max(30, Math.min(60, seconds || 45)) * 1000;

        beat();
        schedule();

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                beat();
                schedule();
            } else {
                clearInterval(timer); // no heartbeats from hidden tabs
            }
        });
        window.addEventListener('storage', (e) => e.key === COUNTS_KEY && readShared());
    }

    function setPlaying(value) {
        if (playing.value === value) return;
        playing.value = value;
        if (started) beat(true);
    }

    return { counts, start, setPlaying };
}
