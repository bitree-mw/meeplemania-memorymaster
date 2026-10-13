<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleCheck, ShieldAlert, Trash2 } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { fmt, relativeTime } from '@/game/format';

defineOptions({ layout: AdminLayout });

defineProps({
    sessions: { type: Object, required: true },
    showReviewed: { type: Boolean, default: false },
});

const reasons = {
    impossible_tap_rate: 'Taps closer together than humanly possible',
    impossible_reaction_time: 'First tap faster than human reaction time',
    non_monotonic_timings: 'Tap timings out of order (tampered payload)',
    clock_mismatch: 'Claimed more play time than the server clock allows',
    clock_overrun: "Claimed more rounds than fit in the Timed Rush clock",
    late_sync: 'Timed Rush results arrived long after the clock ended (possibly offline)',
};

const approve = (uuid) => confirm('Approve this result and count it toward rankings?') && router.post(`/admin/sessions/${uuid}/approve`, {}, { preserveScroll: true });
const voidIt = (uuid) => confirm('Void this result permanently?') && router.post(`/admin/sessions/${uuid}/void`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Reviews" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-3xl font-extrabold text-navy-deep">Suspicious results</h1>
                <p class="text-sm font-medium text-ink-soft">Runs the server could not verify are held out of rankings until reviewed.</p>
            </div>
            <div class="flex gap-1 rounded-full bg-cream-high p-1">
                <Link href="/admin/reviews" class="rounded-full px-3 py-1.5 font-display text-[13px] font-bold" :class="!showReviewed ? 'bg-white text-navy-deep shadow-sm' : 'text-ink-soft'">Pending</Link>
                <Link href="/admin/reviews?reviewed=1" class="rounded-full px-3 py-1.5 font-display text-[13px] font-bold" :class="showReviewed ? 'bg-white text-navy-deep shadow-sm' : 'text-ink-soft'">Reviewed</Link>
            </div>
        </div>

        <article v-for="s in sessions.data" :key="s.uuid" class="card p-5">
            <div class="flex flex-wrap items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-ruby-soft text-ruby-deep"><ShieldAlert :size="20" /></span>
                <div class="min-w-0 flex-1">
                    <p class="font-display text-lg font-bold text-navy-deep">
                        <Link v-if="s.player_id" :href="`/admin/players/${s.player_id}`" class="hover:underline">{{ s.player }}</Link>
                        · {{ s.game }} {{ s.mode }} · {{ fmt(s.score) }} pts · level {{ s.level }}
                    </p>
                    <p v-if="s.wall_ms" class="text-xs font-medium text-outline">Claimed play time {{ Math.round(s.claimed_ms / 1000) }}s of {{ Math.round(s.wall_ms / 1000) }}s on the server clock</p>
                    <p class="text-sm font-semibold text-ruby-deep">{{ reasons[s.flag_reason] ?? s.flag_reason ?? (s.status === 'voided' ? 'Voided by an admin' : '') }}</p>
                    <p class="text-xs font-medium text-outline">Ended {{ relativeTime(s.ended_at) }}<template v-if="s.reviewed_at"> · reviewed {{ relativeTime(s.reviewed_at) }} · {{ s.status }}</template></p>
                </div>
                <div v-if="!s.reviewed_at" class="flex gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" @click="approve(s.uuid)"><CircleCheck :size="16" /> Approve</button>
                    <button type="button" class="btn btn-danger btn-sm" @click="voidIt(s.uuid)"><Trash2 :size="16" /> Void</button>
                </div>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-bold text-ink-soft">Round evidence ({{ s.rounds.length }})</summary>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full min-w-[36rem] text-left text-xs">
                        <thead class="uppercase tracking-wider text-outline"><tr><th class="py-1">#</th><th>Round</th><th>Status</th><th>Size</th><th>Preview ms</th><th>Tap times (ms from unlock)</th><th>Points</th></tr></thead>
                        <tbody class="divide-y divide-cream-high font-mono text-ink-soft">
                            <tr v-for="r in s.rounds" :key="r.number" :class="r.status === 'invalid' ? 'bg-ruby-soft/50' : ''">
                                <td class="py-1">{{ r.number }}</td><td>{{ r.key }}</td><td>{{ r.status }}</td><td>{{ r.units }}</td><td>{{ r.preview_ms }}</td><td>{{ (r.timings ?? []).join(', ') }}</td><td>{{ r.points }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </details>
        </article>

        <p v-if="!sessions.data.length" class="card p-10 text-center font-medium text-ink-soft">{{ showReviewed ? 'No reviewed results yet.' : 'Nothing to review. All recent results passed verification.' }}</p>

        <Pagination :current="sessions.current_page" :last="sessions.last_page" :href="(p) => `/admin/reviews?page=${p}${showReviewed ? '&reviewed=1' : ''}`" />
    </div>
</template>
