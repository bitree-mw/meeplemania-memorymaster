<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarCheck, Flame, Medal, ShieldCheck, Timer, TrendingUp } from 'lucide-vue-next';
import { ICONS, colorVars } from '@/game/palette';

const props = defineProps({
    games: { type: Array, required: true },
    durations: { type: Array, required: true },
    countdown: { type: Number, required: true },
    timezone: { type: String, required: true },
    fastestMinLevel: { type: Number, required: true },
    achievements: { type: Array, required: true },
    boards: { type: Array, required: true },
});

const selected = ref(props.games[0].slug);
const game = computed(() => props.games.find((g) => g.slug === selected.value));
const s = computed(() => game.value.rules.scoring);
const example = computed(() => {
    const level = 4;
    const units = 4;
    const speed = Math.round(units * s.value.speed_bonus_per_unit * 0.5);
    return { level, units, time: (units * s.value.speed_target_ms_per_unit) / 2 / 1000, speed, total: s.value.base_points + s.value.points_per_level * level + speed };
});
</script>

<template>
    <Head title="Rulebook" />

    <div class="mx-auto max-w-3xl space-y-6 pt-6">
        <header class="px-1 text-center">
            <span class="eyebrow !text-[13px]">Rulebook</span>
            <h1 class="mt-1 text-[34px] font-extrabold leading-tight text-navy-deep sm:text-[42px]">How to play</h1>
            <p class="mx-auto mt-2 max-w-lg font-medium text-ink-soft">Five memory games, each playable three ways. Learn one, and you know how all the modes work.</p>
        </header>

        <section class="card space-y-5 p-6 sm:p-8" aria-labelledby="modes-title">
            <h2 id="modes-title" class="text-2xl font-bold text-navy-deep">The three modes</h2>
            <article class="flex gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-amber-mist text-amber ring-1 ring-amber-soft"><TrendingUp :size="22" /></span>
                <div>
                    <h3 class="text-lg font-bold text-navy-deep">Level Builder</h3>
                    <p class="mt-1 text-sm font-medium leading-relaxed text-ink-soft">
                        Start small and climb. Every level is bigger, faster or shorter to look at. Losing a round costs a life and gives you a fresh puzzle at the same level; when your lives run out, the run ends and your points are kept.
                    </p>
                </div>
            </article>
            <article class="flex gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-ruby-soft/60 text-ruby ring-1 ring-ruby-soft"><Timer :size="22" /></span>
                <div>
                    <h3 class="text-lg font-bold text-navy-deep">Timed Rush</h3>
                    <p class="mt-1 text-sm font-medium leading-relaxed text-ink-soft">
                        Pick {{ durations.join(', ').replace(/, (\d+)$/, ' or $1') }} seconds. After a {{ countdown }}-second countdown, clear as many rounds as you can. Rounds get harder every few rounds; a miss costs a few points but the clock keeps running. Each clock length has its own leaderboard.
                    </p>
                </div>
            </article>
            <article class="flex gap-4">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-sky-soft text-[#2f6fe4] ring-1 ring-sky-soft"><CalendarCheck :size="22" /></span>
                <div>
                    <h3 class="text-lg font-bold text-navy-deep">Try Once</h3>
                    <p class="mt-1 text-sm font-medium leading-relaxed text-ink-soft">
                        A daily challenge per game: everyone gets exactly the same puzzles. You get <strong>one ranked attempt per game per day</strong> (account required), locked in the moment you start. Later runs are practice and never replace your result. Challenges reset at midnight ({{ timezone }}); the top three earn badges.
                    </p>
                </div>
            </article>
        </section>

        <!-- Per game -->
        <section class="card p-6 sm:p-8" aria-labelledby="games-title">
            <h2 id="games-title" class="text-2xl font-bold text-navy-deep">The games</h2>
            <div class="mt-3 py-2">
                <div class="flex flex-wrap gap-2" role="tablist">
                    <button
                        v-for="g in games"
                        :key="g.slug"
                        type="button"
                        role="tab"
                        :aria-selected="g.slug === selected"
                        class="flex items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1.5 font-display text-[12px] font-bold transition-all sm:gap-2 sm:px-4 sm:py-2 sm:text-[13px]"
                        :class="g.slug === selected ? 'bg-[var(--tile)] text-white' : 'bg-cream-mid text-ink-soft hover:text-ink'"
                        :style="colorVars(g.color)"
                        @click="selected = g.slug"
                    >
                        <component :is="ICONS[g.icon]" :size="16" /> {{ g.name }}
                    </button>
                </div>
            </div>

            <div class="mt-5" :style="colorVars(game.color)" role="tabpanel">
                <div class="flex items-center gap-3">
                    <span class="grid size-12 place-items-center rounded-2xl bg-[var(--tile-soft)] text-[var(--tile)]"><component :is="ICONS[game.icon]" :size="24" :stroke-width="2.4" /></span>
                    <div>
                        <h3 class="text-xl font-bold text-navy-deep">{{ game.name }}</h3>
                        <p class="text-sm font-semibold text-ink-soft">{{ game.tagline }} · {{ game.description }}</p>
                    </div>
                </div>
                <ol class="mt-4 space-y-2">
                    <li v-for="(line, i) in game.how_to" :key="i" class="flex gap-3 text-[15px] font-medium text-ink-soft">
                        <span class="grid size-6 shrink-0 place-items-center rounded-full bg-[var(--tile-soft)] font-display text-xs font-extrabold text-[var(--tile-deep)]">{{ i + 1 }}</span>
                        {{ line }}
                    </li>
                </ol>

                <ul class="mt-4 grid gap-2 text-sm font-medium text-ink-soft sm:grid-cols-2">
                    <li class="rounded-2xl bg-cream-low p-3"><strong class="text-navy-deep">Level Builder:</strong> {{ game.rules.lives }} {{ game.rules.lives === 1 ? 'life' : 'lives' }}, starting at level {{ game.rules.starting_level }}<template v-if="game.rules.input_timeout_ms">; pausing over {{ game.rules.input_timeout_ms / 1000 }}s loses the round</template>.</li>
                    <li class="rounded-2xl bg-cream-low p-3"><strong class="text-navy-deep">Try Once:</strong> starts at level {{ game.rules.once_starting_level }}, up to {{ game.rules.once_max_levels }} levels, {{ game.rules.once_lives }} {{ game.rules.once_lives === 1 ? 'life' : 'lives' }}.</li>
                </ul>

                <p class="mt-4 rounded-2xl bg-navy px-5 py-4 text-center font-display text-base font-bold text-white sm:text-lg">
                    Level score = <span class="text-amber-dim">{{ s.base_points }}</span> + <span class="text-amber-dim">{{ s.points_per_level }} × level</span> + speed bonus
                </p>
                <ul class="mt-3 space-y-1.5 text-sm font-medium text-ink-soft">
                    <li><strong class="text-navy-deep">Speed bonus:</strong> up to {{ s.speed_bonus_per_unit }} points per {{ game.unit }}, shrinking to zero at {{ s.speed_target_ms_per_unit }}ms per {{ game.unit }}.</li>
                    <li><strong class="text-navy-deep">Timed Rush:</strong> {{ s.timed_base_points }} + {{ s.timed_points_per_unit }} per {{ game.unit }} + speed bonus per cleared round; −{{ s.timed_mistake_penalty }} per miss.</li>
                    <li><strong class="text-navy-deep">Example:</strong> clearing level {{ example.level }} with {{ example.units }} {{ game.unit }}s in {{ example.time }}s scores {{ s.base_points }} + {{ s.points_per_level * example.level }} + {{ example.speed }} = <strong class="text-amber-deep">{{ example.total }} points</strong>.</li>
                </ul>
                <Link :href="`/?game=${game.slug}`" class="btn btn-primary mt-5" prefetch>Play {{ game.name }}</Link>
            </div>
        </section>

        <section class="card p-6 sm:p-8" aria-labelledby="streak-title">
            <h2 id="streak-title" class="text-2xl font-bold text-navy-deep">Streaks</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-3xl bg-amber-mist p-5 ring-1 ring-amber-soft">
                    <Flame :size="24" class="text-orange" aria-hidden="true" />
                    <h3 class="mt-2 font-bold text-navy-deep">Daily activity streak</h3>
                    <p class="mt-1 text-sm font-medium text-ink-soft">Consecutive days on which you finish at least one ranked game — any game counts. Miss a day and it starts again.</p>
                </div>
                <div class="rounded-3xl bg-cream-low p-5 ring-1 ring-cream-high">
                    <ShieldCheck :size="24" class="text-emerald" aria-hidden="true" />
                    <h3 class="mt-2 font-bold text-navy-deep">Success streak</h3>
                    <p class="mt-1 text-sm font-medium text-ink-soft">Rounds cleared in a row within one run. A miss resets it; your best per game is on your profile.</p>
                </div>
            </div>
        </section>

        <section class="card p-6 sm:p-8" aria-labelledby="ach-title">
            <h2 id="ach-title" class="text-2xl font-bold text-navy-deep">Achievements</h2>
            <p class="mt-1 text-sm font-medium text-ink-soft">Each badge unlocks once, from ranked play in any game.</p>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                <li v-for="a in achievements" :key="a.key" class="flex items-center gap-3 rounded-2xl bg-cream-low p-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-amber text-navy-deep"><component :is="ICONS[a.icon] ?? ICONS.Trophy" :size="20" /></span>
                    <span>
                        <span class="block font-display font-bold text-navy-deep">{{ a.name }}</span>
                        <span class="block text-xs font-medium text-ink-soft">{{ a.description }}</span>
                    </span>
                </li>
            </ul>
        </section>

        <section class="card p-6 sm:p-8" aria-labelledby="lb-title">
            <h2 id="lb-title" class="flex items-center gap-2 text-2xl font-bold text-navy-deep"><Medal :size="24" class="text-amber" /> Rankings &amp; tie-breaks</h2>
            <p class="mt-1 text-sm font-medium text-ink-soft">Each game has its own boards, plus all-games boards. Only ranked games from signed-in players count; Fastest requires level {{ fastestMinLevel }}+.</p>
            <dl class="mt-4 divide-y divide-cream-high">
                <div v-for="b in boards" :key="b.label" class="grid gap-1 py-3 sm:grid-cols-[10rem_1fr]">
                    <dt class="font-display font-bold text-navy-deep">{{ b.label }}</dt>
                    <dd class="text-sm font-medium text-ink-soft">{{ b.rule }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-[2rem] bg-cream-low p-6 text-sm font-medium text-ink-soft ring-1 ring-cream-high">
            <h2 class="font-display text-lg font-bold text-navy-deep">Fast play, fair scores</h2>
            <p class="mt-1">
                Games run instantly in your browser: puzzles arrive a few rounds ahead, and your points appear the moment you answer. Behind the scenes every round is sent to the server, which rebuilds the puzzle, replays your taps, checks their timing and calculates the official score. If your connection drops, results are saved on your device and synced when you're back.
                Results that look impossible are held for admin review rather than ranked.
            </p>
        </section>
    </div>
</template>
