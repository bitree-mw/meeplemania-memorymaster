<script setup>
import { computed, ref, watch } from 'vue';
import { COLORS, PAIR_SYMBOLS } from '@/game/palette';

/**
 * Meeple Pairs: the board is shown face-up during the preview, then flipped.
 * A mismatched pair stays visible briefly so the player sees what they flipped.
 */
const props = defineProps({
    spec: { type: Object, required: true },
    phase: { type: String, required: true },
    previewStartsAt: { type: Number, default: 0 },
    roundState: { type: Object, default: null },
    feedback: { type: Object, default: null },
    inputs: { type: Array, default: () => [] },
    review: { type: Boolean, default: false },
    box: { type: Object, default: () => ({ w: 400, h: 400 }) },
});

const emit = defineEmits(['input']);

const gap = computed(() => (props.box.w < 420 ? 8 : 12));
const cell = computed(() => Math.floor(Math.min(
    (props.box.w - gap.value * (props.spec.cols - 1)) / props.spec.cols,
    (props.box.h - gap.value * (props.spec.rows - 1)) / props.spec.rows / 1.15,
    120,
)));

const peekPair = ref(null);
let peekTimer;
watch(() => props.feedback?.id, () => {
    const pair = props.roundState?.lastPair;
    if (props.feedback && props.feedback.correct === false && pair) {
        peekPair.value = pair;
        clearTimeout(peekTimer);
        peekTimer = setTimeout(() => (peekPair.value = null), 750);
    } else if (props.feedback?.correct !== false) {
        peekPair.value = null;
    }
});

// Cards flipped wrongly at least once, for the review.
const missed = computed(() => {
    const set = new Set();
    for (let i = 0; i + 1 < props.inputs.length; i += 2) {
        const [a, b] = [props.inputs[i], props.inputs[i + 1]];
        if (props.spec.cards[a] !== props.spec.cards[b]) set.add(a).add(b);
    }
    return set;
});

function faceUp(i) {
    if (props.review || props.phase === 'preview') return true;
    const s = props.roundState;
    if (!s) return false;
    return Boolean(s.matched[i]) || s.first === i || Boolean(peekPair.value?.includes(i));
}

function cardClass(i) {
    const s = props.roundState;
    if (props.review) return s?.matched?.[i] ? 'ring-4 ring-emerald' : missed.value.has(i) ? 'ring-4 ring-ruby/70' : 'opacity-70';
    if (s?.matched?.[i]) return 'is-matched';
    if (peekPair.value?.includes(i)) return 'is-miss';
    return '';
}

const symbol = (i) => PAIR_SYMBOLS[props.spec.cards[i]];
</script>

<template>
    <div
        class="grid"
        :style="{ gap: `${gap}px`, gridTemplateColumns: `repeat(${spec.cols}, ${cell}px)` }"
        role="group"
        :aria-label="`Card board, ${spec.cards.length} cards`"
    >
        <button
            v-for="(card, i) in spec.cards"
            :key="`${spec.key}-${i}`"
            type="button"
            class="card3d relative"
            :class="[cardClass(i), { 'is-up': faceUp(i) }]"
            :style="{ width: `${cell}px`, height: `${Math.round(cell * 1.15)}px` }"
            :aria-label="faceUp(i) ? `Card ${i + 1}: ${symbol(i).name}` : `Card ${i + 1}, face down`"
            :disabled="phase !== 'input' || roundState?.matched?.[i]"
            :data-card="i"
            @pointerdown.prevent="phase === 'input' && emit('input', i)"
            @keydown.enter.prevent="emit('input', i)"
            @keydown.space.prevent="emit('input', i)"
        >
            <span class="face back" aria-hidden="true">
                <img src="/images/meeple-figure.png" alt="" class="h-[46%] w-auto opacity-25 brightness-0 invert" />
            </span>
            <span class="face front" :style="{ background: COLORS[symbol(i).color].soft, color: COLORS[symbol(i).color].base }" aria-hidden="true">
                <component :is="symbol(i).icon" class="size-[55%]" :stroke-width="2.4" />
            </span>
        </button>
    </div>
</template>

<style scoped>
.card3d {
    perspective: 800px;
    border-radius: 1.1rem;
    touch-action: manipulation;
    transition: transform 150ms ease;
}
.card3d:not(:disabled):hover { transform: translateY(-2px); }
.face {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    border-radius: 1.1rem;
    backface-visibility: hidden;
    transition: transform 320ms cubic-bezier(0.3, 0.7, 0.3, 1);
    box-shadow: 0 5px 0 rgb(13 27 42 / 0.12), 0 6px 14px -6px rgb(13 27 42 / 0.25);
}
.back {
    background:
        radial-gradient(circle at 25% 25%, rgb(255 255 255 / 0.08) 0 12%, transparent 13%) 0 0 / 22px 22px,
        linear-gradient(145deg, #1b2c42, var(--color-navy-deep));
    border: 3px solid rgb(255 255 255 / 0.85);
}
.front {
    transform: rotateY(180deg);
    border: 3px solid #fff;
}
.is-up .back { transform: rotateY(-180deg); }
.is-up .front { transform: rotateY(0deg); }
.is-matched .front { box-shadow: 0 0 0 3px var(--color-emerald), 0 4px 0 rgb(13 27 42 / 0.12); }
.is-miss { animation: var(--animate-shake); }
.is-miss .front { box-shadow: 0 0 0 3px var(--color-ruby); }
</style>
