<script setup>
import { computed } from 'vue';
import { Check, X } from 'lucide-vue-next';

/** Pattern Peek: lit cells glow during the peek; tap them all back in any order. */
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

const cells = computed(() => Array.from({ length: props.spec.size ** 2 }, (_, i) => i));
const lit = computed(() => new Set(props.spec.lit));
const gap = computed(() => (props.spec.size >= 5 ? 6 : 10));
const side = computed(() => Math.max(200, Math.min(props.box.w, props.box.h, 560)));

function state(i) {
    const s = props.roundState;
    if (props.review) {
        if (s?.wrong?.[i]) return 'wrong';
        if (lit.value.has(i)) return s?.found?.[i] ? 'found' : 'missed';
        return 'off';
    }
    if (props.phase === 'preview') return lit.value.has(i) ? 'lit' : 'off';
    if (s?.wrong?.[i]) return 'wrong';
    if (s?.found?.[i]) return 'found';
    return 'off';
}
</script>

<template>
    <div
        class="grid rounded-[1.75rem] bg-navy p-2.5 shadow-lift sm:p-3"
        :style="{ width: `${side}px`, height: `${side}px`, gap: `${gap}px`, gridTemplateColumns: `repeat(${spec.size}, minmax(0, 1fr))` }"
        role="group"
        :aria-label="`Pattern grid, ${spec.size} by ${spec.size}`"
    >
        <button
            v-for="i in cells"
            :key="`${spec.key}-${i}`"
            type="button"
            class="cell grid place-items-center rounded-xl"
            :class="`is-${state(i)}`"
            :disabled="phase !== 'input'"
            :aria-label="`Row ${Math.floor(i / spec.size) + 1}, column ${(i % spec.size) + 1}${state(i) === 'found' ? ', found' : ''}`"
            :data-cell="i"
            @pointerdown.prevent="phase === 'input' && emit('input', i)"
            @keydown.enter.prevent="emit('input', i)"
            @keydown.space.prevent="emit('input', i)"
        >
            <Check v-if="state(i) === 'found'" class="size-1/2 text-navy-deep" :stroke-width="3" aria-hidden="true" />
            <X v-else-if="state(i) === 'wrong'" class="size-1/2 text-white" :stroke-width="3" aria-hidden="true" />
        </button>
    </div>
</template>

<style scoped>
.cell {
    background: rgb(255 255 255 / 0.09);
    box-shadow: inset 0 -3px 0 rgb(0 0 0 / 0.25);
    transition: background-color 160ms ease, transform 120ms ease, box-shadow 160ms ease;
    touch-action: manipulation;
}
.cell:not(:disabled):hover { background: rgb(255 255 255 / 0.16); }
.cell:not(:disabled):active { transform: scale(0.94); }
.is-lit {
    background: var(--color-amber);
    box-shadow: 0 0 18px 2px rgb(245 158 11 / 0.65), inset 0 -3px 0 #d97706;
    animation: var(--animate-pop);
}
.is-found { background: var(--color-amber-dim); box-shadow: inset 0 -3px 0 #d97706; }
.is-wrong { background: var(--color-ruby); animation: var(--animate-shake); }
.is-missed { background: transparent; box-shadow: inset 0 0 0 3px var(--color-emerald); }
</style>
