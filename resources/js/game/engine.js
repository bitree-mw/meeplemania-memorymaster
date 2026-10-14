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
 * pause() freezes a run in progress (every round timer and the Timed Rush clock)
 * and resume() shifts all of its timestamps by the time spent paused, so the
 * round carries on exactly where it stopped. Taps are ignored while paused.
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
        paused: false,
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
    let idleDue = null;
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
    let pausedAt = null;
    let countdownBegin = 0;

    const emit = (name, ...args) => hooks[name]?.(...args);

    function setPhase(phase) {
        state.phase = phase;
        emit('phase', phase);
    }

    /** Run fn at clock time `time`. Entries keep their due time so a pause can reschedule them. */
    function at(time, fn) {
        const entry = { due: time, id: null };
        entry.run = () => {
            timers.delete(entry);
            if (!destroyed) fn();
        };
        schedule(entry);
        timers.add(entry);
        return entry;
    }

    function schedule(entry) {
        entry.id = setTimer(entry.run, Math.max(0, entry.due - clock()));
    }

    function clearRoundTimers() {
        timers.forEach((entry) => clearTimer(entry.id));
        timers = new Set();
        if (idleTimer !== null) clearTimer(idleTimer);
        idleTimer = null;
        idleDue = null;
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
        if (state.phase === PHASE.WAITING && pausedAt === null) beginRound();

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
            countdownBegin = clock(); // shifted by resume()
            const step = (n) => {
                if (n === 0) {
                    state.countdown = null;
                    emit('go');
                    return resolve();
                }
                state.countdown = n;
                emit('countdown', n);
                at(countdownBegin + (seconds - n + 1) * 1000, () => step(n - 1));
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
        armIdleAt(clock() + state.spec.input_timeout_ms);
    }

    function armIdleAt(due) {
        if (idleTimer !== null) clearTimer(idleTimer);
        idleDue = due;
        idleTimer = setTimer(() => {
            idleTimer = null;
            idleDue = null;
            if (state.phase === PHASE.INPUT) {
                state.timedOut = true;
                emit('timeout');
                completeRound('timeout');
            }
        }, Math.max(0, due - clock()));
    }

    /** Returns true / false for a judged tap, null for a neutral one, undefined when ignored. */
    function input(value) {
        if (state.phase !== PHASE.INPUT || pausedAt !== null) return undefined;

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
        idleDue = null;

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
        if (pausedAt === null && [PHASE.CLEARED, PHASE.LIFE_LOST].includes(state.phase)) beginRound();
    }

    /** Freeze a run in progress. Returns false when there is nothing to pause. */
    function pause() {
        if (!started || ended || pausedAt !== null) return false;

        pausedAt = clock();
        timers.forEach((entry) => clearTimer(entry.id));
        if (idleTimer !== null) clearTimer(idleTimer);
        idleTimer = null;
        stopClock();
        state.paused = true;
        // Only Timed Rush has a server deadline, which resume pushes back. Best effort:
        // if it fails, results synced past the deadline are flagged for review, not lost.
        if (timed) api.pause?.(uuid).catch(() => {});
        emit('pause');
        return true;
    }

    /** Carry on from where pause() stopped, as if no time had passed. */
    function resume() {
        if (pausedAt === null) return;

        const delta = clock() - pausedAt;
        pausedAt = null;
        state.paused = false;
        if (ended) return;
        if (timed) api.resume?.(uuid).catch(() => {});

        countdownBegin += delta;
        inputStartedAt += delta;
        if (lastTapAt !== null) lastTapAt += delta;
        state.previewStartsAt += delta;
        timers.forEach((entry) => {
            entry.due += delta;
            schedule(entry);
        });
        if (idleDue !== null) armIdleAt(idleDue + delta);
        if (deadline !== null) {
            deadline += delta;
            tick();
        }
        emit('resume', delta);

        // Rounds that arrived while paused.
        if (state.phase === PHASE.WAITING && specs.has(nextKey())) beginRound();
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
        pausedAt = null;
        state.paused = false;
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
        pause,
        resume,
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
        /** True while a run is under way: started and not yet ended. */
        get inProgress() {
            return started && !ended;
        },
    };
}
