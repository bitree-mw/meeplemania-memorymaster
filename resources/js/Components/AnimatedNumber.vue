<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { fmt } from '@/game/format';

const props = defineProps({
    value: { type: Number, default: 0 },
    duration: { type: Number, default: 700 },
    from: { type: Number, default: null },
});

const reduced = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const shown = ref(props.from ?? props.value);
let frame = null;

function animate(from, to) {
    cancelAnimationFrame(frame);
    if (reduced || from === to) {
        shown.value = to;
        return;
    }
    const start = performance.now();
    const step = (now) => {
        const t = Math.min(1, (now - start) / props.duration);
        const eased = 1 - Math.pow(1 - t, 3);
        shown.value = Math.round(from + (to - from) * eased);
        if (t < 1) frame = requestAnimationFrame(step);
    };
    frame = requestAnimationFrame(step);
}

watch(() => props.value, (to, from) => animate(from ?? 0, to));
if (props.from !== null) animate(props.from, props.value);
onBeforeUnmount(() => cancelAnimationFrame(frame));
</script>

<template>
    <span class="tabular-nums">{{ fmt(shown) }}</span>
</template>
