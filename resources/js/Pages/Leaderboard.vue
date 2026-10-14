<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Crown, Gamepad2, Globe2, Info, Map as MapIcon, MapPin, Search, Trophy, Zap } from 'lucide-vue-next';
import Avatar from '@/Components/Avatar.vue';
import DistrictSelect from '@/Components/DistrictSelect.vue';
import Movement from '@/Components/Movement.vue';
import Pagination from '@/Components/Pagination.vue';
import { describeRow } from '@/game/leaderboard';
import { fmt } from '@/game/format';
import { ICONS, colorVars } from '@/game/palette';

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
    podium: { type: Array, required: true },
    me: { type: Object, default: null },
    // Two places either side of the viewer, when they are below the podium.
    around: { type: Array, default: () => [] },
    totalRanked: { type: Number, default: 0 },
    season: { type: Object, default: null },
    district: { type: String, default: null },
    districts: { type: Array, required: true },
    region: { type: String, default: null },
    regions: { type: Array, required: true },
});

const current = computed(() => props.games.find((g) => g.slug === props.game) ?? null);
const unit = computed(() => current.value?.unit ?? 'item');
const query = ref(props.search ?? '');

function url(params = {}) {
    const merged = { game: props.game, board: props.board, duration: props.board === 'timed' ? props.duration : null, district: props.district, region: props.region, search: query.value.trim(), ...params };
    const p = new URLSearchParams();
    Object.entries(merged).forEach(([k, v]) => v !== null && v !== '' && v !== undefined && !(k === 'page' && v === 1) && p.set(k, v));
    return `/leaderboard?${p}`;
}

let debounce;
watch(query, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => router.get(url({ page: 1 }), {}, { preserveState: true, preserveScroll: true, replace: true }), 300);
});

const podiumSlots = computed(() => [props.podium[1], props.podium[0], props.podium[2]].map((row, i) => ({ row, place: [2, 1, 3][i] })));
const showPodium = computed(() => !props.search && props.meta.current_page === 1 && props.podium.length > 0);
const tableRows = computed(() => (showPodium.value ? props.rows.filter((r) => r.position > 3) : props.rows));
const topPercent = computed(() => (props.me && props.totalRanked ? Math.max(1, Math.ceil((props.me.position / props.totalRanked) * 100)) : null));
const playHref = computed(() => {
    if (!current.value) return '/';
    if (props.board === 'timed') return `/play/${props.game}/timed?duration=${props.duration}`;
    return `/play/${props.game}/${props.board === 'once' ? 'once' : 'classic'}`;
});
const describe = (row) => describeRow(props.board, row, unit.value);

// Location filter: country-wide, the player's region or district, or any other district.
const districtNames = computed(() => Object.fromEntries(props.districts.map((d) => [d.key, d.name])));
const regionNames = computed(() => Object.fromEntries(props.regions.map((r) => [r.key, r.name])));
const myDistrict = computed(() => usePage().props.auth.user?.district ?? null);
const myRegion = computed(() => props.districts.find((d) => d.key === myDistrict.value)?.region ?? null);
const scope = computed(() => (props.district ? 'district' : props.region ? 'region' : 'country'));
const otherDistrict = ref(props.district && props.district !== myDistrict.value ? props.district : null);
watch(() => props.district, (d) => (otherDistrict.value = d && d !== myDistrict.value ? d : null));
const show = (params) => router.get(url({ district: null, region: null, page: 1, ...params }), {}, { preserveScroll: true });
watch(otherDistrict, (d) => d && d !== props.district && show({ district: d }));
const pill = (active) => (active ? 'bg-navy text-white shadow-[0_3px_0_#050b12]' : 'bg-white text-ink-soft ring-1 ring-cream-high hover:text-ink');

const areas = computed(() => [
    { key: 'country', icon: Globe2, label: 'Country', detail: 'All of Malawi', params: {}, active: scope.value === 'country' },
    myRegion.value && { key: 'region', icon: MapIcon, label: 'My region', detail: regionNames.value[myRegion.value]?.replace(' Region', ''), params: { region: myRegion.value }, active: props.region === myRegion.value },
    myDistrict.value && { key: 'district', icon: MapPin, label: 'My district', detail: districtNames.value[myDistrict.value], params: { district: myDistrict.value }, active: props.district === myDistrict.value },
].filter(Boolean));

// Bring the selected game's pill into view in the scrolling strip.
const gameStrip = ref(null);
onMounted(() => gameStrip.value?.querySelector('[aria-current="page"]')?.scrollIntoView({ block: 'nearest', inline: 'center' }));
</script>

<template>
    <Head title="Leaderboards" />

    <div class="mx-auto max-w-3xl space-y-5 pt-6">
        <div class="flex justify-center">
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-mist px-4 py-1.5 text-[14px] font-semibold text-amber-ink ring-1 ring-amber-soft">
                <span class="size-2 rounded-full bg-amber" aria-hidden="true" /> Hall of Fame<template v-if="season"> · {{ season.name }}</template>
            </span>
        </div>

        <!-- Game selector: one row of pills that scrolls sideways inside its own strip (the page never does). -->
        <nav ref="gameStrip" class="-mx-4 snap-x overflow-x-auto overscroll-x-contain px-4 py-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Game">
            <div class="mx-auto flex w-max gap-2">
                <Link
                    :href="url({ game: 'all', board: null, duration: null, search: '', page: 1 })"
                    preserve-scroll
                    class="flex shrink-0 snap-start items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 py-2 font-display text-[13px] font-bold transition-all sm:gap-2 sm:px-4"
                    :class="game === 'all' ? 'bg-navy text-white shadow-[0_3px_0_#050b12]' : 'bg-white text-ink-soft ring-1 ring-cream-high hover:text-ink'"
                    :aria-current="game === 'all' ? 'page' : undefined"
                >
                    <Gamepad2 :size="16" /> All games
                </Link>
                <Link
                    v-for="g in games"
                    :key="g.slug"
                    :href="url({ game: g.slug, board: null, duration: null, search: '', page: 1 })"
                    preserve-scroll
                    class="flex shrink-0 snap-start items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 py-2 font-display text-[13px] font-bold transition-all sm:gap-2 sm:px-4"
                    :class="game === g.slug ? 'bg-[var(--tile)] text-white shadow-[0_3px_0_var(--tile-deep)]' : 'bg-white text-ink-soft ring-1 ring-cream-high hover:text-ink'"
                    :style="colorVars(g.color)"
                    :aria-current="game === g.slug ? 'page' : undefined"
                >
                    <component :is="ICONS[g.icon]" :size="16" /> {{ g.name }}
                </Link>
            </div>
        </nav>

        <!-- Board tabs -->
        <nav class="flex justify-center py-2" aria-label="Leaderboard">
            <div class="flex flex-wrap justify-center gap-1 rounded-3xl bg-cream-high p-1">
                <Link
                    v-for="c in categories"
                    :key="c.key"
                    :href="url({ board: c.key, duration: c.key === 'timed' ? duration ?? durations[0] : null, search: '', page: 1 })"
                    preserve-scroll
                    class="whitespace-nowrap rounded-full px-3 py-1.5 font-display text-[12px] font-bold transition-all sm:px-4 sm:py-2 sm:text-[13px]"
                    :class="c.key === board ? 'bg-white text-navy-deep shadow-sm' : 'text-ink-soft hover:text-ink'"
                    :aria-current="c.key === board ? 'page' : undefined"
                >
                    {{ c.label }}
                </Link>
            </div>
        </nav>

        <div v-if="board === 'timed'" class="flex justify-center gap-2" role="group" aria-label="Clock length">
            <Link
                v-for="d in durations"
                :key="d"
                :href="url({ duration: d, page: 1 })"
                preserve-scroll
                class="rounded-xl px-4 py-1.5 font-display text-sm font-extrabold"
                :class="d === duration ? 'bg-navy text-white' : 'bg-white text-ink-soft ring-1 ring-cream-high'"
                :aria-current="d === duration ? 'page' : undefined"
            >
                {{ d }}s
            </Link>
        </div>

        <!-- Location filter -->
        <div class="mx-auto w-full max-w-md space-y-2 py-1">
            <!-- Three equal squares on one row: the whole country, my region, my district. -->
            <div class="grid gap-2" :class="areas.length === 3 ? 'grid-cols-3' : areas.length === 2 ? 'grid-cols-2' : 'grid-cols-1'" role="group" aria-label="Standings">
                <button
                    v-for="a in areas"
                    :key="a.key"
                    type="button"
                    class="flex aspect-square min-w-0 flex-col items-center justify-center gap-1.5 rounded-3xl p-2 text-center font-display transition-all sm:aspect-auto sm:py-3"
                    :class="pill(a.active)"
                    :aria-pressed="a.active"
                    @click="show(a.params)"
                >
                    <component :is="a.icon" :size="22" :stroke-width="2.3" aria-hidden="true" />
                    <span class="text-[13px] font-bold leading-tight">{{ a.label }}</span>
                    <span class="w-full truncate text-[11px] font-semibold leading-tight opacity-70">{{ a.detail }}</span>
                </button>
            </div>
            <label class="block">
                <span class="sr-only">See another district</span>
                <DistrictSelect v-model="otherDistrict" :districts="districts" :exclude="myDistrict ? [myDistrict] : []" placeholder="Other districts…" class="!rounded-2xl !py-2.5 text-[13px] font-bold" :class="otherDistrict ? '!border-navy' : ''" />
            </label>
        </div>

        <p class="flex items-start justify-center gap-1.5 text-center text-[13px] font-medium text-outline">
            <Info :size="14" class="mt-0.5 shrink-0" aria-hidden="true" /> {{ rule }}
        </p>

        <!-- Podium -->
        <section v-if="showPodium" class="grid grid-cols-3 items-end gap-2 sm:gap-4" aria-label="Top three">
            <div v-for="{ row, place } in podiumSlots" :key="place" :class="place === 1 ? '' : 'pt-6'">
                <Link
                    v-if="row"
                    :href="`/players/${row.username}`"
                    class="relative flex flex-col items-center rounded-[1.75rem] bg-white px-2 pb-4 pt-7 text-center transition-transform hover:-translate-y-0.5 sm:px-4"
                    :class="place === 1 ? 'shadow-card ring-2 ring-amber-dim' : 'ring-1 ring-cream-high'"
                >
                    <span
                        class="absolute -top-4 grid size-8 place-items-center rounded-full font-display text-sm font-extrabold ring-4 ring-cream"
                        :class="{ 'bg-amber text-white': place === 1, 'bg-cream-highest text-ink-soft': place === 2, 'bg-amber-soft text-amber-ink': place === 3 }"
                    >
                        <Crown v-if="place === 1" :size="16" fill="currentColor" aria-label="First place" />
                        <template v-else>{{ place }}</template>
                    </span>
                    <Avatar :avatar="row.avatar" :size="place === 1 ? 'lg' : 'md'" />
                    <span class="mt-2 w-full truncate font-display text-[15px] font-bold text-navy-deep sm:text-[17px]">{{ row.username }}</span>
                    <span class="font-display text-sm font-bold" :class="place === 1 ? 'text-amber-deep' : 'text-ink-soft'">{{ describe(row).primary }}</span>
                    <span class="mt-2 hidden rounded-full px-2.5 py-0.5 text-[11px] font-bold sm:inline-block" :class="place === 1 ? 'bg-amber-soft text-amber-ink' : 'bg-cream-mid text-ink-soft'">{{ describe(row).secondary }}</span>
                </Link>
                <div v-else class="flex h-36 flex-col items-center justify-center rounded-[1.75rem] border-2 border-dashed border-cream-highest text-center text-xs font-semibold text-outline">
                    #{{ place }}<br />Up for grabs
                </div>
            </div>
        </section>

        <!-- Rankings -->
        <section class="card p-4 sm:p-6" :aria-label="label">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h1 class="font-display text-lg font-bold text-navy-deep">{{ current ? `${current.name} · ` : '' }}{{ label }}{{ scope === 'country' ? ' · Malawi' : '' }}</h1>
                <label class="relative w-full sm:w-60">
                    <span class="sr-only">Search players</span>
                    <Search :size="16" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-outline" aria-hidden="true" />
                    <input v-model="query" type="search" placeholder="Search players" class="field !rounded-full !py-2 !pl-10" maxlength="24" />
                </label>
            </div>

            <ol v-if="tableRows.length" class="divide-y divide-cream-high">
                <li v-for="row in tableRows" :key="row.user_id">
                    <Link :href="`/players/${row.username}`" class="flex items-center gap-3 rounded-2xl px-2 py-3 transition-colors hover:bg-cream-low" :class="{ 'bg-amber-mist': me && row.user_id === me.user_id }">
                        <span class="w-11 shrink-0 font-display text-sm font-bold text-outline">#{{ fmt(row.position) }}</span>
                        <Avatar :avatar="row.avatar" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-display font-bold text-navy-deep">
                                {{ row.username }}
                                <span v-if="!district && row.district" class="ml-1 text-xs font-semibold text-outline"><MapPin :size="11" class="inline -mt-0.5" aria-hidden="true" /> {{ districtNames[row.district] }}</span>
                            </span>
                            <span class="block truncate text-xs font-medium text-outline">{{ describe(row).secondary }}</span>
                        </span>
                        <span class="shrink-0 text-right">
                            <span class="block font-display font-bold text-navy-deep">{{ describe(row).primary }}</span>
                            <Movement :value="row.movement" />
                        </span>
                    </Link>
                </li>
            </ol>
            <div v-else-if="!showPodium" class="py-10 text-center">
                <Trophy :size="36" class="mx-auto text-amber-dim" aria-hidden="true" />
                <p class="mt-3 font-display text-lg font-bold text-navy-deep">{{ search ? 'No players match that search' : 'No rankings yet' }}</p>
                <p class="mt-1 text-sm font-medium text-ink-soft">{{ search ? 'Try a different username.' : 'Ranked games will appear here as soon as they are played.' }}</p>
            </div>

            <div class="mt-4">
                <Pagination :current="meta.current_page" :last="meta.last_page" :href="(p) => url({ page: p })" />
            </div>

            <div v-if="me && around.length" class="mt-4 rounded-[1.5rem] bg-cream-low p-2 ring-1 ring-cream-high">
                <p class="flex items-center justify-between px-2 pb-1 pt-1.5 font-display text-xs font-extrabold tracking-wider text-ink-soft">
                    WHERE YOU STAND
                    <span v-if="topPercent" class="text-navy-deep">Top {{ topPercent }}%</span>
                </p>
                <ol>
                    <li v-for="row in around" :key="row.user_id">
                        <Link
                            :href="`/players/${row.username}`"
                            class="flex items-center gap-3 rounded-2xl px-2 py-2.5 transition-colors"
                            :class="row.user_id === me.user_id ? 'bg-amber-mist ring-2 ring-amber-soft' : 'hover:bg-white'"
                            :aria-current="row.user_id === me.user_id ? 'true' : undefined"
                        >
                            <span class="w-11 shrink-0 font-display text-sm font-bold" :class="row.user_id === me.user_id ? 'text-amber-deep' : 'text-outline'">#{{ fmt(row.position) }}</span>
                            <Avatar :avatar="row.avatar" size="sm" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-display font-bold text-navy-deep">{{ row.user_id === me.user_id ? 'You' : row.username }}</span>
                                <span class="block truncate text-xs font-medium text-outline">{{ describe(row).secondary }}</span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block font-display font-bold text-navy-deep">{{ describe(row).primary }}</span>
                                <Movement :value="row.movement" />
                            </span>
                        </Link>
                    </li>
                </ol>
            </div>
            <div v-else-if="me" class="mt-4 flex items-center gap-3 rounded-[1.5rem] bg-amber-mist px-4 py-3 ring-2 ring-amber-soft">
                <span class="font-display text-sm font-extrabold text-amber-deep">#{{ fmt(me.position) }}</span>
                <Avatar :avatar="me.avatar" size="sm" />
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2 font-display font-bold text-navy-deep">
                        You <span class="rounded-full bg-amber-soft px-1.5 py-0.5 text-[10px] font-extrabold tracking-wider text-amber-ink">YOUR POSITION</span>
                    </span>
                    <span class="text-xs font-semibold text-amber-ink">{{ describe(me).primary }} · {{ describe(me).secondary }}</span>
                </span>
                <span class="hidden text-right sm:block">
                    <span v-if="topPercent" class="block font-display text-sm font-bold text-navy-deep">Top {{ topPercent }}%</span>
                    <Movement :value="me.movement" />
                </span>
            </div>
            <p v-else-if="$page.props.auth.user" class="mt-4 rounded-2xl bg-cream-low px-4 py-3 text-center text-sm font-medium text-ink-soft">
                <template v-if="district && district !== myDistrict">You're not from {{ districtNames[district] }}, so you don't appear on this board.</template>
                <template v-else-if="region && region !== myRegion">You're not in the {{ regionNames[region] }}, so you don't appear on this board.</template>
                <template v-else>You're not on this board yet — play a ranked game to enter.</template>
            </p>
        </section>

        <Link :href="playHref" class="btn btn-primary btn-lg w-full" prefetch>
            <Zap :size="20" fill="currentColor" aria-hidden="true" /> {{ current ? `Play ${current.name}` : 'Play & climb' }}
        </Link>
    </div>
</template>
