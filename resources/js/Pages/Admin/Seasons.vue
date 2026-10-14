<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

defineProps({ seasons: { type: Array, required: true } });

const editingId = ref(null);
const form = useForm({ name: '', starts_on: '', ends_on: '' });

function startCreate() {
    editingId.value = 'new';
    form.defaults({ name: '', starts_on: '', ends_on: '' });
    form.reset();
    form.clearErrors();
}

function startEdit(season) {
    editingId.value = season.id;
    form.defaults({ name: season.name, starts_on: season.starts_on, ends_on: season.ends_on });
    form.reset();
    form.clearErrors();
}

function save() {
    const options = { preserveScroll: true, onSuccess: () => (editingId.value = null) };
    editingId.value === 'new' ? form.post('/admin/seasons', options) : form.put(`/admin/seasons/${editingId.value}`, options);
}

const destroy = (season) => confirm(`Delete ${season.name}?`) && router.delete(`/admin/seasons/${season.id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Seasons" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-3xl font-extrabold text-navy-deep">Seasons</h1>
                <p class="text-sm font-medium text-ink-soft">Ranked games are tagged with the season they were played in. The Season leaderboard ranks points within the current season.</p>
            </div>
            <button type="button" class="btn btn-primary btn-sm" @click="startCreate"><Plus :size="16" /> New season</button>
        </div>

        <form v-if="editingId" class="card grid grid-cols-1 gap-3 p-5 sm:grid-cols-4" @submit.prevent="save">
            <label class="sm:col-span-2"><span class="label">Name</span><input v-model="form.name" class="field" required maxlength="60" /></label>
            <label><span class="label">Starts</span><input v-model="form.starts_on" type="date" class="field" required /></label>
            <label><span class="label">Ends</span><input v-model="form.ends_on" type="date" class="field" required /></label>
            <p v-for="(msg, k) in form.errors" :key="k" class="text-sm font-semibold text-ruby-deep sm:col-span-4">{{ msg }}</p>
            <div class="flex gap-2 sm:col-span-4">
                <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">{{ editingId === 'new' ? 'Create season' : 'Save season' }}</button>
                <button type="button" class="btn btn-ghost btn-sm" @click="editingId = null">Cancel</button>
            </div>
        </form>

        <div class="card divide-y divide-cream-high p-2 sm:p-4">
            <div v-for="s in seasons" :key="s.id" class="flex flex-wrap items-center gap-3 px-2 py-3">
                <div class="min-w-0 flex-1">
                    <p class="font-display font-bold text-navy-deep">
                        {{ s.name }}
                        <span v-if="s.is_current" class="ml-1 rounded-full bg-emerald-soft px-2 py-0.5 text-xs font-bold text-emerald-deep">Active</span>
                        <span v-else-if="s.is_past" class="ml-1 rounded-full bg-cream-mid px-2 py-0.5 text-xs font-bold text-outline">Ended</span>
                    </p>
                    <p class="text-sm font-medium text-ink-soft">{{ s.starts_on }} → {{ s.ends_on }}</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" @click="startEdit(s)"><Pencil :size="15" /> Edit</button>
                <button type="button" class="grid size-9 place-items-center rounded-full text-ruby-deep hover:bg-ruby-soft" :aria-label="`Delete ${s.name}`" @click="destroy(s)"><Trash2 :size="16" /></button>
            </div>
            <p v-if="!seasons.length" class="p-6 text-center font-medium text-ink-soft">No seasons yet.</p>
        </div>
    </div>
</template>
