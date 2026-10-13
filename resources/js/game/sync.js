import { playApi } from './api';

/**
 * Background sync of round results ("play now, calibrate later").
 *
 * Results are appended to a per-run outbox that is persisted in localStorage,
 * then sent in order without blocking gameplay. If the network drops, sending
 * is retried with backoff, when the browser comes back online, and on the
 * next page load. The server ignores results it has already verified, so
 * resending is always safe. Results the server refuses (run closed, not
 * yours, malformed) are dropped rather than retried forever.
 */
const STORAGE_KEY = 'mm:outbox';
const MAX_BACKOFF_MS = 30000;

function readStore(storage) {
    try {
        return JSON.parse(storage?.getItem(STORAGE_KEY) || '{}') || {};
    } catch {
        return {};
    }
}

export class SyncQueue {
    constructor({ api = playApi, storage = typeof localStorage !== 'undefined' ? localStorage : null, setTimer = (f, ms) => setTimeout(f, ms), clearTimer = (id) => clearTimeout(id) } = {}) {
        this.api = api;
        this.storage = storage;
        this.setTimer = setTimer;
        this.clearTimer = clearTimer;
        this.entries = readStore(storage);
        this.listeners = new Map();
        this.inflight = new Map();
        this.timers = new Map();
        this.backoff = new Map();
    }

    /** Subscribe to events for one run: (type, payload) => void. Returns an unsubscribe function. */
    on(uuid, handler) {
        const set = this.listeners.get(uuid) ?? new Set();
        set.add(handler);
        this.listeners.set(uuid, set);
        return () => set.delete(handler);
    }

    emit(uuid, type, payload) {
        this.listeners.get(uuid)?.forEach((handler) => handler(type, payload));
    }

    pending(uuid) {
        return this.entries[uuid]?.results.length ?? 0;
    }

    push(uuid, result) {
        const entry = (this.entries[uuid] ??= { results: [], finish: null });
        entry.results.push(result);
        this.persist();
        this.schedule(uuid, 100);
    }

    /**
     * Ask the server to close the run after every pending result is sent.
     * Resolves with the server's response, or with null as soon as the network
     * fails (the request stays queued and is retried later).
     */
    finish(uuid, reason) {
        const entry = (this.entries[uuid] ??= { results: [], finish: null });
        entry.finish = reason;
        this.persist();

        return new Promise((resolve) => {
            const off = this.on(uuid, (type, payload) => {
                if (type === 'finished' || type === 'dropped' || (type === 'status' && payload === 'offline')) {
                    off();
                    resolve(type === 'finished' ? payload : null);
                }
            });
            this.schedule(uuid, 0);
        });
    }

    schedule(uuid, delay) {
        this.clearTimer(this.timers.get(uuid));
        this.timers.set(uuid, this.setTimer(() => this.flush(uuid), delay));
    }

    flushAll() {
        Object.keys(this.entries).forEach((uuid) => this.flush(uuid));
    }

    async flush(uuid) {
        if (this.inflight.has(uuid)) {
            // Send again once the current request settles.
            return this.inflight.get(uuid).finally(() => this.schedule(uuid, 0));
        }

        const entry = this.entries[uuid];
        if (!entry || (!entry.results.length && !entry.finish)) return;

        const batch = entry.results.slice(0, 60);
        const finishing = entry.finish && batch.length === entry.results.length ? entry.finish : null;
        this.emit(uuid, 'status', 'pending');

        const request = (finishing ? this.api.finish(uuid, batch, finishing) : this.api.sync(uuid, batch))
            .then((json) => {
                entry.results.splice(0, batch.length);
                this.backoff.delete(uuid);

                if (finishing) {
                    delete this.entries[uuid];
                    this.persist();
                    this.emit(uuid, 'response', json);
                    this.emit(uuid, 'finished', json);
                } else {
                    this.persist();
                    this.emit(uuid, 'response', json);
                    if (entry.results.length || entry.finish) this.schedule(uuid, 0);
                }

                this.emit(uuid, 'status', this.pending(uuid) ? 'pending' : 'synced');
            })
            .catch((error) => {
                if (error.retryable) {
                    const delay = Math.min(MAX_BACKOFF_MS, (this.backoff.get(uuid) ?? 500) * 2);
                    this.backoff.set(uuid, delay);
                    this.schedule(uuid, delay);
                    this.emit(uuid, 'status', 'offline');
                } else {
                    delete this.entries[uuid];
                    this.persist();
                    this.emit(uuid, 'dropped', error);
                    this.emit(uuid, 'status', 'failed');
                }
            })
            .finally(() => this.inflight.delete(uuid));

        this.inflight.set(uuid, request);
        return request;
    }

    persist() {
        try {
            this.storage?.setItem(STORAGE_KEY, JSON.stringify(this.entries));
        } catch {
            // Storage full or unavailable: results still sync from memory this visit.
        }
    }
}

export const syncQueue = new SyncQueue();

if (typeof window !== 'undefined') {
    window.addEventListener('online', () => syncQueue.flushAll());
}
