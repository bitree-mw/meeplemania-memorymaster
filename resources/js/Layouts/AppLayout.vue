<script setup>
import { computed, onMounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Flame, Trophy, Volume2, VolumeX } from 'lucide-vue-next';
import BrandMark from '@/Components/BrandMark.vue';
import BottomDock from '@/Components/BottomDock.vue';
import DistrictNotice from '@/Components/DistrictNotice.vue';
import FlashToast from '@/Components/FlashToast.vue';
import UserMenu from '@/Components/UserMenu.vue';
import { usePresence } from '@/composables/usePresence';
import { useSound } from '@/composables/useSound';
import { compact, points } from '@/game/format';

const page = usePage();
const user = computed(() => page.props.auth.user);
const player = computed(() => page.props.player);
// The arena is immersive: no bottom dock so the tile grid fits on a phone screen.
const immersive = computed(() => page.component === 'Arena');

const { enabled: soundOn, toggle: toggleSound } = useSound();
const { counts, start } = usePresence();

onMounted(() => start(page.props.presence?.heartbeat_seconds, page.props.online));

const nav = computed(() => [
    { label: 'Arena', href: '/', active: ['Home', 'Arena'].includes(page.component) },
    // Members only: guests are sent to sign in, so don't prefetch it for them.
    { label: 'Hall of Fame', href: '/leaderboard', active: page.component === 'Leaderboard', prefetch: Boolean(user.value) },
    { label: 'Rulebook', href: '/rules', active: page.component === 'Rules' },
]);
</script>

<template>
    <div class="min-h-dvh">
        <FlashToast />
        <DistrictNotice />

        <header class="sticky top-0 z-30 px-3 pt-3 sm:px-4">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-2 rounded-full bg-white/95 py-2 pl-2 pr-2 sm:gap-3 shadow-card ring-1 ring-cream-high backdrop-blur-md sm:pl-3 sm:pr-3">
                <BrandMark />

                <nav class="hidden items-center gap-1 rounded-full bg-cream-high p-1 lg:flex" aria-label="Sections">
                    <Link
                        v-for="item in nav"
                        :key="item.label"
                        :href="item.href"
                        :prefetch="item.prefetch ?? true"
                        class="rounded-full px-4 py-1.5 font-display text-[13px] font-bold tracking-wide transition-all"
                        :class="item.active ? 'bg-white text-navy-deep shadow-sm' : 'text-ink-soft hover:text-ink'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                    <span
                        v-if="counts"
                        class="hidden items-center gap-1.5 rounded-full bg-emerald-soft px-2.5 py-1 text-[12px] font-bold text-emerald-deep md:inline-flex"
                        :title="`${counts.players} players and ${counts.guests} guests online, ${counts.playing} playing now`"
                    >
                        <span class="size-2 rounded-full bg-emerald motion-safe:animate-pulse" aria-hidden="true" />
                        {{ compact(counts.online) }} online
                    </span>

                    <template v-if="user && player">
                        <span class="chip px-2.5 bg-amber-mist text-amber-ink ring-1 ring-amber-soft sm:px-3" :title="`${player.daily_streak}-day play streak`">
                            <Flame :size="16" class="text-orange" aria-hidden="true" />
                            {{ player.daily_streak }}<span class="hidden sm:inline">&nbsp;day streak</span>
                            <span class="sr-only"> day streak</span>
                        </span>
                        <span class="chip hidden bg-cream-low text-ink ring-1 ring-cream-high sm:inline-flex" title="Total points">
                            <Trophy :size="15" class="text-amber" aria-hidden="true" />
                            {{ points(player.total_points) }}
                        </span>
                    </template>

                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-full bg-cream-low text-ink-soft transition-colors hover:bg-cream-high hover:text-ink sm:size-10"
                        :aria-pressed="soundOn"
                        :aria-label="soundOn ? 'Mute sound effects' : 'Turn sound effects on'"
                        @click="toggleSound"
                    >
                        <component :is="soundOn ? Volume2 : VolumeX" :size="18" />
                    </button>

                    <UserMenu v-if="user" :user="user" />
                    <Link v-else href="/login" class="btn btn-primary btn-sm">Sign in</Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4" :class="immersive ? 'pb-0' : 'pb-32'">
            <slot />
        </main>

        <BottomDock v-if="!immersive" />
    </div>
</template>
