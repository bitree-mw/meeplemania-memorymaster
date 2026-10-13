<script setup>
import { computed, ref } from 'vue';
import { Lock } from 'lucide-vue-next';
import { colorVars } from '@/game/palette';

/**
 * One Memory Master tile. State never relies on colour alone: flashing tiles
 * scale up with a "Flash" badge, misses shake with a cross, and every tile
 * carries its icon, name and number.
 */
const props = defineProps({
    tile: { type: Object, required: true },
    index: { type: Number, required: true },
    // idle | locked | flash | ready | correct | wrong
    status: { type: String, default: 'idle' },
    step: { type: Number, default: null },
    feedbackId: { type: Number, default: 0 },
});

const emit = defineEmits(['press']);
const pressed = ref(false);
const interactive = computed(() => ['ready', 'correct', 'wrong'].includes(props.status));

function onPointerDown(event) {
    if (!interactive.value) return;
    event.currentTarget.setPointerCapture?.(event.pointerId);
    pressed.value = true;
    emit('press', props.index);
}

function onClick(event) {
    // Keyboard activation (Enter/Space) arrives as a click with detail 0.
    if (event.detail === 0 && interactive.value) emit('press', props.index);
}
</script>

<template>
    <button
        type="button"
        class="tile @container relative flex aspect-square w-full select-none flex-col items-center justify-between rounded-[var(--radius-tile)] p-3 text-center sm:p-4"
        :class="[`is-${status}`, { 'is-pressed': pressed }]"
        :style="colorVars(tile.color)"
        :aria-label="`${tile.name}, tile ${index + 1}`"
        :aria-disabled="!interactive"
        :data-tile="index"
        @pointerdown="onPointerDown"
        @pointerup="pressed = false"
        @pointercancel="pressed = false"
        @pointerleave="pressed = false"
        @click="onClick"
        @contextmenu.prevent
    >
        <span
            v-if="status === 'flash'"
            class="absolute -top-3 left-1/2 z-10 -translate-x-1/2 whitespace-nowrap rounded-full px-3 py-1 font-display text-[11px] font-extrabold tracking-wider text-white shadow-lg"
            :style="{ backgroundColor: 'var(--tile-deep)' }"
        >
            FLASH{{ step !== null ? ` #${step + 1}` : '' }}
        </span>

        <span class="flex w-full items-center justify-between">
            <span class="tile-tag rounded-full px-2 py-0.5 font-display text-[10px] font-bold tracking-wider @[11rem]:text-[11px]">
                #{{ index + 1 }} <span class="hidden @[9rem]:inline">{{ tile.colorName.toUpperCase() }}</span>
            </span>
            <Lock v-if="status === 'locked'" :size="16" class="text-outline-soft" aria-hidden="true" />
        </span>

        <span class="tile-icon grid aspect-square w-[46%] place-items-center rounded-[28%]">
            <component :is="tile.icon" class="size-[58%]" :stroke-width="2.4" aria-hidden="true" />
        </span>

        <span class="tile-name block w-full truncate font-display text-[12px] font-bold @[10rem]:text-[17px]">{{ tile.name }}</span>

        <span v-if="status === 'wrong'" :key="feedbackId" class="absolute inset-0 grid place-items-center rounded-[var(--radius-tile)] bg-ruby/10" aria-hidden="true">
            <span class="grid size-10 place-items-center rounded-full bg-ruby font-display text-xl font-extrabold text-white shadow-lg">✕</span>
        </span>
    </button>
</template>

<style scoped>
.tile {
    background: #fff;
    color: var(--color-navy-deep);
    box-shadow: 0 8px 0 var(--color-cream-highest), 0 6px 18px -6px rgb(13 27 42 / 0.16);
    transition: transform 140ms ease, box-shadow 140ms ease, background-color 160ms ease, opacity 200ms ease;
    touch-action: manipulation;
    -webkit-user-select: none;
    -webkit-touch-callout: none;
}
.tile-tag { background: var(--tile-soft); color: var(--tile-deep); }
.tile-icon {
    background: var(--tile-soft);
    color: var(--tile);
    transition: transform 160ms ease, background-color 160ms ease, color 160ms ease;
}
.is-locked {
    background: var(--color-cream-high);
    opacity: 0.62;
    box-shadow: 0 4px 0 var(--color-cream-highest);
    cursor: default;
}
.is-locked .tile-icon { filter: saturate(0.45); }
.is-flash {
    background: var(--tile-soft);
    transform: scale(1.05);
    z-index: 2;
    box-shadow:
        0 0 0 4px color-mix(in srgb, var(--tile) 55%, transparent),
        0 0 42px 6px color-mix(in srgb, var(--tile) 45%, transparent),
        0 8px 0 color-mix(in srgb, var(--tile-deep) 40%, transparent);
}
.is-flash .tile-icon { background: var(--tile); color: #fff; transform: scale(1.12) rotate(-4deg); }
.is-flash .tile-name { color: var(--tile-deep); }
.is-ready { cursor: pointer; }
.is-ready:hover { transform: translateY(-3px); box-shadow: 0 11px 0 var(--color-cream-highest), 0 14px 24px -8px rgb(13 27 42 / 0.2); }
.is-pressed, .is-ready:active {
    transform: translateY(6px) !important;
    background: var(--tile-soft);
    box-shadow: 0 2px 0 var(--color-cream-highest) !important;
}
.is-pressed .tile-icon { background: var(--tile); color: #fff; }
.is-correct { box-shadow: 0 0 0 4px var(--color-emerald), 0 8px 0 var(--color-cream-highest); }
.is-wrong { animation: var(--animate-shake); box-shadow: 0 0 0 4px var(--color-ruby), 0 8px 0 var(--color-cream-highest); }
</style>
