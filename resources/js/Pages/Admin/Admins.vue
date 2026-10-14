<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { LogIn, ShieldAlert, ShieldCheck, UserPlus } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import DistrictSelect from '@/Components/DistrictSelect.vue';
import Pagination from '@/Components/Pagination.vue';
import { relativeTime } from '@/game/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    admins: { type: Array, required: true },
    max: { type: Number, required: true },
    slotsLeft: { type: Number, required: true },
    activity: { type: Object, required: true },
    actions: { type: Array, required: true },
    filter: { type: Object, required: true },
    districts: { type: Array, required: true },
});

const form = useForm({ username: '', email: '', password: '', password_confirmation: '', district: null });
const create = () => form.post('/admin/admins', { preserveScroll: true, onSuccess: () => form.reset() });

const url = (params) => {
    const p = new URLSearchParams();
    Object.entries({ admin: props.filter.admin, action: props.filter.action, ...params }).forEach(([k, v]) => v && p.set(k, v));
    return `/admin/admins${p.size ? `?${p}` : ''}`;
};

const ALERTS = ['login_failed', 'login_denied'];
const filteredAdmin = computed(() => props.admins.find((a) => a.id === props.filter.admin)?.username);
const when = (iso) => new Date(iso).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <Head title="Admins" />

    <div class="space-y-5">
        <div>
            <h1 class="text-3xl font-extrabold text-navy-deep">Admins <span class="text-lg text-outline">{{ admins.length }} of {{ max }}</span></h1>
            <p class="text-sm font-medium text-ink-soft">
                At most {{ max }} accounts can hold admin access, deactivated ones included. Grant access to an existing player from their page in
                <Link href="/admin/players" class="font-bold text-amber-deep hover:underline">Players</Link>, or create a new admin account below.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[3fr_2fr]">
            <section class="card p-2 sm:p-4" aria-label="Admin accounts">
                <ul class="divide-y divide-cream-high">
                    <li v-for="a in admins" :key="a.id" class="flex flex-wrap items-center gap-3 px-2 py-3">
                        <Avatar :avatar="a.avatar" size="sm" />
                        <Link :href="`/admin/players/${a.id}`" class="min-w-0 flex-1">
                            <span class="block font-display font-bold text-navy-deep">
                                {{ a.username }}
                                <span v-if="a.id === $page.props.auth.user.id" class="ml-1 rounded-full bg-cream-high px-1.5 text-[10px] text-ink-soft">YOU</span>
                                <span v-if="a.disabled" class="ml-1 rounded-full bg-ruby-soft px-1.5 text-[10px] text-ruby-deep">DEACTIVATED</span>
                            </span>
                            <span class="block truncate text-xs text-outline">{{ a.email }}</span>
                        </Link>
                        <span class="text-right text-xs font-medium text-ink-soft">
                            <span class="block"><LogIn :size="12" class="inline" /> {{ a.last_login ? relativeTime(a.last_login) : 'Never signed in' }}</span>
                            <Link :href="url({ admin: a.id, page: '' })" class="font-bold text-amber-deep hover:underline">Activity</Link>
                        </span>
                    </li>
                </ul>
            </section>

            <form class="card space-y-3 p-5" @submit.prevent="create">
                <h2 class="flex items-center gap-2 font-display text-lg font-bold text-navy-deep"><UserPlus :size="18" /> New admin account</h2>
                <p v-if="slotsLeft === 0" class="rounded-2xl bg-ruby-soft px-4 py-3 text-sm font-semibold text-ruby-deep">
                    All {{ max }} admin slots are taken. Revoke admin access from an account to free one.
                </p>
                <template v-else>
                    <p class="text-sm font-medium text-ink-soft">{{ slotsLeft }} slot{{ slotsLeft === 1 ? '' : 's' }} left.</p>
                    <label class="block"><span class="label">Username</span><input v-model="form.username" class="field !py-2" required minlength="3" maxlength="24" pattern="[A-Za-z0-9_]+" autocomplete="off" /></label>
                    <p v-if="form.errors.username" class="text-sm font-semibold text-ruby-deep">{{ form.errors.username }}</p>
                    <label class="block"><span class="label">Email</span><input v-model="form.email" type="email" class="field !py-2" required autocomplete="off" /></label>
                    <p v-if="form.errors.email" class="text-sm font-semibold text-ruby-deep">{{ form.errors.email }}</p>
                    <label class="block"><span class="label">District</span><DistrictSelect v-model="form.district" :districts="districts" placeholder="Optional" class="!py-2" /></label>
                    <label class="block"><span class="label">Password</span><input v-model="form.password" type="password" class="field !py-2" required autocomplete="new-password" /></label>
                    <p v-if="form.errors.password" class="text-sm font-semibold text-ruby-deep">{{ form.errors.password }}</p>
                    <label class="block"><span class="label">Confirm password</span><input v-model="form.password_confirmation" type="password" class="field !py-2" required autocomplete="new-password" /></label>
                    <p v-if="form.errors.admin" class="text-sm font-semibold text-ruby-deep">{{ form.errors.admin }}</p>
                    <button type="submit" class="btn btn-primary btn-sm w-full" :disabled="form.processing"><ShieldCheck :size="16" /> Create admin</button>
                </template>
            </form>
        </div>

        <section class="card p-2 sm:p-4" aria-label="Admin activity">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3 px-2">
                <h2 class="font-display text-lg font-bold text-navy-deep">
                    Activity log<template v-if="filteredAdmin"> · {{ filteredAdmin }}</template>
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <select class="field !w-auto !rounded-full !py-1.5 text-sm" :value="filter.action ?? ''" aria-label="Filter by action" @change="router.get(url({ action: $event.target.value, page: '' }))">
                        <option value="">All actions</option>
                        <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                    </select>
                    <Link v-if="filter.admin || filter.action" href="/admin/admins" class="btn btn-ghost btn-sm">Clear filters</Link>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[44rem] text-left text-sm">
                    <thead class="text-[12px] uppercase tracking-wider text-outline">
                        <tr><th class="px-2 py-2 font-bold">When</th><th class="font-bold">Admin</th><th class="font-bold">What happened</th><th class="font-bold">IP address</th></tr>
                    </thead>
                    <tbody class="divide-y divide-cream-high font-medium text-ink-soft">
                        <tr v-for="log in activity.data" :key="log.id" :class="ALERTS.includes(log.action) ? 'bg-ruby-soft/40' : ''">
                            <td class="whitespace-nowrap px-2 py-2.5" :title="log.at">{{ when(log.at) }}</td>
                            <td class="font-bold text-navy-deep">{{ log.admin ?? '—' }}</td>
                            <td>
                                <ShieldAlert v-if="ALERTS.includes(log.action)" :size="14" class="mr-1 inline text-ruby-deep" aria-label="Security alert" />
                                {{ log.description }}
                            </td>
                            <td class="whitespace-nowrap text-xs" :title="log.user_agent">{{ log.ip_address ?? 'console' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!activity.data.length" class="py-8 text-center font-medium text-ink-soft">No activity recorded yet.</p>
            <Pagination :current="activity.current_page" :last="activity.last_page" :href="(page) => url({ page })" />
        </section>
    </div>
</template>
