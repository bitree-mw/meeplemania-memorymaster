<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, shallowRef, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarCheck, Eye, Heart, Hourglass, LoaderCircle, Pause, Play, RotateCcw, Timer, WifiOff, X } from 'lucide-vue-next';
import AnimatedNumber from '@/Components/AnimatedNumber.vue';
import CoachMeeple from '@/Components/Game/CoachMeeple.vue';
import Confetti from '@/Components/Game/Confetti.vue';
import GameOver from '@/Components/Game/GameOver.vue';
import GuestPlaysNotice from '@/Components/Game/GuestPlaysNotice.vue';
import LevelComplete from '@/Components/Game/LevelComplete.vue';
import LifeLost from '@/Components/Game/LifeLost.vue';
import RunSummary from '@/Components/Game/RunSummary.vue';
import { usePresence } from '@/composables/usePresence';
import { playApi } from '@/game/api';
import { sounds } from '@/game/audio';
import { BOARDS } from '@/game/boards';
import { coachLine, inputTone } from '@/game/coach';
import { PHASE, createGameEngine, initialState } from '@/game/engine';
import { LOGIC } from '@/game/logic';
import { ICONS, TILES, colorVars } from '@/game/palette';
import { syncQueue } from '@/game/sync';

const props = defineProps({
    game: { type: Object, required: true },
    mode: { type: Object, required: true },
    durations: { type: Array, required: true },
    duration: { type: Number, default: null },
    personalBests: { type: Object, default: null },
    dailyAttempted: { type: Boolean, default: false },
});

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const timed = props.mode.slug === 'timed';
const once = props.mode.slug === 'once';
const slug = props.game.slug;
const board = BOARDS[slug];
const accent = colorVars(props.game.color);
const { setPlaying } = usePresence();

const state = reactive(initialState());
const engine = shallowRef(null);
const duration = ref(props.duration);
const celebrate = ref(false);
const announcement = ref('');
const bestLevel = ref(props.personalBests?.level ?? null);
const step = ref(0);

const hooks = {
    input(value, correct) {
        if (correct === false) {
            sounds.wrong();
            navigator.vibrate?.(110);
        } else if (correct === null) {
            sounds.click();
        } else if (slug === 'meeple-pairs' || slug === 'spot-the-swap') {
            sounds.correct();
        } else {
            sounds.tile(inputTone(slug, value, state.roundState), 150);
        }
    },
    round() {
        step.value = 0;
        if (slug !== 'memory-master') sounds.tone(660, 70, { type: 'sine', gain: 0.06 });
    },
    yourTurn: () => (announcement.value = 'Your turn.'),
    timeout: () => sounds.wrong(),
    roundComplete(record) {
        if (timed && record.status === 'cleared') sounds.correct();
    },
    cleared(record) {
        sounds.levelUp();
        announcement.value = `Level ${record.spec.level} cleared.`;
        if (bestLevel.value !== null && record.spec.level > bestLevel.value) sounds.personalBest();
        burst();
    },
    lifeLost: () => (announcement.value = `Life lost. ${state.lives} left.`),
    gameover() {
        sounds.gameOver();
        announcement.value = 'Run over.';
    },
    finished() {
        sounds.levelUp();
        burst();
    },
    verified: () => refreshHeader(),
    countdown: () => sounds.tick(),
    go: () => sounds.go(),
    phase(phase) {
        setPlaying(![PHASE.IDLE, PHASE.GAMEOVER, PHASE.FINISHED].includes(phase));
        if (phase === PHASE.PREVIEW) celebrate.value = false;
    },
};

function makeEngine() {
    engine.value?.destroy();
    Object.assign(state, initialState());
    engine.value = createGameEngine({
        game: slug,
        mode: props.mode.slug,
        duration: duration.value,
        api: playApi,
        queue: syncQueue,
        logic: LOGIC[slug],
        state,
        hooks,
    });
    // Create the session in the background so Start is instant.
    // Not for Try Once: starting claims the day's ranked attempt.
    if (!once) engine.value.prepare().catch(() => {});
}

function burst() {
    celebrate.value = true;
    setTimeout(() => (celebrate.value = false), 2600);
}

function refreshHeader() {
    router.reload({ only: [isGuest.value ? 'guestPlays' : 'player'] });
}

// A guest who has used every free game can't start another: reloading the page
// lets the server send them to sign-up with its message.
watch(
    () => state.error?.code,
    (code) => {
        if (code === 'guest_limit') router.reload();
    },
);

function onCue(cue) {
    if (slug === 'memory-master') {
        step.value = cue.step;
        sounds.tile(TILES[cue.tile].frequency, cue.ms);
    } else if (cue === 'blink') {
        sounds.tone(300, 140, { type: 'sine', gain: 0.06, slideTo: 620 });
    }
}

const phase = computed(() => state.phase);
const playing = computed(() => [PHASE.STARTING, PHASE.COUNTDOWN, PHASE.PREVIEW, PHASE.INPUT, PHASE.FEEDBACK, PHASE.WAITING].includes(phase.value));
const showBoard = computed(() => playing.value || phase.value === PHASE.IDLE || phase.value === PHASE.ERROR);
// While a puzzle is on the board the arena is locked to one screen so nothing scrolls mid-round.
// The how-to card before that is ordinary page content and may scroll on short phones.
const fitScreen = computed(() => showBoard.value && Boolean(state.spec));

// The how-to card may have been scrolled; show the whole locked board once play begins.
watch(fitScreen, (fit) => {
    if (fit) window.scrollTo({ top: 0 });
});
const remainingSeconds = computed(() => (state.remainingMs === null ? duration.value : Math.ceil(state.remainingMs / 1000)));
const timerFraction = computed(() => (state.remainingMs === null ? 1 : state.remainingMs / (duration.value * 1000)));

const coach = computed(() => {
    const p = phase.value;
    if (p === PHASE.ERROR) return { message: state.error?.message ?? 'Something went wrong.', tone: 'error', status: 'HOLD ON', mood: 'think' };
    if (p === PHASE.COUNTDOWN) return { message: `Get set… go in ${state.countdown}!`, tone: 'live', status: 'COUNTDOWN', mood: 'wave' };
    if (p === PHASE.WAITING) return { message: 'Fetching the next round…', tone: 'neutral', status: null, mood: 'think' };
    if (p === PHASE.PREVIEW) return { message: coachLine(slug, 'preview', state.spec, state.roundState, step.value), tone: 'live', status: 'WATCH', mood: 'watch' };
    if (p === PHASE.INPUT) return { message: coachLine(slug, 'input', state.spec, state.roundState), tone: 'success', status: 'YOUR TURN', mood: 'idle' };
    if (p === PHASE.FEEDBACK) {
        const ok = state.lastRound?.status === 'cleared';
        return { message: ok ? 'Got it! Next one…' : 'Oops — shake it off, next one!', tone: ok ? 'success' : 'error', status: null, mood: ok ? 'cheer' : 'sad' };
    }
    if (once && props.dailyAttempted) return { message: "You've used today's ranked attempt — this run is practice.", tone: 'neutral', status: 'PRACTICE', mood: 'idle' };
    if (once) return { message: 'Everyone gets this same puzzle today. One ranked attempt — make it count!', tone: 'neutral', status: '1 TRY / DAY', mood: 'wave' };
    if (timed) return { message: `${duration.value} seconds on the clock. Speed earns bonus points; misses cost a few.`, tone: 'neutral', status: 'TIMED', mood: 'wave' };
    return { message: 'Ready to test your memory? Watch carefully!', tone: 'neutral', status: 'READY', mood: 'wave' };
});

const statusPill = computed(() => {
    const level = state.level ?? state.session?.level;
    const ok = state.lastRound?.status === 'cleared';
    return {
        [PHASE.IDLE]: { text: `${props.mode.label}${timed ? ` · ${duration.value}s` : ''}`, tone: 'idle' },
        [PHASE.STARTING]: { text: 'Setting up', tone: 'idle' },
        [PHASE.COUNTDOWN]: { text: 'Get ready', tone: 'live' },
        [PHASE.WAITING]: { text: 'Loading', tone: 'idle' },
        [PHASE.PREVIEW]: { text: timed ? 'Watch' : `Level ${level}: Watch`, tone: 'live' },
        [PHASE.INPUT]: { text: timed ? 'Your turn' : `Level ${level}: Your turn`, tone: 'go' },
        [PHASE.FEEDBACK]: { text: ok ? 'Cleared!' : 'Missed', tone: ok ? 'go' : 'error' },
        [PHASE.ERROR]: { text: 'Connection problem', tone: 'error' },
    }[phase.value] ?? { text: 'Run complete', tone: 'idle' };
});

function primaryAction() {
    sounds.unlock();
    if (phase.value === PHASE.IDLE) engine.value.start();
}

function chooseDuration(d) {
    if (phase.value !== PHASE.IDLE || d === duration.value) return;
    sounds.click();
    duration.value = d;
    window.history.replaceState(window.history.state, '', `?duration=${d}`);
    makeEngine();
}

function input(value) {
    sounds.unlock();
    engine.value.input(value);
}

function next() {
    if (bestLevel.value !== null && state.lastRound?.status === 'cleared') {
        bestLevel.value = Math.max(bestLevel.value, state.lastRound.spec.level);
    }
    engine.value.next();
}

function restart() {
    makeEngine();
    primaryAction();
}

const confirmingExit = ref(false);

/** Leaving mid-run pauses the clock and asks first; otherwise leave straight away. */
function exitArena() {
    if (engine.value.inProgress && engine.value.pause()) {
        confirmingExit.value = true;
        return;
    }
    leave();
}

function keepPlaying() {
    confirmingExit.value = false;
    engine.value.resume();
}

function leave() {
    confirmingExit.value = false;
    engine.value.exit();
    router.visit(`/?game=${slug}`);
}

function onKey(e) {
    if (e.target.closest?.('input, textarea, select')) return;
    if (confirmingExit.value) {
        if (e.key === 'Escape') keepPlaying();
        return;
    }
    const n = Number(e.key);

    if (phase.value === PHASE.INPUT && n >= 1 && n <= 9) {
        if (slug === 'memory-master' && n <= state.spec.tiles) {
            e.preventDefault();
            input(n - 1);
        } else if (slug === 'dice-digits' && n <= 6) {
            e.preventDefault();
            input(n);
        }
        return;
    }

    if ((e.key === 'Enter' || e.key === ' ') && e.target === document.body) {
        if (phase.value === PHASE.IDLE) primaryAction();
        else if ([PHASE.CLEARED, PHASE.LIFE_LOST].includes(phase.value)) next();
        else return;
        e.preventDefault();
    }
}

/* Fit the board to the space available so it never needs scrolling on a phone. */
const boardWrap = ref(null);
const box = ref({ w: 360, h: 360 });
const observer = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(() => measure()) : null;

function measure() {
    const r = boardWrap.value?.getBoundingClientRect();
    if (r) box.value = { w: Math.floor(r.width), h: Math.floor(r.height) };
}

watch(boardWrap, (el, old) => {
    if (old) observer?.unobserve(old);
    if (el) {
        observer?.observe(el);
        measure();
    }
});

onMounted(() => {
    makeEngine();
    window.addEventListener('keydown', onKey);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    observer?.disconnect();
    engine.value?.destroy();
    setPlaying(false);
});
</script>

<template>
    <Head :title="`${game.name} · ${mode.label}`" />
    <Confetti v-if="celebrate" />
    <p class="sr-only" aria-live="assertive">{{ announcement }}</p>

    <!-- svh, not dvh: the smallest viewport, so Safari's floating toolbar never covers the controls. -->
    <div
        class="flex flex-col pt-3"
        :class="fitScreen ? 'h-[calc(100svh-4.75rem)] overscroll-none pb-3' : showBoard ? 'min-h-[calc(100svh-4.75rem)] pb-[max(1.5rem,env(safe-area-inset-bottom))]' : 'min-h-[calc(100svh-5.25rem)]'"
        :style="accent"
    >
        <!-- HUD -->
        <!-- The left group asks for a fixed width (basis-0 + min-width), so the status text changing never re-wraps this row. -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex min-w-[11rem] flex-1 basis-0 items-center gap-2">
                <button type="button" class="grid size-10 shrink-0 place-items-center rounded-full bg-white text-ink-soft shadow-sm ring-1 ring-cream-high hover:text-ink" aria-label="Exit to games" title="Exit" @click="exitArena">
                    <X :size="19" />
                </button>
                <span class="hidden size-10 shrink-0 place-items-center rounded-2xl bg-[var(--tile-soft)] text-[var(--tile)] sm:grid" aria-hidden="true">
                    <component :is="ICONS[game.icon]" :size="20" :stroke-width="2.4" />
                </span>
                <span
                    class="inline-flex min-w-0 items-center gap-2 rounded-full px-3.5 py-1.5 font-display text-[12px] font-bold uppercase tracking-wider shadow-sm sm:text-[13px]"
                    :class="{
                        'bg-amber-soft text-amber-dark': ['idle', 'live'].includes(statusPill.tone),
                        'bg-emerald-soft text-emerald-deep': statusPill.tone === 'go',
                        'bg-ruby-soft text-ruby-deep': statusPill.tone === 'error',
                    }"
                >
                    <Eye v-if="phase === 'preview'" :size="15" class="shrink-0" aria-hidden="true" />
                    <span class="truncate">{{ statusPill.text }}</span>
                    <span v-if="statusPill.tone === 'live'" class="size-2 shrink-0 rounded-full bg-ruby motion-safe:animate-ping" aria-hidden="true" />
                </span>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-2">
                <span v-if="timed" class="chip text-white" :class="state.remainingMs !== null && remainingSeconds <= 5 ? 'bg-ruby' : 'bg-navy'" aria-label="Time remaining">
                    <Timer :size="16" class="text-amber-dim" aria-hidden="true" />
                    <span class="tabular-nums">{{ remainingSeconds }}s</span>
                </span>
                <template v-else>
                    <span class="chip bg-cream-mid text-navy-deep">
                        <span class="text-[10px] tracking-wider text-ink-soft">LEVEL</span>
                        <span class="text-lg leading-none">{{ state.level ?? state.session?.level ?? '–' }}</span>
                    </span>
                    <span v-if="state.livesTotal" class="chip bg-cream-mid" :aria-label="`${state.lives} of ${state.livesTotal} lives`">
                        <Heart v-for="i in state.livesTotal" :key="i" :size="15" :class="i <= state.lives ? 'fill-ruby text-ruby' : 'text-outline-soft'" aria-hidden="true" />
                    </span>
                </template>
                <span class="chip bg-cream-mid text-navy-deep" :title="state.sync === 'synced' ? 'Score verified by the server' : 'Score is provisional until synced'">
                    <span class="text-[10px] tracking-wider text-ink-soft">SCORE</span>
                    <span class="text-lg leading-none text-amber"><AnimatedNumber :value="state.score" /></span>
                    <span class="size-1.5 rounded-full" :class="state.sync === 'offline' ? 'bg-ruby' : state.sync === 'pending' ? 'bg-amber' : 'bg-emerald'" aria-hidden="true" />
                </span>
            </div>
        </div>

        <div v-if="timed && state.remainingMs !== null && showBoard" class="mt-2 h-1.5 overflow-hidden rounded-full bg-cream-high" aria-hidden="true">
            <div class="h-full rounded-full transition-[width] duration-100 ease-linear" :class="timerFraction < 0.2 ? 'bg-ruby' : 'bg-amber'" :style="{ width: `${timerFraction * 100}%` }" />
        </div>

        <!-- Board -->
        <template v-if="showBoard">
            <div class="mt-3">
                <CoachMeeple :message="coach.message" :tone="coach.tone" :status="coach.status" :mood="coach.mood" :compact="fitScreen">
                    <template #aside>
                        <span v-if="once" class="hidden shrink-0 items-center gap-1.5 rounded-full bg-sky-soft px-3 py-1.5 font-display text-xs font-bold text-[#1d4ed8] sm:inline-flex">
                            <CalendarCheck :size="15" /> Try Once
                        </span>
                    </template>
                </CoachMeeple>
            </div>

            <div ref="boardWrap" class="relative my-3 flex min-h-0 flex-1 items-center justify-center" :data-round="state.spec?.key">
                <!-- Before starting: how to play this game -->
                <div v-if="!state.spec" class="w-full max-w-md rounded-[2rem] bg-white p-5 text-left shadow-card ring-1 ring-cream-high sm:p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 place-items-center rounded-2xl bg-[var(--tile-soft)] text-[var(--tile)]"><component :is="ICONS[game.icon]" :size="24" :stroke-width="2.4" /></span>
                        <div>
                            <h1 class="text-2xl font-extrabold text-navy-deep">{{ game.name }}</h1>
                            <p class="text-sm font-semibold text-ink-soft">{{ mode.label }} · {{ game.tagline }}</p>
                        </div>
                    </div>
                    <ol class="mt-4 space-y-2">
                        <li v-for="(line, i) in game.how_to" :key="i" class="flex gap-3 text-[15px] font-medium text-ink-soft">
                            <span class="grid size-6 shrink-0 place-items-center rounded-full bg-amber-soft font-display text-xs font-extrabold text-amber-ink">{{ i + 1 }}</span>
                            {{ line }}
                        </li>
                    </ol>
                    <div v-if="timed" class="mt-5">
                        <p class="label">Clock</p>
                        <div class="flex gap-2" role="radiogroup" aria-label="Timed Rush length">
                            <button
                                v-for="d in durations"
                                :key="d"
                                type="button"
                                role="radio"
                                :aria-checked="d === duration"
                                :disabled="phase !== 'idle'"
                                class="flex-1 rounded-2xl py-2 font-display text-base font-extrabold transition-colors sm:py-2.5 sm:text-lg"
                                :class="d === duration ? 'bg-navy text-white shadow-[0_3px_0_#050b12]' : 'bg-cream-mid text-ink-soft hover:bg-cream-high'"
                                @click="chooseDuration(d)"
                            >
                                {{ d }}s
                            </button>
                        </div>
                        <p v-if="personalBests?.timed?.[duration]" class="mt-2 text-xs font-semibold text-outline">Your best at {{ duration }}s: {{ personalBests.timed[duration] }} pts</p>
                    </div>
                </div>

                <!-- Hidden while paused, so a pause can't be used to study the puzzle. -->
                <div v-else-if="state.paused" class="grid w-full max-w-md place-items-center rounded-[2rem] bg-cream-low py-16 font-display text-lg font-bold text-ink-soft ring-1 ring-cream-high">
                    Paused
                </div>
                <component
                    :is="board"
                    v-else
                    :spec="state.spec"
                    :phase="phase"
                    :preview-starts-at="state.previewStartsAt"
                    :round-state="state.roundState"
                    :feedback="state.feedback"
                    :inputs="state.inputs"
                    :timings="state.timings"
                    :box="box"
                    @input="input"
                    @cue="onCue"
                />

                <div v-if="phase === 'countdown' && !state.paused" class="absolute inset-0 grid place-items-center" aria-live="assertive">
                    <span :key="state.countdown" class="grid size-32 place-items-center rounded-full bg-navy font-display text-7xl font-extrabold text-amber shadow-lift motion-safe:animate-count">
                        {{ state.countdown }}
                    </span>
                </div>
            </div>

            <!-- Controls -->
            <div class="mx-auto flex w-full max-w-xl flex-col items-center gap-2.5 pb-[env(safe-area-inset-bottom)]">
                <div v-if="phase === 'error'" class="flex w-full flex-col items-center gap-2 rounded-3xl bg-ruby-soft p-4 text-center" role="alert">
                    <p class="flex items-center gap-2 font-semibold text-ruby-deep"><WifiOff :size="18" /> {{ state.error?.message }}</p>
                    <button v-if="state.error?.retryable" type="button" class="btn btn-danger btn-sm" @click="engine.retry()"><RotateCcw :size="16" /> Retry</button>
                    <Link v-else :href="`/?game=${game.slug}`" class="btn btn-ghost btn-sm">Back to games</Link>
                </div>

                <div class="flex w-full items-center gap-3">
                    <button v-if="phase === 'idle'" type="button" class="btn btn-primary btn-lg flex-1" @click="primaryAction">
                        <Play :size="20" fill="currentColor" aria-hidden="true" />
                        {{ once ? (dailyAttempted ? 'Start practice run' : 'Start Try Once') : timed ? `Start ${duration}s Rush` : 'Start Level Builder' }}
                    </button>
                    <div
                        v-else-if="phase !== 'error'"
                        class="flex flex-1 items-center justify-center gap-2 rounded-full px-5 py-3.5 font-display text-[15px] font-bold"
                        :class="phase === 'input' ? 'bg-emerald-soft text-emerald-deep' : 'bg-navy text-white'"
                        aria-hidden="true"
                    >
                        <LoaderCircle v-if="['starting', 'waiting'].includes(phase)" :size="18" class="text-amber-dim motion-safe:animate-spin" />
                        <Hourglass v-else-if="phase === 'countdown'" :size="18" class="text-amber-dim" />
                        <template v-if="phase === 'preview'">Watch closely…</template>
                        <template v-else-if="phase === 'input'">Your turn{{ state.inputs.length ? ` · ${state.inputs.length}` : '' }}</template>
                        <template v-else-if="phase === 'countdown'">Get ready…</template>
                        <template v-else-if="phase === 'feedback'">{{ state.lastRound?.status === 'cleared' ? 'Nice!' : 'Next one…' }}</template>
                        <template v-else>One moment…</template>
                    </div>
                    <button
                        v-if="phase !== 'idle' && !timed"
                        type="button"
                        class="grid size-12 shrink-0 place-items-center rounded-full bg-white text-ink-soft shadow-sm ring-1 ring-cream-high hover:text-ink"
                        aria-label="Restart run from the beginning"
                        title="Restart"
                        @click="restart"
                    >
                        <RotateCcw :size="19" />
                    </button>
                </div>

                <GuestPlaysNotice v-if="isGuest && phase === 'idle'" variant="line" />
            </div>
        </template>

        <!-- Results -->
        <!-- Bottom room on phones for the floating Next button. -->
        <div v-else class="pb-28 pt-6 sm:py-6">
            <LevelComplete v-if="phase === 'cleared'" :round="state.lastRound" :score="state.score" :unit="game.unit" :previous-best-level="bestLevel" @next="next" @restart="restart" />
            <LifeLost
                v-else-if="phase === 'life_lost'"
                :game="game.slug"
                :round="state.lastRound"
                :round-state="state.roundState"
                :lives="state.lives"
                :lives-total="state.livesTotal"
                @next="next"
            />
            <GameOver
                v-else-if="phase === 'gameover'"
                :game="game"
                :mode="mode.slug"
                :round="state.lastRound"
                :round-state="state.roundState"
                :rounds="state.rounds"
                :score="state.score"
                :summary="state.summary"
                :verifying="state.verifying"
                :sync="state.sync"
                :is-guest="isGuest"
                @retry="restart"
            />
            <RunSummary
                v-else-if="phase === 'finished'"
                :game="game"
                :mode="mode.slug"
                :duration="duration"
                :rounds="state.rounds"
                :score="state.score"
                :summary="state.summary"
                :verifying="state.verifying"
                :sync="state.sync"
                :is-guest="isGuest"
                @retry="restart"
            />
        </div>

        <!-- Exit mid-run: the clock is paused until they choose. -->
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-150" leave-active-class="transition duration-100">
            <div v-if="confirmingExit" class="fixed inset-0 z-50 grid place-items-center bg-navy-deep/50 px-4 backdrop-blur-sm" @click.self="keepPlaying">
                <div class="w-full max-w-sm rounded-[2rem] bg-white p-6 text-center shadow-lift" role="alertdialog" aria-modal="true" aria-labelledby="exit-title" aria-describedby="exit-body">
                    <span class="mx-auto grid size-12 place-items-center rounded-full bg-amber-soft text-amber-ink"><Pause :size="22" fill="currentColor" aria-hidden="true" /></span>
                    <h2 id="exit-title" class="mt-3 text-xl font-extrabold text-navy-deep">Leave this game?</h2>
                    <p id="exit-body" class="mt-1.5 text-[15px] font-medium text-ink-soft">The game is paused. If you leave now, this run ends and you’ll lose your progress in it.</p>
                    <div class="mt-5 flex flex-col gap-2.5">
                        <button type="button" class="btn btn-primary w-full" autofocus @click="keepPlaying"><Play :size="18" fill="currentColor" aria-hidden="true" /> Keep playing</button>
                        <button type="button" class="btn btn-ghost w-full" @click="leave">Leave game</button>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
