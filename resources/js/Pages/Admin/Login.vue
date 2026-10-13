<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/AuthCard.vue';
import BareLayout from '@/Layouts/BareLayout.vue';

defineOptions({ layout: BareLayout });
defineProps({ status: { type: String, default: null } });

const form = useForm({ login: '', password: '', remember: false });
const submit = () => form.post('/admin/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Admin sign in" />
    <AuthCard title="Admin sign in" subtitle="Manage games, players and leaderboards.">
        <p v-if="status" class="mb-4 rounded-2xl bg-emerald-soft px-4 py-3 text-sm font-semibold text-emerald-deep">{{ status }}</p>
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label" for="login">Username or email</label>
                <input id="login" v-model="form.login" type="text" class="field" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus />
                <p v-if="form.errors.login" class="mt-1 text-sm font-semibold text-ruby-deep">{{ form.errors.login }}</p>
            </div>
            <div>
                <div class="flex items-center justify-between">
                    <label class="label" for="password">Password</label>
                    <Link href="/forgot-password" class="mb-1.5 text-[13px] font-bold text-amber-deep hover:underline">Forgot it?</Link>
                </div>
                <input id="password" v-model="form.password" type="password" class="field" autocomplete="current-password" required />
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold text-ink-soft">
                <input v-model="form.remember" type="checkbox" class="size-5 rounded-md accent-amber" /> Keep me signed in
            </label>
            <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="form.processing">Sign in</button>
        </form>
    </AuthCard>
</template>
