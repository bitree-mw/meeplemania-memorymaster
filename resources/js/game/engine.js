/**
 * Local-first gameplay state machine shared by every game and mode.
 *
 * The server sends round specs a few rounds ahead; this engine plays them with
 * no network waits, judges each tap instantly (game logic mirrors the server),
 * keeps a provisional score, and hands every finished round to the sync queue
 * to be verified in the background. The server's score is the official one.
 *
 * Framework-agnostic: it mutates a plain `state` object (pass a Vue reactive()
 * object to render it). Time comes from an injected monotonic clock
 * (performance.now() by default) so previews, tap timings and the Timed Rush
 * clock stay accurate even when rendering lags.
 *
 *   idle ─start─▶ [countdown] ─▶ preview ─▶ input ─▶ feedback ─▶ next round    Timed Rush
 *                                                  ├─▶ cleared ──next──▶ preview  Level modes
 *                                                  ├─▶ life_lost ─next─▶ preview
 *                                                  └─▶ gameover / finished (verifying ▶ official summary)
 */
import { roundPoints } from './scoring';

export const PHASE = Object.freeze({
    IDLE: 'idle',
    STARTING: 'starting',
    COUNTDOWN: 'countdown',
    PREVIEW: 'preview',
    INPUT: 'input',
    FEEDBACK: 'feedback',
    WAITING: 'waiting',
    CLEARED: 'cleared',
    LIFE_LOST: 'life_lost',
    GAMEOVER: 'gameover',
    FINISHED: 'finished',
    ERROR: 'error',
});

/** Taps closer together than this are treated as accidental double-taps. */
export const MIN_TAP_GAP_MS = 90;
/** Ignore touches still landing as input unlocks (matches the server's min_first_tap_ms). */
export const UNLOCK_GUARD_MS = 40;
const LEAD_MS = { classic: 450, once: 450, timed: 200 };
const TIMED_FEEDBACK_MS = 380;
const WAIT_FOR_ROUNDS_MS = 10000;

export function initialState() {
    return {
        phase: PHASE.IDLE,
        session: null,
        spec: null,
        roundState: null,
        previewStartsAt: 0,
        inputs: [],
        timings: [],
        feedback: null,
        lastRound: null,
        rounds: [],
        score: 0,
        level: null,
        lives: null,
        livesTotal: null,
        remainingMs: null,
        countdown: null,
        summary: null,
        verifying: false,
        sync: 'synced',
        error: null,
        timedOut: false,
    };
}

export function createGameEngine({
    game,
    mode,
    duration = null,
    api,
    queue,
    logic,
    state = initialState(),
    clock = () => performance.now(),
    setTimer = (fn, ms) => setTimeout(fn, ms),
    clearTimer = (id) => clearTimeout(id),
    hooks = {},
}) {
    const timed = mode === 'timed';
    const specs = new Map();
    let uuid = null;
    let preparing = null;
    let unsubscribe = null;
    let pointer = { level: 1, attempt: 0, lives: null, n: 1 };
    let timers = new Set();
    let idleTimer = null;
    let tickTimer = null;
    let inputStartedAt = 0;
    let lastTapAt = null;
    let deadline = null;
    let timeUp = false;
    let started = false;
    let ended = false;
    let retryAction = null;
    let feedbackSeq = 0;
    let destroyed = false;

    const emit = (name, ...args) => hooks[name]?.(...args);

    function setPhase(phase) {
        state.phase = phase;
        emit('phase', phase);
    }

    function at(time, fn) {
        const id = setTimer(() => {
            timers.delete(id);
            if (!destroyed) fn();
        }, Math.max(0, time - clock()));
        timers.add(id);
        return id;
    }

    function clearRoundTimers() {
        timers.forEach((id) => clearTimer(id));
        timers = new Set();
        if (idleTimer !== null) clearTimer(idleTimer);
        idleTimer = null;
    }

    function fail(error, retry) {
        state.error = { message: error.message, code: error.code ?? null, retryable: Boolean(error.retryable) };
        retryAction = error.retryable ? retry : null;
        setPhase(PHASE.ERROR);
    }

    function absorb(json) {
        (json.specs ?? []).forEach((spec) => specs.set(spec.key, spec));
        if (json.session) state.session = json.session;
    }

    function onQueue(type, payload) {
        if (type === 'status') state.sync = payload;
        if (type !== 'response') return;

        absorb(payload);
        if (state.phase === PHASE.WAITING) beginRound();

        // The server ended the run on its own (e.g. a result failed verification).
        if (payload.summary && !ended && started) {
            ended = true;
            clearRoundTimers();
            stopClock();
            state.summary = payload.summary;
            state.verifying = false;
            setPhase(timed || payload.summary.status === 'completed' ? PHASE.FINISHED : PHASE.GAMEOVER);
            emit('ended', payload.summary);
        }
    }

    /** Create the server session ahead of time so Start is instant. Safe to call repeatedly. */
    function prepare() {
        if (!preparing) {
            preparing = api.start({ game, mode, duration })
                .then((json) => {
                    absorb(json);
                    uuid = json.session.uuid;
                    unsubscribe = queue.on(uuid, onQueue);
                    return json;
                })
                .catch((error) => {
                    preparing = null;
                    throw error;
                });
        }
        return preparing;
    }

    async function start() {
        setPhase(PHASE.STARTING);
        state.error = null;

        try {
            await prepare();
        } catch (error) {
            return fail(error, start);
        }
        if (destroyed) return;

        const s = state.session;
        pointer = { level: s.level, attempt: s.attempt, lives: s.lives, n: s.rounds_played + 1 };
        state.lives = s.lives;
        state.livesTotal = s.lives_total;
        state.score = s.score;
        started = true;

        if (timed) {
            await countdown(s.countdown_seconds);
            if (destroyed) return;
            deadline = clock() + duration * 1000;
            tick();
        }

        beginRound();
    }

    function countdown(seconds) {
        setPhase(PHASE.COUNTDOWN);

        return new Promise((resolve) => {
            const begin = clock();
            const step = (n) => {
                if (n === 0) {
                    state.countdown = null;
                    emit('go');
                    return resolve();
                }
                state.countdown = n;
                emit('countdown', n);
                at(begin + (seconds - n + 1) * 1000, () => step(n - 1));
            };
            step(seconds);
        });
    }

    function nextKey() {
        return timed ? `N${pointer.n}` : `L${pointer.level}A${pointer.attempt}`;
    }

    function beginRound() {
        clearRoundTimers();
        if (ended) return;

        const spec = specs.get(nextKey());

        if (!spec) {
            // Rounds ahead haven't arrived yet: nudge the sync and wait briefly.
            setPhase(PHASE.WAITING);
            queue.flush(uuid);
            at(clock() + WAIT_FOR_ROUNDS_MS, () => {
                if (state.phase === PHASE.WAITING) {
                    fail({ message: 'Still loading the next round. Check your connection.', retryable: true }, beginRound);
                }
            });
            return;
        }

        state.spec = spec;
        state.level = spec.level;
        state.roundState = logic.init(spec);
        state.inputs = [];
        state.timings = [];
        state.feedback = null;
        state.timedOut = false;
        state.previewStartsAt = clock() + (LEAD_MS[mode] ?? 400);
        setPhase(PHASE.PREVIEW);
        emit('round', spec);

        at(state.previewStartsAt + spec.preview_ms, unlockInput);
    }

    function unlockInput() {
        inputStartedAt = clock();
        lastTapAt = null;
        setPhase(PHASE.INPUT);
        emit('yourTurn');
        armIdle();
    }

    function armIdle() {
        if (timed || !state.spec?.input_timeout_ms) return;
        if (idleTimer !== null) clearTimer(idleTimer);
        idleTimer = setTimer(() => {
            idleTimer = null;
            if (state.phase === PHASE.INPUT) {
                state.timedOut = true;
                emit('timeout');
                completeRound('timeout');
            }
        }, state.spec.input_timeout_ms);
    }

    /** Returns true / false for a judged tap, null for a neutral one, undefined when ignored. */
    function input(value) {
        if (state.phase !== PHASE.INPUT) return undefined;

        const now = clock();
        if (now - inputStartedAt < UNLOCK_GUARD_MS) return undefined;
        if (lastTapAt !== null && now - lastTapAt < MIN_TAP_GAP_MS) return undefined;

        const result = logic.input(state.spec, state.roundState, value);
        if (!result.accepted) return undefined;

        lastTapAt = now;
        state.inputs.push(value);
        state.timings.push(Math.round(now - inputStartedAt));
        state.feedback = { value, correct: result.correct, id: ++feedbackSeq };
        emit('input', value, result.correct);

        if (result.done) {
            completeRound(result.done);
        } else {
            armIdle();
        }

        return result.correct;
    }

    function completeRound(outcome) {
        if (idleTimer !== null) clearTimer(idleTimer);
        idleTimer = null;

        const spec = state.spec;
        const status = outcome === 'cleared' ? 'cleared' : 'failed';
        const responseMs = state.timings.length ? state.timings[state.timings.length - 1] : 0;
        const points = roundPoints(state.session.scoring, mode, spec.level, spec.units, responseMs, status);
        const record = { key: spec.key, spec, inputs: [...state.inputs], timings: [...state.timings], status, outcome, points, responseMs };

        state.lastRound = record;
        state.rounds.push(record);
        state.score = Math.max(0, state.score + points.points);
        queue.push(uuid, { key: spec.key, inputs: record.inputs, timings: record.timings });
        emit('roundComplete', record);

        if (timed) {
            pointer.n++;
            setPhase(PHASE.FEEDBACK);
            at(clock() + TIMED_FEEDBACK_MS, beginRound);
            return;
        }

        if (status === 'cleared') {
            pointer.level++;
        } else {
            pointer.lives--;
            pointer.attempt++;
            state.lives = pointer.lives;
        }

        const maxLevel = state.session.max_level;

        if (pointer.lives <= 0 || (maxLevel !== null && pointer.level > maxLevel)) {
            endRun('ended');
        } else if (status === 'cleared') {
            setPhase(PHASE.CLEARED);
            emit('cleared', record);
        } else {
            setPhase(PHASE.LIFE_LOST);
            emit('lifeLost', record);
        }
    }

    /** Continue after a level-complete or life-lost screen. */
    function next() {
        if ([PHASE.CLEARED, PHASE.LIFE_LOST].includes(state.phase)) beginRound();
    }

    function tick() {
        stopClock();
        const loop = () => {
            state.remainingMs = Math.max(0, Math.round(deadline - clock()));
            if (state.remainingMs <= 0) {
                tickTimer = null;
                timeUp = true;
                emit('timeUp');
                return endRun('time_up');
            }
            tickTimer = setTimer(loop, 100);
        };
        loop();
    }

    function stopClock() {
        if (tickTimer !== null) clearTimer(tickTimer);
        tickTimer = null;
    }

    async function endRun(reason) {
        if (ended) return;
        ended = true;
        clearRoundTimers();
        stopClock();

        const completed = timed || (state.session.max_level !== null && pointer.level > state.session.max_level);
        state.verifying = true;
        setPhase(completed ? PHASE.FINISHED : PHASE.GAMEOVER);
        emit(completed ? 'finished' : 'gameover');

        // An unfinished Timed Rush round is not reported: it would score nothing either way.
        const json = await queue.finish(uuid, reason);
        if (destroyed) return;

        state.verifying = false;
        if (json) {
            absorb(json);
            state.summary = json.summary;
            emit('verified', json.summary);
        } else {
            state.sync = 'offline';
        }
    }

    function retry() {
        if (!retryAction) return;
        const action = retryAction;
        retryAction = null;
        state.error = null;
        return action();
    }

    /** Leave the arena. Verified points are kept; pending results still sync. */
    function exit() {
        clearRoundTimers();
        stopClock();
        if (uuid && !ended) {
            ended = true;
            queue.finish(uuid, 'exited');
        }
    }

    function reset() {
        exit();
        unsubscribe?.();
        Object.assign(state, initialState());
        specs.clear();
        uuid = null;
        preparing = null;
        unsubscribe = null;
        deadline = null;
        timeUp = false;
        started = false;
        ended = false;
        retryAction = null;
    }

    function destroy() {
        exit();
        unsubscribe?.();
        destroyed = true;
    }

    return {
        state,
        prepare,
        start,
        input,
        next,
        retry,
        exit,
        reset,
        destroy,
        get uuid() {
            return uuid;
        },
        get hasTimeUp() {
            return timeUp;
        },
    };
}
