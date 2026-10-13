<script setup>
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Clock, Lock, MapPin } from 'lucide-vue-next';
import Avatar from '@/Components/Avatar.vue';
import DistrictSelect from '@/Components/DistrictSelect.vue';
import { AVATARS } from '@/game/palette';
import { shortDate } from '@/game/format';

defineProps({
    avatars: { type: Array, required: true },
    districts: { type: Array, required: true },
    districtName: { type: String, default: null },
    pendingRequest: { type: Object, default: null },
    declinedRequest: { type: Object, default: null },
});

const page = usePage();
const user = page.props.auth.user;
// A district is chosen once; afterwards it can only be changed by an admin.
const locked = computed(() => Boolean(page.props.auth.user.district));

const profile = useForm({ username: user.username, email: user.email, avatar: user.avatar, district: user.district });
const password = useForm({ current_password: '', password: '', password_confirmation: '' });
const change = useForm({ to_district: null, reason: '' });

function saveProfile() {
    if (!locked.value && profile.district && !confirm('Your district can only be set once. To change it later you will need an admin, and a change resets your game progress. Continue?')) return;
    profile.transform((data) => (locked.value ? { ...data, district: undefined } : data)).put('/profile', { preserveScroll: true });
}
const savePassword = () => password.put('/profile/password', { preserveScroll: true, onSuccess: () => password.reset() });

function requestChange() {
    if (!confirm('If an admin approves this, ALL your game progress is deleted: points, statistics, achievements and run history. Send the request?')) return;
    change.post('/profile/district-request', { preserveScroll: true, onSuccess: () => change.reset() });
}
const cancelRequest = () => router.delete('/profile/district-request', { preserveScroll: true });
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
            <div>
                <span class="label">District</span>
                <p v-if="locked" class="flex items-center gap-2 rounded-2xl border-2 border-cream-high bg-cream-low px-4 py-3 font-medium text-navy-deep">
                    <MapPin :size="16" class="text-amber-deep" /> {{ districtName }}
                    <Lock :size="14" class="ml-auto text-outline" aria-label="Locked" />
                </p>
                <template v-else>
                    <DistrictSelect id="district" v-model="profile.district" :districts="districts" />
                    <p class="mt-1 text-xs font-medium text-outline">You can set this once. After that, only an admin can change it.</p>
                </template>
                <p v-if="profile.errors.district" class="mt-1 text-sm font-semibold text-ruby-deep">{{ profile.errors.district }}</p>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="profile.processing">Save profile</button>
        </form>

        <section v-if="locked" class="card space-y-4 p-6" aria-labelledby="district-change">
            <h2 id="district-change" class="font-display text-lg font-bold text-navy-deep">Change district</h2>

            <div v-if="pendingRequest" class="space-y-3">
                <p class="flex items-start gap-2 rounded-2xl bg-amber-mist px-4 py-3 text-sm font-semibold text-amber-ink ring-1 ring-amber-soft">
                    <Clock :size="16" class="mt-0.5 shrink-0" />
                    <span>Your request to move to <strong>{{ pendingRequest.to }}</strong> is waiting for an admin (sent {{ shortDate(pendingRequest.requested_at) }}).</span>
                </p>
                <button type="button" class="btn btn-ghost btn-sm" @click="cancelRequest">Cancel request</button>
            </div>

            <form v-else class="space-y-4" @submit.prevent="requestChange">
                <p class="text-sm font-medium text-ink-soft">
                    Moved? Ask an admin to change your district.
                    <strong class="text-ruby-deep">If it's approved, all your game progress is reset</strong>: points, statistics, achievements and run history.
                </p>
                <p v-if="declinedRequest" class="rounded-2xl bg-ruby-soft px-4 py-3 text-sm font-semibold text-ruby-deep">
                    Your last request (to {{ declinedRequest.to }}) was declined{{ declinedRequest.note ? `: “${declinedRequest.note}”` : '.' }}
                </p>
                <div>
                    <label class="label" for="to_district">New district</label>
                    <DistrictSelect id="to_district" v-model="change.to_district" :districts="districts" :exclude="[$page.props.auth.user.district]" required />
                    <p v-if="change.errors.to_district" class="mt-1 text-sm font-semibold text-ruby-deep">{{ change.errors.to_district }}</p>
                </div>
                <div>
                    <label class="label" for="reason">Reason (optional)</label>
                    <input id="reason" v-model="change.reason" class="field" maxlength="255" placeholder="e.g. I moved to Mzuzu for work" />
                </div>
                <button type="submit" class="btn btn-navy" :disabled="change.processing || !change.to_district">Send request to admins</button>
            </form>
        </section>

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
