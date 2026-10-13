<script setup>
import { computed, ref } from 'vue';
import { fmt } from '@/game/format';

/**
 * Single-series column chart: ranked + casual games started per day.
 * One hue (validated ≥3:1 against the card), rounded data-ends on the
 * baseline, 2px gaps, per-bar hover tooltip, and a table view for screen readers.
 */
const props = defineProps({ series: { type: Array, required: true } });

const hover = ref(null);
const max = computed(() => Math.max(1, ...props.series.map((d) => d.games)));
// A "nice" axis ceiling so gridlines land on round numbers.
const ceiling = computed(() => {
    const raw = max.value;
    const step = Math.pow(10, Math.floor(Math.log10(raw)));
    return Math.ceil(raw / step) * step;
});
const ticks = computed(() => [0, ceiling.value / 2, ceiling.value]);
const label = (day) => new Date(`${day}T12:00:00`).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
const total = computed(() => props.series.reduce((sum, d) => sum + d.games, 0));
</script>

<template>
    <figure>
        <figcaption class="flex items-baseline justify-between">
            <span class="font-display text-lg font-bold text-navy-deep">Games played per day</span>
            <span class="text-sm font-semibold text-ink-soft">{{ fmt(total) }} in 14 days</span>
        </figcaption>

        <div class="relative mt-4 h-48 pl-8" aria-hidden="true" @mouseleave="hover = null">
            <!-- Recessive gridlines -->
            <div v-for="t in ticks" :key="t" class="absolute inset-x-0 left-8 border-t border-cream-high" :style="{ bottom: `${(t / ceiling) * 100}%` }">
                <span class="absolute -left-8 -top-2 w-6 text-right text-[11px] font-medium text-outline">{{ fmt(t) }}</span>
            </div>

            <div class="relative flex h-full items-end gap-[2px]">
                <div
                    v-for="(d, i) in series"
                    :key="d.day"
                    class="group relative flex h-full flex-1 items-end"
                    @mouseenter="hover = i"
                >
                    <div
                        class="w-full rounded-t-[4px] transition-opacity"
                        :class="hover !== null && hover !== i ? 'opacity-50' : ''"
                        :style="{ height: `${(d.games / ceiling) * 100}%`, minHeight: d.games ? '3px' : '0', backgroundColor: '#d97706' }"
                    />
                    <div
                        v-if="hover === i"
                        class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 whitespace-nowrap rounded-xl bg-navy px-3 py-1.5 text-xs font-semibold text-white shadow-lift"
                    >
                        {{ label(d.day) }} · <strong>{{ fmt(d.games) }}</strong> game{{ d.games === 1 ? '' : 's' }}
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-2 flex justify-between pl-8 text-[11px] font-medium text-outline" aria-hidden="true">
            <span>{{ label(series[0].day) }}</span>
            <span>{{ label(series[series.length - 1].day) }}</span>
        </div>

        <table class="sr-only">
            <caption>Games played per day</caption>
            <thead><tr><th scope="col">Day</th><th scope="col">Games</th></tr></thead>
            <tbody>
                <tr v-for="d in series" :key="d.day"><td>{{ d.day }}</td><td>{{ d.games }}</td></tr>
            </tbody>
        </table>
    </figure>
</template>
