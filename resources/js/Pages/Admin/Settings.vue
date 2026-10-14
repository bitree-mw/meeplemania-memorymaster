<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { RotateCcw } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    scope: { type: String, required: true },
    scopes: { type: Array, required: true },
    sections: { type: Array, required: true },
    values: { type: Object, required: true },
    defaults: { type: Object, required: true },
    timezones: { type: Array, required: true },
});

// Props are reactive proxies, which structuredClone() cannot copy.
const clone = (value) => JSON.parse(JSON.stringify(value));

// One independent form per section so each saves (and validates) on its own.
const forms = reactive({});
watch(
    () => [props.scope, props.values],
    () => {
        Object.keys(forms).forEach((k) => delete forms[k]);
        props.sections.forEach((s) => (forms[s.key] = { data: clone(props.values[s.key]), errors: {}, processing: false }));
    },
    { immediate: true, deep: true },
);

function save(key) {
    const form = forms[key];
    form.processing = true;
    router.put(`/admin/settings/${props.scope}/${key}`, form.data, {
        preserveScroll: true,
        onError: (errors) => (form.errors = errors),
        onSuccess: () => (form.errors = {}),
        onFinish: () => (form.processing = false),
    });
}

function reset(key) {
    if (!confirm('Restore this section to its default values?')) return;
    router.delete(`/admin/settings/${props.scope}/${key}`, { preserveScroll: true });
}

const isChanged = (section, field) => JSON.stringify(forms[section]?.data[field]) !== JSON.stringify(props.defaults[section]?.[field]);
</script>

<template>
    <Head title="Game settings" />

    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-extrabold text-navy-deep">Game configuration</h1>
            <p class="text-sm font-medium text-ink-soft">Changes apply to runs started after saving. Runs in progress keep the rules they began with.</p>
        </div>

        <nav class="-mx-4 overflow-x-auto px-4 py-2 [scrollbar-width:none]" aria-label="Settings scope">
            <div class="flex w-max gap-1 rounded-full bg-cream-high p-1">
                <Link
                    v-for="s in scopes"
                    :key="s.key"
                    :href="`/admin/settings?scope=${s.key}`"
                    class="whitespace-nowrap rounded-full px-4 py-2 font-display text-[13px] font-bold"
                    :class="s.key === scope ? 'bg-white text-navy-deep shadow-sm' : 'text-ink-soft hover:text-ink'"
                    :aria-current="s.key === scope ? 'page' : undefined"
                >
                    {{ s.name }}
                </Link>
            </div>
        </nav>

        <form v-for="section in sections" :key="`${scope}-${section.key}`" class="card p-5 sm:p-6" @submit.prevent="save(section.key)">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-xl font-bold text-navy-deep">{{ section.title }}</h2>
                    <p class="text-sm font-medium text-ink-soft">{{ section.description }}</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" @click="reset(section.key)"><RotateCcw :size="15" /> Defaults</button>
                    <button type="submit" class="btn btn-primary btn-sm" :disabled="forms[section.key]?.processing">Save</button>
                </div>
            </div>

            <div v-if="forms[section.key]" class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="field in section.fields" :key="field.key">
                    <label class="label flex items-center gap-1.5" :for="`${section.key}-${field.key}`">
                        {{ field.label }}
                        <span v-if="isChanged(section.key, field.key)" class="rounded-full bg-amber-soft px-1.5 text-[10px] font-extrabold text-amber-ink" title="Differs from the default">CUSTOM</span>
                    </label>

                    <select v-if="field.type === 'timezone'" :id="`${section.key}-${field.key}`" v-model="forms[section.key].data[field.key]" class="field">
                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                    <div v-else class="relative">
                        <input :id="`${section.key}-${field.key}`" v-model.number="forms[section.key].data[field.key]" type="number" :min="field.min" :max="field.max" class="field" :class="field.unit ? 'pr-12' : ''" />
                        <span v-if="field.unit" class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-sm font-bold text-outline">{{ field.unit }}</span>
                    </div>

                    <p v-if="field.help" class="mt-1 text-xs font-medium text-outline">{{ field.help }}</p>
                    <p v-if="forms[section.key].errors[field.key]" class="mt-1 text-sm font-semibold text-ruby-deep">{{ forms[section.key].errors[field.key] }}</p>
                </div>
            </div>
        </form>
    </div>
</template>
