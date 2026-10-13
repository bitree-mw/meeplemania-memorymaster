<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Gamepad2, Trophy, UserRound } from 'lucide-vue-next';

const page = usePage();

const items = computed(() => [
    { label: 'Play', href: '/', icon: Gamepad2, active: ['Home', 'Arena'].includes(page.component) },
    { label: 'Ranks', href: '/leaderboard', icon: Trophy, active: page.component === 'Leaderboard' },
    {
        label: 'Profile',
        href: page.props.auth.user ? '/profile' : '/login',
        icon: UserRound,
        active: page.component.startsWith('Profile') || page.component.startsWith('Auth'),
    },
    { label: 'Rules', href: '/rules', icon: BookOpen, active: page.component === 'Rules' },
]);
</script>

<template>
    <nav
        class="pointer-events-none fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
        aria-label="Primary"
    >
        <div class="pointer-events-auto flex w-full max-w-md items-center gap-1 rounded-full border border-cream-high bg-white/95 p-1.5 shadow-lift backdrop-blur-md">
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                prefetch
                class="flex flex-1 flex-col items-center gap-0.5 rounded-full px-2 py-2 font-display text-[11px] font-bold transition-colors"
                :class="item.active ? 'bg-amber text-navy-deep shadow-[0_4px_0_#d97706]' : 'text-ink-soft hover:bg-cream-mid hover:text-ink'"
                :aria-current="item.active ? 'page' : undefined"
            >
                <component :is="item.icon" :size="20" :stroke-width="2.3" />
                {{ item.label }}
            </Link>
        </div>
    </nav>
</template>
