<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    subtitle: { type: String, default: 'Memory Games' },
});
</script>

<template>
    <Link href="/" class="flex min-w-0 items-center gap-2.5" aria-label="Meeple Mania home">
        <span class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-full bg-amber-soft p-1 shadow-sm ring-1 ring-amber-dim/40">
            <img src="/images/meeplemania-logo.png" alt="" class="size-full object-contain" width="44" height="44" />
        </span>
        <span class="min-w-0 leading-none">
            <span class="flex items-center gap-1.5">
                <span class="truncate font-display text-[17px] sm:text-[19px] font-bold tracking-tight text-navy-deep">Meeple Mania</span>
                <span class="rounded-full bg-amber-bright px-1.5 py-0.5 max-[420px]:hidden font-display text-[10px] font-extrabold tracking-wider text-amber-dark">MINI</span>
            </span>
            <span class="mt-1 block truncate text-[13px] font-medium text-ink-soft">{{ subtitle }}</span>
        </span>
    </Link>
</template>
