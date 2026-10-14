<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Flame, Grid2x2, LayoutGrid, RotateCcw, Star, Trophy } from 'lucide-vue-next';
import MeepleMascot from '@/Components/Mascot/MeepleMascot.vue';
import OutcomeHighlights from '@/Components/Game/OutcomeHighlights.vue';
import SyncBadge from '@/Components/Game/SyncBadge.vue';
import { useElementWidth } from '@/composables/useElementWidth';
import { BOARDS } from '@/game/boards';
import { explainLoss } from '@/game/coach';
import { fmt, seconds } from '@/game/format';

/**
 * End of a Level Builder or Try Once run that ran out of lives. Shows the
 * provisional result instantly and swaps in the server's official numbers as
 * soon as they arrive.
 */
const props = defineProps({
    game: { type: Object, required: true },
    mode: { type: String, required: true },
    round: { type: Object, default: null },
    roundState: { type: Object, default: null },
    rounds: { type: Array, default: () => [] },
    score: { type: Number, default: 0 },
    summary: { type: Object, default: null },
    verifying: { type: Boolean, default: false },
    sync: { type: String, default: 'synced' },
    isGuest: { type: Boolean, default: false },
});

defineEmits(['retry']);

const board = computed(() => BOARDS[props.game.slug]);
const { el: reviewEl, width: reviewWidth } = useElementWidth(320);
const integrity = computed(() => props.summary?.end_reason === 'integrity');
const highest = computed(() => props.summary?.highest_level ?? Math.max(0, ...props.rounds.filter((r) => r.status === 'cleared').map((r) => r.spec.level)));
const points = computed(() => props.summary?.final_score ?? props.score);
const streak = computed(() => {
    if (props.summary) return props.summary.best_success_streak;
    let best = 0;
    let run = 0;
    props.rounds.forEach((r) => {
        run = r.status === 'cleared' ? run + 1 : 0;
        best = Math.max(best, run);
    });
    return best;
});
const accuracyDeg = computed(() => Math.round(((props.summary?.accuracy ?? 0) / 100) * 360));
</script>

<template>
    <div class="mx-auto w-full max-w-4xl space-y-5 motion-safe:animate-rise">
        <div class="flex flex-wrap items-center justify-center gap-2">
            <span class="inline-flex items-center gap-2 rounded-full bg-ruby-soft px-4 py-1.5 font-display text-[13px] font-bold uppercase tracking-wider text-ruby-deep">
                Run over · {{ game.name }}
            </span>
            <SyncBadge :verifying="verifying" :sync="sync" :verified="!!summary" />
        </div>

        <section class="flex flex-col items-center gap-4 rounded-[2.5rem] bg-cream-low p-5 text-center ring-1 ring-cream-high sm:flex-row sm:p-6 sm:text-left">
            <div class="grid size-24 shrink-0 place-items-center rounded-full bg-amber-soft pt-3">
                <MeepleMascot mood="sad" :size="58" :shadow="false" />
            </div>
            <div>
                <span class="eyebrow">Pattern breakdown</span>
                <h2 class="mt-1 text-2xl font-bold text-navy-deep">Almost! Let's look at where the pattern slipped.</h2>
                <p class="mt-1.5 font-medium text-ink-soft">
                    <template v-if="integrity">Those taps were too fast for us to verify as human, so this run won't count towards the rankings. An admin will review it.</template>
                    <template v-else>{{ explainLoss(game.slug, round) }}</template>
                </p>
            </div>
        </section>

        <section class="card p-5 sm:p-8" aria-labelledby="gameover-title">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <h3 id="gameover-title" class="flex items-center gap-2 text-3xl font-extrabold text-navy-deep sm:text-4xl">
                    <span class="size-3 rounded-full bg-amber" aria-hidden="true" /> {{ integrity ? 'Run not verified' : 'Pattern Broken!' }}
                </h3>
                <div v-if="summary" class="flex items-center gap-3 rounded-full bg-cream-low px-4 py-2 ring-1 ring-cream-high">
                    <span class="size-9 rounded-full" :style="{ background: `conic-gradient(var(--color-amber) ${accuracyDeg}deg, var(--color-cream-highest) 0deg)` }" aria-hidden="true">
                        <span class="m-[5px] block size-[26px] rounded-full bg-cream-low" />
                    </span>
                    <span>
                        <span class="block font-display text-lg font-extrabold text-navy-deep">{{ summary.accuracy }}% accuracy</span>
                        <span class="block text-xs font-semibold text-outline">{{ summary.correct_inputs }} of {{ summary.expected_inputs }} correct</span>
                    </span>
                </div>
            </div>

            <div v-if="round" class="mt-5">
                <h4 class="eyebrow mb-3 !text-[12px] !text-ink-soft">Your last round, revealed</h4>
                <div ref="reviewEl" class="flex justify-center">
                    <component :is="board" :spec="round.spec" phase="review" :round-state="roundState" :inputs="round.inputs" :timings="round.timings" review :box="{ w: Math.min(reviewWidth, 560), h: 360 }" />
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="flex items-center gap-3 rounded-3xl bg-cream-low p-4">
                    <span class="grid size-11 place-items-center rounded-full bg-amber-soft text-amber-deep"><Grid2x2 :size="20" /></span>
                    <div>
                        <dt class="eyebrow">Highest level cleared</dt>
                        <dd class="font-display text-2xl font-extrabold text-navy-deep">{{ highest ? `Level ${highest}` : 'None yet' }}</dd>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-3xl bg-cream-low p-4">
                    <span class="grid size-11 place-items-center rounded-full bg-amber text-navy-deep"><Star :size="20" /></span>
                    <div>
                        <dt class="eyebrow">Points earned</dt>
                        <dd class="font-display text-2xl font-extrabold text-navy-deep">{{ fmt(points) }} pts</dd>
                        <dd v-if="summary" class="text-xs font-bold text-emerald-deep">+{{ fmt(summary.bonus_score) }} bonus</dd>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-3xl bg-cream-low p-4">
                    <span class="grid size-11 place-items-center rounded-full bg-ruby-soft text-ruby-deep"><Flame :size="20" /></span>
                    <div>
                        <dt class="eyebrow">Success streak</dt>
                        <dd class="font-display text-2xl font-extrabold text-navy-deep">{{ streak }} round{{ streak === 1 ? '' : 's' }}</dd>
                        <dd v-if="summary?.avg_unit_ms" class="text-xs font-semibold text-outline">Avg {{ seconds(summary.avg_unit_ms) }} per {{ game.unit }}</dd>
                    </div>
                </div>
            </dl>

            <div v-if="summary" class="mt-5">
                <OutcomeHighlights :outcome="summary.outcome" :is-ranked="summary.is_ranked && !summary.is_flagged" :is-guest="isGuest" />
            </div>
        </section>

        <div class="mx-auto flex max-w-xl flex-col gap-3">
            <button type="button" class="btn btn-primary btn-lg w-full" autofocus @click="$emit('retry')">
                <RotateCcw :size="20" aria-hidden="true" /> {{ mode === 'once' ? 'Practice today’s puzzle' : 'Try again' }}
            </button>
            <div class="grid grid-cols-2 gap-3">
                <Link :href="`/leaderboard?game=${game.slug}`" class="btn btn-ghost" prefetch><Trophy :size="18" aria-hidden="true" /> Leaderboard</Link>
                <Link :href="`/?game=${game.slug}`" class="btn btn-ghost" prefetch><LayoutGrid :size="18" aria-hidden="true" /> Games</Link>
            </div>
        </div>
    </div>
</template>
