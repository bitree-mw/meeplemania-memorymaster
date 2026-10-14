<script setup>
/**
 * Sign-up prompt for guests. Gentle at first; once they pass the nudge
 * threshold it counts down the free games left on this device (the server
 * requires an account once they run out).
 */
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    // "card" for the results screens, "line" under the Start button.
    variant: { type: String, default: 'card' },
});

const page = usePage();
const plays = computed(() => page.props.guestPlays);
</script>

<template>
    <p
        :class="
            variant === 'card'
                ? ['rounded-2xl px-4 py-3 text-sm font-medium', plays?.nudge ? 'bg-amber-mist text-amber-ink ring-1 ring-amber-soft' : 'bg-sky-soft text-navy-soft']
                : ['text-center text-xs font-medium', plays?.nudge ? 'text-amber-ink' : 'text-outline']
        "
        role="status"
    >
        <template v-if="plays?.nudge && plays.remaining === 0">
            That was your last free guest game. <Link href="/register" class="font-bold underline">Create a free account</Link> to keep playing.
        </template>
        <template v-else-if="plays?.nudge">
            You’ve played {{ plays.played }} of {{ plays.limit }} free guest games ({{ plays.remaining }} left).
            <Link href="/register" class="font-bold underline">Create a free account</Link> to compete on the leaderboards and save your progress.
        </template>
        <template v-else>
            Playing as a guest. <Link href="/register" class="font-bold underline">Create a free account</Link> to save scores, earn badges and enter the rankings.
        </template>
    </p>
</template>
