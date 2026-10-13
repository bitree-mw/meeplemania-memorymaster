<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { MapPin, RotateCcw } from 'lucide-vue-next';

/**
 * One-time pop-up telling a player that an admin approved or declined their
 * district change. Shown on the next page they load (e.g. right after login)
 * until they acknowledge it.
 */
const page = usePage();
const notice = computed(() => page.props.districtNotice);
const button = ref(null);
const busy = ref(false);

watch(notice, async (n) => n && (await nextTick(), button.value?.focus()), { immediate: true });

function dismiss() {
    busy.value = true;
    router.post(`/district-notices/${notice.value.id}/dismiss`, {}, { preserveScroll: true, preserveState: true, onFinish: () => (busy.value = false) });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="notice" class="fixed inset-0 z-50 grid place-items-center bg-navy-deep/60 p-4 backdrop-blur-sm">
            <section role="alertdialog" aria-modal="true" aria-labelledby="district-notice-title" aria-describedby="district-notice-body" class="card w-full max-w-md p-6 text-center sm:p-8">
                <span class="mx-auto grid size-14 place-items-center rounded-full" :class="notice.status === 'approved' ? 'bg-amber-soft text-amber-deep' : 'bg-cream-high text-ink-soft'">
                    <MapPin :size="26" />
                </span>

                <template v-if="notice.status === 'approved'">
                    <h2 id="district-notice-title" class="mt-4 text-2xl font-extrabold text-navy-deep">Your district has changed</h2>
                    <div id="district-notice-body" class="mt-2 space-y-3 font-medium text-ink-soft">
                        <p>An admin approved your request. You now play for <strong class="text-navy-deep">{{ notice.to }}</strong><template v-if="notice.from"> (previously {{ notice.from }})</template>.</p>
                        <p class="flex items-start gap-2 rounded-2xl bg-ruby-soft px-4 py-3 text-left text-sm font-semibold text-ruby-deep">
                            <RotateCcw :size="16" class="mt-0.5 shrink-0" />
                            As part of the move, your game progress was reset: points, statistics, achievements and run history start fresh.
                        </p>
                    </div>
                </template>
                <template v-else>
                    <h2 id="district-notice-title" class="mt-4 text-2xl font-extrabold text-navy-deep">District change declined</h2>
                    <div id="district-notice-body" class="mt-2 space-y-3 font-medium text-ink-soft">
                        <p>An admin declined your request to move to <strong class="text-navy-deep">{{ notice.to }}</strong>. Your district and progress are unchanged.</p>
                        <p v-if="notice.note" class="rounded-2xl bg-cream-low px-4 py-3 text-sm">“{{ notice.note }}”</p>
                    </div>
                </template>

                <button ref="button" type="button" class="btn btn-primary btn-lg mt-6 w-full" :disabled="busy" @click="dismiss">Got it</button>
            </section>
        </div>
    </Teleport>
</template>
