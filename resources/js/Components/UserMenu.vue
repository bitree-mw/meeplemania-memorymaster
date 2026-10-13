<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Gauge, LogOut, Settings, UserRound } from 'lucide-vue-next';
import Avatar from '@/Components/Avatar.vue';

const props = defineProps({ user: { type: Object, required: true } });

const open = ref(false);
const root = ref(null);

const close = (e) => {
    if (root.value && !root.value.contains(e.target)) open.value = false;
};
onMounted(() => document.addEventListener('click', close));
onBeforeUnmount(() => document.removeEventListener('click', close));

const logout = () => router.post('/logout');
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex items-center rounded-2xl transition-transform active:scale-95"
            :aria-expanded="open"
            aria-haspopup="menu"
            :aria-label="`Account menu for ${props.user.username}`"
            @click="open = !open"
        >
            <Avatar :avatar="props.user.avatar" size="sm" />
        </button>

        <Transition enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95" enter-active-class="transition duration-150" leave-active-class="transition duration-100">
            <div v-if="open" class="absolute right-0 top-12 z-50 w-56 origin-top-right rounded-3xl bg-white p-2 shadow-lift ring-1 ring-cream-high" role="menu">
                <p class="px-3 pb-2 pt-1 font-display text-sm font-bold text-navy-deep">{{ props.user.username }}</p>
                <Link href="/profile" class="menu-item" role="menuitem"><UserRound :size="17" /> My profile</Link>
                <Link href="/profile/edit" class="menu-item" role="menuitem"><Settings :size="17" /> Account settings</Link>
                <Link v-if="props.user.is_admin" href="/admin" class="menu-item" role="menuitem"><Gauge :size="17" /> Admin dashboard</Link>
                <button type="button" class="menu-item w-full text-ruby-deep" role="menuitem" @click="logout"><LogOut :size="17" /> Sign out</button>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
@reference '../../css/app.css';

.menu-item {
    @apply flex items-center gap-2.5 rounded-2xl px-3 py-2.5 text-left text-sm font-semibold text-ink-soft transition-colors hover:bg-cream-mid hover:text-ink;
}
</style>
