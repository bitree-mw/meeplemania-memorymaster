<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Award, CalendarCheck, CalendarRange, Flag, Gamepad2, Gauge, MapPin, Settings, ShieldCheck, Trophy, Users } from 'lucide-vue-next';
import BrandMark from '@/Components/BrandMark.vue';
import DistrictNotice from '@/Components/DistrictNotice.vue';
import FlashToast from '@/Components/FlashToast.vue';
import UserMenu from '@/Components/UserMenu.vue';

const page = usePage();

const sections = [
    { label: 'Overview', href: '/admin', icon: Gauge, component: 'Admin/Dashboard' },
    { label: 'Players', href: '/admin/players', icon: Users, component: 'Admin/Player' },
    { label: 'Reviews', href: '/admin/reviews', icon: Flag, component: 'Admin/Reviews' },
    { label: 'Settings', href: '/admin/settings', icon: Settings, component: 'Admin/Settings' },
    { label: 'Try Once', href: '/admin/challenges', icon: CalendarCheck, component: 'Admin/Challenges' },
    { label: 'Seasons', href: '/admin/seasons', icon: CalendarRange, component: 'Admin/Seasons' },
    { label: 'Achievements', href: '/admin/achievements', icon: Award, component: 'Admin/Achievements' },
    { label: 'Leaderboards', href: '/admin/leaderboards', icon: Trophy, component: 'Admin/Leaderboards' },
    { label: 'Districts', href: '/admin/district-requests', icon: MapPin, component: 'Admin/DistrictRequests', badge: 'pendingDistrictRequests' },
    { label: 'Admins', href: '/admin/admins', icon: ShieldCheck, component: 'Admin/Admins' },
];

const active = computed(() => sections.find((s) => page.component.startsWith(s.component))?.label);
</script>

<template>
    <div class="min-h-dvh pb-16">
        <FlashToast />
        <DistrictNotice />
        <header class="sticky top-0 z-30 px-3 pt-3 sm:px-4">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 rounded-full bg-white/95 py-2 pl-2.5 pr-2.5 shadow-card ring-1 ring-cream-high backdrop-blur-md">
                <BrandMark subtitle="Admin dashboard" />
                <div class="flex items-center gap-2">
                    <Link href="/" class="btn btn-ghost btn-sm"><Gamepad2 :size="16" /> <span class="hidden sm:inline">Back to game</span></Link>
                    <UserMenu :user="page.props.auth.user" />
                </div>
            </div>
        </header>

        <nav class="mx-auto mt-3 max-w-6xl overflow-x-auto px-4 py-2 [scrollbar-width:none]" aria-label="Admin sections">
            <div class="flex w-max gap-1 rounded-full bg-cream-high p-1">
                <Link
                    v-for="s in sections"
                    :key="s.label"
                    :href="s.href"
                    class="flex items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 py-2 font-display text-[13px] font-bold transition-all"
                    :class="active === s.label ? 'bg-white text-navy-deep shadow-sm' : 'text-ink-soft hover:text-ink'"
                    :aria-current="active === s.label ? 'page' : undefined"
                >
                    <component :is="s.icon" :size="15" /> {{ s.label }}
                    <span v-if="s.badge && page.props[s.badge]" class="rounded-full bg-ruby px-1.5 text-[11px] font-extrabold text-white" :aria-label="`${page.props[s.badge]} waiting`">{{ page.props[s.badge] }}</span>
                </Link>
            </div>
        </nav>

        <main class="mx-auto mt-6 max-w-6xl px-4">
            <slot />
        </main>
    </div>
</template>
