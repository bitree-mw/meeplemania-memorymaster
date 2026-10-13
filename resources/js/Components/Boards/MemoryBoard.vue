<script setup>
import { computed, ref, watch } from 'vue';
import { CircleCheck, CircleX, Lock } from 'lucide-vue-next';
import GameTile from '@/Components/Game/GameTile.vue';
import { usePreviewClock } from '@/composables/usePreviewClock';
import { TILES, colorVars } from '@/game/palette';
import { seconds } from '@/game/format';

const props = defineProps({
    spec: { type: Object, required: true },
    phase: { type: String, required: true },
    previewStartsAt: { type: Number, default: 0 },
    roundState: { type: Object, default: null },
    feedback: { type: Object, default: null },
    inputs: { type: Array, default: () => [] },
    timings: { type: Array, default: () => [] },
    review: { type: Boolean, default: false },
    box: { type: Object, default: () => ({ w: 400, h: 400 }) },
});

const emit = defineEmits(['input', 'cue']);

const tiles = computed(() => TILES.slice(0, props.spec.tiles));
const cols = computed(() => (props.spec.tiles <= 4 ? 2 : 3));
const rows = computed(() => Math.ceil(props.spec.tiles / cols.value));
const width = computed(() => Math.max(200, Math.min(props.box.w, (props.box.h * cols.value) / rows.value, 620)));

const elapsed = usePreviewClock(() => props.previewStartsAt, () => props.phase === 'preview');
const active = computed(() => {
    if (props.phase !== 'preview' || elapsed.value < 0) return null;
    const period = props.spec.flash_ms + props.spec.gap_ms;
    const step = Math.floor(elapsed.value / period);
    const within = elapsed.value - step * period < props.spec.flash_ms;
    return step < props.spec.sequence.length && within ? { tile: props.spec.sequence[step], step } : null;
});

watch(() => active.value?.step, (step) => step !== undefined && emit('cue', { tile: active.value.tile, step, ms: props.spec.flash_ms }));

// Brief green ring after a correct tap.
const flashCorrect = ref(null);
watch(() => props.feedback?.id, () => {
    if (props.feedback?.correct) {
        flashCorrect.value = props.feedback.value;
        setTimeout(() => (flashCorrect.value = null), 260);
    }
});

function status(i) {
    if (props.phase === 'preview') return active.value?.tile === i ? 'flash' : 'locked';
    if (props.phase === 'input') {
        if (props.feedback && props.feedback.correct === false && props.feedback.value === i) return 'wrong';
        return flashCorrect.value === i ? 'correct' : 'ready';
    }
    if (props.feedback && props.feedback.correct === false && props.feedback.value === i) return 'wrong';
    return 'idle';
}

const steps = computed(() => props.spec.sequence.map((expected, i) => ({
    i,
    expected,
    given: props.inputs[i],
    time: i === 0 ? props.timings[0] : props.timings[i] - props.timings[i - 1],
})));
</script>

<template>
    <ol v-if="review" class="grid grid-cols-3 gap-2 sm:grid-cols-5" aria-label="Sequence review">
        <li
            v-for="s in steps"
            :key="s.i"
            class="flex flex-col items-center gap-1 rounded-2xl p-2 text-center"
            :class="s.given === undefined ? 'bg-cream-mid opacity-70' : s.given === s.expected ? 'bg-cream-low ring-1 ring-cream-high' : 'bg-ruby-soft ring-2 ring-ruby/40'"
        >
            <span class="flex w-full items-center justify-between text-[11px] font-bold">
                <span class="text-ink-soft">#{{ s.i + 1 }}</span>
                <CircleCheck v-if="s.given === s.expected" :size="14" class="text-emerald-deep" aria-label="Correct" />
                <CircleX v-else-if="s.given !== undefined" :size="14" class="text-ruby-deep" aria-label="Wrong" />
                <Lock v-else :size="13" class="text-outline" aria-label="Not reached" />
            </span>
            <span class="flex items-center gap-1">
                <span class="grid size-9 place-items-center rounded-xl bg-[var(--tile-soft)] text-[var(--tile)]" :style="colorVars(TILES[s.expected].color)" :title="`Expected ${TILES[s.expected].name}`">
                    <component :is="TILES[s.expected].icon" :size="18" :stroke-width="2.4" />
                </span>
                <template v-if="s.given !== undefined && s.given !== s.expected">
                    <span class="text-[11px] font-extrabold text-ruby-deep">vs</span>
                    <span class="grid size-9 place-items-center rounded-xl bg-[var(--tile-soft)] text-[var(--tile)] ring-2 ring-ruby" :style="colorVars(TILES[s.given].color)" :title="`You tapped ${TILES[s.given].name}`">
                        <component :is="TILES[s.given].icon" :size="18" :stroke-width="2.4" />
                    </span>
                </template>
            </span>
            <span class="text-[11px] font-semibold text-outline">
                {{ s.given === undefined ? 'Not reached' : s.given === s.expected ? seconds(s.time, 1) : 'Slipped here' }}
            </span>
        </li>
    </ol>

    <div v-else class="grid gap-3 sm:gap-5" :style="{ width: `${width}px`, gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }" role="group" aria-label="Memory tiles">
        <GameTile
            v-for="(tile, i) in tiles"
            :key="tile.key"
            :tile="tile"
            :index="i"
            :status="status(i)"
            :step="active?.tile === i ? active.step : null"
            :feedback-id="feedback?.id ?? 0"
            @press="emit('input', $event)"
        />
    </div>
</template>
