/**
 * Instant, local judging of each input, mirroring the evaluate() method of
 * each PHP game definition. Inputs the server would treat as no-ops (tapping a
 * found cell again, flipping a matched card) are rejected here and never sent.
 *
 * input() returns:
 *   accepted  false = ignore this tap entirely
 *   correct   true / false / null (neutral, e.g. first card of a pair)
 *   done      null | 'cleared' | 'failed'
 */

function ordered(field) {
    return {
        init: () => ({ index: 0 }),
        input(spec, state, value) {
            if (value !== spec[field][state.index]) return { accepted: true, correct: false, done: 'failed' };
            state.index++;
            return { accepted: true, correct: true, done: state.index === spec[field].length ? 'cleared' : null };
        },
    };
}

const pairs = {
    init: () => ({ matched: {}, first: null, mismatches: 0, found: 0, lastPair: null }),
    input(spec, state, card) {
        if (state.matched[card] || card === state.first) return { accepted: false };

        if (state.first === null) {
            state.first = card;
            state.lastPair = null;
            return { accepted: true, correct: null, done: null };
        }

        const a = state.first;
        state.first = null;
        state.lastPair = [a, card];

        if (spec.cards[a] === spec.cards[card]) {
            state.matched[a] = state.matched[card] = true;
            state.found++;
            return { accepted: true, correct: true, done: state.found * 2 === spec.cards.length ? 'cleared' : null };
        }

        state.mismatches++;
        return { accepted: true, correct: false, done: state.mismatches > spec.mismatches_allowed ? 'failed' : null };
    },
};

const pattern = {
    init: () => ({ found: {}, wrong: {}, mistakes: 0, count: 0 }),
    input(spec, state, cell) {
        if (state.found[cell] || state.wrong[cell]) return { accepted: false };

        if (spec.lit.includes(cell)) {
            state.found[cell] = true;
            state.count++;
            return { accepted: true, correct: true, done: state.count === spec.lit.length ? 'cleared' : null };
        }

        state.wrong[cell] = true;
        state.mistakes++;
        return { accepted: true, correct: false, done: state.mistakes > spec.mistakes_allowed ? 'failed' : null };
    },
};

const swap = {
    init: () => ({ picked: null }),
    input(spec, state, token) {
        state.picked = token;
        const correct = token === spec.changed;
        return { accepted: true, correct, done: correct ? 'cleared' : 'failed' };
    },
};

export const LOGIC = {
    'memory-master': ordered('sequence'),
    'dice-digits': ordered('dice'),
    'meeple-pairs': pairs,
    'pattern-peek': pattern,
    'spot-the-swap': swap,
};
