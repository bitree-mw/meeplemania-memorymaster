<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Pagination from '@/Components/Pagination.vue';
import { fmt } from '@/game/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    players: { type: Object, required: true },
    search: { type: String, default: '' },
    filter: { type: String, default: null },
});

const query = ref(props.search);
const url = (params) => {
    const p = new URLSearchParams({ search: query.value, filter: props.filter ?? '', ...params });
    [...p.keys()].forEach((k) => !p.get(k) && p.delete(k));
    return `/admin/players${p.size ? `?${p}` : ''}`;
};

let t;
watch(query, () => {
    clearTimeout(t);
    t = setTimeout(() => router.get(url({ page: '' }), {}, { preserveState: true, replace: true }), 300);
});
</script>

<template>
    <Head title="Players" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-3xl font-extrabold text-navy-deep">Players <span class="text-lg text-outline">{{ fmt(players.total) }}</span></h1>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex gap-1 rounded-full bg-cream-high p-1">
                    <Link v-for="f in [{ k: null, l: 'All' }, { k: 'admins', l: 'Admins' }, { k: 'disabled', l: 'Disabled' }]" :key="f.l" :href="url({ filter: f.k ?? '', page: '' })" class="rounded-full px-3 py-1.5 font-display text-[13px] font-bold" :class="filter === f.k ? 'bg-white text-navy-deep shadow-sm' : 'text-ink-soft'">{{ f.l }}</Link>
                </div>
                <label class="relative w-64">
                    <span class="sr-only">Search players</span>
                    <Search :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-outline" />
                    <input v-model="query" type="search" placeholder="Username or email" class="field !rounded-full !py-2 !pl-10" />
                </label>
            </div>
        </div>

        <div class="card overflow-x-auto p-2 sm:p-4">
            <table class="w-full min-w-[44rem] text-left text-sm">
                <thead class="text-[12px] uppercase tracking-wider text-outline">
                    <tr><th class="px-3 py-2 font-bold">Player</th><th class="font-bold">Points</th><th class="font-bold">Games</th><th class="font-bold">Joined</th><th class="font-bold">Status</th></tr>
                </thead>
                <tbody class="divide-y divide-cream-high font-medium text-ink-soft">
                    <tr v-for="p in players.data" :key="p.id" class="hover:bg-cream-low">
                        <td class="px-3 py-2.5">
                            <Link :href="`/admin/players/${p.id}`" class="flex items-center gap-3">
                                <Avatar :avatar="p.avatar" size="sm" />
                                <span>
                                    <span class="block font-display font-bold text-navy-deep">{{ p.username }} <span v-if="p.role === 'admin'" class="ml-1 rounded-full bg-navy px-1.5 text-[10px] text-white">ADMIN</span></span>
                                    <span class="block text-xs text-outline">{{ p.email }}</span>
                                </span>
                            </Link>
                        </td>
                        <td class="font-display font-bold text-navy-deep">{{ fmt(p.total_points) }}</td>
                        <td>{{ fmt(p.games_played) }}</td>
                        <td>{{ p.joined }}</td>
                        <td>
                            <span v-if="p.disabled" class="rounded-full bg-ruby-soft px-2 py-0.5 text-xs font-bold text-ruby-deep">Disabled</span>
                            <span v-else class="rounded-full bg-emerald-soft px-2 py-0.5 text-xs font-bold text-emerald-deep">Active</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!players.data.length" class="py-8 text-center font-medium text-ink-soft">No players found.</p>
        </div>

        <Pagination :current="players.current_page" :last="players.last_page" :href="(page) => url({ page })" />
    </div>
</template>
