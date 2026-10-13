<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import FlashToast from '@/Components/FlashToast.vue';
</script>

<template>
    <!-- Standalone pages (e.g. admin sign-in): no game header or dock, just a way back home. -->
    <div class="min-h-dvh px-4 pb-16 pt-4">
        <FlashToast />
        <Link href="/" class="btn btn-ghost btn-sm"><ArrowLeft :size="16" /> Back to home</Link>
        <main>
            <slot />
        </main>
    </div>
</template>
