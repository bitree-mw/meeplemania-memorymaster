<script setup>
/** A die face (1–6) drawn with pips, or a blank slot. */
defineProps({
    value: { type: Number, default: null },
    size: { type: Number, default: 48 },
    tone: { type: String, default: 'white' }, // white | ruby | empty | wrong | right
});

const PIPS = {
    1: [[50, 50]],
    2: [[28, 28], [72, 72]],
    3: [[26, 26], [50, 50], [74, 74]],
    4: [[28, 28], [72, 28], [28, 72], [72, 72]],
    5: [[26, 26], [74, 26], [50, 50], [26, 74], [74, 74]],
    6: [[28, 24], [72, 24], [28, 50], [72, 50], [28, 76], [72, 76]],
};
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 100 100" :aria-label="value ? `Die showing ${value}` : 'Empty'" role="img">
        <rect
            x="4" y="4" width="92" height="92" rx="22"
            :fill="tone === 'ruby' ? 'var(--color-ruby)' : tone === 'empty' ? 'transparent' : '#fff'"
            :stroke="tone === 'wrong' ? 'var(--color-ruby)' : tone === 'right' ? 'var(--color-emerald)' : tone === 'empty' ? 'var(--color-outline-soft)' : 'var(--color-cream-highest)'"
            :stroke-width="tone === 'empty' ? 4 : 6"
            :stroke-dasharray="tone === 'empty' ? '10 8' : undefined"
        />
        <circle
            v-for="([cx, cy], i) in value ? PIPS[value] : []"
            :key="i"
            :cx="cx" :cy="cy" r="9.5"
            :fill="tone === 'ruby' ? '#fff' : 'var(--color-navy-deep)'"
        />
    </svg>
</template>
