<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    // Optional second line (e.g. "Admin dashboard"); the game header shows just the name.
    subtitle: { type: String, default: null },
});
</script>

<template>
    <Link href="/" class="flex min-w-0 items-center gap-2.5" aria-label="MeepleMania Mini home">
        <span class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-full bg-amber-soft p-1 shadow-sm ring-1 ring-amber-dim/40">
            <img src="/images/meeplemania-logo.png" alt="" class="size-full object-contain" width="44" height="44" />
        </span>
        <span class="min-w-0 leading-none">
            <span class="block truncate font-display text-[17px] font-bold tracking-tight text-navy-deep sm:text-[19px]">MeepleMania <span class="text-amber-deep">Mini</span></span>
            <span v-if="subtitle" class="mt-1 block truncate text-[13px] font-medium text-ink-soft">{{ subtitle }}</span>
        </span>
    </Link>
</template>
