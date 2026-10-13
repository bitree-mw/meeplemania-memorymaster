import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { MIN_TAP_GAP_MS, PHASE, createGameEngine } from './engine';
import { LOGIC } from './logic';

const scoring = {
    base_points: 100, points_per_level: 50, speed_target_ms_per_unit: 800, speed_bonus_per_unit: 20,
    timed_base_points: 50, timed_points_per_unit: 10, timed_mistake_penalty: 25,
};

const memorySpec = (level, attempt = 0, sequence = [2, 0, 3].slice(0, level), extra = {}) => ({
    key: `L${level}A${attempt}`, level, units: sequence.length, preview_ms: 1000, input_timeout_ms: 5000,
    tiles: 4, sequence, flash_ms: 300, gap_ms: 100, ...extra,
});

function session(over = {}) {
    return { uuid: 'run-1', level: 1, attempt: 0, lives: 1, lives_total: 1, max_level: null, rounds_played: 0, score: 0, countdown_seconds: 3, scoring, ...over };
}

function fakeQueue() {
    const listeners = new Set();
    return {
        pushed: [],
        push: vi.fn(function (uuid, result) { this.pushed.push(result); }),
        flush: vi.fn(),
        finish: vi.fn().mockResolvedValue({ summary: { status: 'failed', final_score: 150 }, session: session({ status: 'failed' }) }),
        on: vi.fn((uuid, fn) => { listeners.add(fn); return () => listeners.delete(fn); }),
        emit: (type, payload) => listeners.forEach((fn) => fn(type, payload)),
    };
}

function setup({ game = 'memory-master', mode = 'classic', duration = null, specs, sess, queue = fakeQueue() } = {}) {
    const api = { start: vi.fn().mockResolvedValue({ session: sess ?? session(), specs: specs ?? [memorySpec(1), memorySpec(2), memorySpec(3)] }) };
    const engine = createGameEngine({ game, mode, duration, api, queue, logic: LOGIC[game], clock: () => Date.now() });
    return { engine, api, queue, state: engine.state };
}

const advance = (ms) => vi.advanceTimersByTimeAsync(ms);

/** Step until input unlocks, so tap timings are measured from the unlock moment. */
async function toInput(engine) {
    for (let i = 0; i < 2000 && engine.state.phase !== PHASE.INPUT; i++) await advance(5);
}

async function tap(engine, values, gap = 250) {
    for (const v of values) {
        await advance(gap);
        engine.input(v);
    }
}

beforeEach(() => vi.useFakeTimers({ now: 1_000_000 }));
afterEach(() => vi.useRealTimers());

describe('local-first engine', () => {
    it('prepares the run ahead of time so start is instant', async () => {
        const { engine, api, state } = setup();
        await engine.prepare();
        await engine.prepare();
        expect(api.start).toHaveBeenCalledTimes(1);

        await engine.start();
        expect(state.phase).toBe(PHASE.PREVIEW);
        expect(state.spec.key).toBe('L1A0');
    });

    it('locks input during the preview and unlocks after it', async () => {
        const { engine, state } = setup();
        await engine.start();
        expect(engine.input(2)).toBeUndefined();
        await toInput(engine);
        expect(state.phase).toBe(PHASE.INPUT);
    });

    it('judges taps instantly, scores provisionally and queues the result without waiting', async () => {
        const { engine, queue, state } = setup({ specs: [memorySpec(1, 0, [2]), memorySpec(2, 0, [2, 0])] });
        await engine.start();
        await toInput(engine);
        await tap(engine, [2], 300);

        expect(state.phase).toBe(PHASE.CLEARED);
        expect(queue.push).toHaveBeenCalledWith('run-1', { key: 'L1A0', inputs: [2], timings: [300] });
        // 100 + 50 × 1 + speed bonus 20 × (1 − 300/800) ≈ 13.
        expect(state.score).toBe(163);

        engine.next();
        expect(state.spec.key).toBe('L2A0');
    });

    it('waits for rounds that have not arrived, then continues when a sync reveals them', async () => {
        const { engine, queue, state } = setup({ specs: [memorySpec(1, 0, [2])] });
        await engine.start();
        await toInput(engine);
        await tap(engine, [2]);
        engine.next();

        expect(state.phase).toBe(PHASE.WAITING);
        expect(queue.flush).toHaveBeenCalled();

        queue.emit('response', { specs: [memorySpec(2, 0, [2, 1])], session: session({ rounds_played: 1, level: 2 }) });
        expect(state.phase).toBe(PHASE.PREVIEW);
        expect(state.spec.key).toBe('L2A0');
    });

    it('ignores accidental double taps', async () => {
        const { engine, state } = setup();
        await engine.start();
        await toInput(engine);
        await advance(300);
        engine.input(2);
        await advance(MIN_TAP_GAP_MS - 30);
        expect(engine.input(0)).toBeUndefined();
        expect(state.inputs).toEqual([2]);
    });

    it('accepts an anticipating first tap shortly after unlock', async () => {
        const { engine, state } = setup();
        await engine.start();
        await toInput(engine);
        await advance(60);
        expect(engine.input(2)).toBe(true);
        expect(state.inputs).toEqual([2]);
    });

    it('costs a life on a miss and replays the level with the next attempt', async () => {
        const specs = [memorySpec(1, 0, [2]), memorySpec(1, 1, [3]), memorySpec(1, 2, [1])];
        const { engine, state } = setup({ specs, sess: session({ lives: 3, lives_total: 3 }) });
        await engine.start();
        await toInput(engine);
        await tap(engine, [0]);

        expect(state.phase).toBe(PHASE.LIFE_LOST);
        expect(state.lives).toBe(2);
        engine.next();
        expect(state.spec.key).toBe('L1A1');
    });

    it('ends the run when lives run out and swaps in the official summary', async () => {
        const { engine, queue, state } = setup();
        await engine.start();
        await toInput(engine);
        await tap(engine, [1]);
        await advance(0);

        expect(state.phase).toBe(PHASE.GAMEOVER);
        expect(queue.finish).toHaveBeenCalledWith('run-1', 'ended');
        expect(state.verifying).toBe(false);
        expect(state.summary.final_score).toBe(150);
    });

    it('keeps results on the device when the final sync cannot reach the server', async () => {
        const queue = fakeQueue();
        queue.finish.mockResolvedValue(null);
        const { engine, state } = setup({ queue });
        await engine.start();
        await toInput(engine);
        await tap(engine, [1]);
        await advance(0);

        expect(state.phase).toBe(PHASE.GAMEOVER);
        expect(state.summary).toBeNull();
        expect(state.sync).toBe('offline');
    });

    it('fails a round after the idle timeout', async () => {
        const { engine, queue, state } = setup({ sess: session({ level: 3, lives: 2, lives_total: 2 }), specs: [memorySpec(3), memorySpec(3, 1)] });
        await engine.start();
        await toInput(engine);
        await tap(engine, [2]);
        await advance(5001);

        expect(state.timedOut).toBe(true);
        expect(queue.pushed[0].inputs).toEqual([2]);
        expect(state.phase).toBe(PHASE.LIFE_LOST);
    });

    it('ends when the server finalizes the run on its own (e.g. failed verification)', async () => {
        const { engine, queue, state } = setup();
        await engine.start();
        queue.emit('response', { specs: [], session: session({ status: 'failed' }), summary: { status: 'failed', end_reason: 'integrity' } });
        expect(state.phase).toBe(PHASE.GAMEOVER);
        expect(state.summary.end_reason).toBe('integrity');
    });
});

describe('Timed Rush', () => {
    const timedSpecs = Array.from({ length: 6 }, (_, i) => ({ ...memorySpec(1, 0, [1, 2, 3]), key: `N${i + 1}`, preview_ms: 500, input_timeout_ms: 0 }));

    it('counts down, chains rounds automatically and applies penalties', async () => {
        const { engine, queue, state } = setup({ mode: 'timed', duration: 30, specs: timedSpecs, sess: session({ lives: null, lives_total: null }) });
        const started = engine.start();
        await advance(0);
        expect(state.phase).toBe(PHASE.COUNTDOWN);
        await advance(3000);
        await started;

        await toInput(engine);
        await tap(engine, [1, 2, 3], 200);
        expect(state.phase).toBe(PHASE.FEEDBACK);
        const afterHit = state.score;

        await advance(400);
        await toInput(engine);
        expect(state.spec.key).toBe('N2');
        await tap(engine, [3], 200);
        expect(state.score).toBe(afterHit - 25);
        expect(queue.pushed.map((r) => r.key)).toEqual(['N1', 'N2']);
    });

    it('finishes when the monotonic clock runs out', async () => {
        const { engine, queue, state } = setup({ mode: 'timed', duration: 30, specs: timedSpecs, sess: session({ lives: null, lives_total: null }) });
        queue.finish.mockResolvedValue({ summary: { status: 'completed', final_score: 0 }, session: session() });
        const started = engine.start();
        await advance(3000);
        await started;
        await advance(30_100);

        expect(state.remainingMs).toBe(0);
        expect(state.phase).toBe(PHASE.FINISHED);
        expect(queue.finish).toHaveBeenCalledWith('run-1', 'time_up');
    });
});

describe('game logic mirrors the server', () => {
    it('pairs: neutral first flip, ignores matched cards, fails past the mismatch budget', () => {
        const spec = { cards: [0, 1, 0, 1], mismatches_allowed: 1 };
        const s = LOGIC['meeple-pairs'].init(spec);
        const input = (c) => LOGIC['meeple-pairs'].input(spec, s, c);

        expect(input(0).correct).toBeNull();
        expect(input(2)).toMatchObject({ correct: true, done: null });
        expect(input(0).accepted).toBe(false);
        input(1);
        expect(input(1).accepted).toBe(false); // same card twice
        expect(input(3)).toMatchObject({ correct: true, done: 'cleared' });
    });

    it('pattern: any order, duplicates ignored, wrong cell fails', () => {
        const spec = { lit: [1, 4, 7], mistakes_allowed: 0 };
        const s = LOGIC['pattern-peek'].init(spec);
        const input = (c) => LOGIC['pattern-peek'].input(spec, s, c);

        expect(input(7).correct).toBe(true);
        expect(input(7).accepted).toBe(false);
        expect(input(1).done).toBeNull();
        expect(input(0)).toMatchObject({ correct: false, done: 'failed' });
    });
});
