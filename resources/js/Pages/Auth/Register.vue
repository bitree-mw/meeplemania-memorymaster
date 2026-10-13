<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthCard from '@/Components/AuthCard.vue';
import Avatar from '@/Components/Avatar.vue';
import { AVATARS } from '@/game/palette';

defineProps({ avatars: { type: Array, required: true } });

const form = useForm({ username: '', email: '', password: '', password_confirmation: '', avatar: 'meeple' });
const submit = () => form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Create account" />
    <AuthCard title="Join Meeple Mania" subtitle="Save your progress, earn badges and enter the Daily Cup.">
        <form class="space-y-4" @submit.prevent="submit">
            <fieldset>
                <legend class="label">Pick an avatar</legend>
                <div class="flex flex-wrap gap-1.5">
                    <label v-for="key in avatars" :key="key" class="cursor-pointer rounded-2xl p-0.5 ring-2 transition-all" :class="form.avatar === key ? 'ring-amber' : 'ring-transparent hover:ring-cream-highest'">
                        <input v-model="form.avatar" type="radio" :value="key" class="sr-only" :aria-label="AVATARS[key].label" />
                        <Avatar :avatar="key" size="sm" />
                    </label>
                </div>
            </fieldset>
            <div>
                <label class="label" for="username">Username</label>
                <input id="username" v-model="form.username" class="field" autocomplete="username" required minlength="3" maxlength="24" pattern="[A-Za-z0-9_]+" />
                <p class="mt-1 text-xs font-medium text-outline">Letters, numbers and underscores. Shown on leaderboards.</p>
                <p v-if="form.errors.username" class="mt-1 text-sm font-semibold text-ruby-deep">{{ form.errors.username }}</p>
            </div>
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" v-model="form.email" type="email" class="field" autocomplete="email" required />
                <p v-if="form.errors.email" class="mt-1 text-sm font-semibold text-ruby-deep">{{ form.errors.email }}</p>
            </div>
            <div>
                <label class="label" for="password">Password</label>
                <input id="password" v-model="form.password" type="password" class="field" autocomplete="new-password" required />
                <p v-if="form.errors.password" class="mt-1 text-sm font-semibold text-ruby-deep">{{ form.errors.password }}</p>
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="field" autocomplete="new-password" required />
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="form.processing">Create account</button>
        </form>
        <template #footer>
            Already playing? <Link href="/login" class="font-bold text-amber-deep hover:underline">Sign in</Link>
        </template>
    </AuthCard>
</template>
