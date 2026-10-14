<script setup>
/**
 * Phones only: the "carry on" action pinned bottom-right, within thumb reach,
 * so the player never has to scroll past the round summary to continue.
 * Larger screens use the button inside the card instead. Teleported to <body>:
 * the result cards animate with a transform, which would pin a fixed child to the card.
 */
import { Play, RotateCcw } from 'lucide-vue-next';

defineProps({
    level: { type: Number, required: true },
    // "next" after a cleared level, "retry" after a lost life.
    kind: { type: String, default: 'next' },
});

defineEmits(['click']);
</script>

<template>
    <Teleport to="body">
        <button
            type="button"
            class="fixed bottom-[max(1rem,env(safe-area-inset-bottom))] right-4 z-40 flex items-center gap-2 rounded-full bg-amber py-3 pl-4 pr-5 font-display text-[15px] font-extrabold text-navy-deep shadow-[0_4px_0_#d97706,0_10px_24px_rgb(13_27_42/0.25)] transition-transform active:translate-y-[3px] motion-safe:animate-rise sm:hidden"
            :aria-label="kind === 'next' ? `Next level: level ${level}` : `Try a new puzzle at level ${level}`"
            @click="$emit('click')"
        >
            <component :is="kind === 'next' ? Play : RotateCcw" :size="18" :fill="kind === 'next' ? 'currentColor' : 'none'" aria-hidden="true" />
            {{ kind === 'next' ? 'Next' : 'Retry' }} · Level {{ level }}
        </button>
    </Teleport>
</template>
