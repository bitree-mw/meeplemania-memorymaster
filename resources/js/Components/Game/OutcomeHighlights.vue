<script setup>
import { computed } from 'vue';
import { PartyPopper } from 'lucide-vue-next';
import GuestPlaysNotice from '@/Components/Game/GuestPlaysNotice.vue';
import { ICONS } from '@/game/palette';

/** Personal bests, rank movement and newly unlocked achievements after a ranked run. */
const props = defineProps({
    outcome: { type: Object, default: null },
    isRanked: { type: Boolean, default: true },
    isGuest: { type: Boolean, default: false },
});

const labels = { level: 'highest level', score: 'Level Builder score', speed: 'fastest average', timed: 'Timed Rush score' };

const climb = computed(() => {
    const o = props.outcome;
    if (!o?.rank_after) return null;
    if (!o.rank_before) return { text: `You entered the all-time rankings at #${o.rank_after}!` };
    const delta = o.rank_before - o.rank_after;
    return delta > 0 ? { text: `You climbed ${delta} place${delta === 1 ? '' : 's'} to all-time rank #${o.rank_after}!` } : null;
});

const bests = computed(() => (props.outcome?.personal_bests ?? []).map((b) => labels[b]).filter(Boolean));
</script>

<template>
    <div class="space-y-2.5">
        <GuestPlaysNotice v-if="isGuest" />
        <p v-else-if="!isRanked" class="rounded-2xl bg-cream-mid px-4 py-3 text-sm font-medium text-ink-soft">
            Practice run — this result is not ranked.
        </p>

        <div v-if="bests.length || climb" class="flex items-start gap-3 rounded-2xl bg-amber-mist px-4 py-3 text-sm font-semibold text-amber-ink ring-1 ring-amber-soft motion-safe:animate-rise">
            <PartyPopper :size="20" class="mt-0.5 shrink-0 text-orange" aria-hidden="true" />
            <p>
                <template v-if="bests.length">New personal best {{ bests.join(' & ') }}! </template>
                <template v-if="climb"><span class="text-emerald-deep">{{ climb.text }}</span></template>
            </p>
        </div>

        <ul v-if="outcome?.achievements?.length" class="space-y-2" aria-label="Achievements unlocked">
            <li
                v-for="a in outcome.achievements"
                :key="a.key"
                class="flex items-center gap-3 rounded-2xl bg-navy px-4 py-3 text-white motion-safe:animate-pop"
            >
                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-amber text-navy-deep">
                    <component :is="ICONS[a.icon] ?? ICONS.Trophy" :size="20" :stroke-width="2.4" />
                </span>
                <span>
                    <span class="block font-display text-[11px] font-bold uppercase tracking-wider text-amber-dim">Achievement unlocked</span>
                    <span class="block font-display font-bold">{{ a.name }}</span>
                    <span class="block text-xs text-white/70">{{ a.description }}</span>
                </span>
            </li>
        </ul>
    </div>
</template>
