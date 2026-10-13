<script setup>
import { CloudCheck, CloudOff, LoaderCircle } from 'lucide-vue-next';

/** Shows whether results are confirmed by the server. */
defineProps({
    verifying: { type: Boolean, default: false },
    // synced | pending | offline | failed
    sync: { type: String, default: 'synced' },
    verified: { type: Boolean, default: false },
});
</script>

<template>
    <span v-if="verifying || sync === 'pending'" class="inline-flex items-center gap-1.5 rounded-full bg-cream-mid px-3 py-1 text-xs font-bold text-ink-soft" role="status">
        <LoaderCircle :size="14" class="motion-safe:animate-spin" aria-hidden="true" /> Verifying score…
    </span>
    <span v-else-if="sync === 'offline'" class="inline-flex items-center gap-1.5 rounded-full bg-amber-mist px-3 py-1 text-xs font-bold text-amber-ink ring-1 ring-amber-soft" role="status">
        <CloudOff :size="14" aria-hidden="true" /> Saved on this device — syncs when you're back online
    </span>
    <span v-else-if="sync === 'failed'" class="inline-flex items-center gap-1.5 rounded-full bg-ruby-soft px-3 py-1 text-xs font-bold text-ruby-deep" role="status">
        <CloudOff :size="14" aria-hidden="true" /> This run couldn't be verified
    </span>
    <span v-else-if="verified" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-soft px-3 py-1 text-xs font-bold text-emerald-deep" role="status">
        <CloudCheck :size="14" aria-hidden="true" /> Verified by the server
    </span>
</template>
