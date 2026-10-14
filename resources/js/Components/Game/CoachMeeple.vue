<script setup>
import MeepleMascot from '@/Components/Mascot/MeepleMascot.vue';

defineProps({
    message: { type: String, required: true },
    // neutral | live | success | error | celebrate
    tone: { type: String, default: 'neutral' },
    status: { type: String, default: null },
    // Mascot mood: idle | watch | cheer | sad | think | wave
    mood: { type: String, default: 'idle' },
    compact: { type: Boolean, default: false },
});

const badges = {
    live: 'bg-ruby-soft text-ruby-deep',
    success: 'bg-emerald-soft text-emerald-deep',
    celebrate: 'bg-amber-soft text-amber-ink',
    error: 'bg-ruby-soft text-ruby-deep',
    neutral: 'bg-cream-highest text-ink-soft',
};
</script>

<template>
    <div class="relative flex items-center gap-3 overflow-hidden rounded-[2rem] bg-cream-low ring-1 ring-cream-high sm:gap-4" :class="compact ? 'p-2 pr-4' : 'p-2.5 pr-5 sm:p-3'">
        <div class="pointer-events-none absolute -bottom-10 -right-6 size-40 rounded-full bg-amber-dim/25 blur-2xl" aria-hidden="true" />

        <div class="relative grid shrink-0 place-items-end overflow-hidden rounded-full bg-amber-bright pt-2 shadow-md" :class="compact ? 'size-12' : 'size-14 sm:size-16'">
            <MeepleMascot :mood="mood" :size="compact ? 34 : 42" :shadow="false" class="translate-y-[6%]" />
        </div>

        <div class="relative min-w-0 flex-1">
            <div v-if="!compact" class="mb-0.5 flex items-center gap-2">
                <span class="font-display text-[15px] font-bold text-navy-deep sm:text-lg">Coach Meeple</span>
                <span v-if="status" class="rounded-full px-2 py-0.5 font-display text-[10px] font-bold tracking-wider" :class="badges[tone]">{{ status }}</span>
            </div>
            <Transition mode="out-in" enter-from-class="opacity-0 translate-y-1" leave-to-class="opacity-0 -translate-y-1" enter-active-class="transition duration-200" leave-active-class="transition duration-150">
                <!-- In play (compact) the bubble is always two lines tall, so a longer line never resizes the board below. -->
                <p :key="message" class="text-[14px] font-medium leading-snug text-ink-soft sm:text-base" :class="compact ? 'line-clamp-2 min-h-[2.75em]' : ''" aria-live="polite">“{{ message }}”</p>
            </Transition>
        </div>

        <slot name="aside" />
    </div>
</template>
