<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/AuthCard.vue';

const props = defineProps({
    username: { type: String, required: true },
    token: { type: String, required: true },
    valid: { type: Boolean, required: true },
    expires_at: { type: String, default: null },
});

const form = useForm({ username: props.username, token: props.token, password: '', password_confirmation: '' });
const submit = () => form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
const expiresAt = props.expires_at ? new Date(props.expires_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : null;
</script>

<template>
    <Head title="Choose a new password" />

    <AuthCard v-if="!valid" title="Link expired" subtitle="This reset link is invalid, has already been used or has expired.">
        <Link href="/forgot-password" class="btn btn-primary btn-lg w-full">Request a new link</Link>
    </AuthCard>

    <AuthCard v-else title="Choose a new password" :subtitle="`For ${username}${expiresAt ? ` · link valid until ${expiresAt}` : ''}`">
        <form class="space-y-4" @submit.prevent="submit">
            <input type="text" :value="username" autocomplete="username" class="hidden" readonly aria-hidden="true" tabindex="-1" />
            <p v-if="form.errors.token" class="rounded-2xl bg-ruby-soft px-4 py-3 text-sm font-semibold text-ruby-deep">
                {{ form.errors.token }} <Link href="/forgot-password" class="underline">Request a new link</Link>
            </p>
            <div>
                <label class="label" for="password">New password</label>
                <input id="password" v-model="form.password" type="password" class="field" autocomplete="new-password" required autofocus />
                <p v-if="form.errors.password" class="mt-1 text-sm font-semibold text-ruby-deep">{{ form.errors.password }}</p>
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="field" autocomplete="new-password" required />
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="form.processing">Reset password</button>
        </form>
    </AuthCard>
</template>
