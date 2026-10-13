<script setup>
import { computed } from 'vue';
import Die from './Die.vue';

/** Dice Digits: the roll is shown during the preview; enter it back on the keypad. */
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

const count = computed(() => props.spec.dice.length);
const perRow = computed(() => (count.value <= 7 ? count.value : Math.ceil(count.value / 2)));
const rowCount = computed(() => Math.ceil(count.value / perRow.value));
// Dice rows take up to ~45% of the height; the keypad the rest.
const dieSize = computed(() => Math.floor(Math.max(30, Math.min(
    (props.box.w - (perRow.value - 1) * 8) / perRow.value,
    (props.box.h * 0.42) / rowCount.value - 8,
    72,
))));
const keySize = computed(() => Math.floor(Math.max(48, Math.min((props.box.w - 5 * 10) / 6, props.box.h * 0.36, 84))));

function slot(i) {
    if (props.review) {
        const given = props.inputs[i];
        return { value: props.spec.dice[i], tone: given === undefined ? 'white' : given === props.spec.dice[i] ? 'right' : 'wrong', given };
    }
    if (props.phase === 'preview') return { value: props.spec.dice[i], tone: 'ruby' };
    const given = props.inputs[i];
    if (given === undefined) return { value: null, tone: 'empty' };
    const wrong = props.feedback?.correct === false && i === props.inputs.length - 1;
    return { value: given, tone: wrong ? 'wrong' : 'white' };
}
</script>

<template>
    <div class="flex w-full flex-col items-center justify-between gap-4" :style="{ maxWidth: `${box.w}px` }">
        <div class="flex flex-wrap justify-center gap-2" :style="{ maxWidth: `${perRow * (dieSize + 8)}px` }" :aria-label="phase === 'preview' ? 'Memorize this roll' : 'Your entry'" role="group">
            <span v-for="i in count" :key="`${spec.key}-${i}`" class="relative flex flex-col items-center" :class="{ 'motion-safe:animate-pop': phase === 'preview' }" :style="{ animationDelay: `${(i - 1) * 40}ms` }">
                <Die :value="slot(i - 1).value" :tone="slot(i - 1).tone" :size="dieSize" />
                <span v-if="review && slot(i - 1).tone === 'wrong'" class="mt-1 rounded-full bg-ruby-soft px-1.5 text-[11px] font-bold text-ruby-deep">you: {{ slot(i - 1).given }}</span>
            </span>
        </div>

        <div v-if="!review" class="grid grid-cols-6 gap-2.5" role="group" aria-label="Dice keypad">
            <button
                v-for="v in 6"
                :key="v"
                type="button"
                class="key grid place-items-center rounded-2xl bg-white"
                :style="{ width: `${keySize}px`, height: `${keySize}px` }"
                :disabled="phase !== 'input'"
                :aria-label="`Enter ${v}`"
                @pointerdown.prevent="phase === 'input' && emit('input', v)"
                @keydown.enter.prevent="emit('input', v)"
            >
                <Die :value="v" :size="Math.round(keySize * 0.78)" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.key {
    box-shadow: 0 5px 0 var(--color-cream-highest), 0 6px 14px -8px rgb(13 27 42 / 0.3);
    transition: transform 100ms ease, box-shadow 100ms ease;
    touch-action: manipulation;
}
.key:not(:disabled):active { transform: translateY(4px); box-shadow: 0 1px 0 var(--color-cream-highest); }
.key:disabled { opacity: 0.55; }
</style>
