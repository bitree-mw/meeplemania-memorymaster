<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CircleCheck, CircleX, X } from 'lucide-vue-next';

const page = usePage();
const message = ref(null);
let timer;

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    ([success, error]) => {
        if (!success && !error) return;
        message.value = { text: success || error, error: Boolean(error) };
        clearTimeout(timer);
        timer = setTimeout(() => (message.value = null), 5000);
    },
    { immediate: true },
);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 top-3 z-50 flex justify-center px-4" aria-live="polite">
        <Transition enter-from-class="opacity-0 -translate-y-3" leave-to-class="opacity-0 -translate-y-3" enter-active-class="transition duration-300" leave-active-class="transition duration-200">
            <div
                v-if="message"
                class="pointer-events-auto flex max-w-md items-center gap-3 rounded-full py-2.5 pl-4 pr-2 text-sm font-semibold shadow-lift"
                :class="message.error ? 'bg-ruby-soft text-ruby-deep' : 'bg-navy text-white'"
                role="status"
            >
                <component :is="message.error ? CircleX : CircleCheck" :size="18" class="shrink-0" :class="message.error ? '' : 'text-emerald'" />
                <span>{{ message.text }}</span>
                <button type="button" class="grid size-7 place-items-center rounded-full hover:bg-white/15" aria-label="Dismiss" @click="message = null">
                    <X :size="15" />
                </button>
            </div>
        </Transition>
    </div>
</template>
