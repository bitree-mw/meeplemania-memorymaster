<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { PartyPopper, Play, RotateCcw, TrendingUp, Zap } from 'lucide-vue-next';
import AnimatedNumber from '@/Components/AnimatedNumber.vue';
import MeepleMascot from '@/Components/Mascot/MeepleMascot.vue';
import { seconds } from '@/game/format';

const props = defineProps({
    round: { type: Object, required: true },
    score: { type: Number, required: true },
    unit: { type: String, required: true },
    previousBestLevel: { type: Number, default: null },
    maxLevel: { type: Number, default: null },
});

defineEmits(['next', 'restart']);

const level = computed(() => props.round.spec.level);
const units = computed(() => props.round.spec.units);
const bonus = computed(() => props.round.points.difficulty_bonus + props.round.points.speed_bonus);
const isPersonalBest = computed(() => props.previousBestLevel !== null && level.value > props.previousBestLevel);
</script>

<template>
    <div class="mx-auto flex w-full max-w-lg flex-col items-center motion-safe:animate-rise">
        <div class="relative mb-1 rounded-2xl bg-white px-5 py-3 text-center text-[15px] font-medium text-ink-soft shadow-card">
            <template v-if="isPersonalBest">“New personal best! You're climbing the rankings!”</template>
            <template v-else>“Incredible memory! You nailed <strong class="text-amber-deep">Level {{ level }}</strong>!”</template>
            <span class="absolute -bottom-2 left-1/2 size-4 -translate-x-1/2 rotate-45 bg-white" aria-hidden="true" />
        </div>
        <MeepleMascot mood="cheer" :size="74" class="mt-3" />

        <section class="card mt-1 w-full p-6 text-center sm:p-8" aria-labelledby="level-complete-title">
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-soft px-3 py-1 font-display text-sm font-bold text-amber-ink">
                <span class="size-2 rounded-full bg-amber" aria-hidden="true" /> Level {{ level }} Cleared!
            </span>
            <h2 id="level-complete-title" class="mt-3 text-[34px] font-extrabold leading-tight tracking-tight text-navy-deep sm:text-[42px]">Awesome Memory!</h2>
            <p class="mt-1 font-medium text-outline">Pattern memorized and repeated flawlessly</p>

            <dl class="mt-6 grid grid-cols-3 divide-x divide-cream-high rounded-3xl bg-cream-low px-2 py-4 ring-1 ring-cream-high">
                <div class="px-1">
                    <dt class="eyebrow">Points</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold text-amber"><AnimatedNumber :value="round.points.points" :from="0" /></dd>
                    <dd class="mt-1 inline-block rounded-full bg-emerald-soft px-2 py-0.5 text-[11px] font-bold text-emerald-deep">+{{ bonus }} bonus</dd>
                </div>
                <div class="px-1">
                    <dt class="eyebrow">Size</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold text-navy-deep">{{ units }}<span class="text-sm font-bold text-outline"> {{ unit }}{{ units === 1 ? '' : 's' }}</span></dd>
                    <dd class="mt-1 text-[11px] font-semibold text-outline">Cleared</dd>
                </div>
                <div class="px-1">
                    <dt class="eyebrow">Time</dt>
                    <dd class="mt-1 font-display text-2xl font-extrabold text-emerald">{{ seconds(round.responseMs, 1) }}</dd>
                    <dd class="mt-1 inline-flex items-center gap-0.5 text-[11px] font-bold" :class="round.points.speed_bonus ? 'text-orange' : 'text-outline'">
                        <template v-if="round.points.speed_bonus">+{{ round.points.speed_bonus }} speed <Zap :size="12" /></template>
                        <template v-else>No speed bonus</template>
                    </dd>
                </div>
            </dl>

            <p class="mt-4 text-sm font-medium text-ink-soft">
                Run total <strong class="text-navy-deep"><AnimatedNumber :value="score" /></strong> points
            </p>

            <div v-if="isPersonalBest" class="mt-4 flex items-center justify-center gap-2 rounded-2xl bg-amber-mist px-4 py-3 text-sm font-semibold text-amber-ink ring-1 ring-amber-soft">
                <PartyPopper :size="18" class="text-orange" aria-hidden="true" /> New personal best: level {{ level }}!
            </div>

            <button type="button" class="btn btn-primary btn-lg mt-6 w-full" autofocus @click="$emit('next')">
                <Play :size="20" fill="currentColor" aria-hidden="true" />
                Next Level (Level {{ level + 1 }})
            </button>
            <div class="mt-3 flex gap-3">
                <Link href="/leaderboard" class="btn btn-ghost flex-1" prefetch><TrendingUp :size="18" aria-hidden="true" /> Leaderboard</Link>
                <button type="button" class="btn btn-ghost px-4" aria-label="Restart from the beginning" title="Restart run" @click="$emit('restart')">
                    <RotateCcw :size="18" />
                </button>
            </div>
        </section>
    </div>
</template>
