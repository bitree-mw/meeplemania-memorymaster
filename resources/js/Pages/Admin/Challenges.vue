<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Lock, Pencil } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fmt } from '@/game/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    games: { type: Array, required: true },
    game: { type: String, required: true },
    days: { type: Array, required: true },
    timezone: { type: String, required: true },
});

const editing = ref(null);
const form = useForm({ game: props.game, date: '', title: '', starting_level: 2, max_levels: 12 });

function edit(day) {
    editing.value = day.date;
    form.defaults({ game: props.game, date: day.date, title: day.title ?? '', starting_level: day.starting_level, max_levels: day.max_levels });
    form.reset();
    form.clearErrors();
}

const save = () => form.put('/admin/challenges', { preserveScroll: true, onSuccess: () => (editing.value = null) });
const label = (d) => new Date(`${d}T12:00:00`).toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' });
</script>

<template>
    <Head title="Try Once" />

    <div class="space-y-5">
        <div>
            <h1 class="text-3xl font-extrabold text-navy-deep">Try Once challenges</h1>
            <p class="text-sm font-medium text-ink-soft">
                One challenge per game per day, rolling over at midnight {{ timezone }}. Upcoming days use the game's defaults unless customised; rules lock once anyone has played.
                Puzzles are derived from the date and the app key, so every player gets the same rounds and results can be re-verified.
            </p>
        </div>

        <nav class="-mx-4 overflow-x-auto px-4 py-2 [scrollbar-width:none]" aria-label="Game">
            <div class="flex w-max gap-1 rounded-full bg-cream-high p-1">
                <Link
                    v-for="g in games"
                    :key="g.slug"
                    :href="`/admin/challenges?game=${g.slug}`"
                    class="whitespace-nowrap rounded-full px-4 py-2 font-display text-[13px] font-bold"
                    :class="g.slug === game ? 'bg-white text-navy-deep shadow-sm' : 'text-ink-soft hover:text-ink'"
                >
                    {{ g.name }}
                </Link>
            </div>
        </nav>

        <div class="card divide-y divide-cream-high p-2 sm:p-4">
            <div v-for="day in days" :key="day.date" class="px-2 py-3" :class="day.is_today ? 'rounded-2xl bg-amber-mist' : ''">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-32 shrink-0">
                        <p class="font-display font-bold text-navy-deep">{{ label(day.date) }}</p>
                        <p class="text-xs font-bold" :class="day.is_today ? 'text-amber-deep' : 'text-outline'">{{ day.is_today ? 'Today' : day.is_past ? 'Past' : 'Upcoming' }}</p>
                    </div>
                    <div class="min-w-0 flex-1 text-sm font-medium text-ink-soft">
                        <span v-if="day.title" class="font-bold text-navy-deep">{{ day.title }} · </span>
                        Starts at level {{ day.starting_level }} · {{ day.max_levels }} levels
                        <span v-if="!day.exists && !day.is_past" class="ml-1 text-xs text-outline">(defaults)</span>
                    </div>
                    <div class="text-sm font-medium text-ink-soft">
                        {{ fmt(day.attempts) }} ranked attempt{{ day.attempts === 1 ? '' : 's' }}
                        <template v-if="day.leader"> · leader <strong class="text-navy-deep">{{ day.leader.username }}</strong> ({{ fmt(day.leader.score) }})</template>
                        <span v-if="day.rewards_granted" class="ml-1 rounded-full bg-emerald-soft px-2 py-0.5 text-xs font-bold text-emerald-deep">Badges awarded</span>
                    </div>
                    <button v-if="day.editable" type="button" class="btn btn-ghost btn-sm" @click="edit(day)"><Pencil :size="15" /> Edit</button>
                    <span v-else-if="!day.is_past" class="inline-flex items-center gap-1 text-xs font-bold text-outline"><Lock :size="13" /> Locked</span>
                </div>

                <form v-if="editing === day.date" class="mt-3 grid grid-cols-1 gap-3 rounded-2xl bg-cream-low p-4 sm:grid-cols-4" @submit.prevent="save">
                    <label class="sm:col-span-2"><span class="label">Title (optional)</span><input v-model="form.title" class="field" maxlength="80" placeholder="e.g. Marathon Monday" /></label>
                    <label><span class="label">Starting level</span><input v-model.number="form.starting_level" type="number" min="1" max="50" class="field" /></label>
                    <label><span class="label">Levels</span><input v-model.number="form.max_levels" type="number" min="1" max="200" class="field" /></label>
                    <p v-for="(msg, k) in form.errors" :key="k" class="text-sm font-semibold text-ruby-deep sm:col-span-4">{{ msg }}</p>
                    <div class="flex gap-2 sm:col-span-4">
                        <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing">Save challenge</button>
                        <button type="button" class="btn btn-ghost btn-sm" @click="editing = null">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
