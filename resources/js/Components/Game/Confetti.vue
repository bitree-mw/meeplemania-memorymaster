<script setup>
import { useReducedMotion } from '@/composables/useReducedMotion';

const reduced = useReducedMotion();
const colors = ['#f59e0b', '#ef4444', '#10b981', '#2f6fe4', '#8b5cf6', '#fea619'];
const pieces = Array.from({ length: 36 }, (_, i) => ({
    left: Math.random() * 100,
    delay: Math.random() * 0.35,
    duration: 1.4 + Math.random() * 1.1,
    rotate: Math.random() * 360,
    color: colors[i % colors.length],
    round: i % 3 === 0,
}));
</script>

<template>
    <div v-if="!reduced" class="pointer-events-none fixed inset-0 z-40 overflow-hidden" aria-hidden="true">
        <span
            v-for="(p, i) in pieces"
            :key="i"
            class="confetti absolute top-[-4%] block h-3 w-2"
            :class="p.round ? 'rounded-full !h-2.5 !w-2.5' : 'rounded-sm'"
            :style="{ left: `${p.left}%`, backgroundColor: p.color, animationDelay: `${p.delay}s`, animationDuration: `${p.duration}s`, '--r': `${p.rotate}deg` }"
        />
    </div>
</template>

<style scoped>
.confetti {
    animation-name: fall;
    animation-timing-function: cubic-bezier(0.25, 0.6, 0.4, 1);
    animation-fill-mode: forwards;
}
@keyframes fall {
    0% { transform: translateY(0) rotate(var(--r)); opacity: 1; }
    100% { transform: translateY(105vh) rotate(calc(var(--r) + 540deg)); opacity: 0.8; }
}
</style>
