<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/AuthCard.vue';

defineProps({ status: { type: String, default: null } });

const form = useForm({ login: '' });
</script>

<template>
    <Head title="Reset password" />
    <AuthCard title="Forgot your password?" subtitle="We'll email a one-time link to choose a new one.">
        <p v-if="status" class="mb-4 rounded-2xl bg-emerald-soft px-4 py-3 text-sm font-semibold text-emerald-deep">{{ status }}</p>
        <form class="space-y-4" @submit.prevent="form.post('/forgot-password')">
            <div>
                <label class="label" for="login">Username or email</label>
                <input id="login" v-model="form.login" type="text" class="field" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus />
                <p v-if="form.errors.login" class="mt-1 text-sm font-semibold text-ruby-deep">{{ form.errors.login }}</p>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="form.processing">Email reset link</button>
        </form>
        <template #footer>
            <Link href="/login" class="font-bold text-amber-deep hover:underline">Back to sign in</Link>
        </template>
    </AuthCard>
</template>
