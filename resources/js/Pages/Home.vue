<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CalendarCheck, Flame, Lock, Play, Timer, Trophy, TrendingUp } from 'lucide-vue-next';
import HeroScene from '@/Components/HeroScene.vue';
import RoamingMeeple from '@/Components/Mascot/RoamingMeeple.vue';
import { usePresence } from '@/composables/usePresence';
import { sounds } from '@/game/audio';
import { fmt, points, rankPoints } from '@/game/format';
import { ICONS, colorVars } from '@/game/palette';

const props = defineProps({
    games: { type: Array, required: true },
    durations: { type: Array, required: true },
    me: { type: Object, default: null },
    season: { type: Object, default: null },
    online: { type: Object, default: null },
});

const { counts } = usePresence();
const live = computed(() => counts.value ?? props.online);

const STORAGE_KEY = 'mm:last-game';
const selected = ref(props.games[0]?.slug);
const duration = ref(props.durations[0]);
const game = computed(() => props.games.find((g) => g.slug === selected.value) ?? props.games[0]);

onMounted(() => {
    const fromUrl = new URLSearchParams(window.location.search).get('game');
    let remembered = null;
    try {
        remembered = localStorage.getItem(STORAGE_KEY);
    } catch {
        // ignore
    }
    const pick = [fromUrl, remembered].find((s) => props.games.some((g) => g.slug === s));
    if (pick) selected.value = pick;
});

function select(slug) {
    sounds.click();
    selected.value = slug;
    try {
        localStorage.setItem(STORAGE_KEY, slug);
    } catch {
        // ignore
    }
}

const daily = computed(() => game.value.daily);
const dailyLabel = computed(() => {
    if (!props.me) return 'Sign in to compete';
    if (daily.value.in_progress) return 'Your ranked run is in progress';
    if (daily.value.attempted) return `Played · ${fmt(daily.value.my_score ?? 0)} pts`;
    return 'Not played yet today';
});
</script>

<template>
    <Head title="Play" />

    <div class="mx-auto max-w-5xl space-y-7 pt-6">
        <!-- Hero -->
        <section class="card relative overflow-hidden">
            <div class="grid grid-cols-1 items-center gap-6 p-5 sm:p-8 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] md:gap-8">
                <div class="order-2 md:order-1">
                    <span class="eyebrow !text-[12px] !text-amber-deep">MeepleMania Games</span>
                    <h1 class="mt-1 text-[32px] font-extrabold leading-[1.08] tracking-tight text-navy-deep sm:text-[44px]">
                        {{ me ? 'Ready to test your memory?' : 'Five memory games. One very proud meeple.' }}
                    </h1>
                    <p class="mt-3 max-w-md text-[16px] font-medium leading-relaxed text-ink-soft">
                        Climb levels, race the clock, or take today's one-shot challenge. Every game, three ways to play.
                    </p>

                    <dl v-if="me" class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-2xl bg-cream-low px-3 py-2.5 ring-1 ring-cream-high">
                            <dt class="eyebrow">Points</dt>
                            <dd class="font-display text-lg font-extrabold text-navy-deep">{{ points(me.total_points) }}</dd>
                        </div>
                        <div class="rounded-2xl bg-cream-low px-3 py-2.5 ring-1 ring-cream-high">
                            <dt class="eyebrow">Streak</dt>
                            <dd class="flex items-center gap-1 font-display text-lg font-extrabold text-navy-deep">
                                <Flame :size="16" class="text-orange" aria-hidden="true" />{{ me.daily_streak }}d
                            </dd>
                        </div>
                        <div class="rounded-2xl bg-cream-low px-3 py-2.5 ring-1 ring-cream-high">
                            <dt class="eyebrow">Rank</dt>
                            <dd class="font-display text-lg font-extrabold text-amber-deep">{{ me.rank ? `#${fmt(me.rank)}` : '—' }}</dd>
                        </div>
                        <div class="rounded-2xl bg-cream-low px-3 py-2.5 ring-1 ring-cream-high">
                            <dt class="eyebrow">Games tried</dt>
                            <dd class="font-display text-lg font-extrabold text-navy-deep">{{ me.games_tried }}/{{ games.length }}</dd>
                        </div>
                    </dl>
                    <div v-else class="mt-5 flex flex-wrap gap-3">
                        <Link href="/register" class="btn btn-primary">Create free account</Link>
                        <Link href="/login" class="btn btn-ghost">Sign in</Link>
                    </div>
                </div>

                <div class="order-1 md:order-2">
                    <div class="relative">
                        <HeroScene />
                        <span v-if="season" class="absolute left-1/2 top-2.5 -translate-x-1/2 whitespace-nowrap rounded-full bg-white/95 px-3 py-1 font-display text-xs font-bold text-amber-ink shadow-sm">{{ season.name }} active</span>
                    </div>
                </div>
            </div>

            <!-- Coach Meeple's playground: it roams here, and can be dragged and thrown. -->
            <div class="relative h-28 border-t-2 border-dashed border-cream-high bg-gradient-to-b from-cream-low/0 to-cream-low">
                <p class="pointer-events-none absolute left-5 top-3 text-xs font-semibold text-outline">Psst — tap or drag Coach Meeple</p>
                <RoamingMeeple :size="60" />
            </div>
        </section>

        <!-- Game picker -->
        <section aria-labelledby="games-title">
            <h2 id="games-title" class="eyebrow mb-3 px-1 !text-[13px]">Choose a game</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" role="radiogroup" aria-label="Game">
                <button
                    v-for="(g, i) in games"
                    :key="g.slug"
                    type="button"
                    role="radio"
                    :aria-checked="g.slug === game.slug"
                    class="group flex flex-col items-start rounded-[1.75rem] bg-white p-4 text-left transition-all"
                    :class="[g.slug === game.slug ? 'shadow-card ring-2 ring-[var(--tile)]' : 'ring-1 ring-cream-high hover:-translate-y-0.5 hover:ring-[var(--tile)]/50', i === games.length - 1 && games.length % 2 ? 'col-span-2 sm:col-span-1' : '']"
                    :style="colorVars(g.color)"
                    @click="select(g.slug)"
                >
                    <span class="grid size-12 place-items-center rounded-2xl bg-[var(--tile-soft)] text-[var(--tile)] transition-transform group-hover:-rotate-6">
                        <component :is="ICONS[g.icon]" :size="24" :stroke-width="2.4" aria-hidden="true" />
                    </span>
                    <span class="mt-3 font-display text-[17px] font-bold leading-tight text-navy-deep">{{ g.name }}</span>
                    <span class="text-[13px] font-semibold text-ink-soft">{{ g.tagline }}</span>
                    <span v-if="g.best.level" class="mt-2 rounded-full bg-[var(--tile-soft)] px-2 py-0.5 text-[11px] font-bold text-[var(--tile-deep)]">Best: level {{ g.best.level }}</span>
                </button>
            </div>
        </section>

        <!-- Modes for the selected game -->
        <section class="card p-5 sm:p-7" :style="colorVars(game.color)" aria-labelledby="modes-title">
            <div class="flex flex-wrap items-center gap-3">
                <span class="grid size-12 place-items-center rounded-2xl bg-[var(--tile-soft)] text-[var(--tile)]"><component :is="ICONS[game.icon]" :size="24" :stroke-width="2.4" /></span>
                <div class="min-w-0 flex-1">
                    <h2 id="modes-title" class="text-2xl font-extrabold text-navy-deep">{{ game.name }}</h2>
                    <p class="text-[15px] font-medium text-ink-soft">{{ game.description }}</p>
                </div>
                <Link :href="`/leaderboard?game=${game.slug}`" class="hidden items-center gap-1 font-display text-sm font-bold text-[var(--tile-deep)] sm:flex" :prefetch="Boolean(me)">Rankings <ArrowRight :size="16" /></Link>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                <!-- Level Builder -->
                <div class="flex flex-col rounded-3xl bg-cream-low p-4 ring-1 ring-cream-high">
                    <div class="flex items-center gap-2">
                        <TrendingUp :size="20" class="text-amber" aria-hidden="true" />
                        <h3 class="font-display text-lg font-bold text-navy-deep">Level Builder</h3>
                    </div>
                    <p class="mt-1 flex-1 text-sm font-medium text-ink-soft">Climb level by level as puzzles grow and speed up. A few lives to spare.</p>
                    <p class="mt-2 text-xs font-bold text-outline">{{ game.best.level ? `Your best: level ${game.best.level}` : 'No ranked run yet' }}</p>
                    <Link :href="`/play/${game.slug}/classic`" class="btn btn-primary mt-3" prefetch><Play :size="18" fill="currentColor" aria-hidden="true" /> Play</Link>
                </div>

                <!-- Timed Rush -->
                <div class="flex flex-col rounded-3xl bg-cream-low p-4 ring-1 ring-cream-high">
                    <div class="flex items-center gap-2">
                        <Timer :size="20" class="text-ruby" aria-hidden="true" />
                        <h3 class="font-display text-lg font-bold text-navy-deep">Timed Rush</h3>
                    </div>
                    <p class="mt-1 text-sm font-medium text-ink-soft">Clear as many rounds as you can before the clock runs out.</p>
                    <div class="mt-3 flex gap-1.5" role="radiogroup" aria-label="Clock length">
                        <button
                            v-for="d in durations"
                            :key="d"
                            type="button"
                            role="radio"
                            :aria-checked="d === duration"
                            class="flex-1 rounded-xl py-1.5 font-display text-sm font-extrabold transition-colors"
                            :class="d === duration ? 'bg-navy text-white' : 'bg-white text-ink-soft ring-1 ring-cream-high hover:bg-cream-mid'"
                            @click="duration = d"
                        >
                            {{ d }}s
                        </button>
                    </div>
                    <p class="mt-2 flex-1 text-xs font-bold text-outline">{{ game.best.timed[duration] ? `Your best at ${duration}s: ${fmt(game.best.timed[duration])} pts` : `No ${duration}s score yet` }}</p>
                    <Link :href="`/play/${game.slug}/timed?duration=${duration}`" class="btn btn-navy mt-3" prefetch><Play :size="18" fill="currentColor" aria-hidden="true" /> Play {{ duration }}s</Link>
                </div>

                <!-- Try Once -->
                <div class="flex flex-col rounded-3xl bg-cream-low p-4 ring-1 ring-cream-high">
                    <div class="flex items-center gap-2">
                        <CalendarCheck :size="20" class="text-sky" aria-hidden="true" />
                        <h3 class="font-display text-lg font-bold text-navy-deep">Try Once</h3>
                        <span class="rounded-full bg-sky-soft px-2 py-0.5 font-display text-[10px] font-extrabold text-[#1d4ed8]">1 TRY / DAY</span>
                    </div>
                    <p class="mt-1 text-sm font-medium text-ink-soft">Same puzzle for everyone today. One ranked attempt.</p>
                    <p class="mt-2 flex items-center gap-1 text-xs font-bold" :class="daily.attempted ? 'text-emerald-deep' : 'text-outline'">
                        <Lock v-if="!me" :size="12" aria-hidden="true" /> {{ dailyLabel }}
                    </p>
                    <p class="mt-1 flex-1 text-xs font-semibold text-ink-soft">
                        <template v-if="daily.leader"><Trophy :size="12" class="inline text-amber" /> Leader: {{ daily.leader.username }} · {{ rankPoints(daily.leader.score) }} pts</template>
                        <template v-else>No scores yet today — set the record!</template>
                    </p>
                    <Link :href="me ? `/play/${game.slug}/once` : '/login'" class="btn btn-ghost mt-3" prefetch>
                        <Play :size="18" fill="currentColor" aria-hidden="true" /> {{ !me ? 'Sign in to play' : daily.attempted ? 'Practice' : 'Take the challenge' }}
                    </Link>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm font-semibold text-ink-soft">
            <span v-if="live" class="inline-flex items-center gap-1.5">
                <span class="size-2 rounded-full bg-emerald motion-safe:animate-pulse" aria-hidden="true" />
                {{ fmt(live.online) }} online · {{ fmt(live.playing) }} playing now
            </span>
            <Link href="/leaderboard" class="inline-flex items-center gap-1.5 hover:text-navy" :prefetch="Boolean(me)"><Trophy :size="16" /> Leaderboards</Link>
            <Link href="/rules" class="inline-flex items-center gap-1.5 hover:text-navy" prefetch>How to play</Link>
        </div>
    </div>
</template>
