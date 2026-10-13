<script setup>
import { computed, watch } from 'vue';
import { ArrowRight } from 'lucide-vue-next';
import { usePreviewClock } from '@/composables/usePreviewClock';
import { COLORS, SWAP_COLORS, SWAP_ICONS, SWAP_ICON_NAMES } from '@/game/palette';

/**
 * Spot the Swap: show the tokens, blink, then show them again with one
 * changed. During input the changed board is shown; tap the token that changed.
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

const emit = defineEmits(['input', 'cue']);

const rows = computed(() => Math.ceil(props.spec.tokens.length / props.spec.cols));
const gap = 10;
const cell = computed(() => Math.floor(Math.min(
    (props.box.w - gap * (props.spec.cols - 1)) / props.spec.cols,
    (props.box.h - gap * (rows.value - 1)) / rows.value,
    110,
)));

const elapsed = usePreviewClock(() => props.previewStartsAt, () => props.phase === 'preview');
const blank = computed(() => props.phase === 'preview' && elapsed.value >= props.spec.show_ms);
watch(blank, (on) => on && emit('cue', 'blink'));

const showAfter = computed(() => props.review || ['input', 'feedback'].includes(props.phase) || ['gameover', 'cleared', 'life_lost'].includes(props.phase));

const COLOR_NAMES = { ruby: 'red', amber: 'yellow', jade: 'green', sapphire: 'blue', amethyst: 'purple', slate: 'grey' };

function token(i) {
    const [icon, color] = showAfter.value && i === props.spec.changed ? props.spec.after : props.spec.tokens[i];
    return { icon: SWAP_ICONS[icon], name: `${COLOR_NAMES[SWAP_COLORS[color]]} ${SWAP_ICON_NAMES[icon]}`, color: COLORS[SWAP_COLORS[color]] };
}

function ring(i) {
    if (!props.review && props.phase !== 'feedback') return '';
    if (i === props.spec.changed) return 'ring-4 ring-emerald';
    if (props.roundState?.picked === i) return 'ring-4 ring-ruby';
    return '';
}

const before = computed(() => {
    const [icon, color] = props.spec.tokens[props.spec.changed];
    return { icon: SWAP_ICONS[icon], color: COLORS[SWAP_COLORS[color]] };
});
</script>

<template>
    <div class="flex flex-col items-center gap-3">
        <div
            class="grid transition-opacity duration-150"
            :class="{ 'opacity-0': blank }"
            :style="{ gap: `${gap}px`, gridTemplateColumns: `repeat(${spec.cols}, ${cell}px)` }"
            role="group"
            aria-label="Tokens"
        >
            <button
                v-for="(t, i) in spec.tokens"
                :key="`${spec.key}-${i}`"
                type="button"
                class="token grid aspect-square place-items-center rounded-full bg-white"
                :class="ring(i)"
                :style="{ width: `${cell}px` }"
                :disabled="phase !== 'input'"
                :aria-label="`Token ${i + 1}: ${token(i).name}`"
                :data-token="i"
                @pointerdown.prevent="phase === 'input' && emit('input', i)"
                @keydown.enter.prevent="emit('input', i)"
            >
                <span class="grid size-[78%] place-items-center rounded-full" :style="{ background: token(i).color.soft, color: token(i).color.base }">
                    <component :is="token(i).icon" class="size-[58%]" :stroke-width="2.4" fill="currentColor" fill-opacity="0.25" />
                </span>
            </button>
        </div>

        <p v-if="review" class="flex items-center gap-2 rounded-full bg-cream-low px-4 py-2 text-sm font-semibold text-ink-soft">
            Token #{{ spec.changed + 1 }} changed:
            <span class="grid size-8 place-items-center rounded-full" :style="{ background: before.color.soft, color: before.color.base }"><component :is="before.icon" :size="18" /></span>
            <ArrowRight :size="16" />
            <span class="grid size-8 place-items-center rounded-full" :style="{ background: token(spec.changed).color.soft, color: token(spec.changed).color.base }"><component :is="token(spec.changed).icon" :size="18" /></span>
        </p>
    </div>
</template>

<style scoped>
.token {
    box-shadow: 0 5px 0 var(--color-cream-highest), 0 6px 14px -8px rgb(13 27 42 / 0.3);
    transition: transform 120ms ease, box-shadow 120ms ease;
    touch-action: manipulation;
}
.token:not(:disabled):hover { transform: translateY(-2px); }
.token:not(:disabled):active { transform: translateY(4px); box-shadow: 0 1px 0 var(--color-cream-highest); }
</style>
