<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    current: { type: Number, required: true },
    last: { type: Number, required: true },
    // Builds the URL for a page number.
    href: { type: Function, required: true },
});

const pages = computed(() => {
    const out = new Set([1, props.last, props.current - 1, props.current, props.current + 1]);
    return [...out].filter((p) => p >= 1 && p <= props.last).sort((a, b) => a - b);
});
</script>

<template>
    <nav v-if="last > 1" class="flex items-center justify-center gap-1.5" aria-label="Pagination">
        <Link
            v-if="current > 1"
            :href="href(current - 1)"
            preserve-scroll
            class="grid size-10 place-items-center rounded-full bg-white text-ink-soft ring-1 ring-cream-high hover:text-ink"
            aria-label="Previous page"
        >
            <ChevronLeft :size="18" />
        </Link>
        <template v-for="(p, i) in pages" :key="p">
            <span v-if="i > 0 && p - pages[i - 1] > 1" class="px-1 text-outline" aria-hidden="true">…</span>
            <Link
                :href="href(p)"
                preserve-scroll
                class="grid size-10 place-items-center rounded-full font-display text-sm font-bold"
                :class="p === current ? 'bg-amber text-navy-deep shadow-[0_3px_0_#d97706]' : 'bg-white text-ink-soft ring-1 ring-cream-high hover:text-ink'"
                :aria-current="p === current ? 'page' : undefined"
            >
                {{ p }}
            </Link>
        </template>
        <Link
            v-if="current < last"
            :href="href(current + 1)"
            preserve-scroll
            class="grid size-10 place-items-center rounded-full bg-white text-ink-soft ring-1 ring-cream-high hover:text-ink"
            aria-label="Next page"
        >
            <ChevronRight :size="18" />
        </Link>
    </nav>
</template>
