<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Check, MapPin, X } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Pagination from '@/Components/Pagination.vue';
import { fmt, shortDate } from '@/game/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    requests: { type: Object, required: true },
    status: { type: String, required: true },
});

const notes = reactive({});
const TABS = [
    { k: 'pending', l: 'Waiting' },
    { k: 'approved', l: 'Approved' },
    { k: 'rejected', l: 'Declined' },
];

function approve(r) {
    const progress = r.user.games_played ? `${fmt(r.user.games_played)} games and ${fmt(r.user.total_points)} points` : 'their game progress';
    if (!confirm(`Move ${r.user.username} from ${r.from} to ${r.to}?\n\nThis permanently deletes ${progress}, plus their statistics, achievements and run history.`)) return;
    router.post(`/admin/district-requests/${r.id}/approve`, {}, { preserveScroll: true });
}
const reject = (r) => router.post(`/admin/district-requests/${r.id}/reject`, { note: notes[r.id] ?? '' }, { preserveScroll: true });
</script>

<template>
    <Head title="District requests" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-3xl font-extrabold text-navy-deep">District requests</h1>
                <p class="text-sm font-medium text-ink-soft">Players pick a district once. Approving a change moves them and resets all their game progress; they see a pop-up on their next visit.</p>
            </div>
            <div class="flex gap-1 rounded-full bg-cream-high p-1">
                <Link v-for="t in TABS" :key="t.k" :href="`/admin/district-requests${t.k === 'pending' ? '' : `?status=${t.k}`}`" class="rounded-full px-3 py-1.5 font-display text-[13px] font-bold" :class="status === t.k ? 'bg-white text-navy-deep shadow-sm' : 'text-ink-soft'">{{ t.l }}</Link>
            </div>
        </div>

        <p v-if="$page.props.errors.request" class="rounded-2xl bg-ruby-soft px-4 py-3 text-sm font-semibold text-ruby-deep">{{ $page.props.errors.request }}</p>

        <article v-for="r in requests.data" :key="r.id" class="card flex flex-wrap items-center gap-4 p-5">
            <Link :href="`/admin/players/${r.user.id}`" class="flex min-w-48 items-center gap-3">
                <Avatar :avatar="r.user.avatar" size="sm" />
                <span>
                    <span class="block font-display font-bold text-navy-deep">{{ r.user.username }}</span>
                    <span class="block text-xs text-outline">{{ fmt(r.user.games_played) }} games · {{ fmt(r.user.total_points) }} pts</span>
                </span>
            </Link>

            <div class="min-w-0 flex-1">
                <p class="flex flex-wrap items-center gap-2 font-display font-bold text-navy-deep">
                    <MapPin :size="15" class="text-amber-deep" /> {{ r.from }} <ArrowRight :size="15" class="text-outline" /> {{ r.to }}
                </p>
                <p class="text-sm font-medium text-ink-soft">
                    Requested {{ shortDate(r.requested_at) }}<template v-if="r.reason"> · “{{ r.reason }}”</template>
                </p>
                <p v-if="r.reviewed_at" class="text-xs font-medium text-outline">
                    {{ status === 'approved' ? 'Approved' : 'Declined' }} by {{ r.reviewer ?? 'a removed admin' }} on {{ shortDate(r.reviewed_at) }}<template v-if="r.review_note"> · “{{ r.review_note }}”</template>
                </p>
            </div>

            <div v-if="status === 'pending'" class="flex flex-wrap items-center gap-2">
                <input v-model="notes[r.id]" class="field !w-52 !py-2 text-sm" maxlength="255" placeholder="Note if declining (optional)" :aria-label="`Note for ${r.user.username}`" />
                <button type="button" class="btn btn-ghost btn-sm" @click="reject(r)"><X :size="16" /> Decline</button>
                <button type="button" class="btn btn-danger btn-sm" @click="approve(r)"><Check :size="16" /> Approve &amp; reset</button>
            </div>
        </article>

        <p v-if="!requests.data.length" class="card py-10 text-center font-medium text-ink-soft">
            {{ status === 'pending' ? 'No requests waiting.' : 'Nothing here yet.' }}
        </p>

        <Pagination :current="requests.current_page" :last="requests.last_page" :href="(page) => `/admin/district-requests?status=${status}&page=${page}`" />
    </div>
</template>
