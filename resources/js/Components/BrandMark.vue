<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    // Optional second line (e.g. "Admin dashboard"); the game header shows just the name.
    subtitle: { type: String, default: null },
});
</script>

<template>
    <Link href="/" class="flex min-w-0 items-center gap-2 sm:gap-2.5" aria-label="MeepleMania Mini home">
        <span class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-full bg-amber-soft p-1 shadow-sm ring-1 ring-amber-dim/40 sm:size-11">
            <img src="/images/meeplemania-logo.png" alt="" class="size-full object-contain" width="44" height="44" />
        </span>
        <span class="min-w-0">
            <span class="flex min-w-0 items-center gap-1 sm:gap-1.5">
                <!-- leading-normal leaves room for descenders; truncate clips them under leading-none. -->
                <span class="truncate font-display text-[15px] font-bold leading-normal tracking-tight text-navy-deep sm:text-[19px]">MeepleMania</span>
                <span class="shrink-0 rounded-full bg-orange px-1.5 py-0.5 font-display text-[10px] font-bold uppercase leading-none tracking-wider text-white shadow-sm sm:px-2 sm:text-[12px]">Mini</span>
            </span>
            <span v-if="subtitle" class="mt-1 block truncate text-[13px] font-medium text-ink-soft">{{ subtitle }}</span>
        </span>
    </Link>
</template>
