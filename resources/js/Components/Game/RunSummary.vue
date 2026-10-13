<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { LayoutGrid, RotateCcw, Trophy } from 'lucide-vue-next';
import AnimatedNumber from '@/Components/AnimatedNumber.vue';
import MeepleMascot from '@/Components/Mascot/MeepleMascot.vue';
import OutcomeHighlights from '@/Components/Game/OutcomeHighlights.vue';
import SyncBadge from '@/Components/Game/SyncBadge.vue';
import { seconds } from '@/game/format';

/** End of a Timed Rush, or a completed Try Once challenge. */
const props = defineProps({
    game: { type: Object, required: true },
    mode: { type: String, required: true },
    duration: { type: Number, default: null },
    rounds: { type: Array, default: () => [] },
    score: { type: Number, default: 0 },
    summary: { type: Object, default: null },
    verifying: { type: Boolean, default: false },
    sync: { type: String, default: 'synced' },
    isGuest: { type: Boolean, default: false },
});

defineEmits(['retry']);

const timed = computed(() => props.mode === 'timed');
const cleared = computed(() => props.summary?.rounds_cleared ?? props.rounds.filter((r) => r.status === 'cleared').length);
const mistakes = computed(() => props.summary?.mistakes ?? props.rounds.filter((r) => r.status !== 'cleared').length);
const stats = computed(() => {
    const s = props.summary;
    return timed.value
        ? [
              { label: 'Rounds cleared', value: cleared.value },
              { label: 'Misses', value: mistakes.value, tone: mistakes.value ? 'text-ruby-deep' : '' },
              { label: 'Accuracy', value: s ? `${s.accuracy}%` : '…' },
              { label: 'Best streak', value: s ? s.best_success_streak : '…' },
          ]
        : [
              { label: 'Levels cleared', value: cleared.value },
              { label: 'Final level', value: s?.highest_level ?? '…' },
              { label: 'Accuracy', value: s ? `${s.accuracy}%` : '…' },
              { label: `Avg per ${props.game.unit}`, value: s ? seconds(s.avg_unit_ms) : '…' },
          ];
});
</script>

<template>
    <div class="mx-auto flex w-full max-w-lg flex-col items-center motion-safe:animate-rise">
        <div class="relative mb-1 rounded-2xl bg-white px-5 py-3 text-center text-[15px] font-medium text-ink-soft shadow-card">
            <template v-if="timed">“Time! {{ cleared }} round{{ cleared === 1 ? '' : 's' }} in {{ duration }} seconds — great hustle!”</template>
            <template v-else>“You conquered today's Try Once! Legendary focus.”</template>
            <span class="absolute -bottom-2 left-1/2 size-4 -translate-x-1/2 rotate-45 bg-white" aria-hidden="true" />
        </div>
        <MeepleMascot mood="cheer" :size="74" class="mt-3" />

        <section class="card mt-1 w-full p-6 text-center sm:p-8">
            <div class="flex flex-wrap items-center justify-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-full bg-amber-soft px-3 py-1 font-display text-sm font-bold text-amber-ink">
                    {{ game.name }} · {{ timed ? `Timed Rush ${duration}s` : 'Try Once complete' }}
                </span>
                <SyncBadge :verifying="verifying" :sync="sync" :verified="!!summary" />
            </div>
            <p class="eyebrow mt-5">{{ summary ? 'Final points' : 'Points (verifying)' }}</p>
            <p class="font-display text-6xl font-extrabold tracking-tight text-amber"><AnimatedNumber :value="summary?.final_score ?? score" :from="0" :duration="1100" /></p>
            <p v-if="summary?.penalty_score" class="mt-1 text-sm font-semibold text-ruby-deep">−{{ summary.penalty_score }} in miss penalties</p>

            <dl class="mt-6 grid grid-cols-2 gap-3">
                <div v-for="s in stats" :key="s.label" class="rounded-3xl bg-cream-low px-3 py-4 ring-1 ring-cream-high">
                    <dt class="eyebrow">{{ s.label }}</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold text-navy-deep" :class="s.tone">{{ s.value }}</dd>
                </div>
            </dl>

            <div v-if="summary" class="mt-5 text-left">
                <OutcomeHighlights :outcome="summary.outcome" :is-ranked="summary.is_ranked && !summary.is_flagged" :is-guest="isGuest" />
            </div>

            <button type="button" class="btn btn-primary btn-lg mt-6 w-full" autofocus @click="$emit('retry')">
                <RotateCcw :size="20" aria-hidden="true" /> {{ timed ? 'Rush again' : 'Practice today’s puzzle' }}
            </button>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <Link :href="`/leaderboard?game=${game.slug}&board=${timed ? `timed&duration=${duration}` : 'once'}`" class="btn btn-ghost" prefetch><Trophy :size="18" aria-hidden="true" /> Rankings</Link>
                <Link :href="`/?game=${game.slug}`" class="btn btn-ghost" prefetch><LayoutGrid :size="18" aria-hidden="true" /> Games</Link>
            </div>
        </section>
    </div>
</template>
