import { DIE_TONES, TILES } from './palette';

/** What Coach Meeple says while a round is being shown, and while the player answers. */
const LINES = {
    'memory-master': {
        preview: (s, step) => `Keep your eyes peeled! Remember tile #${step + 1}!`,
        input: (s, st) => (st.index ? `Nice, ${st.index} of ${s.sequence.length}. Keep going!` : 'Your turn! Repeat the sequence!'),
    },
    'meeple-pairs': {
        preview: () => 'Memorize where every picture is!',
        input: (s, st) => {
            const spare = s.mismatches_allowed - st.mismatches;
            const pairs = `Find the pairs: ${s.cards.length / 2 - st.found} to go.`;
            if (!st.mismatches) return pairs;
            return spare > 0 ? `${pairs} ${spare} more slip${spare === 1 ? '' : 's'} allowed.` : `${pairs} No more slips!`;
        },
    },
    'pattern-peek': {
        preview: () => 'Peek! Remember every lit cell.',
        input: (s, st) => `Tap the lit cells: ${s.lit.length - st.count} left.`,
    },
    'dice-digits': {
        preview: (s) => `Memorize the roll: ${s.dice.length} dice, left to right!`,
        input: (s, st) => (st.index ? `${st.index} of ${s.dice.length} entered. Keep going!` : 'Enter the dice in order!'),
    },
    'spot-the-swap': {
        preview: () => 'Study the tokens… something is about to change!',
        input: () => 'One token changed. Tap it!',
    },
};

export function coachLine(game, phase, spec, roundState, step = 0) {
    const lines = LINES[game];
    if (!lines || !spec) return null;
    return phase === 'preview' ? lines.preview(spec, step) : lines.input(spec, roundState ?? {});
}

/** One-sentence explanation of how a round was lost. */
export function explainLoss(game, round) {
    if (!round) return 'The run ended.';
    const { spec, inputs, outcome } = round;

    if (outcome === 'timeout') return 'You paused too long, so the round timed out. Keep the rhythm going!';

    switch (game) {
        case 'memory-master': {
            const i = inputs.length - 1;
            return `Tile #${i + 1} was the ${TILES[spec.sequence[i]].name}, but you tapped the ${TILES[inputs[i]].name}.`;
        }
        case 'dice-digits': {
            const i = inputs.length - 1;
            return `Die #${i + 1} was a ${spec.dice[i]}, but you entered ${inputs[i]}.`;
        }
        case 'meeple-pairs':
            return `That was mismatch number ${spec.mismatches_allowed + 1} — the board allows ${spec.mismatches_allowed}.`;
        case 'pattern-peek': {
            const found = inputs.filter((c) => spec.lit.includes(c)).length;
            return `That cell wasn't lit. You'd found ${found} of ${spec.lit.length} cells.`;
        }
        case 'spot-the-swap':
            return `Token #${spec.changed + 1} changed, but you picked #${inputs[0] + 1}.`;
        default:
            return 'The round slipped away.';
    }
}

/** Tone to play for a correct input, by game. */
export function inputTone(game, value, roundState) {
    switch (game) {
        case 'memory-master':
            return TILES[value].frequency;
        case 'dice-digits':
            return DIE_TONES[value - 1];
        case 'pattern-peek':
            return 523.25 * Math.pow(2, Math.min(roundState?.count ?? 0, 12) / 12);
        default:
            return 880;
    }
}
