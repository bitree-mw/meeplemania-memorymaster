<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Ban, CircleCheck, ExternalLink, KeyRound, ShieldCheck, ShieldOff } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Pagination from '@/Components/Pagination.vue';
import { END_REASONS, duration, fmt, seconds, shortDate } from '@/game/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    player: { type: Object, required: true },
    profile: { type: Object, default: null },
    perGame: { type: Array, required: true },
    achievements: { type: Array, required: true },
    sessions: { type: Object, required: true },
    adminSlotsLeft: { type: Number, required: true },
    maxAdmins: { type: Number, required: true },
});

const reason = ref('');

function disable() {
    if (!confirm(`Disable ${props.player.username}? They will be signed out and removed from leaderboards.`)) return;
    router.post(`/admin/players/${props.player.id}/disable`, { reason: reason.value }, { preserveScroll: true });
}
const enable = () => router.post(`/admin/players/${props.player.id}/enable`, {}, { preserveScroll: true });

function sendReset() {
    if (!confirm(`Email ${props.player.username} a one-time link to choose a new password?`)) return;
    router.post(`/admin/players/${props.player.id}/password-reset`, {}, { preserveScroll: true });
}
function promote() {
    if (!confirm(`Give ${props.player.username} full admin access? (${props.adminSlotsLeft} of ${props.maxAdmins} admin slots left)`)) return;
    router.post(`/admin/players/${props.player.id}/promote`, {}, { preserveScroll: true });
}
function revoke() {
    if (!confirm(`Remove admin access from ${props.player.username}? Their account stays active as a player.`)) return;
    router.post(`/admin/players/${props.player.id}/revoke`, {}, { preserveScroll: true });
}

function voidSession(uuid) {
    if (!confirm('Void this result? It will be removed from statistics and leaderboards.')) return;
    router.post(`/admin/sessions/${uuid}/void`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${player.username} · Players`" />

    <div class="space-y-5">
        <Link href="/admin/players" class="inline-flex items-center gap-1 text-sm font-bold text-ink-soft hover:text-navy"><ArrowLeft :size="16" /> All players</Link>

        <section class="card flex flex-wrap items-center gap-5 p-5 sm:p-6">
            <Avatar :avatar="player.avatar" size="lg" />
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-extrabold text-navy-deep">{{ player.username }}</h1>
                <p class="text-sm font-medium text-ink-soft">{{ player.email }} · {{ player.district ?? 'no district' }} · joined {{ player.joined }} · {{ player.role }}<span v-if="player.is_online" class="font-bold text-emerald-deep"> · online</span></p>
                <p v-if="player.disabled" class="mt-1 text-sm font-semibold text-ruby-deep">Disabled{{ player.disabled_reason ? `: ${player.disabled_reason}` : '' }}</p>
                <Link :href="`/players/${player.username}`" class="mt-1 inline-flex items-center gap-1 text-xs font-bold text-amber-deep hover:underline">Public profile <ExternalLink :size="12" /></Link>
            </div>
            <div v-if="player.disabled">
                <button type="button" class="btn btn-ghost btn-sm" @click="enable"><CircleCheck :size="16" /> Re-enable account</button>
            </div>
            <div v-else-if="player.id !== $page.props.auth.user.id" class="flex flex-wrap gap-2">
                <input v-model="reason" class="field !w-56 !py-2" placeholder="Reason (optional)" maxlength="255" aria-label="Reason for disabling" />
                <button type="button" class="btn btn-danger btn-sm" @click="disable"><Ban :size="16" /> Deactivate</button>
            </div>
            <div class="flex w-full flex-wrap items-center gap-2 border-t border-cream-high pt-4">
                <span class="eyebrow mr-1">Account</span>
                <button type="button" class="btn btn-ghost btn-sm" @click="sendReset"><KeyRound :size="16" /> Send password reset link</button>
                <template v-if="player.id !== $page.props.auth.user.id">
                    <button v-if="player.role === 'admin'" type="button" class="btn btn-ghost btn-sm" @click="revoke"><ShieldOff :size="16" /> Revoke admin access</button>
                    <button v-else-if="!player.disabled" type="button" class="btn btn-ghost btn-sm" :disabled="adminSlotsLeft === 0" :title="adminSlotsLeft === 0 ? `All ${maxAdmins} admin slots are taken` : null" @click="promote">
                        <ShieldCheck :size="16" /> Make admin <span class="text-xs text-outline">({{ adminSlotsLeft }} of {{ maxAdmins }} slots left)</span>
                    </button>
                </template>
                <p v-for="key in ['reset', 'admin']" v-show="$page.props.errors[key]" :key="key" class="w-full text-sm font-semibold text-ruby-deep">{{ $page.props.errors[key] }}</p>
            </div>
        </section>

        <dl v-if="profile" class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div v-for="[label, value] in [
                ['Games', fmt(profile.games_played)], ['Total points', fmt(profile.total_points)],
                ['Daily streak', profile.current_daily_streak], ['Longest streak', profile.longest_daily_streak],
            ]" :key="label" class="rounded-3xl bg-white p-4 ring-1 ring-cream-high">
                <dt class="eyebrow">{{ label }}</dt>
                <dd class="font-display text-xl font-extrabold text-navy-deep">{{ value }}</dd>
            </div>
        </dl>
        <p v-else class="rounded-2xl bg-cream-low p-4 text-sm font-medium text-ink-soft">No ranked statistics yet.</p>

        <section v-if="profile" class="card overflow-x-auto p-2 sm:p-4">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead class="text-[12px] uppercase tracking-wider text-outline">
                    <tr><th class="px-2 py-2 font-bold">Game</th><th class="font-bold">Games</th><th class="font-bold">Best level</th><th class="font-bold">Best Timed 60s</th><th class="font-bold">Accuracy</th></tr>
                </thead>
                <tbody class="divide-y divide-cream-high font-medium text-ink-soft">
                    <tr v-for="g in perGame" :key="g.name">
                        <td class="px-2 py-2.5 font-bold text-navy-deep">{{ g.name }}</td>
                        <td>{{ g.games_played }}</td>
                        <td>{{ g.best_level || '—' }}</td>
                        <td>{{ g.best_timed_60 ? fmt(g.best_timed_60) : '—' }}</td>
                        <td>{{ g.games_played ? `${g.accuracy}%` : '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section v-if="achievements.length" class="card p-5">
            <h2 class="font-display text-lg font-bold text-navy-deep">Achievements</h2>
            <p class="mt-2 flex flex-wrap gap-2">
                <span v-for="a in achievements" :key="a.name" class="rounded-full bg-amber-mist px-3 py-1 text-xs font-bold text-amber-ink ring-1 ring-amber-soft">{{ a.name }} · {{ shortDate(a.unlocked_at) }}</span>
            </p>
        </section>

        <section class="card overflow-x-auto p-2 sm:p-4">
            <h2 class="px-2 pb-2 font-display text-lg font-bold text-navy-deep">Game history</h2>
            <table class="w-full min-w-[52rem] text-left text-sm">
                <thead class="text-[12px] uppercase tracking-wider text-outline">
                    <tr><th class="px-2 py-2 font-bold">Started</th><th class="font-bold">Game</th><th class="font-bold">Score</th><th class="font-bold">Level</th><th class="font-bold">Rounds</th><th class="font-bold">Accuracy</th><th class="font-bold">Avg/item</th><th class="font-bold">Length</th><th class="font-bold">Result</th><th /></tr>
                </thead>
                <tbody class="divide-y divide-cream-high font-medium text-ink-soft">
                    <tr v-for="s in sessions.data" :key="s.uuid">
                        <td class="px-2 py-2.5">{{ new Date(s.started_at).toLocaleString() }}</td>
                        <td>{{ s.game }} <span class="text-xs text-outline">{{ s.mode }}{{ s.is_ranked ? '' : ' · practice' }}</span></td>
                        <td class="font-display font-bold text-navy-deep">{{ fmt(s.score) }}</td>
                        <td>{{ s.level }}</td>
                        <td>{{ s.rounds }}</td>
                        <td>{{ s.accuracy }}%</td>
                        <td>{{ seconds(s.avg_unit_ms) }}</td>
                        <td>{{ duration(s.duration) }}</td>
                        <td>
                            <span v-if="s.is_flagged" class="rounded-full bg-ruby-soft px-2 py-0.5 text-xs font-bold text-ruby-deep" :title="s.flag_reason">Flagged</span>
                            <span v-else class="text-xs">{{ s.status }} · {{ END_REASONS[s.end_reason] ?? s.end_reason ?? '—' }}</span>
                        </td>
                        <td class="text-right">
                            <button v-if="!['active', 'voided'].includes(s.status)" type="button" class="rounded-full px-3 py-1 text-xs font-bold text-ruby-deep hover:bg-ruby-soft" @click="voidSession(s.uuid)">Void</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!sessions.data.length" class="py-6 text-center font-medium text-ink-soft">No games yet.</p>
            <div class="mt-3"><Pagination :current="sessions.current_page" :last="sessions.last_page" :href="(p) => `/admin/players/${player.id}?page=${p}`" /></div>
        </section>
    </div>
</template>
