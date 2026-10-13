import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from './api';
import { SyncQueue } from './sync';

function memoryStorage() {
    const data = {};
    return { getItem: (k) => data[k] ?? null, setItem: (k, v) => (data[k] = v), data };
}

const flushPromises = () => vi.advanceTimersByTimeAsync(0);

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

describe('SyncQueue', () => {
    it('sends results in the background and reports the server response', async () => {
        const api = { sync: vi.fn().mockResolvedValue({ verified: 1, specs: [] }), finish: vi.fn() };
        const queue = new SyncQueue({ api, storage: memoryStorage() });
        const events = [];
        queue.on('run', (type) => events.push(type));

        queue.push('run', { key: 'L1A0', inputs: [1], timings: [300] });
        await vi.advanceTimersByTimeAsync(150);

        expect(api.sync).toHaveBeenCalledWith('run', [{ key: 'L1A0', inputs: [1], timings: [300] }]);
        expect(events).toContain('response');
        expect(queue.pending('run')).toBe(0);
    });

    it('persists results and retries with backoff while offline', async () => {
        const storage = memoryStorage();
        const api = {
            sync: vi.fn().mockRejectedValueOnce(new ApiError('offline', { network: true })).mockResolvedValue({ verified: 1 }),
            finish: vi.fn(),
        };
        const queue = new SyncQueue({ api, storage });
        const statuses = [];
        queue.on('run', (type, payload) => type === 'status' && statuses.push(payload));

        queue.push('run', { key: 'L1A0', inputs: [1], timings: [300] });
        await vi.advanceTimersByTimeAsync(150);

        expect(statuses).toContain('offline');
        expect(JSON.parse(storage.data['mm:outbox']).run.results).toHaveLength(1);

        await vi.advanceTimersByTimeAsync(1100);
        expect(api.sync).toHaveBeenCalledTimes(2);
        expect(statuses.at(-1)).toBe('synced');
    });

    it('survives a page reload: a new queue picks up stored results', async () => {
        const storage = memoryStorage();
        storage.setItem('mm:outbox', JSON.stringify({ old: { results: [{ key: 'N1', inputs: [], timings: [] }], finish: 'time_up' } }));
        const api = { sync: vi.fn(), finish: vi.fn().mockResolvedValue({ summary: {} }) };

        new SyncQueue({ api, storage }).flushAll();
        await flushPromises();

        expect(api.finish).toHaveBeenCalledWith('old', [{ key: 'N1', inputs: [], timings: [] }], 'time_up');
        expect(JSON.parse(storage.data['mm:outbox'])).toEqual({});
    });

    it('finish resolves with the summary, or null as soon as the network fails', async () => {
        const api = { sync: vi.fn(), finish: vi.fn().mockResolvedValue({ summary: { final_score: 10 } }) };
        const queue = new SyncQueue({ api, storage: memoryStorage() });
        const done = queue.finish('run', 'ended');
        await flushPromises();
        expect((await done).summary.final_score).toBe(10);

        const offlineApi = { sync: vi.fn(), finish: vi.fn().mockRejectedValue(new ApiError('offline', { network: true })) };
        const offline = new SyncQueue({ api: offlineApi, storage: memoryStorage() });
        const pending = offline.finish('run', 'ended');
        await flushPromises();
        expect(await pending).toBeNull();
        expect(offline.entries.run.finish).toBe('ended');
    });

    it('drops results the server refuses instead of retrying forever', async () => {
        const api = { sync: vi.fn().mockRejectedValue(new ApiError('nope', { status: 403 })), finish: vi.fn() };
        const queue = new SyncQueue({ api, storage: memoryStorage() });
        queue.push('run', { key: 'L1A0', inputs: [], timings: [] });
        await vi.advanceTimersByTimeAsync(150);
        await vi.advanceTimersByTimeAsync(60_000);

        expect(api.sync).toHaveBeenCalledTimes(1);
        expect(queue.entries.run).toBeUndefined();
    });
});
