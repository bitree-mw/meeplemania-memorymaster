<script setup>
import { ArrowDown, ArrowUp, Minus } from 'lucide-vue-next';

defineProps({ value: { type: [Number, String], default: null } });
</script>

<template>
    <span v-if="value === 'new'" class="rounded-full bg-sky-soft px-1.5 py-0.5 font-display text-[10px] font-bold text-[#1d4ed8]" title="New entry since yesterday">NEW</span>
    <span v-else-if="value > 0" class="inline-flex items-center text-[12px] font-bold text-emerald-deep" :title="`Up ${value} since yesterday`">
        <ArrowUp :size="13" :stroke-width="3" aria-hidden="true" />{{ value }}<span class="sr-only"> places up</span>
    </span>
    <span v-else-if="value < 0" class="inline-flex items-center text-[12px] font-bold text-ruby-deep" :title="`Down ${-value} since yesterday`">
        <ArrowDown :size="13" :stroke-width="3" aria-hidden="true" />{{ -value }}<span class="sr-only"> places down</span>
    </span>
    <span v-else-if="value === 0" class="text-outline-soft" title="No change since yesterday"><Minus :size="13" aria-label="No change" /></span>
</template>
