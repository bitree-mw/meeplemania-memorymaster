<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ICONS } from '@/game/palette';
import { fmt } from '@/game/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({ achievements: { type: Array, required: true } });

const drafts = reactive(Object.fromEntries(props.achievements.map((a) => [a.id, { name: a.name, description: a.description, threshold: a.threshold, is_active: a.is_active }])));
const errors = reactive({});

const save = (a) =>
    router.put(`/admin/achievements/${a.id}`, drafts[a.id], {
        preserveScroll: true,
        onError: (e) => (errors[a.id] = e),
        onSuccess: () => (errors[a.id] = null),
    });
</script>

<template>
    <Head title="Achievements" />

    <div class="space-y-5">
        <div>
            <h1 class="text-3xl font-extrabold text-navy-deep">Achievements</h1>
            <p class="text-sm font-medium text-ink-soft">Unlock conditions are a metric and a threshold. Each badge is awarded once per player; changing a threshold never revokes existing unlocks.</p>
        </div>

        <form v-for="a in achievements" :key="a.id" class="card flex flex-wrap items-start gap-4 p-5" @submit.prevent="save(a)">
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl" :class="drafts[a.id].is_active ? 'bg-amber text-navy-deep' : 'bg-cream-highest text-outline'">
                <component :is="ICONS[a.icon] ?? ICONS.Trophy" :size="22" />
            </span>
            <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-[1fr_2fr]">
                <label><span class="label">Name</span><input v-model="drafts[a.id].name" class="field !py-2" maxlength="60" /></label>
                <label><span class="label">Description</span><input v-model="drafts[a.id].description" class="field !py-2" maxlength="160" /></label>
                <label>
                    <span class="label">{{ a.criteria_label }} {{ a.direction === 'max' ? '≤' : '≥' }}</span>
                    <input v-model.number="drafts[a.id].threshold" type="number" min="1" class="field !py-2" />
                </label>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="flex items-center gap-2 pb-2 text-sm font-semibold text-ink-soft"><input v-model="drafts[a.id].is_active" type="checkbox" class="size-5 accent-amber" /> Active</label>
                    <span class="pb-2 text-sm font-medium text-outline">Unlocked by {{ fmt(a.unlocked_by) }} player{{ a.unlocked_by === 1 ? '' : 's' }}</span>
                    <button type="submit" class="btn btn-primary btn-sm ml-auto">Save</button>
                </div>
                <p v-for="(msg, k) in errors[a.id] ?? {}" :key="k" class="text-sm font-semibold text-ruby-deep sm:col-span-2">{{ msg }}</p>
            </div>
        </form>
    </div>
</template>
