<script setup>
/**
 * A free-roaming Coach Meeple. It wanders its stage (the positioned parent),
 * walking, hopping, waving, spinning and looking around on its own.
 *
 * Interaction:
 *  - Tap: jumps and says something.
 *  - Drag and put down gently: stumbles a little, then stands.
 *  - Throw hard: falls flat on landing, lies dazed, then gets up.
 *  - Poke too often (5 taps within 4 s): falls, runs off the edge to hide,
 *    waits 5 s, peeks back in and walks back.
 *
 * With reduced motion it simply stands still.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';
import MeepleMascot from './MeepleMascot.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { sounds } from '@/game/audio';

const props = defineProps({
    size: { type: Number, default: 64 },
    lines: {
        type: Array,
        default: () => [
            'Wheee!',
            'Pick a game, any game!',
            'Memory muscles: activated!',
            'Try Once resets at midnight. No pressure!',
            'Hehe, that tickles!',
            'Have you tried Pattern Peek?',
            'Five games, three ways to play!',
        ],
    },
});

const GRAVITY = 2200;
const WALK_SPEED = 85;
const RUN_SPEED = 300;
const HARD_THROW = 850; // px/s release speed that knocks him over on landing
const POKES_TO_HIDE = 5;
const POKE_WINDOW_MS = 4000;
const HIDE_MS = 5000;

const reduced = useReducedMotion();
const stage = ref(null);
const pos = ref({ x: 0, y: 0, facing: 1, squash: 1, spin: 0 });
const mood = ref('idle');
const bubble = ref(null);
const bubbleSide = ref('center');
const touchable = ref(true);

let x = 40;
let y = 0;
let vx = 0;
let vy = 0;
let facing = 1;
let squash = 1;
let spin = 0;
let spinSpeed = 0;
let squashVel = 0;
let tilt = 0;
let tiltVel = 0;
let dragVx = 0;
// idle | walk | hop | spin | wave | look | thrown | stumble | fallen | getup | flee | hidden | peek
let action = 'idle';
let next = null; // what to do once the current timed action ends
let targetX = null;
let actionUntil = 0;
let hardThrow = false;
let hiding = false;
let hideSide = -1;
let pokes = [];
let frame = null;
let last = 0;
let dragging = false;
let dragOffset = { x: 0, y: 0 };
let dragMoved = false;
let trail = [];
let bubbleTimer;

const height = () => props.size * 1.255;

function bounds() {
    const el = stage.value?.parentElement;
    return { w: el?.clientWidth ?? 300, h: el?.clientHeight ?? 200, rect: el?.getBoundingClientRect() };
}

function say(text, ms = 2200) {
    bubble.value = text;
    clearTimeout(bubbleTimer);
    bubbleTimer = setTimeout(() => (bubble.value = null), ms);
}

/** Start a timed action; `then` runs when it ends (otherwise he picks something new). */
function act(name, ms, then = null) {
    action = name;
    actionUntil = performance.now() + ms;
    next = then;
}

function choose() {
    const { w } = bounds();
    const roll = Math.random();

    if (roll < 0.6) {
        // Stroll somewhere at least a few steps away; walking is his favourite thing.
        const span = Math.max(0, w - props.size - 20);
        let to = 10 + Math.random() * span;
        if (Math.abs(to - x) < span * 0.25) to = x < span / 2 ? 10 + span * (0.6 + Math.random() * 0.4) : 10 + span * Math.random() * 0.4;
        targetX = to;
        act('walk', 12000);
    } else if (roll < 0.7) {
        vy = 520 + Math.random() * 260;
        vx = (Math.random() - 0.5) * 260;
        squash = 0.75;
        act('hop', 1200);
    } else if (roll < 0.76) {
        vy = 760;
        spinSpeed = 720 * (Math.random() < 0.5 ? 1 : -1);
        squash = 0.7;
        act('spin', 1400);
    } else if (roll < 0.86) {
        act('wave', 1600);
    } else if (roll < 0.92) {
        facing = -facing;
        act('look', 900);
    } else {
        act('idle', 1500 + Math.random() * 2500);
    }
}

function stumble() {
    vx = 0;
    act('stumble', 1100);
    sounds.tone(520, 70, { type: 'sine', gain: 0.05 });
    sounds.tone(440, 90, { type: 'sine', gain: 0.05, delayMs: 140 });
}

/** Fall over, lie there, then get up; `then` runs once he's back on his feet. */
function fall(lieMs, then = null) {
    // Topple toward the middle of the stage so he never lies half off the edge.
    facing = x + props.size / 2 > bounds().w / 2 ? 1 : -1;
    vy = 0;
    spinSpeed = 0; // any leftover spin eases out while he topples
    squash = 0.82;
    squashVel = 0;
    if (!hiding) say('Ouch!', 1400);
    sounds.tone(180, 160, { type: 'triangle', gain: 0.12, slideTo: 90 });
    act('fallen', lieMs, () => act('getup', 950, then));
}

function hide() {
    hiding = true;
    touchable.value = false;
    pokes = [];
    say('Hey! Too many pokes!', 1600);
    const { w } = bounds();
    hideSide = x + props.size / 2 < w / 2 ? -1 : 1;

    fall(1300, () => {
        say('I’m hiding!', 1200);
        facing = hideSide;
        act('flee', 6000);
    });
}

function comeBack() {
    const { w } = bounds();
    // Peek in from the side he left by, facing the stage.
    x = hideSide < 0 ? -props.size * 0.45 : w - props.size * 0.55;
    y = 0;
    vx = 0;
    facing = -hideSide;
    say('Is it safe?', 1800);
    act('peek', 1500, () => {
        hiding = false;
        touchable.value = true;
        targetX = hideSide < 0 ? props.size * 0.6 : w - props.size * 1.6;
        act('walk', 6000);
    });
}

function step(now) {
    const dt = Math.min(0.05, (now - last) / 1000 || 0);
    last = now;
    const { w, h } = bounds();
    const maxX = Math.max(0, w - props.size);
    const maxY = Math.max(0, h - height());
    const offstageAllowed = ['flee', 'hidden', 'peek'].includes(action);

    if (!dragging && action !== 'hidden') {
        const wasAirborne = y > 0;

        if (y > 0 || vy > 0) vy -= GRAVITY * dt;

        if (action === 'walk' && targetX !== null && y <= 0) {
            const dir = Math.sign(targetX - x);
            vx = dir * WALK_SPEED;
            facing = dir || facing;
            if (Math.abs(targetX - x) < 4) {
                vx = 0;
                actionUntil = 0;
            }
        } else if (action === 'flee') {
            vx = hideSide * RUN_SPEED;
            facing = hideSide;
        } else if (y <= 0 && vy <= 0) {
            vx *= ['fallen', 'stumble'].includes(action) ? 0.9 : 0.82; // ground friction (a fallen meeple slides a bit)
        }

        x += vx * dt;
        y += vy * dt;
        spin += spinSpeed * dt;

        if (!offstageAllowed && (x < 0 || x > maxX)) {
            x = Math.min(maxX, Math.max(0, x));
            vx = -vx * 0.5;
            facing = Math.sign(vx) || -facing;
        }
        if (y > maxY) {
            y = maxY;
            vy = -Math.abs(vy) * 0.3;
        }

        if (y <= 0 && vy <= 0) {
            const impact = vy;
            y = 0;

            if (wasAirborne && action === 'thrown') {
                // Landing after being let go: a hard throw knocks him over, a gentle drop makes him stumble.
                if (hardThrow || impact < -1300) {
                    vx *= 0.45;
                    fall(1700);
                } else {
                    stumble();
                }
            } else if (impact < -420 && !['fallen', 'getup', 'stumble'].includes(action)) {
                vy = -impact * 0.32; // bounce off a hop
                squash = 0.7;
            } else {
                vy = 0;
            }

            if (vy === 0) spinSpeed = 0;
        }

        // On the ground, ease any leftover spin back upright instead of snapping.
        if (y <= 0 && spinSpeed === 0 && spin !== 0) {
            spin = ((((spin + 180) % 360) + 360) % 360) - 180;
            spin += -spin * Math.min(1, dt * 12);
            if (Math.abs(spin) < 0.5) spin = 0;
        }

        // Ran off the edge: wait out of sight, then come back.
        if (action === 'flee' && (x < -props.size - 10 || x > w + 10)) {
            vx = 0;
            act('hidden', HIDE_MS, comeBack);
        }
    }

    if (!dragging && now > actionUntil && y <= 0 && vy === 0) {
        if (next) {
            const then = next;
            next = null;
            then();
        } else if (!hiding) {
            choose();
        }
    }

    // Springy squash and stretch: stretch with speed in the air, wobble back after an impact.
    const squashTarget = y > 2 && !dragging ? 1 + Math.min(0.12, Math.abs(vy) / 6000) : 1;
    squashVel += ((squashTarget - squash) * 320 - squashVel * 13) * dt;
    squash += squashVel * dt;

    // Pendulum swing while held: the body trails behind the drag, then springs back.
    if (!dragging) dragVx *= 0.85;
    const tiltTarget = dragging ? Math.max(-38, Math.min(38, dragVx * 0.035)) : 0;
    tiltVel += ((tiltTarget - tilt) * 90 - tiltVel * 7) * dt;
    tilt += tiltVel * dt;

    mood.value = dragging
        ? 'dangle'
        : { stumble: 'stumble', fallen: 'fallen', getup: 'getup', flee: 'run', peek: 'peek', wave: 'wave', look: 'think' }[action]
          ?? (y > 2 ? 'air' : action === 'walk' && Math.abs(vx) > 5 ? 'walk' : 'idle');

    pos.value = { x, y, facing, squash, spin, tilt };
    // Keep the speech bubble inside the stage near either edge.
    bubbleSide.value = x + props.size / 2 < 90 ? 'left' : x + props.size / 2 > w - 90 ? 'right' : 'center';
    frame = requestAnimationFrame(step);
}

function pointerDown(e) {
    if (reduced.value || !touchable.value) return;
    e.preventDefault();
    e.currentTarget.setPointerCapture(e.pointerId);
    const { rect } = bounds();
    dragging = true;
    dragMoved = false;
    dragOffset = { x: e.clientX - rect.left - x, y: rect.bottom - e.clientY - y };
    trail = [{ x, y, t: performance.now() }];
    vx = vy = 0;
    spinSpeed = 0;
    spin = 0;
    next = null;
}

function pointerMove(e) {
    if (!dragging) return;
    const { rect, w, h } = bounds();
    const nx = Math.min(Math.max(0, e.clientX - rect.left - dragOffset.x), Math.max(0, w - props.size));
    const ny = Math.min(Math.max(0, rect.bottom - e.clientY - dragOffset.y), Math.max(0, h - height()));
    if (Math.abs(nx - x) + Math.abs(ny - y) > 3) dragMoved = true;
    if (nx !== x) facing = Math.sign(nx - x);
    const now = performance.now();
    const prev = trail[trail.length - 1];
    if (prev && now > prev.t) dragVx = dragVx * 0.6 + 0.4 * (((nx - prev.x) / (now - prev.t)) * 1000);
    x = nx;
    y = ny;
    trail.push({ x, y, t: performance.now() });
    trail = trail.slice(-6);
}

function pointerUp() {
    if (!dragging) return;
    dragging = false;
    const now = performance.now();

    if (!dragMoved) {
        pokes = [...pokes.filter((t) => now - t < POKE_WINDOW_MS), now];
        if (pokes.length >= POKES_TO_HIDE) return hide();

        if (['fallen', 'getup', 'stumble'].includes(action)) {
            // Already down: no hop, just a complaint (and the poke still counts).
            say('Ow! Give me a second!', 1200);
            return;
        }

        // A tap: jump for joy and say something.
        vy = 680;
        squash = 0.72;
        act('hop', 1200);
        say(props.lines[Math.floor(Math.random() * props.lines.length)]);
        sounds.tone(880, 90, { type: 'sine', gain: 0.08 });
        sounds.tone(1318, 120, { type: 'sine', gain: 0.07, delayMs: 80 });
        return;
    }

    // Released: carry the drag velocity.
    const first = trail[0];
    const lastPoint = trail[trail.length - 1];
    const dt = Math.max(16, lastPoint.t - first.t) / 1000;
    vx = Math.max(-1400, Math.min(1400, (lastPoint.x - first.x) / dt));
    vy = Math.max(-1400, Math.min(1600, (lastPoint.y - first.y) / dt));
    hardThrow = Math.hypot(vx, vy) > HARD_THROW;

    if (y <= 1 && !hardThrow) {
        // Set down on the floor: a little stumble, then he stands.
        y = 0;
        vy = 0;
        vx = 0;
        return stumble();
    }

    spinSpeed = hardThrow ? vx * 0.6 : 0;
    if (hardThrow) say(Math.abs(vx) + Math.abs(vy) > 1400 ? 'Wheeeee!' : 'Whoa!', 1200);
    act('thrown', 4000);
}

onMounted(() => {
    const { w } = bounds();
    x = Math.max(0, w * 0.7 - props.size / 2);
    if (!reduced.value) {
        last = performance.now();
        actionUntil = last + 1200;
        frame = requestAnimationFrame(step);
    } else {
        pos.value = { x, y: 0, facing: 1, squash: 1, spin: 0 };
    }
});

onBeforeUnmount(() => {
    cancelAnimationFrame(frame);
    clearTimeout(bubbleTimer);
});
</script>

<template>
    <div ref="stage" class="pointer-events-none absolute inset-0" aria-hidden="true">
        <div
            class="absolute bottom-0 left-0 select-none touch-none"
            :class="touchable ? 'pointer-events-auto cursor-grab active:cursor-grabbing' : 'pointer-events-none'"
            :style="{ transform: `translate3d(${pos.x}px, ${-pos.y}px, 0)` }"
            data-mascot
            :data-mood="mood"
            @pointerdown="pointerDown"
            @pointermove="pointerMove"
            @pointerup="pointerUp"
            @pointercancel="pointerUp"
        >
            <Transition enter-from-class="opacity-0 scale-75" leave-to-class="opacity-0 scale-75" enter-active-class="transition duration-200" leave-active-class="transition duration-200">
                <span
                    v-if="bubble"
                    class="absolute bottom-full mb-2 w-max max-w-44 rounded-2xl bg-white px-3 py-1.5 text-center text-[12px] font-bold text-navy-deep shadow-lift"
                    :class="{ 'left-1/2 -translate-x-1/2': bubbleSide === 'center', 'right-1/2': bubbleSide === 'right', 'left-1/2': bubbleSide === 'left' }"
                >
                    {{ bubble }}
                    <span
                        class="absolute -bottom-1 size-2.5 rotate-45 bg-white"
                        :class="{ 'left-1/2 -translate-x-1/2': bubbleSide === 'center', 'right-4': bubbleSide === 'right', 'left-4': bubbleSide === 'left' }"
                    />
                </span>
            </Transition>
            <!-- Swing hangs from the top of the head (where he's held); everything else pivots at the feet. -->
            <div :style="{ transform: `rotate(${pos.tilt ?? 0}deg)`, transformOrigin: '50% 0' }">
                <div :style="{ transform: `scaleX(${pos.facing}) rotate(${pos.spin}deg) scale(${2 - pos.squash}, ${pos.squash})`, transformOrigin: '50% 100%' }">
                    <MeepleMascot :mood="mood" :size="size" :shadow="pos.y < 4" />
                </div>
            </div>
        </div>
    </div>
</template>
