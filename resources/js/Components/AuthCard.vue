<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <div class="mx-auto flex max-w-md flex-col items-center pt-8">
        <img src="/images/coach-meeple.png" alt="" class="relative z-10 -mb-6 h-24 w-auto object-contain drop-shadow-md motion-safe:animate-float" width="64" height="96" />
        <section class="card w-full p-6 pt-9 sm:p-8 sm:pt-10">
            <h1 class="text-center text-3xl font-extrabold text-navy-deep">{{ title }}</h1>
            <p v-if="subtitle" class="mt-1 text-center font-medium text-ink-soft">{{ subtitle }}</p>
            <div class="mt-6">
                <slot />
            </div>
        </section>
        <div class="mt-5 text-center text-sm font-medium text-ink-soft">
            <slot name="footer" />
        </div>
    </div>
</template>
