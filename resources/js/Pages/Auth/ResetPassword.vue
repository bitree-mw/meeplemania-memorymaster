<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/AuthCard.vue';

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
});

const form = useForm({ token: props.token, email: props.email ?? '', password: '', password_confirmation: '' });
const submit = () => form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Choose a new password" />
    <AuthCard title="Choose a new password">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" v-model="form.email" type="email" class="field" autocomplete="email" required />
                <p v-if="form.errors.email" class="mt-1 text-sm font-semibold text-ruby-deep">{{ form.errors.email }}</p>
            </div>
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
