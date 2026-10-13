<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Movement from '@/Components/Movement.vue';
import Pagination from '@/Components/Pagination.vue';
import { describeRow } from '@/game/leaderboard';
import { fmt } from '@/game/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    games: { type: Array, required: true },
    game: { type: String, required: true },
    board: { type: String, required: true },
    duration: { type: Number, default: null },
    durations: { type: Array, required: true },
    categories: { type: Array, required: true },
    label: { type: String, required: true },
    rule: { type: String, required: true },
    search: { type: String, default: null },
    rows: { type: Array, required: true },
    meta: { type: Object, required: true },
});

const query = ref(props.search ?? '');
const unit = computed(() => props.games.find((g) => g.slug === props.game)?.unit ?? 'item');

function url(params = {}) {
    const merged = { game: props.game, board: props.board, duration: props.board === 'timed' ? props.duration : null, search: query.value.trim(), ...params };
    const p = new URLSearchParams();
    Object.entries(merged).forEach(([k, v]) => v !== null && v !== '' && v !== undefined && p.set(k, v));
    return `/admin/leaderboards?${p}`;
}

let t;
watch(query, () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(url({ page: 1 }), {}, { preserveState: true, replace: true }), 300);
});
</script>

<template>
    <Head title="Leaderboard review" />

    <div class="space-y-5">
        <div>
            <h1 class="text-3xl font-extrabold text-navy-deep">Leaderboard review</h1>
            <p class="text-sm font-medium text-ink-soft">Open a player to inspect their runs and void any result that shouldn't count.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link :href="url({ game: 'all', board: 'all_time', duration: null, page: 1 })" class="rounded-full px-3.5 py-1.5 font-display text-[13px] font-bold" :class="game === 'all' ? 'bg-navy text-white' : 'bg-white text-ink-soft ring-1 ring-cream-high'">All games</Link>
            <Link v-for="g in games" :key="g.slug" :href="url({ game: g.slug, board: 'level', duration: null, page: 1 })" class="rounded-full px-3.5 py-1.5 font-display text-[13px] font-bold" :class="game === g.slug ? 'bg-navy text-white' : 'bg-white text-ink-soft ring-1 ring-cream-high'">{{ g.name }}</Link>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex flex-wrap gap-1 rounded-full bg-cream-high p-1">
                <Link v-for="c in categories" :key="c.key" :href="url({ board: c.key, duration: c.key === 'timed' ? duration ?? durations[0] : null, page: 1 })" class="whitespace-nowrap rounded-full px-3.5 py-1.5 font-display text-[13px] font-bold" :class="c.key === board ? 'bg-white text-navy-deep shadow-sm' : 'text-ink-soft'">{{ c.label }}</Link>
            </div>
            <div v-if="board === 'timed'" class="flex gap-1">
                <Link v-for="d in durations" :key="d" :href="url({ duration: d, page: 1 })" class="rounded-xl px-3 py-1.5 font-display text-sm font-extrabold" :class="d === duration ? 'bg-navy text-white' : 'bg-white text-ink-soft ring-1 ring-cream-high'">{{ d }}s</Link>
            </div>
            <input v-model="query" type="search" placeholder="Search username" class="field !w-56 !rounded-full !py-2" aria-label="Search username" />
        </div>
        <p class="text-xs font-medium text-outline">{{ rule }}</p>

        <div class="card overflow-x-auto p-2 sm:p-4">
            <table class="w-full min-w-[36rem] text-left text-sm">
                <thead class="text-[12px] uppercase tracking-wider text-outline"><tr><th class="px-2 py-2 font-bold">Rank</th><th class="font-bold">Player</th><th class="font-bold">Value</th><th class="font-bold">Detail</th><th class="font-bold">Move</th></tr></thead>
                <tbody class="divide-y divide-cream-high font-medium text-ink-soft">
                    <tr v-for="r in rows" :key="r.user_id">
                        <td class="px-2 py-2.5 font-display font-bold">#{{ fmt(r.position) }}</td>
                        <td><Link :href="`/admin/players/${r.user_id}`" class="flex items-center gap-2 font-bold text-navy-deep hover:underline"><Avatar :avatar="r.avatar" size="sm" /> {{ r.username }}</Link></td>
                        <td class="font-display font-bold text-navy-deep">{{ describeRow(board, r, unit).primary }}</td>
                        <td class="text-xs">{{ describeRow(board, r, unit).secondary }}</td>
                        <td><Movement :value="r.movement" /></td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!rows.length" class="py-8 text-center font-medium text-ink-soft">No ranked players on this board yet.</p>
        </div>
        <Pagination :current="meta.current_page" :last="meta.last_page" :href="(page) => url({ page })" />
    </div>
</template>
