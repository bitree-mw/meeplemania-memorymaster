<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ICONS } from '@/game/palette';
import { fmt } from '@/game/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    achievements: { type: Array, required: true },
    criteria: { type: Array, required: true },
    icons: { type: Array, required: true },
});

const creating = useForm({ name: '', description: '', icon: 'Trophy', criteria: props.criteria[0]?.key ?? '', threshold: 1, is_active: true });
const create = () => creating.post('/admin/achievements', { preserveScroll: true, onSuccess: () => creating.reset() });
const chosenCriteria = computed(() => props.criteria.find((c) => c.key === creating.criteria));

const drafts = reactive({});
const errors = reactive({});
// Newly created achievements arrive in props after the redirect; give each one a draft.
watch(
    () => props.achievements,
    (list) => list.forEach((a) => (drafts[a.id] ??= { name: a.name, description: a.description, threshold: a.threshold, is_active: a.is_active })),
    { immediate: true },
);

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

        <form class="card space-y-4 p-5 ring-2 ring-amber-soft" @submit.prevent="create">
            <h2 class="flex items-center gap-2 font-display text-lg font-bold text-navy-deep"><Plus :size="18" /> New achievement</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_2fr]">
                <label><span class="label">Name</span><input v-model="creating.name" class="field !py-2" maxlength="60" required /></label>
                <label><span class="label">Description</span><input v-model="creating.description" class="field !py-2" maxlength="160" required placeholder="What the player has to do" /></label>
                <label>
                    <span class="label">Unlocks when</span>
                    <select v-model="creating.criteria" class="field !py-2">
                        <option v-for="c in criteria" :key="c.key" :value="c.key">{{ c.label }}</option>
                    </select>
                </label>
                <label>
                    <span class="label">{{ chosenCriteria?.direction === 'max' ? 'Is at or better than (≤)' : 'Reaches at least (≥)' }}</span>
                    <input v-model.number="creating.threshold" type="number" min="1" class="field !py-2" required />
                </label>
            </div>
            <fieldset>
                <legend class="label">Badge icon</legend>
                <div class="flex flex-wrap gap-1.5">
                    <label v-for="icon in icons" :key="icon" class="grid size-10 cursor-pointer place-items-center rounded-xl ring-2 transition-all" :class="creating.icon === icon ? 'bg-amber text-navy-deep ring-amber' : 'bg-cream-low text-ink-soft ring-transparent hover:ring-cream-highest'">
                        <input v-model="creating.icon" type="radio" :value="icon" class="sr-only" :aria-label="icon" />
                        <component :is="ICONS[icon]" :size="18" />
                    </label>
                </div>
            </fieldset>
            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm font-semibold text-ink-soft"><input v-model="creating.is_active" type="checkbox" class="size-5 accent-amber" /> Active</label>
                <button type="submit" class="btn btn-primary btn-sm ml-auto" :disabled="creating.processing">Create achievement</button>
            </div>
            <p v-for="(msg, k) in creating.errors" :key="k" class="text-sm font-semibold text-ruby-deep">{{ msg }}</p>
        </form>

        <form v-for="a in achievements" :key="a.id" class="card flex flex-wrap items-start gap-4 p-5" @submit.prevent="save(a)">
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl" :class="drafts[a.id].is_active ? 'bg-amber text-navy-deep' : 'bg-cream-highest text-outline'">
                <component :is="ICONS[a.icon] ?? ICONS.Trophy" :size="22" />
            </span>
            <div class="grid grid-cols-1 min-w-0 flex-1 gap-3 sm:grid-cols-[1fr_2fr]">
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
