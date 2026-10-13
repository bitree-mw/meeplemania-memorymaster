<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import { AVATARS } from '@/game/palette';

defineProps({ avatars: { type: Array, required: true } });

const user = usePage().props.auth.user;

const profile = useForm({ username: user.username, email: user.email, avatar: user.avatar });
const password = useForm({ current_password: '', password: '', password_confirmation: '' });

const saveProfile = () => profile.put('/profile', { preserveScroll: true });
const savePassword = () => password.put('/profile/password', { preserveScroll: true, onSuccess: () => password.reset() });
</script>

<template>
    <Head title="Account settings" />

    <div class="mx-auto max-w-xl space-y-6 pt-6">
        <h1 class="px-1 text-3xl font-extrabold text-navy-deep">Account settings</h1>

        <form class="card space-y-5 p-6" @submit.prevent="saveProfile">
            <h2 class="font-display text-lg font-bold text-navy-deep">Profile</h2>
            <fieldset>
                <legend class="label">Avatar</legend>
                <div class="flex flex-wrap gap-2">
                    <label v-for="key in avatars" :key="key" class="cursor-pointer rounded-2xl p-1 ring-2 transition-all" :class="profile.avatar === key ? 'ring-amber' : 'ring-transparent hover:ring-cream-highest'">
                        <input v-model="profile.avatar" type="radio" :value="key" class="sr-only" :aria-label="AVATARS[key].label" />
                        <Avatar :avatar="key" />
                    </label>
                </div>
            </fieldset>
            <div>
                <label class="label" for="username">Username</label>
                <input id="username" v-model="profile.username" class="field" autocomplete="username" required maxlength="24" />
                <p v-if="profile.errors.username" class="mt-1 text-sm font-semibold text-ruby-deep">{{ profile.errors.username }}</p>
            </div>
            <div>
                <label class="label" for="email">Email</label>
                <input id="email" v-model="profile.email" type="email" class="field" autocomplete="email" required />
                <p v-if="profile.errors.email" class="mt-1 text-sm font-semibold text-ruby-deep">{{ profile.errors.email }}</p>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="profile.processing">Save profile</button>
        </form>

        <form class="card space-y-5 p-6" @submit.prevent="savePassword">
            <h2 class="font-display text-lg font-bold text-navy-deep">Change password</h2>
            <div>
                <label class="label" for="current_password">Current password</label>
                <input id="current_password" v-model="password.current_password" type="password" class="field" autocomplete="current-password" required />
                <p v-if="password.errors.current_password" class="mt-1 text-sm font-semibold text-ruby-deep">{{ password.errors.current_password }}</p>
            </div>
            <div>
                <label class="label" for="new_password">New password</label>
                <input id="new_password" v-model="password.password" type="password" class="field" autocomplete="new-password" required />
                <p v-if="password.errors.password" class="mt-1 text-sm font-semibold text-ruby-deep">{{ password.errors.password }}</p>
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" v-model="password.password_confirmation" type="password" class="field" autocomplete="new-password" required />
            </div>
            <button type="submit" class="btn btn-navy" :disabled="password.processing">Update password</button>
        </form>
    </div>
</template>
