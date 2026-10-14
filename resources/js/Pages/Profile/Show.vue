<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Compass, Crosshair, Flame, Gamepad2, Layers, Lock, MapPin, Settings, Timer, Trophy } from 'lucide-vue-next';
import Avatar from '@/Components/Avatar.vue';
import { ICONS, colorVars } from '@/game/palette';
import { END_REASONS, compact, fmt, relativeTime, seconds, shortDate } from '@/game/format';

const props = defineProps({
    player: { type: Object, required: true },
    isSelf: { type: Boolean, default: false },
    stats: { type: Object, required: true },
    perGame: { type: Array, required: true },
    rank: { type: Number, default: null },
    achievements: { type: Array, required: true },
    recent: { type: Array, required: true },
});

const unlockedCount = computed(() => props.achievements.filter((a) => a.unlocked_at).length);
const gameBySlug = computed(() => Object.fromEntries(props.perGame.map((g) => [g.slug, g])));

const tiles = computed(() => [
    { label: 'Total points', value: compact(props.stats.total_points), icon: Trophy, tone: 'bg-amber-soft text-amber-deep' },
    { label: 'Games played', value: fmt(props.stats.games_played), icon: Gamepad2, tone: 'bg-cream-highest text-navy-soft' },
    { label: 'Highest level', value: props.stats.best_level || '—', icon: Layers, tone: 'bg-sky-soft text-[#1d4ed8]' },
    { label: 'Accuracy', value: props.stats.games_played ? `${props.stats.accuracy}%` : '—', icon: Crosshair, tone: 'bg-emerald-soft text-emerald-deep' },
    { label: 'Fastest answers', value: props.stats.best_avg_unit_ms ? `${seconds(props.stats.best_avg_unit_ms)}/item` : '—', icon: Timer, tone: 'bg-ruby-soft text-ruby-deep' },
    { label: 'Games tried', value: `${props.stats.games_tried}/${props.perGame.length}`, icon: Compass, tone: 'bg-amber-mist text-orange' },
]);

function statusLabel(g) {
    if (g.is_flagged) return { text: 'Under review', tone: 'bg-ruby-soft text-ruby-deep' };
    if (g.status === 'completed') return { text: g.mode === 'timed' ? 'Finished' : 'Completed', tone: 'bg-emerald-soft text-emerald-deep' };
    return { text: END_REASONS[g.end_reason] ?? g.status, tone: 'bg-cream-mid text-ink-soft' };
}
</script>

<template>
    <Head :title="player.username" />

    <div class="mx-auto max-w-5xl space-y-6 pt-6">
        <section class="card flex flex-col items-center gap-5 p-6 text-center sm:flex-row sm:p-8 sm:text-left">
            <div class="relative">
                <Avatar :avatar="player.avatar" size="xl" />
                <span v-if="player.is_online" class="absolute -bottom-1 -right-1 size-5 rounded-full bg-emerald ring-4 ring-white" title="Online now" />
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-3xl font-extrabold text-navy-deep sm:text-4xl">{{ player.username }}</h1>
                <p class="mt-1 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-sm font-medium text-ink-soft sm:justify-start">
                    <span v-if="player.district" class="inline-flex items-center gap-1"><MapPin :size="15" /> {{ player.district }}</span>
                    <span class="inline-flex items-center gap-1"><CalendarDays :size="15" /> Joined {{ shortDate(player.joined) }}</span>
                    <span v-if="player.is_online" class="font-bold text-emerald-deep">● Online</span>
                </p>
                <Link href="/leaderboard" class="chip mt-3 bg-amber-mist text-amber-ink ring-1 ring-amber-soft" prefetch>
                    <Trophy :size="15" /> {{ rank ? `#${fmt(rank)} across all games` : 'Unranked' }}
                </Link>
            </div>
            <div class="flex shrink-0 gap-3">
                <div class="rounded-3xl bg-amber-mist px-5 py-3 text-center ring-1 ring-amber-soft">
                    <Flame :size="20" class="mx-auto text-orange" aria-hidden="true" />
                    <p class="font-display text-2xl font-extrabold text-navy-deep">{{ stats.current_daily_streak }}</p>
                    <p class="eyebrow">Day streak</p>
                </div>
                <div class="rounded-3xl bg-cream-low px-5 py-3 text-center ring-1 ring-cream-high">
                    <Flame :size="20" class="mx-auto text-outline" aria-hidden="true" />
                    <p class="font-display text-2xl font-extrabold text-navy-deep">{{ stats.longest_daily_streak }}</p>
                    <p class="eyebrow">Longest</p>
                </div>
            </div>
        </section>

        <Link v-if="isSelf" href="/profile/edit" class="btn btn-ghost btn-sm mx-auto flex w-max"><Settings :size="16" /> Edit profile</Link>

        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div v-for="t in tiles" :key="t.label" class="flex items-center gap-3 rounded-3xl bg-white p-4 ring-1 ring-cream-high">
                <span class="grid size-10 shrink-0 place-items-center rounded-2xl" :class="t.tone"><component :is="t.icon" :size="19" /></span>
                <div class="min-w-0">
                    <dt class="eyebrow truncate">{{ t.label }}</dt>
                    <dd class="truncate font-display text-xl font-extrabold text-navy-deep">{{ t.value }}</dd>
                </div>
            </div>
        </dl>

        <!-- Per game -->
        <section aria-labelledby="games-title">
            <h2 id="games-title" class="eyebrow mb-3 px-1 !text-[13px]">By game</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div v-for="g in perGame" :key="g.slug" class="rounded-3xl bg-white p-4 ring-1 ring-cream-high" :style="colorVars(g.color)">
                    <div class="flex items-center gap-2">
                        <span class="grid size-9 place-items-center rounded-xl bg-[var(--tile-soft)] text-[var(--tile)]"><component :is="ICONS[g.icon]" :size="18" :stroke-width="2.4" /></span>
                        <span class="font-display font-bold leading-tight text-navy-deep">{{ g.name }}</span>
                    </div>
                    <dl v-if="g.games_played" class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="font-medium text-ink-soft">Best level</dt><dd class="font-display font-bold text-navy-deep">{{ g.best_level || '—' }}</dd></div>
                        <div v-for="d in [30, 45, 60]" :key="d" class="flex justify-between">
                            <dt class="font-medium text-ink-soft">Timed {{ d }}s</dt><dd class="font-display font-bold text-navy-deep">{{ g.best_timed[d] ? fmt(g.best_timed[d]) : '—' }}</dd>
                        </div>
                        <div class="flex justify-between"><dt class="font-medium text-ink-soft">Accuracy</dt><dd class="font-display font-bold text-navy-deep">{{ g.accuracy }}%</dd></div>
                        <div class="flex justify-between"><dt class="font-medium text-ink-soft">Games</dt><dd class="font-display font-bold text-navy-deep">{{ g.games_played }}</dd></div>
                    </dl>
                    <p v-else class="mt-3 text-sm font-medium text-outline">Not played yet.</p>
                </div>
            </div>
        </section>

        <!-- Achievements -->
        <section aria-labelledby="ach-title">
            <div class="mb-3 flex items-center justify-between px-1">
                <h2 id="ach-title" class="eyebrow !text-[13px]">Achievements</h2>
                <span class="font-display text-sm font-bold text-ink-soft">{{ unlockedCount }} / {{ achievements.length }}</span>
            </div>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <li v-for="a in achievements" :key="a.key" class="flex flex-col items-center rounded-3xl p-4 text-center" :class="a.unlocked_at ? 'bg-white shadow-soft ring-2 ring-amber-soft' : 'bg-cream-mid opacity-75'">
                    <span class="grid size-12 place-items-center rounded-2xl" :class="a.unlocked_at ? 'bg-amber text-navy-deep' : 'bg-cream-highest text-outline'">
                        <component :is="ICONS[a.icon] ?? ICONS.Trophy" v-if="a.unlocked_at" :size="22" :stroke-width="2.4" />
                        <Lock v-else :size="20" />
                    </span>
                    <span class="mt-2 font-display text-sm font-bold text-navy-deep">{{ a.name }}</span>
                    <span class="mt-0.5 text-[11px] font-medium leading-snug text-ink-soft">{{ a.description }}</span>
                    <span v-if="a.unlocked_at" class="mt-1.5 text-[11px] font-bold text-emerald-deep">Unlocked {{ shortDate(a.unlocked_at) }}</span>
                </li>
            </ul>
        </section>

        <!-- History -->
        <section class="card p-4 sm:p-6" aria-labelledby="history-title">
            <h2 id="history-title" class="mb-2 font-display text-lg font-bold text-navy-deep">Recent games</h2>
            <ul v-if="recent.length" class="divide-y divide-cream-high">
                <li v-for="r in recent" :key="r.uuid" class="flex items-center gap-3 py-3" :style="colorVars(gameBySlug[r.game]?.color)">
                    <span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-[var(--tile-soft)] text-[var(--tile)]">
                        <component :is="ICONS[gameBySlug[r.game]?.icon] ?? Gamepad2" :size="18" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="font-display font-bold text-navy-deep">{{ gameBySlug[r.game]?.name }}</span>
                            <span class="text-xs font-semibold text-ink-soft">{{ r.mode_label }}{{ r.duration ? ` ${r.duration}s` : '' }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold" :class="statusLabel(r).tone">{{ statusLabel(r).text }}</span>
                            <span v-if="!r.is_ranked" class="rounded-full bg-cream-mid px-2 py-0.5 text-[11px] font-bold text-outline">Practice</span>
                        </span>
                        <span class="block text-xs font-medium text-outline">
                            {{ r.mode === 'timed' ? `${r.rounds_cleared} rounds` : `Level ${r.level}` }} · {{ r.accuracy }}% accuracy · {{ relativeTime(r.ended_at) }}
                        </span>
                    </span>
                    <span class="shrink-0 font-display text-lg font-extrabold text-amber-deep">{{ fmt(r.score) }}</span>
                </li>
            </ul>
            <p v-else class="py-6 text-center text-sm font-medium text-ink-soft">
                No games yet. <Link v-if="isSelf" href="/" class="font-bold text-amber-deep underline">Pick a game</Link>
            </p>
        </section>
    </div>
</template>
