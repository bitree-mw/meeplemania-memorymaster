<script setup>
import { computed } from 'vue';
import { AVATARS, COLORS } from '@/game/palette';

const props = defineProps({
    avatar: { type: String, default: 'meeple' },
    size: { type: String, default: 'md' }, // sm | md | lg | xl
});

const item = computed(() => AVATARS[props.avatar] ?? AVATARS.meeple);
const color = computed(() => COLORS[item.value.color]);
const sizes = { sm: 'size-8', md: 'size-11', lg: 'size-16', xl: 'size-24' };
const iconSizes = { sm: 16, md: 22, lg: 30, xl: 44 };
</script>

<template>
    <span
        class="grid shrink-0 place-items-center overflow-hidden rounded-2xl ring-2 ring-white"
        :class="sizes[size]"
        :style="{ backgroundColor: color.soft, color: color.deep }"
        aria-hidden="true"
    >
        <img v-if="item.image" :src="item.image" alt="" class="size-[85%] translate-y-[6%] object-contain" />
        <component :is="item.icon" v-else :size="iconSizes[size]" :stroke-width="2.4" />
    </span>
</template>
