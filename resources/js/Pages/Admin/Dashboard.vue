<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Activity, Clock, Crown, Flag, Gamepad2, Radio, UserCheck, Users } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import GamesChart from '@/Components/Admin/GamesChart.vue';
import { duration, fmt, relativeTime } from '@/game/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    metrics: { type: Object, required: true },
    gameUsage: { type: Array, required: true },
    series: { type: Array, required: true },
    recent: { type: Array, required: true },
    timezone: { type: String, required: true },
});

const m = props.metrics;
const cards = [
    { label: 'Registered players', value: fmt(m.registered_players), icon: Users, note: m.disabled_accounts ? `${m.disabled_accounts} disabled` : null },
    { label: 'Online now', value: fmt(m.online_players), icon: Radio, note: `+ ${fmt(m.online_guests)} guests` },
    { label: 'Playing now', value: fmt(m.playing_now), icon: Activity },
    { label: 'Games today', value: fmt(m.games_today), icon: Gamepad2 },
    { label: 'Daily active players', value: fmt(m.daily_active_players), icon: UserCheck },
    { label: 'Avg session (7 days)', value: duration(m.avg_session_seconds), icon: Clock },
    { label: 'Highest level', value: m.highest_level ? `Lvl ${m.highest_level.level}` : '—', icon: Crown, note: m.highest_level ? `${m.highest_level.player} · ${m.highest_level.game}` : null },
    { label: 'Most popular game', value: m.popular_game?.game ?? '—', icon: Gamepad2, note: m.popular_game ? `${fmt(m.popular_game.games)} games this week` : null },
];
const usageMax = Math.max(1, ...props.gameUsage.map((u) => u.games));
</script>

<template>
    <Head title="Admin overview" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-3xl font-extrabold text-navy-deep">Overview</h1>
                <p class="text-sm font-medium text-ink-soft">Live figures from the database · days in {{ timezone }}</p>
            </div>
            <Link v-if="m.pending_reviews" href="/admin/reviews" class="btn btn-danger btn-sm"><Flag :size="16" /> {{ m.pending_reviews }} result{{ m.pending_reviews === 1 ? '' : 's' }} to review</Link>
        </div>

        <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div v-for="c in cards" :key="c.label" class="rounded-3xl bg-white p-4 ring-1 ring-cream-high sm:p-5">
                <dt class="flex items-center gap-2 text-[13px] font-semibold text-ink-soft"><component :is="c.icon" :size="16" class="text-amber-deep" /> {{ c.label }}</dt>
                <dd class="mt-1 truncate font-display text-2xl font-extrabold text-navy-deep sm:text-3xl">{{ c.value }}</dd>
                <dd v-if="c.note" class="truncate text-xs font-medium text-outline">{{ c.note }}</dd>
            </div>
        </dl>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <section class="card p-5 sm:p-6 lg:col-span-2">
                <GamesChart :series="series" />
            </section>
            <section class="card p-5 sm:p-6">
                <h2 class="font-display text-lg font-bold text-navy-deep">Game popularity · 7 days</h2>
                <ul v-if="gameUsage.length" class="mt-4 space-y-3">
                    <li v-for="u in gameUsage" :key="u.game">
                        <div class="flex justify-between text-sm font-semibold"><span class="text-navy-deep">{{ u.game }}</span><span class="text-ink-soft">{{ fmt(u.games) }}</span></div>
                        <div class="mt-1 h-2 rounded-full bg-cream-high"><div class="h-full rounded-full bg-[#d97706]" :style="{ width: `${(u.games / usageMax) * 100}%` }" /></div>
                        <p class="mt-1 text-xs font-medium text-outline">
                            <template v-for="(count, mode, i) in u.modes" :key="mode">{{ i ? ' · ' : '' }}{{ mode }} {{ fmt(count) }}</template>
                        </p>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm font-medium text-ink-soft">No games in the last 7 days.</p>
            </section>
        </div>

        <section class="card p-5 sm:p-6">
            <h2 class="font-display text-lg font-bold text-navy-deep">Recently finished games</h2>
            <div class="mt-3 overflow-x-auto">
                <table v-if="recent.length" class="w-full min-w-[36rem] text-left text-sm">
                    <thead class="text-[12px] uppercase tracking-wider text-outline">
                        <tr><th class="py-2 font-bold">Player</th><th class="font-bold">Game</th><th class="font-bold">Mode</th><th class="font-bold">Score</th><th class="font-bold">Level</th><th class="font-bold">Status</th><th class="font-bold">When</th></tr>
                    </thead>
                    <tbody class="divide-y divide-cream-high font-medium text-ink-soft">
                        <tr v-for="g in recent" :key="g.uuid">
                            <td class="py-2.5 font-bold text-navy-deep">{{ g.player ?? 'Guest' }}</td>
                            <td>{{ g.game }}</td>
                            <td>{{ g.mode }}</td>
                            <td class="font-display font-bold text-navy-deep">{{ fmt(g.score) }}</td>
                            <td>{{ g.level }}</td>
                            <td>
                                <span v-if="g.is_flagged" class="rounded-full bg-ruby-soft px-2 py-0.5 text-xs font-bold text-ruby-deep">Flagged</span>
                                <span v-else class="text-xs">{{ g.status }}{{ g.is_ranked ? '' : ' · casual' }}</span>
                            </td>
                            <td class="text-xs">{{ relativeTime(g.ended_at) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="py-4 text-sm font-medium text-ink-soft">No games have been played yet.</p>
            </div>
        </section>
    </div>
</template>
